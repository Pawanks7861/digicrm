<?php

namespace App\Automations\Actions;

use App\Automations\AutomationActionHandler;
use App\Automations\AutomationContext;
use App\Services\MailConfigService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class SendEmailAction implements AutomationActionHandler
{
    public function validate(array $config): void
    {
        if (empty($config['to']) || empty($config['subject'])) {
            throw ValidationException::withMessages(['to' => 'to and subject required']);
        }
    }

    public function handle(array $config, AutomationContext $context): array
    {
        $this->validate($config);
        if ($context->dryRun) {
            return ['dry_run' => true, 'to' => $config['to'], 'subject' => $config['subject']];
        }

        app(MailConfigService::class)->applyFromSettings();
        Mail::raw($config['body'] ?? '', function ($message) use ($config) {
            $message->to($config['to'])->subject($config['subject']);
        });

        return ['to' => $config['to']];
    }
}
