<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class WebhookEvent extends Model
{
    protected $fillable = [
        'provider', 'external_event_id', 'event_type', 'payload_hash',
        'payload_encrypted', 'status', 'received_at', 'processed_at', 'error',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    protected $hidden = ['payload_encrypted'];

    public function setPayloadAttribute(?array $value): void
    {
        if ($value === null) {
            $this->attributes['payload_encrypted'] = null;

            return;
        }
        $json = json_encode($value);
        $this->attributes['payload_encrypted'] = Crypt::encryptString($json);
        $this->attributes['payload_hash'] = hash('sha256', $json);
    }

    public function getPayloadAttribute(): ?array
    {
        $enc = $this->attributes['payload_encrypted'] ?? null;
        if (! $enc) {
            return null;
        }
        try {
            return json_decode(Crypt::decryptString($enc), true);
        } catch (\Throwable) {
            return null;
        }
    }
}
