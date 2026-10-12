<?php

namespace Tests\Feature\Phase12;

use App\Models\Client;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\LeadTouchpoint;
use App\Services\FollowUpService;
use App\Services\LeadDuplicateService;
use App\Services\LeadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Phase12\Concerns\BuildsLeads;
use Tests\TestCase;

class LeadDuplicateTest extends TestCase
{
    use BuildsLeads, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLeads();
    }

    public function test_mobile_normalisation_uses_the_leads_country(): void
    {
        $svc = app(LeadDuplicateService::class);

        $this->assertSame('+919876543210', $svc->normalizeMobile('098765 43210', 'India'));
        $this->assertSame('+919876543210', $svc->normalizeMobile('+91 98765-43210', null));
        $this->assertSame('+971501234567', $svc->normalizeMobile('050 123 4567', 'UAE'));
        $this->assertSame('9876543210', $svc->normalizeMobile('98765 43210', null));
        $this->assertSame('a@b.com', $svc->normalizeEmail('  A@B.com '));
    }

    public function test_possible_duplicates_are_flagged_within_category_and_owning_client_only(): void
    {
        $clientA = Client::factory()->create();
        $clientB = Client::factory()->create();

        $original = $this->agencyLead(['email' => 'dup@example.com', 'mobile' => '9876543210', 'country' => 'India']);
        $byEmail = $this->agencyLead(['email' => 'DUP@example.com']);
        $byMobile = $this->agencyLead(['email' => null, 'mobile' => '+91 98765 43210']);
        $campaignA = $this->campaignLead($clientA, ['email' => 'dup@example.com']);
        $campaignA2 = $this->campaignLead($clientA, ['email' => 'dup@example.com']);
        $campaignB = $this->campaignLead($clientB, ['email' => 'dup@example.com']);

        $this->assertNull($original->possible_duplicate_of_id);
        $this->assertSame($original->id, (int) $byEmail->possible_duplicate_of_id);
        $this->assertSame('possible', $byEmail->duplicate_status);
        $this->assertSame($original->id, (int) $byMobile->possible_duplicate_of_id);
        $this->assertNull($campaignA->possible_duplicate_of_id);
        $this->assertSame($campaignA->id, (int) $campaignA2->possible_duplicate_of_id);
        $this->assertNull($campaignB->possible_duplicate_of_id);
        $this->assertSame(6, Lead::count());
    }

    public function test_provider_id_is_a_hard_dedupe_key(): void
    {
        $first = $this->agencyLead(['provider' => 'form:1', 'provider_lead_id' => 'abc']);
        $again = $this->agencyLead(['provider' => 'form:1', 'provider_lead_id' => 'abc', 'name' => 'Different']);

        $this->assertTrue($first->wasRecentlyCreated);
        $this->assertFalse($again->wasRecentlyCreated);
        $this->assertSame($first->id, $again->id);
        $this->assertSame(1, Lead::count());
    }

    public function test_merge_moves_related_records_and_soft_deletes_source(): void
    {
        $admin = $this->makeUser('admin');
        $target = $this->agencyLead(['email' => 'keep@example.com', 'company' => null], $admin);
        $source = $this->agencyLead(['email' => 'keep@example.com', 'company' => 'Acme Pvt Ltd', 'mobile' => '9876543210', 'country' => 'India'], $admin);

        LeadNote::create(['lead_id' => $source->id, 'user_id' => $admin->id, 'body' => 'Source note', 'visibility' => 'internal']);
        app(FollowUpService::class)->create([
            'followupable_type' => Lead::class, 'followupable_id' => $source->id,
            'assigned_to' => $admin->id, 'title' => 'Call source', 'follow_up_type' => 'call', 'due_at' => now()->addDay(),
        ], $admin);

        $this->actingAs($admin)->post(route('leads.merge', $source), ['target_id' => $target->id])
            ->assertRedirect(route('leads.show', $target))->assertSessionHasNoErrors();

        $source = Lead::withTrashed()->find($source->id);
        $target->refresh();
        $this->assertTrue($source->trashed());
        $this->assertSame($target->id, (int) $source->merged_into_id);
        $this->assertSame('merged', $source->duplicate_status);
        $this->assertSame('Acme Pvt Ltd', $target->company);
        $this->assertSame('+919876543210', $target->mobile_normalized);
        $this->assertSame(1, LeadNote::where('lead_id', $target->id)->count());
        $this->assertSame(1, FollowUp::where('followupable_type', Lead::class)->where('followupable_id', $target->id)->count());
        $this->assertSame(0, LeadTouchpoint::where('lead_id', $source->id)->count());
        $this->assertTrue(LeadTouchpoint::where('lead_id', $target->id)->where('touch_type', 'merge')->exists());
        $this->assertSame(1, Lead::count());

        $this->actingAs($admin)->get(route('leads.show', $source->id))->assertOk();
    }

    public function test_merge_across_categories_or_clients_is_rejected_and_requires_permission(): void
    {
        $admin = $this->makeUser('admin');
        $am = $this->makeUser('account_manager');
        $client = Client::factory()->forManager($am)->create();
        $agency = $this->agencyLead(['assigned_to' => $am->id], $admin);
        $campaign = $this->campaignLead($client, [], $admin);

        try {
            app(LeadService::class)->merge($campaign, $agency, $admin);
            $this->fail('Cross-category merge must fail');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('target', $e->errors());
        }

        $other = $this->agencyLead(['assigned_to' => $am->id], $admin);
        $this->actingAs($am)->post(route('leads.merge', $other), ['target_id' => $agency->id])->assertForbidden();
        $this->assertSame(3, Lead::count());
    }

    public function test_keep_separate_and_mark_duplicate(): void
    {
        $admin = $this->makeUser('admin');
        $original = $this->agencyLead(['email' => 'same@example.com'], $admin);
        $dupA = $this->agencyLead(['email' => 'same@example.com'], $admin);
        $dupB = $this->agencyLead(['email' => 'same@example.com'], $admin);

        $this->actingAs($admin)->post(route('leads.keep-separate', $dupA))->assertRedirect();
        $this->actingAs($admin)->post(route('leads.mark-duplicate', $dupB))->assertRedirect();

        $this->assertSame('kept_separate', $dupA->fresh()->duplicate_status);
        $this->assertSame('duplicate', $dupB->fresh()->duplicate_status);
        $this->assertSame('lost', $dupB->fresh()->status);
        $this->assertSame($original->id, (int) $dupB->fresh()->possible_duplicate_of_id);
    }
}
