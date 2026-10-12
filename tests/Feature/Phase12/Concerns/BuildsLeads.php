<?php

namespace Tests\Feature\Phase12\Concerns;

use App\Models\Client;
use App\Models\ClientPortalSetting;
use App\Models\Lead;
use App\Models\LeadCaptureForm;
use App\Models\LostReason;
use App\Models\NotificationSetting;
use App\Models\User;
use App\Services\LeadService;
use Database\Seeders\RolePermissionSeeder;

trait BuildsLeads
{
    protected function setUpLeads(): void
    {
        $this->seed(RolePermissionSeeder::class);
        NotificationSetting::current();
    }

    protected function makeUser(string $role, array $attrs = []): User
    {
        $user = User::factory()->create(array_merge([
            'email_verified_at' => now(),
            'is_active' => true,
        ], $attrs));
        $user->assignRole($role);

        return $user;
    }

    protected function portalUser(Client $client, array $settings = []): User
    {
        ClientPortalSetting::forClient($client)->update(array_merge(['show_leads' => true], $settings));

        return $this->makeUser('client', ['client_id' => $client->id]);
    }

    protected function agencyLead(array $data = [], ?User $actor = null, array $context = []): Lead
    {
        return app(LeadService::class)->create(array_merge([
            'lead_category' => Lead::CATEGORY_AGENCY,
            'name' => 'Agency Prospect',
            'email' => fake()->unique()->safeEmail(),
        ], $data), $actor, $context + ['auto_assign' => false]);
    }

    protected function campaignLead(Client $client, array $data = [], ?User $actor = null, array $context = []): Lead
    {
        return app(LeadService::class)->create(array_merge([
            'lead_category' => Lead::CATEGORY_CAMPAIGN,
            'client_id' => $client->id,
            'name' => 'Campaign Lead',
            'email' => fake()->unique()->safeEmail(),
        ], $data), $actor, $context + ['auto_assign' => false]);
    }

    protected function captureForm(array $attrs = []): LeadCaptureForm
    {
        return LeadCaptureForm::create(array_merge([
            'name' => 'Website Contact',
            'form_key' => LeadCaptureForm::generateKey(),
            'lead_category' => Lead::CATEGORY_AGENCY,
            'is_active' => true,
            'require_consent' => false,
            'min_fill_seconds' => 0,
            'honeypot_field' => 'website_url',
            'fields' => ['name', 'email', 'mobile', 'requirement'],
        ], $attrs));
    }

    protected function lostReasonId(string $code = 'budget'): int
    {
        return (int) (LostReason::query()->where('code', $code)->value('id') ?? LostReason::query()->value('id'));
    }
}
