<?php

namespace Tests\Feature\Phase10;

use App\Integrations\Google\FakeGoogleCalendarProvider;
use App\Integrations\ProviderRegistry;
use App\Jobs\SyncGoogleCalendarEvent;
use App\Models\Client;
use App\Models\FollowUp;
use App\Models\IntegrationConnection;
use App\Models\NotificationSetting;
use App\Models\User;
use App\Services\FollowUpService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GoogleCalendarIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected FakeGoogleCalendarProvider $fake;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        NotificationSetting::current();

        $this->fake = new FakeGoogleCalendarProvider;
        $registry = app(ProviderRegistry::class);
        $registry->register($this->fake);
        $this->app->instance(FakeGoogleCalendarProvider::class, $this->fake);
        // Replace google_calendar provider
        $ref = new \ReflectionClass($registry);
        $prop = $ref->getProperty('providers');
        $prop->setAccessible(true);
        $providers = $prop->getValue($registry);
        $providers['google_calendar'] = $this->fake;
        $prop->setValue($registry, $providers);
    }

    public function test_create_update_cancel_calendar_events(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $admin->assignRole('super_admin');
        $client = Client::factory()->create(['account_manager_id' => $admin->id]);

        $conn = IntegrationConnection::create([
            'provider' => 'google_calendar',
            'name' => 'GC',
            'status' => 'connected',
            'is_enabled' => true,
            'meta' => ['calendar_id' => 'primary'],
        ]);
        $conn->access_token = 'tok';
        $conn->save();

        $fu = app(FollowUpService::class)->create([
            'client_id' => $client->id,
            'assigned_to' => $admin->id,
            'title' => 'Call client',
            'due_at' => now()->addDay()->toDateTimeString(),
            'sync_to_google_calendar' => true,
        ], $admin);

        // Job runs sync in testing (sync queue)
        $this->assertNotNull($fu->fresh()->google_calendar_event_id);
        $this->assertEquals('create', $this->fake->calls[0]['op']);

        app(FollowUpService::class)->reschedule($fu->fresh(), now()->addDays(2)->toDateTimeString());
        $ops = collect($this->fake->calls)->pluck('op')->all();
        $this->assertContains('update', $ops);

        app(FollowUpService::class)->cancel($fu->fresh());
        $ops = collect($this->fake->calls)->pluck('op')->all();
        $this->assertContains('delete', $ops);
        $this->assertNull($fu->fresh()->google_calendar_event_id);
    }
}
