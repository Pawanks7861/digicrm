<?php

namespace App\Services;

use App\Models\BillingSetting;
use App\Models\Client;
use App\Models\TaxRate;
use Illuminate\Validation\ValidationException;

/**
 * Backend source of truth for commercial money math (BCMath, scale 2, half-up).
 * Order: line totals → document subtotal → discount → taxable → GST → grand total.
 */
class CommercialCalculationService
{
    public const SCALE = 2;

    public function money(string|float|int $value): string
    {
        return bcadd((string) $value, '0', self::SCALE);
    }

    public function mul(string $a, string $b): string
    {
        return bcmul($a, $b, self::SCALE);
    }

    public function resolveTaxSplit(string $sellerState, string $buyerState, TaxRate $rate, string $treatment = 'auto'): array
    {
        if ($rate->tax_type === 'exempt' || $treatment === 'exempt' || bccomp((string) $rate->percentage, '0', 3) === 0) {
            return [
                'tax_percent' => '0.000',
                'cgst_percent' => '0.000',
                'sgst_percent' => '0.000',
                'igst_percent' => '0.000',
                'mode' => 'exempt',
            ];
        }

        $seller = $this->normalizeState($sellerState);
        $buyer = $this->normalizeState($buyerState);

        if ($treatment === 'auto') {
            if ($seller === '' || $buyer === '') {
                throw ValidationException::withMessages([
                    'tax_treatment' => 'Seller and buyer billing states are required for automatic GST. Choose Intra-state, Inter-state, or Exempt.',
                ]);
            }
            $treatment = $seller === $buyer ? 'intra' : 'inter';
        }

        if ($treatment === 'intra') {
            return [
                'tax_percent' => $this->fmt3($rate->percentage),
                'cgst_percent' => $this->fmt3($rate->cgst_percentage ?: bcdiv((string) $rate->percentage, '2', 3)),
                'sgst_percent' => $this->fmt3($rate->sgst_percentage ?: bcdiv((string) $rate->percentage, '2', 3)),
                'igst_percent' => '0.000',
                'mode' => 'intra',
            ];
        }

        return [
            'tax_percent' => $this->fmt3($rate->percentage),
            'cgst_percent' => '0.000',
            'sgst_percent' => '0.000',
            'igst_percent' => $this->fmt3($rate->igst_percentage ?: $rate->percentage),
            'mode' => 'inter',
        ];
    }

    /**
     * @param  array<int, array{quantity: mixed, unit_rate: mixed, discount_amount?: mixed, tax_percent?: mixed, cgst_percent?: mixed, sgst_percent?: mixed, igst_percent?: mixed}>  $lines
     * @return array{subtotal: string, discount_amount: string, taxable_amount: string, tax_amount: string, cgst_amount: string, sgst_amount: string, igst_amount: string, grand_total: string, lines: array}
     */
    public function calculateDocument(array $lines, ?string $discountType, $discountValue): array
    {
        $computed = [];
        $subtotal = '0.00';

        foreach ($lines as $i => $line) {
            $qty = $this->money($line['quantity'] ?? 1);
            $rate = $this->money($line['unit_rate'] ?? 0);
            $lineDisc = $this->money($line['discount_amount'] ?? 0);
            $lineSub = bcsub($this->mul($qty, $rate), $lineDisc, self::SCALE);
            if (bccomp($lineSub, '0', self::SCALE) < 0) {
                $lineSub = '0.00';
            }
            $subtotal = bcadd($subtotal, $lineSub, self::SCALE);
            $computed[$i] = array_merge($line, [
                'line_subtotal' => $lineSub,
                'quantity' => $qty,
                'unit_rate' => $rate,
                'discount_amount' => $lineDisc,
            ]);
        }

        $discountAmount = '0.00';
        $dv = $this->money($discountValue ?? 0);
        if ($discountType === 'percent') {
            $discountAmount = $this->mul($subtotal, bcdiv($dv, '100', 6));
        } elseif ($discountType === 'fixed') {
            $discountAmount = $dv;
        }
        if (bccomp($discountAmount, $subtotal, self::SCALE) > 0) {
            $discountAmount = $subtotal;
        }

        $taxable = bcsub($subtotal, $discountAmount, self::SCALE);
        $cgstTotal = '0.00';
        $sgstTotal = '0.00';
        $igstTotal = '0.00';

        // Allocate document discount across lines proportionally by line_subtotal
        $allocated = '0.00';
        $keys = array_keys($computed);
        foreach ($keys as $idx => $i) {
            $line = &$computed[$i];
            if (bccomp($subtotal, '0', self::SCALE) === 0) {
                $share = '0.00';
            } elseif ($idx === count($keys) - 1) {
                $share = bcsub($discountAmount, $allocated, self::SCALE);
            } else {
                $share = $this->mul($discountAmount, bcdiv($line['line_subtotal'], $subtotal, 8));
                $allocated = bcadd($allocated, $share, self::SCALE);
            }
            $lineTaxable = bcsub($line['line_subtotal'], $share, self::SCALE);
            if (bccomp($lineTaxable, '0', self::SCALE) < 0) {
                $lineTaxable = '0.00';
            }

            $cgstP = $this->fmt3($line['cgst_percent'] ?? 0);
            $sgstP = $this->fmt3($line['sgst_percent'] ?? 0);
            $igstP = $this->fmt3($line['igst_percent'] ?? 0);
            $taxP = $this->fmt3($line['tax_percent'] ?? bcadd(bcadd($cgstP, $sgstP, 3), $igstP, 3));

            $cgst = $this->mul($lineTaxable, bcdiv($cgstP, '100', 6));
            $sgst = $this->mul($lineTaxable, bcdiv($sgstP, '100', 6));
            $igst = $this->mul($lineTaxable, bcdiv($igstP, '100', 6));
            $tax = bcadd(bcadd($cgst, $sgst, self::SCALE), $igst, self::SCALE);

            $line['taxable_amount'] = $lineTaxable;
            $line['tax_percent'] = $taxP;
            $line['cgst_percent'] = $cgstP;
            $line['sgst_percent'] = $sgstP;
            $line['igst_percent'] = $igstP;
            $line['cgst_amount'] = $cgst;
            $line['sgst_amount'] = $sgst;
            $line['igst_amount'] = $igst;
            $line['tax_amount'] = $tax;
            $line['total_amount'] = bcadd($lineTaxable, $tax, self::SCALE);

            $cgstTotal = bcadd($cgstTotal, $cgst, self::SCALE);
            $sgstTotal = bcadd($sgstTotal, $sgst, self::SCALE);
            $igstTotal = bcadd($igstTotal, $igst, self::SCALE);
            unset($line);
        }

        $taxAmount = bcadd(bcadd($cgstTotal, $sgstTotal, self::SCALE), $igstTotal, self::SCALE);
        $grand = bcadd($taxable, $taxAmount, self::SCALE);

        return [
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'taxable_amount' => $taxable,
            'tax_amount' => $taxAmount,
            'cgst_amount' => $cgstTotal,
            'sgst_amount' => $sgstTotal,
            'igst_amount' => $igstTotal,
            'grand_total' => $grand,
            'lines' => array_values($computed),
        ];
    }

    public function sellerState(?BillingSetting $settings = null): string
    {
        $settings ??= BillingSetting::current();

        return (string) ($settings->state ?? '');
    }

    public function buyerState(Client $client): string
    {
        return (string) ($client->billing_state ?: $client->state ?: '');
    }

    protected function normalizeState(string $state): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $state) ?? ''));
    }

    protected function fmt3($v): string
    {
        return bcadd((string) $v, '0', 3);
    }
}
