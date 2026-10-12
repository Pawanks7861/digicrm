<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserCostRate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserCostRateService
{
    public function __construct(protected ActivityLogger $activityLogger)
    {
    }

    public function setRate(User $user, array $data, User $actor): UserCostRate
    {
        $from = Carbon::parse($data['effective_from'])->startOfDay();
        $to = isset($data['effective_to']) ? Carbon::parse($data['effective_to'])->startOfDay() : null;
        $cost = (string) $data['hourly_cost'];
        if (bccomp($cost, '0', 2) <= 0) {
            throw ValidationException::withMessages(['hourly_cost' => 'Hourly cost must be positive.']);
        }

        return DB::transaction(function () use ($user, $from, $to, $cost, $data, $actor) {
            $overlap = UserCostRate::where('user_id', $user->id)
                ->where(function ($q) use ($from, $to) {
                    $q->where(function ($qq) use ($from) {
                        $qq->whereDate('effective_from', '<=', $from)
                            ->where(function ($q2) use ($from) {
                                $q2->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from);
                            });
                    });
                    if ($to) {
                        $q->orWhere(function ($qq) use ($to) {
                            $qq->whereDate('effective_from', '<=', $to)
                                ->where(function ($q2) use ($to) {
                                    $q2->whereNull('effective_to')->orWhereDate('effective_to', '>=', $to);
                                });
                        });
                    } else {
                        $q->orWhereNull('effective_to');
                    }
                })
                ->exists();

            // Close open-ended previous rate the day before new from
            UserCostRate::where('user_id', $user->id)
                ->whereNull('effective_to')
                ->whereDate('effective_from', '<', $from)
                ->update([
                    'effective_to' => $from->copy()->subDay()->toDateString(),
                    'updated_by' => $actor->id,
                ]);

            // Re-check hard overlap after close
            $stillOverlap = UserCostRate::where('user_id', $user->id)
                ->whereDate('effective_from', '<=', $to?->toDateString() ?? '9999-12-31')
                ->where(function ($q) use ($from) {
                    $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from);
                })
                ->whereDate('effective_from', '>=', $from)
                ->exists();

            if ($stillOverlap) {
                throw ValidationException::withMessages(['effective_from' => 'Cost rate ranges cannot overlap.']);
            }

            $rate = UserCostRate::create([
                'user_id' => $user->id,
                'hourly_cost' => $cost,
                'currency' => $data['currency'] ?? 'INR',
                'effective_from' => $from,
                'effective_to' => $to,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->activityLogger->log('cost_rate_created', 'finance', $user, null, [
                'hourly_cost' => $cost,
                'effective_from' => $from->toDateString(),
            ]);

            return $rate;
        });
    }

    public function rateFor(User $user, Carbon $at): ?UserCostRate
    {
        return UserCostRate::where('user_id', $user->id)
            ->whereDate('effective_from', '<=', $at)
            ->where(function ($q) use ($at) {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $at);
            })
            ->orderByDesc('effective_from')
            ->first();
    }

    /** @return array{cost: string, missing_seconds: int, missing_entries: int, currency: string} */
    public function laborCostForEntries(iterable $entries): array
    {
        $total = '0.00';
        $missingSeconds = 0;
        $missingEntries = 0;
        $currency = 'INR';

        foreach ($entries as $entry) {
            if (! $entry->ended_at || $entry->status === 'rejected') {
                continue;
            }
            $rate = $this->rateFor($entry->user ?? User::find($entry->user_id), $entry->started_at);
            if (! $rate) {
                $missingSeconds += (int) $entry->duration_seconds;
                $missingEntries++;
                continue;
            }
            $currency = $rate->currency;
            $hours = bcdiv((string) $entry->duration_seconds, '3600', 6);
            $line = bcmul($hours, (string) $rate->hourly_cost, 2);
            $total = bcadd($total, $line, 2);
        }

        return [
            'cost' => $total,
            'missing_seconds' => $missingSeconds,
            'missing_entries' => $missingEntries,
            'currency' => $currency,
        ];
    }
}
