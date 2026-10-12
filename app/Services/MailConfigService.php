<?php

namespace App\Services;

use App\Models\NotificationSetting;
use Illuminate\Support\Facades\Config;

/**
 * Applies runtime SMTP from notification_settings.
 * Prefer calling applyFromSettings() at toMail / testEmail start so queue workers
 * pick up SMTP changes without restart. Restarting workers after SMTP change is still recommended.
 */
class MailConfigService
{
    public function applyFromSettings(?NotificationSetting $settings = null): void
    {
        $settings = $settings ?? NotificationSetting::current();

        if (! $settings->smtp_host) {
            return;
        }

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.transport', 'smtp');
        Config::set('mail.mailers.smtp.host', $settings->smtp_host);
        Config::set('mail.mailers.smtp.port', $settings->smtp_port ?: 587);
        Config::set('mail.mailers.smtp.encryption', $settings->smtp_encryption ?: 'tls');
        Config::set('mail.mailers.smtp.username', $settings->smtp_username);
        Config::set('mail.mailers.smtp.password', $settings->smtp_password);

        if ($settings->mail_from_address) {
            Config::set('mail.from.address', $settings->mail_from_address);
        }
        if ($settings->mail_from_name) {
            Config::set('mail.from.name', $settings->mail_from_name);
        }
    }
}
