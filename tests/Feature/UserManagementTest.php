<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Phase13\Concerns\BuildsAgencyUsers;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use BuildsAgencyUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAgency();
    }

    public function test_team_member_cannot_create_users_or_view_the_audit_log(): void
    {
        $member = $this->makeUser('team_member');

        $this->actingAs($member)->post(route('settings.users.store'), [
            'name' => 'New Person',
            'email' => 'new@digicrm.test',
            'role' => 'team_member',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertForbidden();

        $this->actingAs($member)->get(route('settings.audit-logs.index'))->assertForbidden();
    }

    public function test_super_admin_creates_a_user_and_the_change_is_audited(): void
    {
        $admin = $this->makeUser('super_admin');

        $this->actingAs($admin)->post(route('settings.users.store'), [
            'name' => 'Studio Lead',
            'email' => 'lead@digicrm.test',
            'job_title' => 'Lead',
            'role' => 'admin',
            'is_active' => true,
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('settings.users.index'));

        $created = User::where('email', 'lead@digicrm.test')->first();
        $this->assertNotNull($created);
        $this->assertTrue($created->hasRole('admin'));
        $this->assertTrue(ActivityLog::where('action', 'user_created')->where('subject_id', $created->id)->exists());
    }

    public function test_profile_update_is_recorded_without_the_password(): void
    {
        $admin = $this->makeUser('super_admin');

        $this->actingAs($admin)->patch(route('profile.update'), [
            'name' => 'Updated Admin',
            'email' => $admin->email,
        ])->assertRedirect();

        $log = ActivityLog::where('module', 'access')->where('action', 'like', 'submitted profile.update')->first();
        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertArrayNotHasKey('password', $log->new_values['input'] ?? []);
    }

    public function test_the_last_active_super_admin_cannot_be_deactivated(): void
    {
        $admin = $this->makeUser('super_admin');

        $this->actingAs($admin)->put(route('settings.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'admin',
            'is_active' => false,
            'password' => '',
            'password_confirmation' => '',
        ])->assertSessionHasErrors('role');

        $this->assertTrue($admin->fresh()->is_active);
        $this->assertTrue($admin->fresh()->hasRole('super_admin'));
    }

    public function test_inactive_users_cannot_sign_in(): void
    {
        $user = $this->makeUser('team_member', [
            'email' => 'paused@digicrm.test',
            'is_active' => false,
            'password' => bcrypt('password'),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertTrue(ActivityLog::where('action', 'login_blocked')->exists());
    }
}
