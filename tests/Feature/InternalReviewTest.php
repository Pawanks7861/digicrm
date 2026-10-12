<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\User;
use App\Services\CampaignDeliverableService;
use App\Services\InternalReviewService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function user(string $role): User
    {
        $u = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $u->assignRole($role);

        return $u;
    }

    public function test_review_cycle_approve_and_changes(): void
    {
        $admin = $this->user('super_admin');
        $designer = $this->user('team_member');
        $client = Client::factory()->create();
        $campaign = Campaign::factory()->create(['client_id' => $client->id, 'status' => 'active']);
        $dSvc = app(CampaignDeliverableService::class);
        $rSvc = app(InternalReviewService::class);

        $d = $dSvc->create($campaign, [
            'title' => 'Instagram Reel',
            'deliverable_type' => 'reel',
        ], $admin);
        $dSvc->transition($d, 'ready', $admin);
        $dSvc->transition($d->fresh(), 'in_progress', $admin);

        $review1 = $rSvc->submit($d->fresh(), $designer);
        $this->assertEquals('pending', $review1->status);
        $this->assertEquals('internal_review', $d->fresh()->status);

        $rSvc->requestChanges($review1, $admin, 'Use new product photo');
        $this->assertEquals('changes_required', $d->fresh()->status);
        $this->assertEquals('changes_requested', $review1->fresh()->status);

        $dSvc->transition($d->fresh(), 'in_progress', $admin);
        $review2 = $rSvc->submit($d->fresh(), $designer);
        $rSvc->approve($review2, $admin);
        $this->assertEquals('ready_for_client_review', $d->fresh()->status);
        $this->assertEquals(2, $d->reviews()->count());
    }

    public function test_request_changes_requires_feedback(): void
    {
        $admin = $this->user('super_admin');
        $client = Client::factory()->create();
        $campaign = Campaign::factory()->create(['client_id' => $client->id]);
        $dSvc = app(CampaignDeliverableService::class);
        $d = $dSvc->create($campaign, ['title' => 'Post', 'deliverable_type' => 'static_post'], $admin);
        $dSvc->transition($d, 'ready', $admin);
        $dSvc->transition($d->fresh(), 'in_progress', $admin);
        $review = app(InternalReviewService::class)->submit($d->fresh(), $admin);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(InternalReviewService::class)->requestChanges($review, $admin, '  ');
    }
}
