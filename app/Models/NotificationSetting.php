<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class NotificationSetting extends Model
{
    protected $fillable = [
        'email_enabled', 'push_enabled', 'in_app_enabled', 'sounds_enabled_default',
        'quiet_hours_start', 'quiet_hours_end', 'default_timezone',
        'invoice_reminder_offsets', 'renewal_reminder_offsets', 'task_reminder_offsets', 'followup_reminder_offsets',
        'digest_enabled', 'digest_time',
        'mail_from_name', 'mail_from_address', 'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username',
        'smtp_password_encrypted', 'vapid_public_key', 'vapid_private_key_encrypted', 'vapid_subject',
        'last_test_email_at', 'last_test_push_at', 'last_scheduler_run_at',
    ];

    protected $casts = [
        'email_enabled' => 'boolean',
        'push_enabled' => 'boolean',
        'in_app_enabled' => 'boolean',
        'sounds_enabled_default' => 'boolean',
        'digest_enabled' => 'boolean',
        'invoice_reminder_offsets' => 'array',
        'renewal_reminder_offsets' => 'array',
        'task_reminder_offsets' => 'array',
        'followup_reminder_offsets' => 'array',
        'last_test_email_at' => 'datetime',
        'last_test_push_at' => 'datetime',
        'last_scheduler_run_at' => 'datetime',
    ];

    protected $hidden = ['smtp_password_encrypted', 'vapid_private_key_encrypted'];

    public static function current(): self
    {
        return static::query()->first() ?? static::create([
            'invoice_reminder_offsets' => [-3, 0, 1, 7],
            'renewal_reminder_offsets' => [30, 15, 7, 0],
            'task_reminder_offsets' => [-1, 0],
            'followup_reminder_offsets' => [0],
            'mail_from_name' => config('app.name'),
            'mail_from_address' => config('mail.from.address'),
            'vapid_subject' => 'mailto:'.(config('mail.from.address') ?: 'admin@example.com'),
        ]);
    }

    public function setSmtpPasswordAttribute(?string $value): void
    {
        if ($value === null || $value === '') {
            return;
        }
        $this->attributes['smtp_password_encrypted'] = Crypt::encryptString($value);
    }

    public function getSmtpPasswordAttribute(): ?string
    {
        $enc = $this->attributes['smtp_password_encrypted'] ?? null;
        if (! $enc) {
            return null;
        }
        try {
            return Crypt::decryptString($enc);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setVapidPrivateKeyAttribute(?string $value): void
    {
        if ($value === null || $value === '') {
            return;
        }
        $this->attributes['vapid_private_key_encrypted'] = Crypt::encryptString($value);
    }

    public function getVapidPrivateKeyAttribute(): ?string
    {
        $enc = $this->attributes['vapid_private_key_encrypted'] ?? null;
        if (! $enc) {
            return null;
        }
        try {
            return Crypt::decryptString($enc);
        } catch (\Throwable) {
            return null;
        }
    }
}
