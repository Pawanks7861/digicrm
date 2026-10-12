<?php

namespace Tests\Feature\Phase12;

use App\Models\Opportunity;
use App\Models\SalesPipelineStage;
use App\Services\OpportunityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Phase12\Concerns\BuildsLeads;
use Tests\TestCase;

class LeadPipelineTest extends TestCase
{
    use BuildsLeads, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLeads();
    }

    protected function stage(string $code): SalesPipelineStage
    {
        return SalesPipelineStage::where('code', $code)->firstOrFail();
    }

    public function test_moving_stages_updates_probability_and_won_closes_lead(): void
    {
        $am = $this->makeUser('account_manager');
        $lead = $this->agencyLead(['assigned_to' => $am->id, 'estimated_budget' => 80000], $am);
        $opp = app(OpportunityService::class)->create($lead, [], $am);

        $this->assertSame('prospecting', $opp->stage->code);
        $this->assertSame('opportunity', $lead->fresh()->status);

        $this->actingAs($am)->post(route('opportunities.stage', $opp), ['sales_pipeline_stage_id' => $this->stage('proposal')->id])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(50, (int) $opp->fresh()->probability);

        $this->actingAs($am)->post(route('opportunities.stage', $opp), ['sales_pipeline_stage_id' => $this->stage('won')->id])
            ->assertRedirect()->assertSessionHasNoErrors();

        $opp->refresh();
        $this->assertSame('won', $opp->status);
        $this->assertNotNull($opp->won_at);
        $this->assertSame(100, (int) $opp->probability);
        $this->assertSame('won', $lead->fresh()->status);

        $this->actingAs($am)->post(route('opportunities.stage', $opp), ['sales_pipeline_stage_id' => $this->stage('negotiation')->id])
            ->assertSessionHasErrors('stage');
    }

    public function test_dropping_on_lost_requires_a_reason(): void
    {
        $am = $this->makeUser('account_manager');
        $lead = $this->agencyLead(['assigned_to' => $am->id], $am);
        $opp = app(OpportunityService::class)->create($lead, [], $am);

        $this->actingAs($am)->post(route('opportunities.stage', $opp), ['sales_pipeline_stage_id' => $this->stage('lost')->id])
            ->assertSessionHasErrors('lost_reason_id');
        $this->assertSame('open', $opp->fresh()->status);

        $this->actingAs($am)->post(route('opportunities.lost', $opp), ['lost_reason_id' => $this->lostReasonId('competitor'), 'lost_notes' => 'Went elsewhere'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $opp->refresh();
        $this->assertSame('lost', $opp->status);
        $this->assertSame(0, (int) $opp->probability);
        $this->assertSame('lost', $opp->stage->code);
    }

    public function test_pipeline_board_shows_only_visible_opportunities(): void
    {
        $am1 = $this->makeUser('account_manager');
        $am2 = $this->makeUser('account_manager');
        $mine = app(OpportunityService::class)->create($this->agencyLead(['assigned_to' => $am1->id], $am1), ['title' => 'Mine', 'estimated_value' => 1000], $am1);
        $theirs = app(OpportunityService::class)->create($this->agencyLead(['assigned_to' => $am2->id], $am2), ['title' => 'Theirs', 'estimated_value' => 5000], $am2);

        $response = $this->actingAs($am1)->get(route('pipeline.index'))->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Leads/Pipeline'));
        $ids = collect($response->viewData('page')['props']['columns'])->flatMap(fn ($c) => collect($c['items'])->pluck('id'))->all();

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($theirs->id, $ids);

        $this->actingAs($am1)->get(route('opportunities.show', $theirs))->assertForbidden();
        $this->actingAs($am1)->post(route('opportunities.stage', $theirs), ['sales_pipeline_stage_id' => $this->stage('proposal')->id])->assertForbidden();
    }

    public function test_team_member_can_view_but_not_move_opportunities(): void
    {
        $team = $this->makeUser('team_member');
        $admin = $this->makeUser('admin');
        $lead = $this->agencyLead(['assigned_to' => $team->id], $admin);
        $opp = app(OpportunityService::class)->create($lead, [], $admin);

        $this->actingAs($team)->get(route('opportunities.show', $opp))->assertOk();
        $this->actingAs($team)->post(route('opportunities.stage', $opp), ['sales_pipeline_stage_id' => $this->stage('proposal')->id])->assertForbidden();
        $this->actingAs($team)->post(route('opportunities.won', $opp))->assertForbidden();
        $this->assertSame('open', $opp->fresh()->status);
    }

    public function test_closed_opportunity_cannot_be_reopened_via_service(): void
    {
        $admin = $this->makeUser('admin');
        $opp = app(OpportunityService::class)->create($this->agencyLead([], $admin), [], $admin);
        app(OpportunityService::class)->markLost($opp, $this->lostReasonId('timing'), null, $admin);

        $this->expectException(ValidationException::class);
        app(OpportunityService::class)->moveToStage($opp->fresh(), $this->stage('proposal'), $admin);
    }

    public function test_opportunity_keeps_currency_and_value(): void
    {
        $admin = $this->makeUser('admin');
        $opp = app(OpportunityService::class)->create($this->agencyLead([], $admin), ['estimated_value' => 2500, 'currency' => 'USD'], $admin);

        $this->assertInstanceOf(Opportunity::class, $opp);
        $this->assertSame('USD', $opp->currency);
        $this->assertSame('2500.00', (string) $opp->estimated_value);
        $this->assertMatchesRegularExpression('/^OPP-/', $opp->opportunity_number);
    }
}
