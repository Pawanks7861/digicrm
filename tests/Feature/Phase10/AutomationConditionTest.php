<?php

namespace Tests\Feature\Phase10;

use App\Automations\AutomationContext;
use App\Automations\AutomationEvaluator;
use App\Models\AutomationCondition;
use App\Models\AutomationRule;
use App\Models\ClientRequest;
use App\Models\NotificationSetting;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomationConditionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        NotificationSetting::current();
    }

    public function test_and_or_operators(): void
    {
        $evaluator = app(AutomationEvaluator::class);
        $rule = AutomationRule::create([
            'name' => 'Cond',
            'trigger_type' => 'client_request.submitted',
            'status' => 'active',
            'group_operator' => 'and',
        ]);
        AutomationCondition::create(['automation_rule_id' => $rule->id, 'field' => 'priority', 'operator' => 'equals', 'value' => 'urgent']);
        AutomationCondition::create(['automation_rule_id' => $rule->id, 'field' => 'request_type', 'operator' => 'equals', 'value' => 'reel']);
        $rule->load('conditions');

        $req = new ClientRequest(['priority' => 'urgent', 'request_type' => 'reel']);
        $ctx = new AutomationContext($req, 'client_request.submitted', $req->toArray());
        $this->assertTrue($evaluator->matches($rule, $ctx));

        $req2 = new ClientRequest(['priority' => 'urgent', 'request_type' => 'post']);
        $this->assertFalse($evaluator->matches($rule, new AutomationContext($req2, 'x', $req2->toArray())));

        $rule->update(['group_operator' => 'or']);
        $rule->refresh()->load('conditions');
        $this->assertTrue($evaluator->matches($rule, new AutomationContext($req2, 'x', $req2->toArray())));
    }

    public function test_contains_in_empty_operators(): void
    {
        $evaluator = app(AutomationEvaluator::class);
        $c = new AutomationCondition(['field' => 'title', 'operator' => 'contains', 'value' => 'reel']);
        $ctx = new AutomationContext((object) ['title' => 'Diwali Reel Need'], 't', ['title' => 'Diwali Reel Need']);
        $this->assertTrue($evaluator->evaluateCondition($c, $ctx));

        $c2 = new AutomationCondition(['field' => 'priority', 'operator' => 'in', 'value' => 'high,urgent']);
        $this->assertTrue($evaluator->evaluateCondition($c2, new AutomationContext((object) ['priority' => 'urgent'], 't', ['priority' => 'urgent'])));

        $c3 = new AutomationCondition(['field' => 'notes', 'operator' => 'is_empty', 'value' => null]);
        $this->assertTrue($evaluator->evaluateCondition($c3, new AutomationContext((object) ['notes' => ''], 't', ['notes' => ''])));
    }
}
