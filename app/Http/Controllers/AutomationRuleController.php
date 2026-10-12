<?php

namespace App\Http\Controllers;

use App\Automations\AutomationActionRegistry;
use App\Automations\AutomationContext;
use App\Automations\AutomationTriggerRegistry;
use App\Models\AutomationAction;
use App\Models\AutomationCondition;
use App\Models\AutomationRule;
use App\Services\AutomationEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AutomationRuleController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('automations.view') || $request->user()->hasRole('super_admin'), 403);

        return Inertia::render('Automations/Index', [
            'rules' => AutomationRule::query()->withCount(['conditions', 'actions', 'runs'])->latest()->paginate(30),
            'canCreate' => $request->user()->can('automations.create') || $request->user()->hasRole('super_admin'),
            'canActivate' => $request->user()->can('automations.activate') || $request->user()->hasRole('super_admin'),
        ]);
    }

    public function create(Request $request, AutomationActionRegistry $actions): Response
    {
        abort_unless($request->user()->can('automations.create') || $request->user()->hasRole('super_admin'), 403);

        return Inertia::render('Automations/Form', [
            'rule' => null,
            'triggers' => AutomationTriggerRegistry::labels(),
            'actionTypes' => $actions->types(),
            'operators' => ['equals', 'not_equals', 'contains', 'greater_than', 'less_than', 'in', 'not_in', 'is_empty', 'is_not_empty', 'before', 'after'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('automations.create') || $request->user()->hasRole('super_admin'), 403);
        $data = $this->validated($request);

        $rule = DB::transaction(function () use ($data, $request) {
            $rule = AutomationRule::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'trigger_type' => $data['trigger_type'],
                'status' => 'draft',
                'group_operator' => $data['group_operator'] ?? 'and',
                'priority' => $data['priority'] ?? 100,
                'max_actions_per_hour' => $data['max_actions_per_hour'] ?? 50,
                'trigger_config' => $data['trigger_config'] ?? null,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
            $this->syncChildren($rule, $data);

            return $rule;
        });

        return redirect()->route('automations.show', $rule)->with('success', 'Automation saved as draft.');
    }

    public function show(Request $request, AutomationRule $automation): Response
    {
        abort_unless($request->user()->can('automations.view') || $request->user()->hasRole('super_admin'), 403);
        $automation->load(['conditions', 'actions', 'runs' => fn ($q) => $q->latest()->limit(20)]);

        return Inertia::render('Automations/Show', [
            'rule' => $automation,
            'canEdit' => $request->user()->can('automations.edit') || $request->user()->hasRole('super_admin'),
            'canActivate' => $request->user()->can('automations.activate') || $request->user()->hasRole('super_admin'),
        ]);
    }

    public function edit(Request $request, AutomationRule $automation, AutomationActionRegistry $actions): Response
    {
        abort_unless($request->user()->can('automations.edit') || $request->user()->hasRole('super_admin'), 403);
        $automation->load(['conditions', 'actions']);

        return Inertia::render('Automations/Form', [
            'rule' => $automation,
            'triggers' => AutomationTriggerRegistry::labels(),
            'actionTypes' => $actions->types(),
            'operators' => ['equals', 'not_equals', 'contains', 'greater_than', 'less_than', 'in', 'not_in', 'is_empty', 'is_not_empty', 'before', 'after'],
        ]);
    }

    public function update(Request $request, AutomationRule $automation): RedirectResponse
    {
        abort_unless($request->user()->can('automations.edit') || $request->user()->hasRole('super_admin'), 403);
        $data = $this->validated($request);
        DB::transaction(function () use ($automation, $data, $request) {
            $automation->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'trigger_type' => $data['trigger_type'],
                'group_operator' => $data['group_operator'] ?? 'and',
                'priority' => $data['priority'] ?? 100,
                'max_actions_per_hour' => $data['max_actions_per_hour'] ?? 50,
                'trigger_config' => $data['trigger_config'] ?? null,
                'updated_by' => $request->user()->id,
            ]);
            $automation->conditions()->delete();
            $automation->actions()->delete();
            $this->syncChildren($automation, $data);
        });

        return redirect()->route('automations.show', $automation)->with('success', 'Automation updated.');
    }

    public function activate(Request $request, AutomationRule $automation): RedirectResponse
    {
        abort_unless($request->user()->can('automations.activate') || $request->user()->hasRole('super_admin'), 403);
        $automation->update(['status' => 'active', 'activated_at' => now(), 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Automation activated.');
    }

    public function deactivate(Request $request, AutomationRule $automation): RedirectResponse
    {
        abort_unless($request->user()->can('automations.activate') || $request->user()->hasRole('super_admin'), 403);
        $automation->update(['status' => 'disabled', 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Automation disabled.');
    }

    public function destroy(Request $request, AutomationRule $automation): RedirectResponse
    {
        abort_unless($request->user()->can('automations.delete') || $request->user()->hasRole('super_admin'), 403);
        $automation->delete();

        return redirect()->route('automations.index')->with('success', 'Automation deleted.');
    }

    public function dryRun(Request $request, AutomationRule $automation, AutomationEngine $engine): RedirectResponse
    {
        abort_unless($request->user()->can('automations.edit') || $request->user()->hasRole('super_admin'), 403);
        $entityType = $request->input('entity_type');
        $entityId = $request->input('entity_id');
        $entity = ($entityType && $entityId && class_exists($entityType)) ? $entityType::find($entityId) : null;
        $run = $engine->dryRun($automation, new AutomationContext(
            entity: $entity,
            triggerType: $automation->trigger_type,
            data: $request->input('data', []),
            dryRun: true,
        ));

        return redirect()->route('automation-runs.show', $run)->with('success', 'Dry run completed (no external sends).');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'trigger_type' => ['required', 'string', Rule::in(array_keys(AutomationTriggerRegistry::labels()))],
            'group_operator' => ['nullable', Rule::in(['and', 'or'])],
            'priority' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'max_actions_per_hour' => ['nullable', 'integer', 'min:1', 'max:500'],
            'trigger_config' => ['nullable', 'array'],
            'conditions' => ['nullable', 'array'],
            'conditions.*.field' => ['required_with:conditions', 'string'],
            'conditions.*.operator' => ['required_with:conditions', 'string'],
            'conditions.*.value' => ['nullable'],
            'actions' => ['nullable', 'array'],
            'actions.*.action_type' => ['required_with:actions', 'string'],
            'actions.*.config' => ['nullable', 'array'],
            'actions.*.delay_minutes' => ['nullable', 'integer', 'min:0'],
            'actions.*.continue_on_match' => ['nullable', 'boolean'],
        ]);
    }

    protected function syncChildren(AutomationRule $rule, array $data): void
    {
        foreach ($data['conditions'] ?? [] as $i => $c) {
            AutomationCondition::create([
                'automation_rule_id' => $rule->id,
                'field' => $c['field'],
                'operator' => $c['operator'],
                'value' => is_array($c['value'] ?? null) ? json_encode($c['value']) : ($c['value'] ?? null),
                'sort_order' => $i,
            ]);
        }
        foreach ($data['actions'] ?? [] as $i => $a) {
            AutomationAction::create([
                'automation_rule_id' => $rule->id,
                'action_type' => $a['action_type'],
                'config' => $a['config'] ?? [],
                'delay_minutes' => $a['delay_minutes'] ?? 0,
                'continue_on_match' => $a['continue_on_match'] ?? true,
                'sort_order' => $i,
            ]);
        }
    }
}
