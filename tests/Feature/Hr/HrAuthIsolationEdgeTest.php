<?php

namespace Tests\Feature\Hr;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Hr\Concerns\BuildsHrFixtures;
use Tests\TestCase;

class HrAuthIsolationEdgeTest extends TestCase
{
    use BuildsHrFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpHr();
    }

    public function test_unauthenticated_hr_routes_redirect_to_login(): void
    {
        $this->get(route('hr.dashboard'))->assertRedirect(route('login'));
        $this->get(route('hr.attendance.index'))->assertRedirect(route('login'));
        $this->get(route('hr.leave.index'))->assertRedirect(route('login'));
        $this->post(route('hr.attendance.punch'), ['punch_type' => 'check_in'])->assertRedirect(route('login'));
    }

    public function test_employee_cannot_view_another_employee_profile(): void
    {
        $aUser = $this->makeUser('team_member', ['email' => 'a-iso@example.com']);
        $bUser = $this->makeUser('team_member', ['email' => 'b-iso@example.com']);
        $this->makeEmployee($aUser, ['employee_code' => 'ISO-A']);
        $b = $this->makeEmployee($bUser, ['employee_code' => 'ISO-B']);

        $this->actingAs($aUser)->get(route('hr.employees.show', $b))->assertForbidden();
    }

    public function test_manager_cannot_view_employee_outside_reporting_line(): void
    {
        $mgrUser = $this->makeUser('manager', ['email' => 'mgr-iso@example.com']);
        $mgr = $this->makeEmployee($mgrUser, ['employee_code' => 'ISO-M']);
        $otherMgrUser = $this->makeUser('manager', ['email' => 'mgr2-iso@example.com']);
        $otherMgr = $this->makeEmployee($otherMgrUser, ['employee_code' => 'ISO-M2']);
        $empUser = $this->makeUser('team_member', ['email' => 'emp-iso@example.com']);
        $emp = $this->makeEmployee($empUser, [
            'employee_code' => 'ISO-E',
            'reporting_manager_id' => $otherMgr->id,
        ]);

        $this->actingAs($mgrUser)->get(route('hr.employees.show', $emp))->assertForbidden();
        $this->assertTrue($mgr->isActiveEmployment());
    }

    public function test_manager_can_view_direct_report(): void
    {
        $mgrUser = $this->makeUser('manager', ['email' => 'mgr-ok@example.com']);
        $mgr = $this->makeEmployee($mgrUser, ['employee_code' => 'OK-M']);
        $empUser = $this->makeUser('team_member', ['email' => 'emp-ok@example.com']);
        $emp = $this->makeEmployee($empUser, [
            'employee_code' => 'OK-E',
            'reporting_manager_id' => $mgr->id,
        ]);

        $this->actingAs($mgrUser)->get(route('hr.employees.show', $emp))->assertOk();
    }

    public function test_cross_company_employee_show_is_forbidden_for_non_owner(): void
    {
        $other = $this->secondCompany();
        $mgrUser = $this->makeUser('manager', ['email' => 'mgr-co@example.com']);
        $this->makeEmployee($mgrUser, ['employee_code' => 'CO-M']);

        $foreignUser = $this->makeUser('team_member', ['email' => 'foreign@example.com']);
        $foreign = Employee::factory()->forUser($foreignUser)->create([
            'company_id' => $other->id,
            'employee_code' => 'CO-F',
        ]);

        $this->actingAs($mgrUser)->get(route('hr.employees.show', $foreign))->assertForbidden();
    }

    public function test_exited_employee_user_cannot_punch(): void
    {
        $user = $this->makeUser('team_member', ['email' => 'exit@example.com']);
        $employee = $this->makeEmployee($user, [
            'employee_code' => 'EXIT1',
            'employment_status' => 'exited',
            'exit_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($user)
            ->post(route('hr.attendance.punch'), ['punch_type' => 'check_in'])
            ->assertSessionHasErrors();

        $this->assertDatabaseMissing('attendance_punches', ['employee_id' => $employee->id]);
    }
}
