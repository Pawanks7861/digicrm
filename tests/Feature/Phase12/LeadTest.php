<?php

namespace Tests\Feature\Phase12;

use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\LeadStatusHistory;
use App\Models\LeadTouchpoint;
use App\Services\LeadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Phase12\Concerns\BuildsLeads;
use Tests\TestCase;

class LeadTest extends TestCase
{
    use BuildsLeads, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLeads();
    }

    public function test_website_form_creates_exactly_one_valid_agency_lead(): void
    {
        $form = $this->captureForm(['require_consent' => true, 'consent_text' => 'I agree', 'min_fill_seconds' => 3]);

        $payload = [
            'name' => 'Riya Shah',
            'email' => 'Riya@Example.com',
            'mobile' => '98765 43210',
            'requirement' => 'Need social media management',
            'consent' => '1',
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            '_ts' => time() - 30,
            'submission_id' => 'sub-001',
        ];

        $this->postJson(route('lead-capture.submit', $form->form_key), $payload)
            ->assertCreated()
            ->assertJson(['ok' => true]);

        $this->assertSame(1, Lead::count());
        $lead = Lead::first();
        $this->assertSame(Lead::CATEGORY_AGENCY, $lead->lead_category);
        $this->assertNull($lead->client_id);
        $this->assertSame('riya@example.com', $lead->email);
        $this->assertSame(LeadSource::idFor('website_form'), (int) $lead->lead_source_id);
        $this->assertSame($form->id, (int) $lead->lead_capture_form_id);
        $this->assertTrue($lead->consent_given);
        $this->assertSame('I agree', $lead->consent_text);
        $this->assertSame('google', $lead->utm_source);
        $this->assertSame(1, LeadTouchpoint::where('lead_id', $lead->id)->where('touch_type', 'first_touch')->count());
        $this->assertGreaterThanOrEqual(1, LeadStatusHistory::where('lead_id', $lead->id)->count());
        $this->assertSame(1, (int) $form->fresh()->submissions_count);

        $this->postJson(route('lead-capture.submit', $form->form_key), $payload)->assertOk();
        $this->assertSame(1, Lead::count());
        $this->assertSame(1, (int) $form->fresh()->submissions_count);
    }

    public function test_public_form_cannot_override_category_client_or_source(): void
    {
        $client = Client::factory()->create();
        $form = $this->captureForm();

        $this->postJson(route('lead-capture.submit', $form->form_key), [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'lead_category' => Lead::CATEGORY_CAMPAIGN,
            'client_id' => $client->id,
            'lead_source_id' => LeadSource::idFor('referral'),
            'assigned_to' => 1,
        ])->assertCreated();

        $lead = Lead::firstOrFail();
        $this->assertSame(Lead::CATEGORY_AGENCY, $lead->lead_category);
        $this->assertNull($lead->client_id);
        $this->assertSame(LeadSource::idFor('website_form'), (int) $lead->lead_source_id);
    }

    public function test_honeypot_and_too_fast_submissions_create_no_lead(): void
    {
        $form = $this->captureForm(['min_fill_seconds' => 5]);

        $this->postJson(route('lead-capture.submit', $form->form_key), [
            'name' => 'Bot', 'email' => 'bot@example.com', 'website_url' => 'http://spam.test', '_ts' => time() - 60,
        ])->assertOk();

        $this->postJson(route('lead-capture.submit', $form->form_key), [
            'name' => 'Fast Bot', 'email' => 'fast@example.com', '_ts' => time(),
        ])->assertOk();

        $this->assertSame(0, Lead::count());
    }

    public function test_origin_allowlist_signature_and_consent_are_enforced(): void
    {
        $form = $this->captureForm(['allowed_origins' => ['https://good.example'], 'require_consent' => true]);
        $url = route('lead-capture.submit', $form->form_key);

        $this->withHeaders(['Origin' => 'https://evil.example'])
            ->postJson($url, ['name' => 'X', 'email' => 'x@example.com', 'consent' => '1'])
            ->assertForbidden();

        $this->withHeaders(['Origin' => 'https://good.example'])
            ->postJson($url, ['name' => 'X', 'email' => 'x@example.com'])
            ->assertStatus(422);

        $this->withHeaders(['Origin' => 'https://good.example'])
            ->postJson($url, ['name' => 'X', 'email' => 'x@example.com', 'consent' => '1'])
            ->assertCreated();

        $signed = $this->captureForm();
        $signed->hmac_secret = 'form-secret';
        $signed->save();
        $body = json_encode(['name' => 'Signed', 'email' => 'signed@example.com']);
        $signedUrl = route('lead-capture.submit', $signed->form_key);

        $this->call('POST', $signedUrl, [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_X-Lead-Signature' => 'sha256=bad',
        ], $body)->assertForbidden();

        $this->call('POST', $signedUrl, [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_X-Lead-Signature' => 'sha256='.hash_hmac('sha256', $body, 'form-secret'),
        ], $body)->assertCreated();

        $this->assertSame(2, Lead::count());
        $this->assertStringNotContainsString('form-secret', json_encode($signed->fresh()->toArray()));
    }

    public function test_inactive_or_unknown_form_returns_404(): void
    {
        $form = $this->captureForm(['is_active' => false]);

        $this->postJson(route('lead-capture.submit', $form->form_key), ['name' => 'X', 'email' => 'x@example.com'])->assertNotFound();
        $this->postJson(route('lead-capture.submit', 'nope'), ['name' => 'X', 'email' => 'x@example.com'])->assertNotFound();
        $this->assertSame(0, Lead::count());
    }

    public function test_status_changes_keep_full_history(): void
    {
        $am = $this->makeUser('account_manager');
        $lead = $this->agencyLead(['assigned_to' => $am->id], $am);
        $service = app(LeadService::class);

        $service->transition($lead, 'contacted', $am, ['reason' => 'Called']);
        $service->transition($lead, 'qualified', $am);
        $service->transition($lead, 'lost', $am, ['lost_reason_id' => $this->lostReasonId('budget'), 'lost_notes' => 'Too pricey']);

        $lead->refresh();
        $this->assertSame('lost', $lead->status);
        $this->assertNotNull($lead->first_contacted_at);
        $this->assertNotNull($lead->qualified_at);
        $this->assertSame($this->lostReasonId('budget'), (int) $lead->lost_reason_id);

        $trail = LeadStatusHistory::where('lead_id', $lead->id)->orderBy('id')->get();
        $this->assertSame(['new', 'assigned', 'contacted', 'qualified', 'lost'], $trail->pluck('to_status')->all());
        $this->assertSame([null, 'new', 'assigned', 'contacted', 'qualified'], $trail->pluck('from_status')->all());
        $this->assertSame('Called', $trail[2]->reason);
        $this->assertSame($am->id, (int) $trail[4]->changed_by);

        $service->transition($lead, 'contacted', $am, ['reason' => 'Reopened']);
        $lead->refresh();
        $this->assertSame('contacted', $lead->status);
        $this->assertNull($lead->lost_reason_id);
        $this->assertNotNull($lead->qualified_at);
        $this->assertSame(6, LeadStatusHistory::where('lead_id', $lead->id)->count());
    }

    public function test_lost_requires_reason_and_invalid_transitions_are_rejected(): void
    {
        $admin = $this->makeUser('admin');
        $lead = $this->agencyLead([], $admin);

        try {
            app(LeadService::class)->transition($lead, 'lost', $admin);
            $this->fail('Lost without reason should fail');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('lost_reason_id', $e->errors());
        }

        $this->expectException(ValidationException::class);
        app(LeadService::class)->transition($lead->fresh(), 'won', $admin);
    }

    public function test_campaign_lead_cannot_enter_agency_only_statuses(): void
    {
        $admin = $this->makeUser('admin');
        $lead = $this->campaignLead(Client::factory()->create(), [], $admin);
        app(LeadService::class)->transition($lead, 'contacted', $admin);

        $this->expectException(ValidationException::class);
        app(LeadService::class)->transition($lead->fresh(), 'opportunity', $admin);
    }

    public function test_status_transition_via_http_records_actor(): void
    {
        $am = $this->makeUser('account_manager');
        $lead = $this->agencyLead(['assigned_to' => $am->id], $am);

        $this->actingAs($am)->post(route('leads.transition', $lead), ['status' => 'contacted', 'reason' => 'Intro call'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('contacted', $lead->fresh()->status);
        $this->assertDatabaseHas('lead_status_histories', [
            'lead_id' => $lead->id, 'from_status' => 'assigned', 'to_status' => 'contacted', 'changed_by' => $am->id, 'reason' => 'Intro call',
        ]);
    }

    public function test_agency_user_can_create_lead_manually_and_is_default_assignee(): void
    {
        $am = $this->makeUser('account_manager');

        $this->actingAs($am)->post(route('leads.store'), [
            'lead_category' => Lead::CATEGORY_AGENCY,
            'name' => 'Walk-in Prospect',
            'mobile' => '9876543210',
            'country' => 'India',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $lead = Lead::firstOrFail();
        $this->assertSame($am->id, (int) $lead->assigned_to);
        $this->assertSame('+919876543210', $lead->mobile_normalized);
        $this->assertSame(LeadSource::idFor('manual'), (int) $lead->lead_source_id);
    }
}
