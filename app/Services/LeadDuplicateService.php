<?php

namespace App\Services;

use App\Models\Lead;

class LeadDuplicateService
{
    /** Calling code and national number length per country (name, ISO2 or ISO3, lower-cased). */
    protected const COUNTRIES = [
        'india' => ['91', 10], 'in' => ['91', 10], 'ind' => ['91', 10],
        'united states' => ['1', 10], 'usa' => ['1', 10], 'us' => ['1', 10], 'united states of america' => ['1', 10],
        'canada' => ['1', 10], 'ca' => ['1', 10], 'can' => ['1', 10],
        'united kingdom' => ['44', 10], 'uk' => ['44', 10], 'gb' => ['44', 10], 'gbr' => ['44', 10],
        'united arab emirates' => ['971', 9], 'uae' => ['971', 9], 'ae' => ['971', 9], 'are' => ['971', 9],
        'australia' => ['61', 9], 'au' => ['61', 9], 'aus' => ['61', 9],
        'singapore' => ['65', 8], 'sg' => ['65', 8], 'sgp' => ['65', 8],
        'saudi arabia' => ['966', 9], 'sa' => ['966', 9], 'sau' => ['966', 9],
        'nepal' => ['977', 10], 'np' => ['977', 10],
        'bangladesh' => ['880', 10], 'bd' => ['880', 10],
        'sri lanka' => ['94', 9], 'lk' => ['94', 9],
        'germany' => ['49', 11], 'de' => ['49', 11],
        'qatar' => ['974', 8], 'qa' => ['974', 8],
    ];

    /**
     * Normalise to E.164-like form using the lead's own country.
     * Unknown country: digits only (never assume India).
     */
    public function normalizeMobile(?string $mobile, ?string $country): ?string
    {
        if ($mobile === null || trim($mobile) === '') {
            return null;
        }

        $raw = trim($mobile);
        $hasPlus = str_starts_with($raw, '+') || str_starts_with($raw, '00');
        $digits = preg_replace('/\D+/', '', $raw);
        if (str_starts_with($raw, '00')) {
            $digits = substr($digits, 2);
        }
        if ($digits === '' || $digits === null) {
            return null;
        }
        if ($hasPlus) {
            return '+'.$digits;
        }

        $meta = self::COUNTRIES[mb_strtolower(trim((string) $country))] ?? null;
        if (! $meta) {
            return $digits;
        }

        [$code, $length] = $meta;
        $national = ltrim($digits, '0');
        if (strlen($national) === $length) {
            return '+'.$code.$national;
        }
        if (str_starts_with($digits, $code) && strlen($digits) === strlen($code) + $length) {
            return '+'.$digits;
        }

        return $digits;
    }

    public function normalizeEmail(?string $email): ?string
    {
        $email = $email !== null ? mb_strtolower(trim($email)) : null;

        return $email !== '' ? $email : null;
    }

    public function findHardDuplicate(?string $provider, ?string $providerLeadId): ?Lead
    {
        if (! $provider || ! $providerLeadId) {
            return null;
        }

        return Lead::withTrashed()
            ->where('provider', $provider)
            ->where('provider_lead_id', $providerLeadId)
            ->first();
    }

    /**
     * Possible duplicate within the same category and owning client only.
     */
    public function findPossibleDuplicate(string $category, ?int $clientId, ?string $email, ?string $mobileNormalized, ?int $excludeId = null): ?Lead
    {
        if (! $email && ! $mobileNormalized) {
            return null;
        }

        return Lead::query()
            ->where('lead_category', $category)
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId), fn ($q) => $q->whereNull('client_id'))
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->whereNull('merged_into_id')
            ->where(function ($q) use ($email, $mobileNormalized) {
                if ($email) {
                    $q->orWhereRaw('lower(email) = ?', [$email]);
                }
                if ($mobileNormalized) {
                    $q->orWhere('mobile_normalized', $mobileNormalized);
                }
            })
            ->orderBy('id')
            ->first();
    }
}
