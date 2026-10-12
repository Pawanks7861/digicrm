<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientBrandProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'brand_description',
        'target_audience',
        'tone_of_voice',
        'preferred_colors',
        'brand_fonts',
        'dos',
        'donts',
        'competitors',
        'hashtags',
        'content_preferences',
        'important_urls',
        'reference_accounts',
        'logo_path',
        'brand_guidelines_path',
    ];

    protected $casts = [
        'preferred_colors' => 'array',
        'brand_fonts' => 'array',
        'hashtags' => 'array',
        'important_urls' => 'array',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
