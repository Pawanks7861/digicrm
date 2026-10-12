<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\NotificationSetting;
use App\Services\NotificationDispatchService;
use App\Services\NotificationRecipientResolver;
use Illuminate\Console\Command;

class SendInvoiceReminders extends Command
{
    protected $signature = 'invoices:send-reminders';

    protected $description = 'Send invoice due/overdue reminders (deduped)';

    public function handle(NotificationDispatchService $dispatch, NotificationRecipientResolver $resolver): int
    {
        $settings = NotificationSetting::current();
        $settings->forceFill(['last_scheduler_run_at' => now()])->save();
        $offsets = $settings->invoice_reminder_offsets ?? [-3, 0, 1, 7];
        $today = now()->startOfDay();
        $count = 0;

        $invoices = Invoice::query()
            ->with('client')
            ->whereNotIn('status', ['draft', 'cancelled', 'void', 'paid'])
            ->whereNotNull('due_date')
            ->whereRaw('balance_due > 0')
            ->get();

        foreach ($invoices as $invoice) {
            $dueDay = $invoice->due_date->copy()->startOfDay();
            $daysUntil = (int) $today->diffInDays($dueDay, false);

            $matchedOffset = null;
            foreach ($offsets as $offset) {
                if ((int) $offset === $daysUntil) {
                    $matchedOffset = (int) $offset;
                    break;
                }
            }
            if ($matchedOffset === null && $daysUntil < 0) {
                // overdue catch-all using positive overdue day if listed, else day 1/7 style
                foreach ($offsets as $offset) {
                    if ((int) $offset > 0 && abs($daysUntil) === (int) $offset) {
                        $matchedOffset = (int) $offset;
                        break;
                    }
                }
            }
            if ($matchedOffset === null) {
                continue;
            }

            $recipients = $resolver->clientFinanceContacts($invoice->client);
            $agency = $resolver->invoiceAgencyRecipients($invoice);
            $all = $recipients->merge($agency)->unique('id');

            $label = $daysUntil < 0
                ? 'Invoice overdue'
                : ($daysUntil === 0 ? 'Invoice due today' : 'Invoice reminder');

            foreach ($all as $user) {
                $isPortal = $user->isClientUser();
                $dispatch->send($user, 'invoice.reminder', 'invoice', $label, "Invoice {$invoice->invoice_number}", [
                    'priority' => $daysUntil < 0 ? 'high' : 'normal',
                    'route' => $isPortal ? 'portal.invoices.show' : 'invoices.show',
                    'params' => ['invoice' => $invoice->id],
                    'audience' => $isPortal ? 'portal' : 'agency',
                    'entity_type' => 'invoice',
                    'entity_id' => $invoice->id,
                    'occurrence' => 'reminder-'.$today->toDateString().'-'.$matchedOffset,
                    'skip_if_deduped' => true,
                    'template' => 'invoice_reminder',
                    'mail_vars' => [
                        'invoice_number' => $invoice->invoice_number,
                        'amount_due' => $invoice->balance_due,
                        'due_date' => $invoice->due_date->toDateString(),
                    ],
                ]);
                $count++;
            }
        }

        $this->info("Sent {$count} invoice reminders.");

        return self::SUCCESS;
    }
}
