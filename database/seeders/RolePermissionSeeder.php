<?php

namespace Database\Seeders;

use App\Models\ClientOnboardingTemplate;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'users.manage', 'roles.manage', 'agency.settings',
            'clients.view', 'clients.view_all', 'clients.create', 'clients.update', 'clients.delete',
            'finance.view', 'finance.internal',
            'packages.manage', 'packages.view', 'packages.create', 'packages.edit', 'packages.delete',
            'client_packages.view', 'client_packages.assign', 'client_packages.edit', 'client_packages.cancel',
            'package_usage.view', 'package_usage.adjust', 'package_overages.view', 'package_overages.manage',
            'campaigns.manage', 'campaigns.view', 'campaigns.view_all', 'campaigns.create', 'campaigns.edit',
            'campaigns.delete', 'campaigns.manage_status',
            'deliverables.view', 'deliverables.create', 'deliverables.edit', 'deliverables.manage_status',
            'tasks.manage', 'tasks.view', 'tasks.view_all', 'tasks.view_assigned', 'tasks.create', 'tasks.edit', 'tasks.assign',
            'internal_reviews.view', 'internal_reviews.submit', 'internal_reviews.approve', 'internal_reviews.request_changes',
            'content.manage', 'content.view', 'content.view_all', 'content.create', 'content.edit', 'content.delete',
            'content.upload_revision', 'content.schedule', 'content.publish',
            'client_approvals.view', 'client_approvals.send', 'client_approvals.cancel',
            'client_approvals.approve', 'client_approvals.request_changes',
            'content_calendar.view', 'content_comments.internal', 'content_comments.client_visible',
            'client_requests.view', 'client_requests.view_all', 'client_requests.create', 'client_requests.edit',
            'client_requests.review', 'client_requests.assign', 'client_requests.convert', 'client_requests.complete', 'client_requests.cancel',
            'communications.view', 'communications.create', 'communications.edit', 'communications.delete',
            'followups.view', 'followups.view_all', 'followups.create', 'followups.edit', 'followups.complete', 'followups.assign',
            'portal_settings.view', 'portal_settings.edit',
            'notifications.view',
            'notification_settings.view', 'notification_settings.manage',
            'notification_templates.view', 'notification_templates.manage',
            'notification_delivery_logs.view',
            'email_settings.manage', 'push_settings.manage', 'notifications.send_test',
            'automations.view', 'automations.create', 'automations.edit', 'automations.activate', 'automations.delete',
            'automation_runs.view',
            'integrations.view', 'integrations.manage', 'integration_logs.view',
            'performance.view', 'performance.sync', 'performance.export',
            'campaign_kpis.view', 'campaign_kpis.manage',
            'client_reports.view', 'client_reports.create', 'client_reports.edit', 'client_reports.publish', 'client_reports.delete',
            'metric_sources.view', 'metric_sources.manage', 'manual_metrics.manage',
            'time_entries.view_own', 'time_entries.view_all', 'time_entries.create', 'time_entries.edit_own',
            'time_entries.edit_all', 'time_entries.delete', 'time_entries.approve',
            'workload.view', 'workload.manage_capacity', 'utilization.view',
            'delay_reasons.view', 'delay_reasons.manage', 'delays.create', 'delays.resolve', 'delays.view',
            'timesheets.view_own', 'timesheets.view_all', 'timesheets.approve',
            'dashboard.executive', 'dashboard.operations', 'dashboard.account_manager',
            'reports.operational', 'reports.financial',
            'cost_rates.view', 'cost_rates.manage',
            'profitability.view', 'profitability.view_clients', 'profitability.view_campaigns',
            'financial_dashboard.view',
            'quotations.view', 'quotations.create', 'quotations.edit', 'quotations.send', 'quotations.cancel', 'quotations.convert',
            'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.send', 'invoices.cancel',
            'payments.view', 'payments.record', 'payments.reverse',
            'receivables.view', 'billing_settings.view', 'billing_settings.manage',
            'renewals.view', 'renewals.manage',
            'reports.view', 'portal.access',
            'leads.view', 'leads.view_all', 'leads.create', 'leads.edit', 'leads.assign', 'leads.import',
            'leads.merge', 'leads.convert', 'leads.manage_status',
            'opportunities.view', 'opportunities.create', 'opportunities.edit', 'opportunities.manage',
            'lead_reports.view', 'lead_sources.manage', 'lead_assignment_rules.manage',
            'company_settings.view', 'company_settings.manage', 'company_branding.manage',
            'chat.use', 'chat.create_group', 'chat.upload_attachment', 'chat.manage_own_groups', 'chat.admin_recovery',
            // HRMS
            'hr.dashboard.view', 'hr.dashboard.manager', 'hr.dashboard.owner',
            'hr.view_all',
            'hr.employees.view_own', 'hr.employees.view_team', 'hr.employees.view_all', 'hr.employees.manage',
            'hr.attendance.view_own', 'hr.attendance.view_team', 'hr.attendance.manage', 'hr.attendance.punch',
            'hr.leave.view_own', 'hr.leave.view_team', 'hr.leave.request', 'hr.leave.approve', 'hr.leave.manage',
            'hr.approvals.act_as_owner',
            'hr.documents.view_own', 'hr.documents.view_team', 'hr.documents.review', 'hr.documents.manage',
            'hr.expenses.view_own', 'hr.expenses.view_team', 'hr.expenses.request', 'hr.expenses.approve', 'hr.expenses.pay',
            'hr.payroll.view', 'hr.payroll.manage', 'hr.salary.manage',
            'hr.org.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $super = Role::findOrCreate('super_admin');
        $admin = Role::findOrCreate('admin');
        $am = Role::findOrCreate('account_manager');
        $team = Role::findOrCreate('team_member');
        $client = Role::findOrCreate('client');
        $manager = Role::findOrCreate('manager');
        $employeeRole = Role::findOrCreate('employee');

        $super->syncPermissions(Permission::all());

        // Admin gets ops dashboards + operational reports; financial owner metrics stay on super_admin unless granted.
        $admin->syncPermissions(Permission::whereNotIn('name', [
            'portal.access',
            'finance.internal',
            'dashboard.executive',
            'financial_dashboard.view',
            'cost_rates.view',
            'cost_rates.manage',
            'profitability.view',
            'profitability.view_clients',
            'profitability.view_campaigns',
            'reports.financial',
            'quotations.view', 'quotations.create', 'quotations.edit', 'quotations.send', 'quotations.cancel', 'quotations.convert',
            'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.send', 'invoices.cancel',
            'payments.view', 'payments.record', 'payments.reverse',
            'receivables.view', 'billing_settings.view', 'billing_settings.manage',
            'renewals.view', 'renewals.manage',
            'company_settings.view', 'company_settings.manage', 'company_branding.manage',
            'chat.admin_recovery',
        ])->pluck('name')->merge(['dashboard.operations', 'reports.operational'])->unique());

        $am->syncPermissions([
            'clients.view', 'clients.create', 'clients.update',
            'packages.view', 'client_packages.view', 'client_packages.assign', 'client_packages.edit',
            'package_usage.view', 'package_usage.adjust', 'package_overages.view', 'package_overages.manage',
            'campaigns.view', 'campaigns.create', 'campaigns.edit', 'campaigns.manage_status',
            'deliverables.view', 'deliverables.create', 'deliverables.edit', 'deliverables.manage_status',
            'tasks.view', 'tasks.create', 'tasks.edit', 'tasks.assign', 'tasks.manage',
            'internal_reviews.view', 'internal_reviews.submit', 'internal_reviews.approve', 'internal_reviews.request_changes',
            'content.view', 'content.create', 'content.edit', 'content.upload_revision', 'content.schedule', 'content.publish',
            'client_approvals.view', 'client_approvals.send', 'client_approvals.cancel',
            'content_calendar.view', 'content_comments.internal', 'content_comments.client_visible',
            'client_requests.view', 'client_requests.create', 'client_requests.edit', 'client_requests.review',
            'client_requests.assign', 'client_requests.convert', 'client_requests.complete', 'client_requests.cancel',
            'communications.view', 'communications.create', 'communications.edit',
            'followups.view', 'followups.create', 'followups.edit', 'followups.complete', 'followups.assign',
            'portal_settings.view', 'portal_settings.edit',
            'notifications.view',
            'automations.view',
            'integrations.view',
            'performance.view', 'performance.sync', 'performance.export',
            'campaign_kpis.view', 'campaign_kpis.manage',
            'client_reports.view', 'client_reports.create', 'client_reports.edit', 'client_reports.publish',
            'metric_sources.view', 'manual_metrics.manage',
            'time_entries.view_own', 'time_entries.view_all', 'time_entries.create', 'time_entries.edit_own',
            'workload.view', 'utilization.view',
            'delays.create', 'delays.resolve', 'delays.view', 'delay_reasons.view',
            'timesheets.view_own', 'timesheets.view_all',
            'dashboard.account_manager', 'reports.operational',
            'reports.view',
            'quotations.view', 'quotations.create', 'quotations.edit', 'quotations.send',
            'invoices.view',
            'renewals.view',
            'leads.view', 'leads.create', 'leads.edit', 'leads.assign', 'leads.import', 'leads.manage_status', 'leads.convert',
            'opportunities.view', 'opportunities.create', 'opportunities.edit',
            'lead_reports.view',
            'chat.use', 'chat.create_group', 'chat.upload_attachment', 'chat.manage_own_groups',
            'hr.dashboard.view', 'hr.dashboard.manager',
            'hr.employees.view_own', 'hr.employees.view_team',
            'hr.attendance.view_own', 'hr.attendance.view_team', 'hr.attendance.punch',
            'hr.leave.view_own', 'hr.leave.view_team', 'hr.leave.request', 'hr.leave.approve',
            'hr.documents.view_own', 'hr.documents.view_team',
            'hr.expenses.view_own', 'hr.expenses.view_team', 'hr.expenses.request', 'hr.expenses.approve',
        ]);

        $manager->syncPermissions([
            'hr.dashboard.view', 'hr.dashboard.manager',
            'hr.employees.view_own', 'hr.employees.view_team',
            'hr.attendance.view_own', 'hr.attendance.view_team', 'hr.attendance.punch',
            'hr.leave.view_own', 'hr.leave.view_team', 'hr.leave.request', 'hr.leave.approve',
            'hr.documents.view_own', 'hr.documents.view_team',
            'hr.expenses.view_own', 'hr.expenses.view_team', 'hr.expenses.request', 'hr.expenses.approve',
            'tasks.view', 'tasks.view_assigned', 'tasks.create', 'tasks.edit', 'tasks.assign',
            'notifications.view',
            'chat.use',
        ]);

        $employeeRole->syncPermissions([
            'hr.dashboard.view',
            'hr.employees.view_own',
            'hr.attendance.view_own', 'hr.attendance.punch',
            'hr.leave.view_own', 'hr.leave.request',
            'hr.documents.view_own',
            'hr.expenses.view_own', 'hr.expenses.request',
            'tasks.view', 'tasks.view_assigned',
            'notifications.view',
            'chat.use',
        ]);

        $team->syncPermissions([
            'tasks.view', 'tasks.view_assigned', 'tasks.create', 'tasks.edit',
            'campaigns.view', 'deliverables.view',
            'package_usage.view',
            'internal_reviews.submit', 'internal_reviews.view',
            'content.view', 'content.create', 'content.edit', 'content.upload_revision',
            'content_comments.internal',
            'client_requests.view',
            'followups.view', 'followups.complete',
            'notifications.view',
            'time_entries.view_own', 'time_entries.create', 'time_entries.edit_own', 'time_entries.delete',
            'timesheets.view_own',
            'delays.create', 'delays.resolve', 'delays.view',
            'leads.view', 'leads.edit', 'leads.manage_status',
            'opportunities.view',
            'chat.use', 'chat.create_group', 'chat.upload_attachment', 'chat.manage_own_groups',
            'hr.dashboard.view',
            'hr.employees.view_own',
            'hr.attendance.view_own', 'hr.attendance.punch',
            'hr.leave.view_own', 'hr.leave.request',
            'hr.documents.view_own',
            'hr.expenses.view_own', 'hr.expenses.request',
        ]);

        $client->syncPermissions([
            'portal.access',
            'client_approvals.view',
            'client_approvals.approve',
            'client_approvals.request_changes',
            'content.view',
            'content_comments.client_visible',
            'client_requests.view',
            'client_requests.create',
            'client_requests.cancel',
            'notifications.view',
        ]);

        $defaults = [
            'Agreement Signed', 'Advance Payment Received', 'Logo Received', 'Brand Guidelines Received',
            'Product Photos Received', 'Product Videos Received', 'Instagram Access', 'Facebook Access',
            'Meta Business Manager Access', 'Google Ads Access', 'Google Analytics Access', 'Website Access',
            'Search Console Access', 'Company Profile Received', 'Brand Questionnaire Completed',
        ];

        foreach ($defaults as $i => $label) {
            ClientOnboardingTemplate::updateOrCreate(
                ['label' => $label],
                ['sort_order' => $i + 1, 'is_active' => true, 'is_default' => true]
            );
        }

        $this->call(DelayReasonSeeder::class);
        $this->call(LeadDefaultsSeeder::class);

        if (! \App\Models\TaxRate::where('name', 'GST 18%')->exists()) {
            \App\Models\TaxRate::create([
                'name' => 'GST 18%',
                'percentage' => 18,
                'tax_type' => 'gst',
                'cgst_percentage' => 9,
                'sgst_percentage' => 9,
                'igst_percentage' => 18,
                'is_active' => true,
            ]);
            \App\Models\TaxRate::create([
                'name' => 'Exempt',
                'percentage' => 0,
                'tax_type' => 'exempt',
                'cgst_percentage' => 0,
                'sgst_percentage' => 0,
                'igst_percentage' => 0,
                'is_active' => true,
            ]);
        }

        \App\Models\BillingSetting::current();
        \App\Models\NotificationSetting::current();
        $this->seedNotificationTemplates();
        $this->seedExampleAutomations();
    }

    protected function seedExampleAutomations(): void
    {
        if (! \App\Models\AutomationRule::where('name', 'Urgent request follow-up')->exists()) {
            $urgent = \App\Models\AutomationRule::create([
                'name' => 'Urgent request follow-up',
                'description' => 'When an urgent client request is submitted, create a follow-up for the account manager.',
                'trigger_type' => \App\Automations\AutomationTriggerRegistry::CLIENT_REQUEST_SUBMITTED,
                'status' => 'disabled',
                'is_system' => true,
                'group_operator' => 'and',
                'priority' => 10,
            ]);
            \App\Models\AutomationCondition::create([
                'automation_rule_id' => $urgent->id,
                'field' => 'priority',
                'operator' => 'equals',
                'value' => 'urgent',
                'sort_order' => 0,
            ]);
            \App\Models\AutomationAction::create([
                'automation_rule_id' => $urgent->id,
                'action_type' => 'create_follow_up',
                'config' => [
                    'title' => 'Urgent request: {{title}}',
                    'priority' => 'high',
                ],
                'sort_order' => 0,
            ]);

            $approval = \App\Models\AutomationRule::create([
                'name' => 'Approval delay reminder (template)',
                'description' => 'Draft template — remind after approval requested (activate and tune delay).',
                'trigger_type' => \App\Automations\AutomationTriggerRegistry::APPROVAL_REQUESTED,
                'status' => 'draft',
                'is_system' => true,
                'group_operator' => 'and',
                'priority' => 50,
            ]);
            \App\Models\AutomationAction::create([
                'automation_rule_id' => $approval->id,
                'action_type' => 'send_notification',
                'config' => [
                    'title' => 'Approval still pending',
                    'body' => 'Please follow up on pending client approval.',
                    'category' => 'approval',
                ],
                'delay_minutes' => 1440,
                'continue_on_match' => true,
                'sort_order' => 0,
            ]);
        }

        if (! \App\Models\AutomationRule::where('name', 'Monthly performance report draft')->exists()) {
            $monthly = \App\Models\AutomationRule::create([
                'name' => 'Monthly performance report draft',
                'description' => 'Disabled template — generate a draft client performance report on a schedule. Never auto-publishes.',
                'trigger_type' => \App\Automations\AutomationTriggerRegistry::SCHEDULED,
                'status' => 'disabled',
                'is_system' => true,
                'group_operator' => 'and',
                'priority' => 80,
                'trigger_config' => ['cron' => '0 6 1 * *'],
            ]);
            \App\Models\AutomationAction::create([
                'automation_rule_id' => $monthly->id,
                'action_type' => 'generate_performance_report',
                'config' => [
                    'title' => 'Monthly performance report',
                ],
                'sort_order' => 0,
            ]);
        }

        if (! \App\Models\AutomationRule::where('name', 'New lead auto-assign')->exists()) {
            $assign = \App\Models\AutomationRule::create([
                'name' => 'New lead auto-assign',
                'description' => 'Disabled template — assign new unassigned leads using the lead assignment rules.',
                'trigger_type' => \App\Automations\AutomationTriggerRegistry::LEAD_CREATED,
                'status' => 'disabled',
                'is_system' => true,
                'group_operator' => 'and',
                'priority' => 20,
            ]);
            \App\Models\AutomationAction::create([
                'automation_rule_id' => $assign->id,
                'action_type' => 'assign_lead',
                'config' => ['mode' => 'rules', 'only_unassigned' => true],
                'sort_order' => 0,
            ]);
        }

        if (! \App\Models\AutomationRule::where('name', 'Uncontacted lead follow-up')->exists()) {
            $uncontacted = \App\Models\AutomationRule::create([
                'name' => 'Uncontacted lead follow-up',
                'description' => 'Disabled template — create a follow-up when a lead has not been contacted in time.',
                'trigger_type' => \App\Automations\AutomationTriggerRegistry::LEAD_UNCONTACTED,
                'status' => 'disabled',
                'is_system' => true,
                'group_operator' => 'and',
                'priority' => 30,
            ]);
            \App\Models\AutomationAction::create([
                'automation_rule_id' => $uncontacted->id,
                'action_type' => 'create_follow_up',
                'config' => ['title' => 'Contact uncontacted lead', 'priority' => 'high'],
                'sort_order' => 0,
            ]);
        }

        \App\Models\ReportTemplateSetting::current();
    }

    protected function seedNotificationTemplates(): void
    {
        $templates = [
            [
                'key' => 'task_assigned',
                'category' => 'task',
                'name' => 'Task Assigned',
                'subject' => 'Task assigned: {{ task_name }}',
                'heading' => 'New task assignment',
                'body' => 'You have been assigned “{{ task_name }}”. Due: {{ due_date }}.',
                'cta_label' => 'Open task',
            ],
            [
                'key' => 'task_due',
                'category' => 'task',
                'name' => 'Task Due',
                'subject' => 'Task due: {{ task_name }}',
                'heading' => 'Task reminder',
                'body' => '“{{ task_name }}” is due on {{ due_date }}.',
                'cta_label' => 'Open task',
            ],
            [
                'key' => 'follow_up_reminder',
                'category' => 'follow_up',
                'name' => 'Follow-up Reminder',
                'subject' => 'Follow-up: {{ follow_up_title }}',
                'heading' => 'Follow-up reminder',
                'body' => 'Follow-up “{{ follow_up_title }}” is due at {{ due_at }}.',
                'cta_label' => 'Open follow-ups',
            ],
            [
                'key' => 'client_request_received',
                'category' => 'client_request',
                'name' => 'Client Request Received',
                'subject' => 'New request {{ request_number }} from {{ client_name }}',
                'heading' => 'New client request',
                'body' => '{{ client_name }} submitted request {{ request_number }}.',
                'cta_label' => 'Review request',
            ],
            [
                'key' => 'content_approval_required',
                'category' => 'approval',
                'name' => 'Content Approval Required',
                'subject' => 'Approval needed: {{ content_title }}',
                'heading' => 'Content awaiting approval',
                'body' => 'Please review “{{ content_title }}” for {{ client_name }}.',
                'cta_label' => 'Review now',
            ],
            [
                'key' => 'quotation_sent',
                'category' => 'quotation',
                'name' => 'Quotation Sent',
                'subject' => 'Quotation {{ quotation_number }} ready',
                'heading' => 'New quotation',
                'body' => 'Quotation {{ quotation_number }} totaling {{ amount }} is ready for review.',
                'cta_label' => 'View quotation',
            ],
            [
                'key' => 'invoice_sent',
                'category' => 'invoice',
                'name' => 'Invoice Sent',
                'subject' => 'Invoice {{ invoice_number }} issued',
                'heading' => 'Invoice issued',
                'body' => 'Invoice {{ invoice_number }} for {{ amount }} is due on {{ due_date }}.',
                'cta_label' => 'View invoice',
            ],
            [
                'key' => 'invoice_reminder',
                'category' => 'invoice',
                'name' => 'Invoice Reminder',
                'subject' => 'Reminder: Invoice {{ invoice_number }}',
                'heading' => 'Payment reminder',
                'body' => 'Invoice {{ invoice_number }} has {{ amount_due }} due on {{ due_date }}.',
                'cta_label' => 'View invoice',
            ],
            [
                'key' => 'payment_recorded',
                'category' => 'payment',
                'name' => 'Payment Recorded',
                'subject' => 'Payment {{ payment_number }} recorded',
                'heading' => 'Payment received',
                'body' => 'Payment {{ payment_number }} of {{ amount }} was recorded.',
                'cta_label' => 'View invoice',
            ],
            [
                'key' => 'package_renewal',
                'category' => 'renewal',
                'name' => 'Package Renewal',
                'subject' => 'Renewal: {{ package_name }}',
                'heading' => 'Package renewal coming up',
                'body' => '{{ package_name }} renews on {{ end_date }} ({{ days_until }} days).',
                'cta_label' => 'View package',
            ],
            [
                'key' => 'daily_digest',
                'category' => 'system',
                'name' => 'Daily Digest',
                'subject' => 'Your DigiCRM daily digest',
                'heading' => 'Daily digest',
                'body' => '{{ digest_body }}',
                'cta_label' => 'Open notifications',
            ],
        ];

        foreach ($templates as $tpl) {
            \App\Models\NotificationTemplate::updateOrCreate(
                ['key' => $tpl['key']],
                array_merge($tpl, [
                    'footer' => 'DigiCRM notifications',
                    'is_active' => true,
                    'is_system' => true,
                ])
            );
        }
    }
}
