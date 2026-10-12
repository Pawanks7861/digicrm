<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Collection;

class NotificationRecipientResolver
{
    public function accountManager(Client $client): Collection
    {
        $users = collect();
        if ($client->accountManager) {
            $users->push($client->accountManager);
        }

        return $users->filter()->unique('id')->values();
    }

    public function clientApprovers(Client $client): Collection
    {
        return $client->portalUsers()
            ->where('is_active', true)
            ->where('can_approve_content', true)
            ->get();
    }

    public function clientRequestUsers(Client $client): Collection
    {
        return $client->portalUsers()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('can_submit_requests', true)->orWhereNull('can_submit_requests');
            })
            ->get();
    }

    public function clientFinanceContacts(Client $client): Collection
    {
        $users = $client->portalUsers()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('can_receive_finance_notifications', true)
                    ->orWhere('can_view_finance', true);
            })
            ->get();

        if ($users->isEmpty() && $client->billing_email) {
            // Fall back: users matching billing email
            $match = $client->portalUsers()->where('email', $client->billing_email)->where('is_active', true)->get();
            $users = $match;
        }

        if ($users->isEmpty()) {
            $users = $client->portalUsers()->where('is_active', true)->limit(1)->get();
        }

        return $users;
    }

    public function clientQuotationContacts(Client $client): Collection
    {
        $users = $client->portalUsers()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('can_accept_quotations', true)->orWhere('can_view_finance', true);
            })
            ->get();

        if ($users->isEmpty()) {
            $users = $client->portalUsers()->where('is_active', true)->limit(1)->get();
        }

        return $users;
    }

    public function invoiceAgencyRecipients(Invoice $invoice): Collection
    {
        return $this->accountManager($invoice->client);
    }

    public function adminsFallback(): Collection
    {
        return User::role(['super_admin', 'admin'])->where('is_active', true)->whereNull('client_id')->get();
    }
}
