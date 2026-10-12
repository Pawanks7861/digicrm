<?php

namespace Tests\Feature\Phase12;

use App\Events\LeadCreated;
use App\Events\LeadUncontacted;
use App\Models\Client;
use App\Models\Lead;
use App\Models\User;
use App\Services\LeadAssignmentService;
use App\Services\LeadConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Phase12\Concerns\BuildsLeads;
use Tests\TestCase;

class LeadNotificationTest extends TestCase
{
    use BuildsLeads, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLeads();
    }

    protected function countFor(User $user, string $eventKey): int
    {
        return $user->fresh()->notifications()->get()->filter(fn ($n) => ($n->data['event_key'] ?? null) === $eventKey)->count();
    }

    public function test_assignee_is_notified_once_and_not_for_self_assignment(): void
    {
        $admin = $this->makeUser('admin');
        $am = $this->makeUser('account_manager');
        $lead = $this->agencyLead([], $admin);

        app(LeadAssignmentService::class)->assign($lead, $am, $admin);
        app(LeadAssignmentService::class)->assign($lead->fresh(), $am, $admin);
        event(new \App\Events\LeadAssigned($lead->fresh(), ['actor_id' => $admin->id]));

        $this->assertSame(1, $this->countFor($am, 'lead.assigned'));

        $self = $this->makeUser('account_manager');
        app(LeadAssignmentService::class)->assign($lead->fresh(), $self, $self);
        $this->assertSame(0, $this->countFor($self, 'lead.assigned'));
    }

    public function test_unassigned_campaign_lead_notifies_client_account_manager(): void
    {
        $am = $this->makeUser('account_manager');
        $client = Client::factory()->forManager($am)->create();

        $lead = $this->campaignLead($client);
        event(new LeadCreated($lead, ['channel' => 'manual']));

        $this->assertSame(1, $this->countFor($am, 'lead.unassigned'));
    }

    public function test_uncontacted_check_alerts_once_even_when_run_twice(): void
    {
        $am = $this->makeUser('account_manager');
        $lead = $this->agencyLead(['assigned_to' => $am->id], $am);
        $contacted = $this->agencyLead(['assigned_to' => $am->id], $am);
        $contacted->forceFill(['first_contacted_at' => now()])->save();

        $this->travel(5)->hours();
        Artisan::call('leads:check-uncontacted');
        Artisan::call('leads:check-uncontacted');
        event(new LeadUncontacted($lead->fresh(), []));

        $this->assertSame(1, $this->countFor($am, 'lead.uncontacted'));
        $this->assertNotNull($lead->fresh()->uncontacted_alerted_at);
        $this->assertNull($contacted->fresh()->uncontacted_alerted_at);
    }

    public function test_conversion_notification_is_not_repeated(): void
    {
        $super = $this->makeUser('super_admin');
        $am = $this->makeUser('account_manager');
        $lead = $this->agencyLead(['assigned_to' => $am->id], $super);

        app(LeadConversionService::class)->convert($lead, $super);
        app(LeadConversionService::class)->convert($lead->fresh(), $super);

        $this->assertSame(1, $this->countFor($am, 'lead.converted'));
    }

    public function test_listener_failure_does_not_roll_back_lead_creation(): void
    {
        Event::listen(LeadCreated::class, function () {
            throw new \RuntimeException('Notification provider down');
        });

        $lead = $this->agencyLead(['name' => 'Survives']);

        $this->assertTrue($lead->exists);
        $this->assertSame(1, Lead::where('name', 'Survives')->count());
        $this->assertSame(1, $lead->statusHistories()->where('to_status', 'new')->count());
    }
}
