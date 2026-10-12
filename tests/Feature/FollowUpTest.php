<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Services\FollowUpService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FollowUpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function admin(): User
    {
        $u = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $u->assignRole('super_admin');

        return $u;
    }

    public function test_create_complete_snooze_and_next_follow_up(): void
    {
        $admin = $this->admin();
        $client = Client::factory()->create();
        $svc = app(FollowUpService::class);

        $fu = $svc->create([
            'client_id' => $client->id,
            'assigned_to' => $admin->id,
            'title' => 'Call about budget',
            'follow_up_type' => 'call',
            'due_at' => now()->subDay(),
            'priority' => 'high',
        ], $admin);

        $this->assertTrue($fu->due_at->lt(now()));
        $this->assertEquals('pending', $fu->status);

        $snoozed = $svc->snooze($fu, now()->addDays(3));
        $this->assertEquals('snoozed', $snoozed->status);
        $this->assertNotNull($snoozed->snoozed_until);

        $svc->reschedule($snoozed, now()->addDay()->toDateTimeString());
        $this->assertEquals('pending', $snoozed->fresh()->status);

        $result = $svc->complete($snoozed->fresh(), $admin, 'Client approved budget', [
            'title' => 'Confirm kickoff',
            'due_at' => now()->addWeek()->toDateTimeString(),
            'follow_up_type' => 'meeting',
        ]);

        $this->assertEquals('completed', $result['follow_up']->status);
        $this->assertNotNull($result['next']);
        $this->assertEquals('Confirm kickoff', $result['next']->title);
    }

    public function test_am_cannot_access_unrelated_follow_up(): void
    {
        $am = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $am->assignRole('account_manager');
        $other = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $other->assignRole('account_manager');
        $client = Client::factory()->create(['account_manager_id' => $other->id]);

        $fu = app(FollowUpService::class)->create([
            'client_id' => $client->id,
            'assigned_to' => $other->id,
            'title' => 'Private',
            'due_at' => now()->addDay(),
        ], $other);

        $this->actingAs($am)->post(route('follow-ups.complete', $fu), [
            'outcome' => 'hack',
        ])->assertForbidden();
    }
}
