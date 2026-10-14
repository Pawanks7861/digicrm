<?php

use App\Http\Controllers\AutomationRuleController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CompanySettingsController;
use App\Http\Controllers\SettingsHubController;
use App\Http\Controllers\ClientPortalUserController;
use App\Http\Controllers\MenuAccessController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\AutomationRunController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CampaignDeliverableController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientPackageController;
use App\Http\Controllers\ClientRequestController;
use App\Http\Controllers\CommunicationController;
use App\Http\Controllers\ContentCalendarController;
use App\Http\Controllers\ContentItemController;
use App\Http\Controllers\CostRateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DelayController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\InternalReviewController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\Leads\LeadCaptureFormController;
use App\Http\Controllers\Leads\LeadController;
use App\Http\Controllers\Leads\LeadDuplicateController;
use App\Http\Controllers\Leads\LeadImportController;
use App\Http\Controllers\Leads\LeadReportController;
use App\Http\Controllers\Leads\LeadSettingsController;
use App\Http\Controllers\Leads\OpportunityController;
use App\Http\Controllers\Leads\PipelineController;
use App\Http\Controllers\Leads\PublicLeadCaptureController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationPreferenceController;
use App\Http\Controllers\NotificationSettingsController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Portal\PortalController;
use App\Http\Controllers\Portal\PortalFinanceController;
use App\Http\Controllers\Portal\PortalLeadController;
use App\Http\Controllers\Portal\PortalPerformanceController;
use App\Http\Controllers\Portal\PortalReportsController;
use App\Http\Controllers\Performance\CampaignKpiController;
use App\Http\Controllers\Performance\CampaignPerformanceController;
use App\Http\Controllers\Performance\ClientPerformanceController;
use App\Http\Controllers\Performance\ClientPerformanceReportController;
use App\Http\Controllers\Performance\ContentPerformanceController;
use App\Http\Controllers\Performance\DataSourcesController;
use App\Http\Controllers\Performance\ManualMetricController;
use App\Http\Controllers\Performance\MetricExportController;
use App\Http\Controllers\Performance\MetricMappingController;
use App\Http\Controllers\Performance\PerformanceReportCenterController;
use App\Http\Controllers\Performance\SyncHistoryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ServicePackageController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TimeEntryController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\WorkloadController;
use App\Http\Controllers\Hr\AttendanceController as HrAttendanceController;
use App\Http\Controllers\Hr\EmployeeController as HrEmployeeController;
use App\Http\Controllers\Hr\HolidayController as HrHolidayController;
use App\Http\Controllers\Hr\HrDashboardController;
use App\Http\Controllers\Hr\LeaveRequestController as HrLeaveRequestController;
use App\Http\Controllers\Hr\OvertimeController as HrOvertimeController;
use App\Http\Controllers\Hr\PayrollController as HrPayrollController;
use App\Http\Controllers\Hr\PayslipController as HrPayslipController;
use App\Http\Controllers\Hr\SalaryController as HrSalaryController;
use App\Http\Controllers\Hr\ShiftController as HrShiftController;
use App\Services\ClientApprovalService;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/login');

Route::post('/webhooks/{provider}/{event?}', WebhookController::class)->middleware('throttle:webhooks')->name('webhooks.inbound');
Route::get('/webhooks/{provider}/{event?}', WebhookController::class)->middleware('throttle:webhooks')->name('webhooks.challenge');
Route::post('/lead-capture/{formKey}', PublicLeadCaptureController::class)->middleware('throttle:lead-capture')->name('lead-capture.submit');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified', 'agency'])
    ->name('dashboard');

