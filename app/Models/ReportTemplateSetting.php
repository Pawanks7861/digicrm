<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportTemplateSetting extends Model
{
    protected $fillable = [
        'default_sections', 'footer_text', 'primary_color', 'accent_color',
        'show_ad_spend_default', 'show_conversion_value_default',
    ];

    protected $casts = [
        'default_sections' => 'array',
        'show_ad_spend_default' => 'boolean',
        'show_conversion_value_default' => 'boolean',
    ];

    public static function current(): self
    {
        return static::query()->first() ?? static::create([
            'default_sections' => ['summary', 'awareness', 'engagement', 'paid', 'leads', 'conversions'],
            'footer_text' => 'Confidential — prepared for the named client only.',
            'primary_color' => '#0f766e',
            'accent_color' => '#134e4a',
            'show_ad_spend_default' => true,
            'show_conversion_value_default' => true,
        ]);
    }
}
