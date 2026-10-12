<?php

namespace App\Services;

use App\Models\AgencyHoliday;
use App\Models\User;
use App\Models\UserWorkCapacity;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Validation\ValidationException;

class CapacityService
{
    public function __construct(protected ActivityLogger $activityLogger)
    {
    }

    public function setCapacity(User $user, array $data, User $actor): UserWorkCapacity
    {
        $hours = (float) ($data['working_hours_per_day'] ?? config('agency.default_capacity.working_hours_per_day'));
        if ($hours <= 0) {
            throw ValidationException::withMessages(['working_hours_per_day' => 'Hours must be positive.']);
        }

        $days = $data['working_days'] ?? config('agency.default_capacity.working_days');
        $from = Carbon::parse($data['effective_from'] ?? now()->toDateString())->startOfDay();

        // Close previous open capacity
        UserWorkCapacity::where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNull('effective_to')
            ->update(['effective_to' => $from->copy()->subDay()->toDateString()]);

        $cap = UserWorkCapacity::create([
            'user_id' => $user->id,
            'working_hours_per_day' => $hours,
            'working_days' => array_values(array_map('intval', $days)),
            'effective_from' => $from,
            'effective_to' => $data['effective_to'] ?? null,
            'status' => 'active',
        ]);

        $this->activityLogger->log('capacity_changed', 'capacity', $user, null, [
            'hours_per_day' => $hours,
            'working_days' => $days,
        ]);

        return $cap;
    }

    public function capacityFor(User $user, Carbon $date): ?UserWorkCapacity
    {
        return UserWorkCapacity::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereDate('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date);
            })
            ->orderByDesc('effective_from')
            ->first();
    }

    /** Available minutes in [from, to] inclusive. */
    public function availableMinutes(User $user, Carbon $from, Carbon $to): int
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();
        $holidays = AgencyHoliday::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->keyBy(fn ($h) => $h->date->toDateString());

        $defaults = config('agency.default_capacity');
        $minutes = 0;

        foreach (CarbonPeriod::create($from, $to) as $day) {
            /** @var Carbon $day */
            $cap = $this->capacityFor($user, $day);
            $hours = $cap ? (float) $cap->working_hours_per_day : (float) $defaults['working_hours_per_day'];
            $days = $cap?->working_days ?? $defaults['working_days'];
            $iso = (int) $day->dayOfWeekIso; // 1=Mon … 7=Sun

            $dateKey = $day->toDateString();
            $holiday = $holidays->get($dateKey);

            if ($holiday && ! $holiday->is_working_day_override) {
                continue;
            }

            if ($holiday && $holiday->is_working_day_override) {
                $minutes += (int) round($hours * 60);
                continue;
            }

            if (! in_array($iso, $days, true)) {
                continue;
            }

            $minutes += (int) round($hours * 60);
        }

        return $minutes;
    }

    public function addHoliday(string $date, string $name, bool $workingOverride = false): AgencyHoliday
    {
        return AgencyHoliday::updateOrCreate(
            ['date' => $date],
            ['name' => $name, 'is_working_day_override' => $workingOverride]
        );
    }
}
