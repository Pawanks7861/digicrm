<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingSetting extends Model
{
    protected $fillable = [
        'legal_name', 'trading_name', 'address', 'city', 'state', 'pin', 'country',
        'gstin', 'pan', 'email', 'phone', 'website',
        'quotation_prefix', 'invoice_prefix', 'payment_prefix',
        'bank_name', 'account_name', 'account_number', 'ifsc', 'upi_id',
        'logo_path', 'favicon_path', 'signatory_name', 'default_terms', 'default_currency',
    ];

    public function logoDataUri(): ?string
    {
        if (! $this->logo_path || ! \Illuminate\Support\Facades\Storage::disk('public')->exists($this->logo_path)) {
            return null;
        }

        $full = \Illuminate\Support\Facades\Storage::disk('public')->path($this->logo_path);
        $mime = @mime_content_type($full) ?: '';
        if (! in_array($mime, ['image/png', 'image/jpeg'], true)) {
            return null;
        }
        $bytes = @file_get_contents($full);
        if ($bytes === false || $bytes === '') {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }

    public static function current(): self
    {
        return static::query()->first() ?? static::create([
            'legal_name' => config('app.name', 'Agency'),
            'state' => 'Gujarat',
            'country' => 'India',
            'quotation_prefix' => 'QUO',
            'invoice_prefix' => 'INV',
            'payment_prefix' => 'PAY',
            'default_currency' => 'INR',
            'default_terms' => 'Payment due within 15 days of invoice date.',
        ]);
    }

    public function sellerSnapshot(): array
    {
        return [
            'seller_name' => $this->legal_name,
            'seller_address' => trim(implode(', ', array_filter([
                $this->address, $this->city, $this->state, $this->pin, $this->country,
            ]))),
            'seller_state' => $this->state,
            'seller_gstin' => $this->gstin,
        ];
    }
}
