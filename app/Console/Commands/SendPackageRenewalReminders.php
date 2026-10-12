<?php

namespace App\Console\Commands;

use App\Models\ClientPackage;
use App\Models\NotificationSetting;
use App\Services\NotificationDispatchService;
use App\Services\NotificationRecipientResolver;
use Illuminate\Console\Command;

class SendPackageRenewalReminders extends Command
{
    protected $signature = 'packages:renewal-reminders';

    protected $description = 'Send package renewal reminders (deduped)';

    public function handle(NotificationDispatchService $dispatch, NotificationRecipientResolver $resolver): int
    {
        $settings = NotificationSetting::current();
        $settings->forceFill(['last_scheduler_run_at' => now()])->save();
        $offsets = $settings->renewal_reminder_offsets ?? [30, 15, 7, 0];
        $today = now()->startOfDay();
        $count = 0;

        $packages = ClientPackage::query()
            ->with('client')
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->get();

        foreach ($packages as $pkg) {
            $end = $pkg->end_date->copy()->startOfDay();
            $daysUntil = (int) $today->diffInDays($end, false);

            if (! in_array($daysUntil, array_map('intval', $offsets), true)) {
                continue;
            }

            $agency = $resolver->accountManager($pkg->client);
            if ($agency->isEmpty()) {
                $agency = $resolver->adminsFallback();
            }
            $portal = $resolver->clientFinanceContacts($pkg->client);
            $all = $agency->merge($portal)->unique('id');

            $packageName = $pkg->package_name ?? ('Package #'.$pkg->id);
            $label = $daysUntil === 0 ? 'Package renews today' : "Package renews in {$daysUntil} days";

            foreach ($all as $user) {
                $isPortal = $user->isClientUser();
                $dispatch->send($user, 'renewal.reminder', 'renewal', $label, $packageName, [
                    'priority' => $daysUntil <= 7 ? 'high' : 'normal',
                    'route' => $isPortal ? 'portal.dashboard' : 'client-packages.show',
                    'params' => $isPortal ? [] : ['clientPackage' => $pkg->id],
                    'audience' => $isPortal ? 'portal' : 'agency',
                    'entity_type' => 'client_package',
                    'entity_id' => $pkg->id,
                    'occurrence' => 'renewal-'.$today->toDateString().'-'.$daysUntil,
                    'skip_if_deduped' => true,
                    'template' => 'package_renewal',
                    'mail_vars' => [
                        'package_name' => $packageName,
                        'end_date' => $pkg->end_date->toDateString(),
                        'days_until' => $daysUntil,
                    ],
                ]);
                $count++;
            }
        }

        $this->info("Sent {$count} renewal reminders.");

        return self::SUCCESS;
    }
}
