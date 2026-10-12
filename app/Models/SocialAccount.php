<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialAccount extends Model
{
    protected $fillable = [
        'client_id', 'integration_connection_id', 'provider', 'platform',
        'external_account_id', 'display_name', 'username', 'status', 'meta',
    ];

    protected $casts = ['meta' => 'array'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'integration_connection_id');
    }

    public function publications(): HasMany
    {
        return $this->hasMany(ContentPublication::class);
    }
}