Route::middleware(['auth', 'verified', 'agency'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings', [SettingsHubController::class, 'index'])->name('settings.index');
    Route::get('settings/users', [UserManagementController::class, 'index'])->name('settings.users.index');
    Route::get('settings/users/create', [UserManagementController::class, 'create'])->name('settings.users.create');
    Route::post('settings/users', [UserManagementController::class, 'store'])->name('settings.users.store');
    Route::get('settings/users/{managedUser}/edit', [UserManagementController::class, 'edit'])->name('settings.users.edit');
    Route::put('settings/users/{managedUser}', [UserManagementController::class, 'update'])->name('settings.users.update');
    Route::get('settings/users/{managedUser}/menus', [MenuAccessController::class, 'editUser'])->name('settings.users.menus');
    Route::put('settings/users/{managedUser}/menus', [MenuAccessController::class, 'updateUser'])->name('settings.users.menus.update');
    Route::get('settings/menu-access', [MenuAccessController::class, 'index'])->name('settings.menu-access.index');
    Route::put('settings/menu-access/roles/{role}', [MenuAccessController::class, 'updateRole'])->name('settings.menu-access.roles.update');
    Route::get('settings/audit-logs', [AuditLogController::class, 'index'])->name('settings.audit-logs.index');
    Route::get('settings/company', [CompanySettingsController::class, 'edit'])->name('settings.company');
    Route::put('settings/company', [CompanySettingsController::class, 'update'])->name('settings.company.update');
    Route::post('settings/company/logo', [CompanySettingsController::class, 'uploadLogo'])->name('settings.company.logo');
    Route::delete('settings/company/logo', [CompanySettingsController::class, 'removeLogo'])->name('settings.company.logo.remove');
    Route::post('settings/company/favicon', [CompanySettingsController::class, 'uploadFavicon'])->name('settings.company.favicon');
    Route::delete('settings/company/favicon', [CompanySettingsController::class, 'removeFavicon'])->name('settings.company.favicon.remove');

    Route::prefix('hr')->name('hr.')->group(function () {
        Route::get('/', HrDashboardController::class)->name('dashboard');
        Route::get('employees', [HrEmployeeController::class, 'index'])->name('employees.index');
        Route::get('employees/create', [HrEmployeeController::class, 'create'])->name('employees.create');
        Route::post('employees', [HrEmployeeController::class, 'store'])->name('employees.store');
        Route::get('employees/{employee}', [HrEmployeeController::class, 'show'])->name('employees.show');
        Route::get('employees/{employee}/edit', [HrEmployeeController::class, 'edit'])->name('employees.edit');
        Route::put('employees/{employee}', [HrEmployeeController::class, 'update'])->name('employees.update');
        Route::get('attendance', [HrAttendanceController::class, 'index'])->name('attendance.index');
        Route::post('attendance/punch', [HrAttendanceController::class, 'punch'])->name('attendance.punch');
        Route::post('attendance/{attendanceDay}/corrections', [HrAttendanceController::class, 'correct'])->name('attendance.correct');
        Route::get('shifts', [HrShiftController::class, 'index'])->name('shifts.index');
        Route::post('shifts', [HrShiftController::class, 'store'])->name('shifts.store');
        Route::put('shifts/{workShift}', [HrShiftController::class, 'update'])->name('shifts.update');
        Route::post('shifts/assign', [HrShiftController::class, 'assign'])->name('shifts.assign');
        Route::get('shifts/calendar/{employee}', [HrShiftController::class, 'calendar'])->name('shifts.calendar');
        Route::get('holidays', [HrHolidayController::class, 'index'])->name('holidays.index');
        Route::post('holidays', [HrHolidayController::class, 'store'])->name('holidays.store');
        Route::put('holidays/{holiday}', [HrHolidayController::class, 'update'])->name('holidays.update');
        Route::delete('holidays/{holiday}', [HrHolidayController::class, 'destroy'])->name('holidays.destroy');
        Route::get('holidays/export', [HrHolidayController::class, 'export'])->name('holidays.export');
        Route::post('holidays/import', [HrHolidayController::class, 'import'])->name('holidays.import');
        Route::get('overtime', [HrOvertimeController::class, 'index'])->name('overtime.index');
        Route::post('overtime/policies', [HrOvertimeController::class, 'storePolicy'])->name('overtime.policies.store');
        Route::post('overtime/{attendanceDay}', [HrOvertimeController::class, 'submit'])->name('overtime.submit');
        Route::post('overtime/{overtimeEntry}/decide', [HrOvertimeController::class, 'decide'])->name('overtime.decide');
        Route::get('salary', [HrSalaryController::class, 'index'])->name('salary.index');
        Route::post('salary/components', [HrSalaryController::class, 'storeComponent'])->name('salary.components.store');
        Route::post('salary/structures', [HrSalaryController::class, 'storeStructure'])->name('salary.structures.store');
        Route::post('salary/assignments', [HrSalaryController::class, 'assign'])->name('salary.assign');
        Route::get('payslips/{payrollItem}', [HrPayslipController::class, 'show'])->name('payslips.show');
        Route::get('leave', [HrLeaveRequestController::class, 'index'])->name('leave.index');
        Route::post('leave', [HrLeaveRequestController::class, 'store'])->name('leave.store');
        Route::post('leave/{leaveRequest}/approve', [HrLeaveRequestController::class, 'approve'])->name('leave.approve');
        Route::post('leave/{leaveRequest}/reject', [HrLeaveRequestController::class, 'reject'])->name('leave.reject');
        Route::post('leave/{leaveRequest}/cancel', [HrLeaveRequestController::class, 'cancel'])->name('leave.cancel');
        Route::get('payroll', [HrPayrollController::class, 'index'])->name('payroll.index');
        Route::post('payroll', [HrPayrollController::class, 'generate'])->name('payroll.generate');
        Route::post('payroll/{payrollRun}/transition', [HrPayrollController::class, 'transition'])->name('payroll.transition');
    });

    Route::get('chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('chat/feed', [ChatController::class, 'feed'])->name('chat.feed');
    Route::get('chat/users', [ChatController::class, 'users'])->name('chat.users');
    Route::post('chat/direct', [ChatController::class, 'direct'])->name('chat.direct');
    Route::post('chat/groups', [ChatController::class, 'storeGroup'])->name('chat.groups.store');
    Route::get('chat/attachments/{attachment}', [ChatController::class, 'download'])->name('chat.attachments.show');
    Route::get('chat/conversations/{conversation}', [ChatController::class, 'show'])->name('chat.conversations.show');
    Route::post('chat/conversations/{conversation}/messages', [ChatController::class, 'storeMessage'])->name('chat.messages.store');
    Route::put('chat/messages/{message}', [ChatController::class, 'updateMessage'])->name('chat.messages.update');
    Route::delete('chat/messages/{message}', [ChatController::class, 'destroyMessage'])->name('chat.messages.destroy');
    Route::post('chat/conversations/{conversation}/read', [ChatController::class, 'read'])->name('chat.conversations.read');
    Route::post('chat/conversations/{conversation}/mute', [ChatController::class, 'mute'])->name('chat.conversations.mute');
    Route::put('chat/conversations/{conversation}', [ChatController::class, 'updateGroup'])->name('chat.conversations.update');
    Route::post('chat/conversations/{conversation}/members', [ChatController::class, 'addMember'])->name('chat.conversations.members.store');
    Route::delete('chat/conversations/{conversation}/members/{user}', [ChatController::class, 'removeMember'])->name('chat.conversations.members.destroy');
    Route::post('chat/conversations/{conversation}/members/{user}/promote', [ChatController::class, 'promote'])->name('chat.conversations.members.promote');
    Route::post('chat/conversations/{conversation}/members/{user}/demote', [ChatController::class, 'demote'])->name('chat.conversations.members.demote');
    Route::post('chat/conversations/{conversation}/leave', [ChatController::class, 'leave'])->name('chat.conversations.leave');
    Route::post('chat/conversations/{conversation}/recover', [ChatController::class, 'recover'])->name('chat.conversations.recover');

    Route::resource('clients', ClientController::class);
    Route::put('clients/{client}/brand', [ClientController::class, 'updateBrand'])->name('clients.brand.update');
    Route::post('clients/{client}/onboarding', [ClientController::class, 'addOnboarding'])->name('clients.onboarding.store');
    Route::put('clients/{client}/onboarding/{item}', [ClientController::class, 'toggleOnboarding'])->name('clients.onboarding.toggle');
    Route::post('clients/{client}/files', [ClientController::class, 'storeFile'])->name('clients.files.store');
    Route::get('clients/{client}/files/{file}/download', [ClientController::class, 'downloadFile'])->name('clients.files.download');

    Route::resource('service-packages', ServicePackageController::class);
    Route::post('service-packages/{service_package}/duplicate', [ServicePackageController::class, 'duplicate'])->name('service-packages.duplicate');
    Route::post('service-packages/{service_package}/toggle-status', [ServicePackageController::class, 'toggleStatus'])->name('service-packages.toggle-status');

    Route::post('clients/{client}/packages', [ClientPackageController::class, 'store'])->name('clients.packages.store');
    Route::get('client-packages/{clientPackage}', [ClientPackageController::class, 'show'])->name('client-packages.show');
    Route::get('client-packages/{clientPackage}/items/{item}', [ClientPackageController::class, 'itemHistory'])->name('client-packages.items.history');
    Route::post('client-packages/{clientPackage}/adjust', [ClientPackageController::class, 'adjust'])->name('client-packages.adjust');
    Route::post('client-packages/{clientPackage}/renew', [ClientPackageController::class, 'renew'])->name('client-packages.renew');
    Route::post('client-packages/{clientPackage}/cancel', [ClientPackageController::class, 'cancel'])->name('client-packages.cancel');
    Route::post('package-overages/{overage}/decide', [ClientPackageController::class, 'decideOverage'])->name('package-overages.decide');

    Route::resource('campaigns', CampaignController::class);
    Route::post('campaigns/{campaign}/deliverables', [CampaignDeliverableController::class, 'store'])->name('campaigns.deliverables.store');
    Route::post('campaigns/{campaign}/deliverables/bulk', [CampaignDeliverableController::class, 'bulkStore'])->name('campaigns.deliverables.bulk');
    Route::get('campaigns/{campaign}/deliverables/suggest', [CampaignDeliverableController::class, 'suggest'])->name('campaigns.deliverables.suggest');
    Route::get('deliverables/{deliverable}', [CampaignDeliverableController::class, 'show'])->name('deliverables.show');
    Route::post('deliverables/{deliverable}/transition', [CampaignDeliverableController::class, 'transition'])->name('deliverables.transition');
    Route::post('deliverables/{deliverable}/cancel', [CampaignDeliverableController::class, 'cancel'])->name('deliverables.cancel');
    Route::post('deliverables/{deliverable}/content/generate', [ContentItemController::class, 'generateFromDeliverable'])->name('deliverables.content.generate');

    Route::get('tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::get('tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::post('tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.status');
    Route::post('tasks/{task}/checklist', [TaskController::class, 'addChecklist'])->name('tasks.checklist.store');
    Route::put('tasks/{task}/checklist/{item}', [TaskController::class, 'toggleChecklist'])->name('tasks.checklist.toggle');
    Route::post('tasks/{task}/comments', [TaskController::class, 'addComment'])->name('tasks.comments.store');
    Route::post('tasks/{task}/dependencies', [TaskController::class, 'addDependency'])->name('tasks.dependencies.store');

    Route::get('internal-reviews', [InternalReviewController::class, 'index'])->name('internal-reviews.index');
    Route::post('deliverables/{deliverable}/reviews', [InternalReviewController::class, 'submitDeliverable'])->name('deliverables.reviews.submit');
    Route::post('tasks/{task}/reviews', [InternalReviewController::class, 'submitTask'])->name('tasks.reviews.submit');
    Route::post('internal-reviews/{review}/approve', [InternalReviewController::class, 'approve'])->name('internal-reviews.approve');
    Route::post('internal-reviews/{review}/request-changes', [InternalReviewController::class, 'requestChanges'])->name('internal-reviews.request-changes');

    Route::get('content', [ContentItemController::class, 'index'])->name('content.index');
    Route::get('content/create', [ContentItemController::class, 'create'])->name('content.create');
    Route::post('content', [ContentItemController::class, 'store'])->name('content.store');
    Route::get('content/{content}', [ContentItemController::class, 'show'])->name('content.show');
    Route::get('content/{content}/edit', [ContentItemController::class, 'edit'])->name('content.edit');
    Route::put('content/{content}', [ContentItemController::class, 'update'])->name('content.update');
    Route::post('content/{content}/revisions', [ContentItemController::class, 'uploadRevision'])->name('content.revisions.store');
    Route::post('content/{content}/send-to-client', [ContentItemController::class, 'sendToClient'])->name('content.send-to-client');
    Route::post('content/{content}/schedule', [ContentItemController::class, 'schedule'])->name('content.schedule');
    Route::post('content/{content}/publish', [ContentItemController::class, 'publish'])->name('content.publish');
    Route::post('content/{content}/reviews', [ContentItemController::class, 'submitReview'])->name('content.reviews.submit');
    Route::post('content/{content}/comments', [ContentItemController::class, 'comment'])->name('content.comments.store');
    Route::post('content/{content}/duplicate', [ContentItemController::class, 'duplicate'])->name('content.duplicate');
    Route::get('content/{content}/preview', [ContentItemController::class, 'preview'])->name('content.preview');

    Route::post('client-approvals/{approval}/cancel', function (\App\Models\ClientApproval $approval) {
        abort_unless(auth()->user()->can('cancel', $approval), 403);
        app(ClientApprovalService::class)->cancel($approval, auth()->user());

        return back()->with('success', 'Approval cancelled.');
    })->name('client-approvals.cancel');

    Route::get('calendar', [ContentCalendarController::class, 'index'])->name('calendar.index');

    Route::get('requests', [ClientRequestController::class, 'index'])->name('requests.index');
    Route::get('requests/{request}', [ClientRequestController::class, 'show'])->name('requests.show');
    Route::post('requests/{request}/check-scope', [ClientRequestController::class, 'checkScope'])->name('requests.check-scope');
    Route::post('requests/{request}/decide-overage', [ClientRequestController::class, 'decideOverage'])->name('requests.decide-overage');
    Route::post('requests/{request}/accept', [ClientRequestController::class, 'accept'])->name('requests.accept');
    Route::post('requests/{request}/need-info', [ClientRequestController::class, 'needInfo'])->name('requests.need-info');
    Route::post('requests/{request}/reject', [ClientRequestController::class, 'reject'])->name('requests.reject');
    Route::post('requests/{request}/convert-deliverable', [ClientRequestController::class, 'convertDeliverable'])->name('requests.convert-deliverable');
    Route::post('requests/{request}/convert-task', [ClientRequestController::class, 'convertTask'])->name('requests.convert-task');
    Route::post('requests/{request}/convert-campaign', [ClientRequestController::class, 'convertCampaign'])->name('requests.convert-campaign');
    Route::post('requests/{request}/comments', [ClientRequestController::class, 'comment'])->name('requests.comments.store');
    Route::post('requests/{request}/complete', [ClientRequestController::class, 'complete'])->name('requests.complete');
    Route::post('requests/{request}/cancel', [ClientRequestController::class, 'cancel'])->name('requests.cancel');

    Route::get('follow-ups', [FollowUpController::class, 'index'])->name('follow-ups.index');
    Route::post('follow-ups', [FollowUpController::class, 'store'])->name('follow-ups.store');
    Route::post('follow-ups/{followUp}/complete', [FollowUpController::class, 'complete'])->name('follow-ups.complete');
    Route::post('follow-ups/{followUp}/snooze', [FollowUpController::class, 'snooze'])->name('follow-ups.snooze');
    Route::post('follow-ups/{followUp}/reschedule', [FollowUpController::class, 'reschedule'])->name('follow-ups.reschedule');

    Route::post('clients/{client}/communications', [CommunicationController::class, 'store'])->name('clients.communications.store');
    Route::put('clients/{client}/portal-settings', [ClientController::class, 'updatePortalSettings'])->name('clients.portal-settings.update');
    Route::post('clients/{client}/portal-users', [ClientPortalUserController::class, 'store'])->name('clients.portal-users.store');
    Route::put('clients/{client}/portal-users/{portalUser}', [ClientPortalUserController::class, 'update'])->name('clients.portal-users.update');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    Route::get('profile/notifications', [NotificationPreferenceController::class, 'edit'])->name('profile.notifications');
    Route::put('profile/notifications', [NotificationPreferenceController::class, 'update'])->name('profile.notifications.update');
    Route::get('push/vapid-public-key', [NotificationPreferenceController::class, 'vapidPublicKey'])->name('push.vapid');
    Route::post('push/subscriptions', [NotificationPreferenceController::class, 'storePush'])->name('push.subscriptions.store');
    Route::delete('push/subscriptions/{subscription}', [NotificationPreferenceController::class, 'destroyPush'])->name('push.subscriptions.destroy');
    Route::post('push/test', [NotificationPreferenceController::class, 'testPush'])->name('push.test');

    Route::get('settings/notifications/email', [NotificationSettingsController::class, 'email'])->name('settings.notifications.email');
    Route::put('settings/notifications/email', [NotificationSettingsController::class, 'updateEmail'])->name('settings.notifications.email.update');
    Route::post('settings/notifications/email/test', [NotificationSettingsController::class, 'testEmail'])->name('settings.notifications.email.test');
    Route::get('settings/notifications/push', [NotificationSettingsController::class, 'push'])->name('settings.notifications.push');
    Route::put('settings/notifications/push', [NotificationSettingsController::class, 'updatePush'])->name('settings.notifications.push.update');
    Route::get('settings/notifications/defaults', [NotificationSettingsController::class, 'defaults'])->name('settings.notifications.defaults');
    Route::put('settings/notifications/defaults', [NotificationSettingsController::class, 'updateDefaults'])->name('settings.notifications.defaults.update');
    Route::get('settings/notifications/templates', [NotificationSettingsController::class, 'templates'])->name('settings.notifications.templates');
    Route::put('settings/notifications/templates/{template}', [NotificationSettingsController::class, 'updateTemplate'])->name('settings.notifications.templates.update');
    Route::get('settings/notifications/templates/{template}/preview', [NotificationSettingsController::class, 'previewTemplate'])->name('settings.notifications.templates.preview');
    Route::get('settings/notifications/deliveries', [NotificationSettingsController::class, 'deliveries'])->name('settings.notifications.deliveries');
    Route::post('settings/notifications/deliveries/{delivery}/retry', [NotificationSettingsController::class, 'retryDelivery'])->name('settings.notifications.deliveries.retry');
    Route::get('settings/notifications/health', [NotificationSettingsController::class, 'health'])->name('settings.notifications.health');

    Route::get('integrations', [IntegrationController::class, 'index'])->name('integrations.index');
    Route::get('integrations/google/redirect', [IntegrationController::class, 'googleRedirect'])->name('integrations.google.redirect');
    Route::get('integrations/google/callback', [IntegrationController::class, 'googleCallback'])->name('integrations.google.callback');
    Route::get('integrations/{integration}', [IntegrationController::class, 'show'])->name('integrations.show');
    Route::get('integrations/{integration}/health', [IntegrationController::class, 'health'])->name('integrations.health');
    Route::post('integrations/{integration}/disconnect', [IntegrationController::class, 'disconnect'])->name('integrations.disconnect');
    Route::post('integrations/{integration}/disable', [IntegrationController::class, 'disable'])->name('integrations.disable');
    Route::post('integrations/{integration}/test', [IntegrationController::class, 'testConnection'])->name('integrations.test');

    Route::get('automations', [AutomationRuleController::class, 'index'])->name('automations.index');
    Route::get('automations/create', [AutomationRuleController::class, 'create'])->name('automations.create');
    Route::post('automations', [AutomationRuleController::class, 'store'])->name('automations.store');
    Route::get('automations/{automation}', [AutomationRuleController::class, 'show'])->name('automations.show');
    Route::get('automations/{automation}/edit', [AutomationRuleController::class, 'edit'])->name('automations.edit');
    Route::put('automations/{automation}', [AutomationRuleController::class, 'update'])->name('automations.update');
    Route::delete('automations/{automation}', [AutomationRuleController::class, 'destroy'])->name('automations.destroy');
    Route::post('automations/{automation}/activate', [AutomationRuleController::class, 'activate'])->name('automations.activate');
    Route::post('automations/{automation}/deactivate', [AutomationRuleController::class, 'deactivate'])->name('automations.deactivate');
    Route::post('automations/{automation}/dry-run', [AutomationRuleController::class, 'dryRun'])->name('automations.dry-run');
    Route::get('automation-runs', [AutomationRunController::class, 'index'])->name('automation-runs.index');
    Route::get('automation-runs/{automation_run}', [AutomationRunController::class, 'show'])->name('automation-runs.show');

    // Phase 11 — Marketing Performance
    Route::get('performance', [PerformanceReportCenterController::class, 'index'])->name('performance.index');
    Route::get('performance/data-sources', [DataSourcesController::class, 'index'])->name('performance.data-sources');
    Route::post('performance/clients/{client}/sync', [DataSourcesController::class, 'sync'])->name('performance.sync');
    Route::get('performance/mappings', [MetricMappingController::class, 'index'])->name('performance.mappings');
    Route::post('performance/mappings', [MetricMappingController::class, 'store'])->name('performance.mappings.store');
    Route::get('performance/sync-history', [SyncHistoryController::class, 'index'])->name('performance.sync-history');
    Route::get('performance/manual', [ManualMetricController::class, 'index'])->name('performance.manual');
    Route::post('performance/manual', [ManualMetricController::class, 'store'])->name('performance.manual.store');
    Route::post('performance/manual/preview-csv', [ManualMetricController::class, 'previewCsv'])->name('performance.manual.preview-csv');
    Route::post('performance/manual/import-csv', [ManualMetricController::class, 'importCsv'])->name('performance.manual.import-csv');
    Route::get('clients/{client}/performance', [ClientPerformanceController::class, 'show'])->name('clients.performance');
    Route::get('clients/{client}/metrics/export', [MetricExportController::class, 'csv'])->name('clients.metrics.export');
    Route::get('campaigns/{campaign}/performance', [CampaignPerformanceController::class, 'show'])->name('campaigns.performance');
    Route::get('campaigns/{campaign}/kpis', [CampaignKpiController::class, 'index'])->name('campaigns.kpis');
    Route::post('campaigns/{campaign}/kpis', [CampaignKpiController::class, 'store'])->name('campaigns.kpis.store');
    Route::put('campaign-kpis/{kpi}', [CampaignKpiController::class, 'update'])->name('campaign-kpis.update');
    Route::get('content/{content}/performance', [ContentPerformanceController::class, 'show'])->name('content.performance');
    Route::get('performance/top-content', [ContentPerformanceController::class, 'top'])->name('performance.top-content');

    Route::get('client-reports', [ClientPerformanceReportController::class, 'index'])->name('client-reports.index');
    Route::get('client-reports/create', [ClientPerformanceReportController::class, 'create'])->name('client-reports.create');
    Route::post('client-reports', [ClientPerformanceReportController::class, 'store'])->name('client-reports.store');
    Route::get('client-reports/{clientReport}', [ClientPerformanceReportController::class, 'show'])->name('client-reports.show');
    Route::post('client-reports/{clientReport}/submit-review', [ClientPerformanceReportController::class, 'submitReview'])->name('client-reports.submit-review');
    Route::post('client-reports/{clientReport}/approve', [ClientPerformanceReportController::class, 'approve'])->name('client-reports.approve');
    Route::post('client-reports/{clientReport}/publish', [ClientPerformanceReportController::class, 'publish'])->name('client-reports.publish');
    Route::get('client-reports/{clientReport}/pdf', [ClientPerformanceReportController::class, 'pdf'])->name('client-reports.pdf');

    Route::get('timesheet', [TimeEntryController::class, 'timesheet'])->name('timesheet.index');
    Route::get('timesheets/team', [TimeEntryController::class, 'team'])->name('timesheets.team');
    Route::get('timesheets/export', [TimeEntryController::class, 'export'])->name('timesheets.export');
    Route::post('time-entries', [TimeEntryController::class, 'store'])->name('time-entries.store');
    Route::post('time-entries/start', [TimeEntryController::class, 'start'])->name('time-entries.start');
    Route::post('time-entries/{timeEntry}/stop', [TimeEntryController::class, 'stop'])->name('time-entries.stop');
    Route::put('time-entries/{timeEntry}', [TimeEntryController::class, 'update'])->name('time-entries.update');
    Route::delete('time-entries/{timeEntry}', [TimeEntryController::class, 'destroy'])->name('time-entries.destroy');

    Route::get('workload', [WorkloadController::class, 'index'])->name('workload.index');
    Route::get('workload/users/{user}', [WorkloadController::class, 'show'])->name('workload.show');
    Route::get('utilization', [WorkloadController::class, 'utilization'])->name('utilization.index');
    Route::get('capacity', [WorkloadController::class, 'capacityIndex'])->name('capacity.index');
    Route::post('capacity', [WorkloadController::class, 'storeCapacity'])->name('capacity.store');
    Route::post('capacity/holidays', [WorkloadController::class, 'storeHoliday'])->name('capacity.holidays.store');
    Route::get('workload/preview-assign', [WorkloadController::class, 'previewAssign'])->name('workload.preview-assign');

    Route::get('delays', [DelayController::class, 'index'])->name('delays.index');
    Route::post('delays', [DelayController::class, 'start'])->name('delays.start');
    Route::post('delays/{delayRecord}/resolve', [DelayController::class, 'resolve'])->name('delays.resolve');
    Route::get('delays/export', [DelayController::class, 'export'])->name('delays.export');

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/client-profitability', [ReportController::class, 'clientProfitability'])->name('reports.client-profitability');
    Route::get('reports/client-profitability/{client}', [ReportController::class, 'clientProfitabilityShow'])->name('reports.client-profitability.show');
    Route::get('reports/campaign-profitability', [ReportController::class, 'campaignProfitability'])->name('reports.campaign-profitability');
    Route::get('reports/package-profitability', [ReportController::class, 'packageProfitability'])->name('reports.package-profitability');
    Route::get('reports/scope-impact', [ReportController::class, 'scopeImpact'])->name('reports.scope-impact');
    Route::get('reports/complimentary-work', [ReportController::class, 'complimentaryWork'])->name('reports.complimentary-work');
    Route::get('reports/planned-vs-actual', [ReportController::class, 'plannedVsActual'])->name('reports.planned-vs-actual');
    Route::get('reports/delivery-performance', [ReportController::class, 'deliveryPerformance'])->name('reports.delivery-performance');

    Route::get('settings/cost-rates', [CostRateController::class, 'index'])->name('cost-rates.index');
    Route::post('settings/cost-rates', [CostRateController::class, 'store'])->name('cost-rates.store');
    Route::post('settings/operational-costs', [CostRateController::class, 'storeOperationalCost'])->name('operational-costs.store');

    Route::get('finance/receivables', [FinanceController::class, 'receivables'])->name('finance.receivables');
    Route::get('finance/renewals', [FinanceController::class, 'renewals'])->name('finance.renewals');
    Route::get('finance/unbilled-overages', [FinanceController::class, 'unbilledOverages'])->name('finance.unbilled-overages');
    Route::get('finance/billing-settings', [FinanceController::class, 'billingSettings'])->name('finance.billing-settings');
    Route::put('finance/billing-settings', [FinanceController::class, 'updateBillingSettings'])->name('finance.billing-settings.update');
    Route::post('finance/tax-rates', [FinanceController::class, 'storeTaxRate'])->name('finance.tax-rates.store');

    Route::get('quotations', [QuotationController::class, 'index'])->name('quotations.index');
    Route::get('quotations/create', [QuotationController::class, 'create'])->name('quotations.create');
    Route::post('quotations', [QuotationController::class, 'store'])->name('quotations.store');
    Route::get('quotations/{quotation}', [QuotationController::class, 'show'])->name('quotations.show');
    Route::get('quotations/{quotation}/edit', [QuotationController::class, 'edit'])->name('quotations.edit');
    Route::put('quotations/{quotation}', [QuotationController::class, 'update'])->name('quotations.update');
    Route::post('quotations/{quotation}/send', [QuotationController::class, 'send'])->name('quotations.send');
    Route::post('quotations/{quotation}/revise', [QuotationController::class, 'revise'])->name('quotations.revise');
    Route::post('quotations/{quotation}/convert', [QuotationController::class, 'convert'])->name('quotations.convert');
    Route::get('quotations/{quotation}/pdf', [QuotationController::class, 'pdf'])->name('quotations.pdf');
    Route::post('overages/{overage}/quotation', [QuotationController::class, 'fromOverage'])->name('overages.quotation');

    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
    Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
    Route::put('invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
    Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');
    Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
    Route::post('invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('invoices.payments.store');
    Route::post('invoices/{invoice}/overages/{overage}', [InvoiceController::class, 'addOverage'])->name('invoices.overages.add');
    Route::post('client-packages/{clientPackage}/invoice', [InvoiceController::class, 'fromPackage'])->name('client-packages.invoice');

    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('payments/{payment}/reverse', [PaymentController::class, 'reverse'])->name('payments.reverse');
    Route::get('payments/{payment}/pdf', [PaymentController::class, 'pdf'])->name('payments.pdf');

    Route::get('leads', [LeadController::class, 'index'])->name('leads.index');
    Route::get('leads/create', [LeadController::class, 'create'])->name('leads.create');
    Route::post('leads', [LeadController::class, 'store'])->name('leads.store');
    Route::get('leads/import', [LeadImportController::class, 'create'])->name('leads.import');
    Route::post('leads/import/preview', [LeadImportController::class, 'preview'])->name('leads.import.preview');
    Route::post('leads/import', [LeadImportController::class, 'store'])->name('leads.import.store');
    Route::get('leads/duplicates', [LeadDuplicateController::class, 'index'])->name('leads.duplicates');
    Route::get('leads/reports/analytics', [LeadReportController::class, 'analytics'])->name('leads.reports.analytics');
    Route::get('leads/reports/campaigns', [LeadReportController::class, 'campaignLeads'])->name('leads.reports.campaigns');
    Route::get('leads/reports/sales-revenue', [LeadReportController::class, 'salesRevenue'])->name('leads.reports.sales-revenue');
    Route::get('leads/settings/sources', [LeadSettingsController::class, 'sources'])->name('leads.settings.sources');
    Route::post('leads/settings/sources', [LeadSettingsController::class, 'storeSource'])->name('leads.settings.sources.store');
    Route::put('leads/settings/sources/{source}', [LeadSettingsController::class, 'updateSource'])->name('leads.settings.sources.update');
    Route::post('leads/settings/lost-reasons', [LeadSettingsController::class, 'storeLostReason'])->name('leads.settings.lost-reasons.store');
    Route::put('leads/settings/lost-reasons/{lostReason}', [LeadSettingsController::class, 'updateLostReason'])->name('leads.settings.lost-reasons.update');
    Route::get('leads/settings/assignment-rules', [LeadSettingsController::class, 'rules'])->name('leads.settings.rules');
    Route::post('leads/settings/assignment-rules', [LeadSettingsController::class, 'storeRule'])->name('leads.settings.rules.store');
    Route::put('leads/settings/assignment-rules/{rule}', [LeadSettingsController::class, 'updateRule'])->name('leads.settings.rules.update');
    Route::delete('leads/settings/assignment-rules/{rule}', [LeadSettingsController::class, 'destroyRule'])->name('leads.settings.rules.destroy');
    Route::get('leads/settings/capture-forms', [LeadCaptureFormController::class, 'index'])->name('leads.settings.forms');
    Route::post('leads/settings/capture-forms', [LeadCaptureFormController::class, 'store'])->name('leads.settings.forms.store');
    Route::put('leads/settings/capture-forms/{form}', [LeadCaptureFormController::class, 'update'])->name('leads.settings.forms.update');
    Route::post('leads/settings/capture-forms/{form}/regenerate-key', [LeadCaptureFormController::class, 'regenerateKey'])->name('leads.settings.forms.regenerate');
    Route::delete('leads/settings/capture-forms/{form}', [LeadCaptureFormController::class, 'destroy'])->name('leads.settings.forms.destroy');
    Route::post('leads/settings/meta-mappings', [LeadCaptureFormController::class, 'storeMapping'])->name('leads.settings.mappings.store');
    Route::put('leads/settings/meta-mappings/{mapping}', [LeadCaptureFormController::class, 'updateMapping'])->name('leads.settings.mappings.update');
    Route::delete('leads/settings/meta-mappings/{mapping}', [LeadCaptureFormController::class, 'destroyMapping'])->name('leads.settings.mappings.destroy');
    Route::get('leads/{lead}', [LeadController::class, 'show'])->withTrashed()->name('leads.show');
    Route::get('leads/{lead}/edit', [LeadController::class, 'edit'])->name('leads.edit');
    Route::put('leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
    Route::post('leads/{lead}/transition', [LeadController::class, 'transition'])->name('leads.transition');
    Route::post('leads/{lead}/assign', [LeadController::class, 'assign'])->name('leads.assign');
    Route::post('leads/{lead}/notes', [LeadController::class, 'storeNote'])->name('leads.notes.store');
    Route::get('leads/{lead}/notes/{note}/download', [LeadController::class, 'downloadNote'])->withTrashed()->name('leads.notes.download');
    Route::post('leads/{lead}/communications', [LeadController::class, 'storeCommunication'])->name('leads.communications.store');
    Route::post('leads/{lead}/convert', [LeadController::class, 'convert'])->name('leads.convert');
    Route::post('leads/{lead}/merge', [LeadController::class, 'merge'])->name('leads.merge');
    Route::post('leads/{lead}/keep-separate', [LeadController::class, 'keepSeparate'])->name('leads.keep-separate');
    Route::post('leads/{lead}/mark-duplicate', [LeadController::class, 'markDuplicate'])->name('leads.mark-duplicate');
    Route::post('leads/{lead}/opportunities', [OpportunityController::class, 'store'])->name('leads.opportunities.store');

    Route::get('pipeline', [PipelineController::class, 'index'])->name('pipeline.index');
    Route::get('opportunities', [OpportunityController::class, 'index'])->name('opportunities.index');
    Route::get('opportunities/{opportunity}', [OpportunityController::class, 'show'])->name('opportunities.show');
    Route::put('opportunities/{opportunity}', [OpportunityController::class, 'update'])->name('opportunities.update');
    Route::post('opportunities/{opportunity}/stage', [OpportunityController::class, 'stage'])->name('opportunities.stage');
    Route::post('opportunities/{opportunity}/won', [OpportunityController::class, 'won'])->name('opportunities.won');
    Route::post('opportunities/{opportunity}/lost', [OpportunityController::class, 'lost'])->name('opportunities.lost');
    Route::post('opportunities/{opportunity}/quotation', [OpportunityController::class, 'quotation'])->name('opportunities.quotation');
});

Route::middleware(['auth', 'verified', 'portal'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('/', [PortalController::class, 'dashboard'])->name('dashboard');
    Route::get('/approvals', [PortalController::class, 'approvals'])->middleware('portal.module:approvals')->name('approvals');
    Route::get('/approvals/{approval}', [PortalController::class, 'approvalShow'])->middleware('portal.module:approvals')->name('approvals.show');
    Route::post('/approvals/{approval}/approve', [PortalController::class, 'approve'])->middleware('portal.module:approvals')->name('approvals.approve');
    Route::post('/approvals/{approval}/request-changes', [PortalController::class, 'requestChanges'])->middleware('portal.module:approvals')->name('approvals.request-changes');
    Route::get('/content', [PortalController::class, 'content'])->middleware('portal.module:content')->name('content');
    Route::get('/content/{content}', [PortalController::class, 'contentShow'])->middleware('portal.module:content')->name('content.show');
    Route::get('/content/{content}/preview', [PortalController::class, 'previewRevision'])->middleware('portal.module:content')->name('content.preview');
    Route::get('/campaigns', [PortalController::class, 'campaigns'])->middleware('portal.module:campaigns')->name('campaigns');
    Route::get('/files', [PortalController::class, 'files'])->middleware('portal.module:files')->name('files');
    Route::get('/files/{file}/download', [PortalController::class, 'downloadFile'])->middleware('portal.module:files')->name('files.download');

    Route::get('/requests', [PortalController::class, 'requests'])->middleware('portal.module:requests')->name('requests');
    Route::get('/requests/create', [PortalController::class, 'requestCreate'])->middleware('portal.module:requests')->name('requests.create');
    Route::post('/requests', [PortalController::class, 'requestStore'])->middleware('portal.module:requests')->name('requests.store');
    Route::get('/requests/{clientRequest}', [PortalController::class, 'requestShow'])->middleware('portal.module:requests')->name('requests.show');
    Route::post('/requests/{clientRequest}/comments', [PortalController::class, 'requestComment'])->middleware('portal.module:requests')->name('requests.comments');
    Route::post('/requests/{clientRequest}/cancel', [PortalController::class, 'requestCancel'])->middleware('portal.module:requests')->name('requests.cancel');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('/notification-preferences', [NotificationPreferenceController::class, 'edit'])->name('notification-preferences');
    Route::put('/notification-preferences', [NotificationPreferenceController::class, 'update'])->name('notification-preferences.update');
    Route::get('/push/vapid-public-key', [NotificationPreferenceController::class, 'vapidPublicKey'])->name('push.vapid');
    Route::post('/push/subscriptions', [NotificationPreferenceController::class, 'storePush'])->name('push.subscriptions.store');
    Route::delete('/push/subscriptions/{subscription}', [NotificationPreferenceController::class, 'destroyPush'])->name('push.subscriptions.destroy');
    Route::post('/push/test', [NotificationPreferenceController::class, 'testPush'])->name('push.test');

    Route::get('/quotations', [PortalFinanceController::class, 'quotations'])->middleware('portal.module:quotations')->name('quotations');
    Route::get('/quotations/{quotation}', [PortalFinanceController::class, 'quotationShow'])->middleware('portal.module:quotations')->name('quotations.show');
    Route::post('/quotations/{quotation}/accept', [PortalFinanceController::class, 'acceptQuotation'])->middleware('portal.module:quotations')->name('quotations.accept');
    Route::post('/quotations/{quotation}/request-changes', [PortalFinanceController::class, 'requestQuotationChanges'])->middleware('portal.module:quotations')->name('quotations.request-changes');
    Route::post('/quotations/{quotation}/reject', [PortalFinanceController::class, 'rejectQuotation'])->middleware('portal.module:quotations')->name('quotations.reject');
    Route::get('/quotations/{quotation}/pdf', [PortalFinanceController::class, 'quotationPdf'])->middleware('portal.module:quotations')->name('quotations.pdf');

    Route::get('/invoices', [PortalFinanceController::class, 'invoices'])->middleware('portal.module:invoices')->name('invoices');
    Route::get('/invoices/{invoice}', [PortalFinanceController::class, 'invoiceShow'])->middleware('portal.module:invoices')->name('invoices.show');
    Route::get('/invoices/{invoice}/pdf', [PortalFinanceController::class, 'invoicePdf'])->middleware('portal.module:invoices')->name('invoices.pdf');

    Route::get('/payments', [PortalFinanceController::class, 'payments'])->middleware('portal.module:payments')->name('payments');
    Route::get('/payments/{payment}/pdf', [PortalFinanceController::class, 'paymentPdf'])->middleware('portal.module:payments')->name('payments.pdf');

    Route::get('/performance', [PortalPerformanceController::class, 'index'])->middleware('portal.module:performance')->name('performance');
    Route::get('/reports', [PortalReportsController::class, 'index'])->middleware('portal.module:reports')->name('reports');
    Route::get('/reports/{report}', [PortalReportsController::class, 'show'])->middleware('portal.module:reports')->name('reports.show');
    Route::get('/reports/{report}/pdf', [PortalReportsController::class, 'pdf'])->middleware('portal.module:reports')->name('reports.pdf');

    Route::get('/leads', [PortalLeadController::class, 'index'])->middleware('portal.module:leads')->name('leads');
    Route::get('/leads/summary', [PortalLeadController::class, 'summary'])->middleware('portal.module:lead_reports')->name('leads.summary');
    Route::get('/leads/{lead}', [PortalLeadController::class, 'show'])->middleware('portal.module:leads')->name('leads.show');
    Route::post('/leads/{lead}/notes', [PortalLeadController::class, 'storeNote'])->middleware('portal.module:leads')->name('leads.notes');
});

require __DIR__.'/auth.php';
