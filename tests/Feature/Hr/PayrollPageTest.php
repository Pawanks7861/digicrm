<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_users_without_payroll_access_cannot_open_the_page(): void
    {
        $member = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $member->assignRole('team_member');

        $this->actingAs($member)->get(route('hr.payroll.index'))->assertForbidden();
    }

    public function test_super_admin_can_open_payroll_and_generate_a_draft(): void
    {
        $admin = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $admin->assignRole('super_admin');

        $this->actingAs($admin)->get(route('hr.payroll.index'))->assertOk();

        $period = now()->format('Y-m');
        $this->actingAs($admin)->post(route('hr.payroll.generate'), [
            'period' => $period,
        ])->assertRedirect(route('hr.payroll.index'));

        $this->assertDatabaseHas('payroll_runs', [
            'period_year' => (int) now()->year,
            'period_month' => (int) now()->month,
            'status' => 'draft',
        ]);
    }
}
