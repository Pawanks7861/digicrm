<?php

namespace Database\Seeders;

use App\Models\AutomationAction;
use App\Models\AutomationActionRun;
use App\Models\AutomationCondition;
use App\Models\AutomationRule;
use App\Models\AutomationRun;
use App\Models\Campaign;
use App\Models\CampaignKpi;
use App\Models\Client;
use App\Models\ClientApproval;
use App\Models\ClientBrandProfile;
use App\Models\ClientCommunication;
use App\Models\ClientContact;
use App\Models\ClientFile;
use App\Models\ClientOnboardingItem;
use App\Models\ClientPackage;
use App\Models\ClientPackageItem;
use App\Models\ClientPerformanceReport;
use App\Models\ClientPortalSetting;
use App\Models\ClientReportMetric;
use App\Models\ClientRequest;
use App\Models\CompanyHoliday;
use App\Models\ContentComment;
use App\Models\ContentItem;
use App\Models\ContentRevision;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\ExpenseClaim;
use App\Models\FollowUp;
use App\Models\IntegrationConnection;
use App\Models\IntegrationLog;
use App\Models\InternalReview;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\LeadAssignmentRule;
use App\Models\LeadCaptureForm;
use App\Models\LeadNote;
use App\Models\LeadSource;
use App\Models\LeaveType;
use App\Models\MarketingMetricSnapshot;
use App\Models\MetricSyncRun;
use App\Models\NotificationTemplate;
use App\Models\OperationalCost;
use App\Models\Opportunity;
use App\Models\Quotation;
use App\Models\SocialAccount;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\TaskComment;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\UserCostRate;
use App\Models\UserWorkCapacity;
use App\Services\CampaignDeliverableService;
use App\Services\CampaignService;
use App\Services\ChatService;
use App\Services\ClientRequestService;
use App\Services\ContentItemService;
use App\Services\FollowUpService;
use App\Services\Hr\AttendanceService as HrAttendanceService;
use App\Services\Hr\LeaveService;
use App\Services\InvoiceService;
use App\Services\LeadAssignmentService;
use App\Services\LeadService;
use App\Services\NotificationDispatchService;
use App\Services\OpportunityService;
use App\Services\PackageScopeService;
use App\Services\PaymentService;
use App\Services\QuotationService;
use App\Services\TaskService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class DemoShowcaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DelayReasonSeeder::class,
            LeadDefaultsSeeder::class,
            DemoAgencySeeder::class,
            HrDemoSeeder::class,
        ]);

        $super = User::where('email', 'admin@digicrm.test')->firstOrFail();
        $am = User::where('email', 'am@digicrm.test')->firstOrFail();
        $designer = User::where('email', 'designer@digicrm.test')->firstOrFail();
        Auth::login($super);

        $glow = Client::where('company_name', 'Glow Beauty Studio')->firstOrFail();
        $abc = Client::where('company_name', 'ABC Developers')->firstOrFail();

        $this->step('client profile', fn () => $this->clientProfile($glow, $abc, $am, $super));
        $campaign = $this->step('campaign', fn () => $this->campaign($glow, $am, $super));
        $this->step('package usage', fn () => $this->packageUsage($abc, $super));
        $task = $this->step('tasks', fn () => $this->tasks($glow, $abc, $campaign, $designer, $super));
        $content = $this->step('content', fn () => $this->content($glow, $campaign, $super, $am));
        $this->step('request and follow-up', fn () => $this->requestAndFollowUp($glow, $am, $super));
        $salesLead = $this->step('leads', fn () => $this->leads($glow, $campaign, $am, $super));
        $this->step('finance', fn () => $this->finance($glow, $campaign, $super));
        $this->step('chat', fn () => $this->chat($super, $am));
        $this->step('people and time', fn () => $this->people($designer, $am, $glow, $campaign, $task, $super));
        $this->step('performance', fn () => $this->performance($glow, $campaign, $content, $super));
        $this->step('automation and alerts', fn () => $this->automation($super, $am));
        $this->call(DemoClientTabSeeder::class);
    }

    protected function clientProfile(Client $glow, Client $abc, User $am, User $super): void
    {
        ClientContact::firstOrCreate(
            ['client_id' => $glow->id, 'email' => 'neha@glowbeauty.test'],
            ['name' => 'Neha Kapoor', 'phone' => '9123456780', 'role' => 'Founder', 'is_primary' => true]
        );

        ClientBrandProfile::updateOrCreate(
            ['client_id' => $glow->id],
            [
                'brand_description' => 'A neighbourhood beauty studio known for festive makeup and bridal trials.',
                'target_audience' => 'Women 22–40 in Mumbai who book looks for weddings and festivals.',
                'tone_of_voice' => 'Warm, confident, and practical.',
                'preferred_colors' => ['Gold', 'Blush', 'Black'],
                'hashtags' => ['#GlowBeauty', '#FestiveLooks'],
                'dos' => 'Show real clients and the finished look.',
                'donts' => 'No heavy filters or competitor product names.',
            ]
        );

        ClientCommunication::firstOrCreate(
            ['client_id' => $glow->id, 'subject' => 'October content plan'],
            [
                'user_id' => $am->id,
                'communication_type' => 'call',
                'direction' => 'outbound',
                'notes' => 'Agreed on one festive reel and a Diwali carousel for this month.',
                'occurred_at' => now()->subDay(),
                'follow_up_required' => true,
                'next_follow_up_at' => now()->addDay(),
                'visibility' => 'internal',
                'created_by' => $am->id,
            ]
        );

        ClientOnboardingItem::firstOrCreate(
            ['client_id' => $abc->id, 'label' => 'Collect brand logo and colour codes'],
            ['sort_order' => 1, 'is_completed' => false, 'is_custom' => true]
        );

        $settings = ClientPortalSetting::forClient($glow);
        $settings->update([
            'portal_enabled' => true,
            'show_campaigns' => true,
            'show_content' => true,
            'show_calendar' => true,
            'show_files' => true,
            'show_requests' => true,
            'show_approvals' => true,
            'show_reports' => true,
            'show_performance' => true,
            'show_invoices' => true,
            'show_quotations' => true,
            'show_payments' => true,
            'show_leads' => true,
            'show_lead_reports' => true,
            'show_lead_source' => true,
            'allow_request_creation' => true,
            'allow_comments' => true,
            'allow_content_approval' => true,
            'allow_quotation_acceptance' => true,
        ]);

        $portal = User::updateOrCreate(
            ['email' => 'portal@glowbeauty.test'],
            [
                'name' => 'Neha Kapoor',
                'password' => Hash::make('password'),
                'client_id' => $glow->id,
                'is_active' => true,
                'email_verified_at' => now(),
                'can_approve_content' => true,
                'can_submit_requests' => true,
                'can_comment_portal' => true,
                'can_view_finance' => true,
                'can_accept_quotations' => true,
            ]
        );
        $portal->syncRoles(['client']);

        $path = 'clients/glow-brand-notes.txt';
        Storage::disk('public')->put($path, "Glow Beauty Studio\nGold, blush, and black. Warm captions. No heavy filters.\n");
        ClientFile::firstOrCreate(
            ['client_id' => $glow->id, 'original_name' => 'Brand notes.txt'],
            [
                'folder' => 'Brand Assets',
                'stored_name' => 'glow-brand-notes.txt',
                'path' => $path,
                'mime_type' => 'text/plain',
                'size' => Storage::disk('public')->size($path),
                'visibility' => 'client',
                'version' => 1,
                'uploaded_by' => $super->id,
            ]
        );
    }

    protected function campaign(Client $glow, User $am, User $super): Campaign
    {
        $existing = Campaign::where('name', 'Festive Launch')->where('client_id', $glow->id)->first();
        if ($existing) {
            return $existing;
        }

        $campaign = app(CampaignService::class)->create([
            'client_id' => $glow->id,
            'name' => 'Festive Launch',
            'campaign_type' => 'social_media',
            'objective' => 'Book festive makeup slots through Instagram.',
            'description' => 'One reel and one carousel for the October festive window.',
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'budget' => 85000,
            'client_charge' => 45000,
            'currency' => 'INR',
            'campaign_manager_id' => $am->id,
            'status' => 'active',
            'priority' => 'high',
        ], $super);

        app(CampaignDeliverableService::class)->create($campaign, [
            'title' => 'Festive look reel',
            'deliverable_type' => 'reel',
            'platform' => 'instagram',
            'description' => '30-second look transformation for Diwali bookings.',
            'quantity' => 1,
            'unit' => 'Reel',
            'priority' => 'high',
            'internal_due_date' => now()->addDays(3)->toDateString(),
            'client_due_date' => now()->addDays(4)->toDateString(),
            'status' => 'in_progress',
        ], $super);

        return $campaign;
    }

    protected function packageUsage(Client $abc, User $super): void
    {
        $package = ClientPackage::where('client_id', $abc->id)->latest('id')->first();
        if ($package && $package->status !== 'cancelled') {
            $package->forceFill([
                'start_date' => now()->startOfMonth()->toDateString(),
                'end_date' => now()->endOfMonth()->toDateString(),
                'status' => 'active',
            ])->save();
        }
        $item = $package
            ? ClientPackageItem::where('client_package_id', $package->id)->where('service_code', 'STATIC')->first()
            : null;
        if (! $item || $item->usageTransactions()->exists()) {
            return;
        }

        app(PackageScopeService::class)->consume($item, 3, $super, null, [], now());
    }

    protected function tasks(Client $glow, Client $abc, ?Campaign $campaign, User $designer, User $super): ?Task
    {
        if (! $campaign) {
            return null;
        }

        $tasks = app(TaskService::class);
        $edit = Task::where('title', 'Edit festive reel')->first();
        if (! $edit) {
            $edit = $tasks->create([
                'client_id' => $glow->id,
                'campaign_id' => $campaign->id,
                'title' => 'Edit festive reel',
                'description' => 'Cut the look transformation to 30 seconds and add captions.',
                'status' => 'in_progress',
                'priority' => 'high',
                'start_date' => now()->toDateString(),
                'due_date' => now()->addDays(3)->toDateString(),
                'estimated_minutes' => 180,
                'assignee_id' => $designer->id,
            ], $super);
            TaskChecklistItem::create([
                'task_id' => $edit->id,
                'title' => 'Add on-screen captions',
                'is_completed' => false,
                'sort_order' => 1,
            ]);
            TaskComment::create([
                'task_id' => $edit->id,
                'user_id' => $super->id,
                'comment' => 'Keep the gold titles consistent with the brand notes.',
                'visibility' => 'internal',
            ]);
        }

        if (! Task::where('title', 'Collect product photos')->exists()) {
            $blocked = $tasks->create([
                'client_id' => $abc->id,
                'title' => 'Collect product photos',
                'description' => 'Need site photography before the teaser posts can start.',
                'status' => 'to_do',
                'priority' => 'normal',
                'due_date' => now()->addDays(5)->toDateString(),
                'estimated_minutes' => 60,
                'assignee_id' => $designer->id,
            ], $super);
            $tasks->changeStatus($blocked, 'blocked', $super, 'waiting_for_assets', 'Waiting on the client for exterior photos.');
        }

        if (! TimeEntry::where('description', 'First cut of the festive reel')->exists()) {
            $start = now()->setTime(10, 0);
            TimeEntry::create([
                'user_id' => $designer->id,
                'client_id' => $glow->id,
                'task_id' => $edit->id,
                'campaign_id' => $campaign->id,
                'started_at' => $start,
                'ended_at' => $start->copy()->addMinutes(90),
                'duration_seconds' => 90 * 60,
                'entry_type' => 'manual',
                'billable' => true,
                'category' => 'production',
                'description' => 'First cut of the festive reel',
                'status' => 'submitted',
                'created_by' => $designer->id,
            ]);
        }

        return $edit;
    }

    protected function content(Client $glow, ?Campaign $campaign, User $super, User $am): ?ContentItem
    {
        if (! $campaign) {
            return null;
        }

        $items = app(ContentItemService::class);
        $reel = ContentItem::where('title', 'Festive look reel')->first();
        if (! $reel) {
            $reel = $items->create([
                'client_id' => $glow->id,
                'campaign_id' => $campaign->id,
                'title' => 'Festive look reel',
                'content_type' => 'reel',
                'platform' => 'instagram',
                'caption' => 'Three looks, one festive evening. Book your trial this week.',
                'hashtags' => '#GlowBeauty #FestiveLooks',
                'status' => 'draft',
            ], $super);
            $items->schedule($reel, now()->addDays(3)->setTime(18, 0)->toDateTimeString(), $super, true, 'Demo calendar entry');
            ContentComment::create([
                'content_item_id' => $reel->id,
                'user_id' => $am->id,
                'comment' => 'Caption is approved from the call yesterday.',
                'visibility' => 'internal',
                'comment_type' => 'note',
            ]);
        }

        if (! ContentItem::where('title', 'Diwali carousel')->exists()) {
            $carousel = $items->create([
                'client_id' => $glow->id,
                'campaign_id' => $campaign->id,
                'title' => 'Diwali carousel',
                'content_type' => 'carousel',
                'platform' => 'instagram',
                'caption' => 'Five festive looks clients booked last year.',
                'status' => 'sent_to_client',
            ], $super);
            $revision = ContentRevision::create([
                'content_item_id' => $carousel->id,
                'revision_number' => 1,
                'original_name' => 'diwali-carousel-notes.txt',
                'path' => 'content/diwali-carousel-notes.txt',
                'mime_type' => 'text/plain',
                'size' => 48,
                'caption_snapshot' => $carousel->caption,
                'notes' => 'Slide order agreed with the client.',
                'status' => 'sent_to_client',
                'uploaded_by' => $super->id,
            ]);
            Storage::disk('public')->put('content/diwali-carousel-notes.txt', "Slide 1: Gold eye\nSlide 2: Bridal trial\n");
            $carousel->update(['current_revision_id' => $revision->id]);
            ClientApproval::firstOrCreate(
                ['content_item_id' => $carousel->id, 'status' => 'pending'],
                [
                    'client_id' => $glow->id,
                    'content_revision_id' => $revision->id,
                    'requested_by' => $am->id,
                    'requested_at' => now(),
                    'expires_at' => now()->addDays(5),
                ]
            );
            InternalReview::firstOrCreate(
                ['reviewable_type' => ContentItem::class, 'reviewable_id' => $carousel->id, 'status' => 'pending'],
                [
                    'content_revision_id' => $revision->id,
                    'reviewer_id' => $am->id,
                    'submitted_by' => $super->id,
                    'submitted_at' => now(),
                ]
            );
        }

        return ContentItem::where('title', 'Festive look reel')->first();
    }

    protected function requestAndFollowUp(Client $glow, User $am, User $super): void
    {
        if (! ClientRequest::where('title', 'Add a Diwali story set')->exists()) {
            app(ClientRequestService::class)->create($glow, [
                'title' => 'Add a Diwali story set',
                'request_type' => 'story',
                'description' => 'Three stories covering hours, festive looks, and how to book.',
                'platform' => 'instagram',
                'priority' => 'normal',
                'requested_due_date' => now()->addDays(6)->toDateString(),
                'quantity' => 3,
                'unit' => 'Story',
            ], $super);
        }

        if (! FollowUp::where('title', 'Confirm reel publish time')->exists()) {
            app(FollowUpService::class)->create([
                'client_id' => $glow->id,
                'assigned_to' => $am->id,
                'title' => 'Confirm reel publish time',
                'notes' => 'Check that 6pm works for the studio before the reel goes live.',
                'follow_up_type' => 'call',
                'due_at' => now()->addDay()->setTime(11, 0),
                'priority' => 'high',
            ], $super);
        }
    }

    protected function leads(Client $glow, ?Campaign $campaign, User $am, User $super): ?Lead
    {
        $leads = app(LeadService::class);
        $source = LeadSource::where('code', 'referral')->first();

        $sales = Lead::where('email', 'meera@northwind.test')->first();
        if (! $sales) {
            $sales = $leads->create([
                'name' => 'Meera Jain',
                'company' => 'Northwind Interiors',
                'email' => 'meera@northwind.test',
                'mobile' => '9811112233',
                'city' => 'Jaipur',
                'state' => 'Rajasthan',
                'country' => 'India',
                'industry' => 'Interior design',
                'interested_service' => 'Social media retainer',
                'requirement' => 'Monthly posts for a new studio opening.',
                'estimated_budget' => 40000,
                'currency' => 'INR',
                'lead_source_id' => $source?->id,
                'priority' => 'high',
                'consent_given' => true,
            ], $super, ['auto_assign' => false, 'channel' => 'manual']);
            app(LeadAssignmentService::class)->assign($sales, $am, $super, 'Demo owner');
            $leads->transition($sales->fresh(), 'qualified', $super, ['reason' => 'Budget and timeline fit a retainer.']);
            LeadNote::create([
                'lead_id' => $sales->id,
                'user_id' => $am->id,
                'body' => 'Asked for a proposal covering 12 posts and 4 reels.',
                'visibility' => 'internal',
            ]);
        }

        if ($sales->isAgencySales() && ! Opportunity::where('lead_id', $sales->id)->exists()) {
            app(OpportunityService::class)->create($sales->fresh(), [
                'title' => 'Northwind Interiors retainer',
                'estimated_value' => 40000,
                'currency' => 'INR',
                'expected_close_date' => now()->addDays(20)->toDateString(),
                'owner_id' => $am->id,
                'description' => 'Monthly social retainer for the studio opening.',
            ], $super);
        }

        if (! Lead::where('email', 'walkin@glowbeauty.test')->exists()) {
            $leads->create([
                'lead_category' => Lead::CATEGORY_CAMPAIGN,
                'client_id' => $glow->id,
                'campaign_id' => $campaign?->id,
                'name' => 'Asha Patel',
                'email' => 'walkin@glowbeauty.test',
                'mobile' => '9822233344',
                'city' => 'Mumbai',
                'interested_service' => 'Festive makeup trial',
                'requirement' => 'Saw the reel preview and wants a trial slot.',
                'lead_source_id' => LeadSource::where('code', 'instagram')->value('id'),
                'priority' => 'normal',
            ], $super, ['auto_assign' => false, 'channel' => 'manual']);
        }

        if (! LeadCaptureForm::where('name', 'Website enquiry')->exists()) {
            LeadCaptureForm::create([
                'name' => 'Website enquiry',
                'form_key' => Str::random(32),
                'lead_category' => Lead::CATEGORY_AGENCY,
                'lead_source_id' => LeadSource::where('code', 'website_form')->value('id'),
                'is_active' => true,
                'require_consent' => true,
                'consent_text' => 'I agree to be contacted about this enquiry.',
                'fields' => LeadCaptureForm::FIELDS,
                'success_message' => 'Thanks. We will call you shortly.',
                'created_by' => $super->id,
            ]);
        }

        if (! LeadAssignmentRule::where('name', 'Website enquiries to Priya')->exists()) {
            LeadAssignmentRule::create([
                'name' => 'Website enquiries to Priya',
                'priority' => 10,
                'is_active' => true,
                'match_category' => Lead::CATEGORY_AGENCY,
                'match_lead_source_id' => LeadSource::where('code', 'website_form')->value('id'),
                'strategy' => 'specific_user',
                'user_id' => $am->id,
                'created_by' => $super->id,
            ]);
        }

        return $sales;
    }

    protected function finance(Client $glow, ?Campaign $campaign, User $super): void
    {
        $line = fn (string $name, $rate) => [[
            'name' => $name,
            'quantity' => 1,
            'unit_rate' => $rate,
        ]];

        if (! Quotation::where('notes', 'Festive campaign add-on for Glow Beauty Studio.')->exists()) {
            $quote = app(QuotationService::class)->create($glow, [
                'campaign_id' => $campaign?->id,
                'tax_treatment' => 'exempt',
                'notes' => 'Festive campaign add-on for Glow Beauty Studio.',
                'valid_until' => now()->addDays(15)->toDateString(),
                'items' => $line('Festive reel and carousel', 45000),
            ], $super);
            app(QuotationService::class)->send($quote, $super);
        }

        if (! Invoice::where('period_label', 'October 2026 retainer')->exists()) {
            $invoice = app(InvoiceService::class)->create($glow, [
                'campaign_id' => $campaign?->id,
                'tax_treatment' => 'exempt',
                'period_label' => 'October 2026 retainer',
                'notes' => 'Monthly social retainer.',
                'due_date' => now()->addDays(10)->toDateString(),
                'items' => $line('Social media retainer — October', 32000),
            ], $super);
            app(InvoiceService::class)->send($invoice, $super);
            app(PaymentService::class)->record($invoice->fresh(), [
                'amount' => 15000,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'upi',
                'reference' => 'UPI-DEMO-15000',
            ], $super);
        }
    }

    protected function chat(User $super, User $am): void
    {
        $chat = app(ChatService::class);
        $conversation = $chat->direct($super, $am);
        $already = $conversation->messages()->where('body', 'Festive reel is in edit. I will send the cut tomorrow.')->exists();
        if (! $already) {
            $chat->send($am, $conversation, 'Festive reel is in edit. I will send the cut tomorrow.');
        }
    }

    protected function people(User $designer, User $am, Client $glow, ?Campaign $campaign, ?Task $task, User $super): void
    {
        UserCostRate::firstOrCreate(
            ['user_id' => $designer->id, 'effective_from' => now()->startOfYear()->toDateString()],
            ['hourly_cost' => 450, 'currency' => 'INR', 'created_by' => $super->id]
        );
        UserWorkCapacity::firstOrCreate(
            ['user_id' => $designer->id, 'effective_from' => now()->startOfYear()->toDateString()],
            [
                'working_hours_per_day' => 8,
                'working_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
                'status' => 'active',
            ]
        );

        if ($campaign && ! OperationalCost::where('description', 'Boost for the festive reel')->exists()) {
            OperationalCost::create([
                'client_id' => $glow->id,
                'campaign_id' => $campaign->id,
                'cost_type' => 'ad_spend',
                'description' => 'Boost for the festive reel',
                'amount' => 12000,
                'currency' => 'INR',
                'incurred_at' => now()->toDateString(),
                'created_by' => $super->id,
            ]);
        }

        $employee = Employee::where('user_id', $designer->id)->first();
        if ($employee) {
            CompanyHoliday::firstOrCreate(
                ['company_id' => $employee->company_id, 'date' => '2026-10-20', 'name' => 'Diwali'],
                ['is_optional' => false]
            );

            try {
                app(HrAttendanceService::class)->punch($employee, 'in', $designer, [
                    'punched_at' => now()->setTime(9, 30)->toDateTimeString(),
                    'source' => 'manual',
                ]);
            } catch (Throwable) {
                // A punch may already exist for today.
            }

            $cl = LeaveType::where('company_id', $employee->company_id)->where('code', 'CL')->first();
            if ($cl && ! \App\Models\LeaveRequest::where('employee_id', $employee->id)->where('reason', 'Family function')->exists()) {
                app(LeaveService::class)->submit($employee, [
                    'leave_type_id' => $cl->id,
                    'start_date' => now()->addDays(8)->toDateString(),
                    'end_date' => now()->addDays(8)->toDateString(),
                    'reason' => 'Family function',
                ], $designer);
            }

            $category = ExpenseCategory::where('company_id', $employee->company_id)->where('code', 'TRAVEL')->first();
            if ($category && ! ExpenseClaim::where('title', 'Client visit — Glow Beauty')->exists()) {
                ExpenseClaim::create([
                    'company_id' => $employee->company_id,
                    'employee_id' => $employee->id,
                    'expense_category_id' => $category->id,
                    'amount' => 850,
                    'currency' => 'INR',
                    'expense_date' => now()->subDay()->toDateString(),
                    'title' => 'Client visit — Glow Beauty',
                    'description' => 'Cab to the studio for the festive shoot briefing.',
                    'client_id' => $glow->id,
                    'campaign_id' => $campaign?->id,
                    'task_id' => $task?->id,
                    'status' => 'pending',
                    'payment_status' => 'unpaid',
                ]);
            }
        }
    }

    protected function performance(Client $glow, ?Campaign $campaign, ?ContentItem $content, User $super): void
    {
        if (! $campaign) {
            return;
        }

        $connection = IntegrationConnection::firstOrCreate(
            ['provider' => 'meta', 'name' => 'Demo Meta — Glow Beauty'],
            [
                'status' => 'connected',
                'connected_by' => $super->id,
                'connected_at' => now()->subDays(3),
                'health_status' => 'ok',
                'health_message' => 'Sample connection for the demo. No live token is stored.',
                'last_health_check_at' => now(),
                'client_id' => $glow->id,
                'is_enabled' => true,
                'meta' => ['demo' => true],
            ]
        );

        $account = SocialAccount::firstOrCreate(
            ['client_id' => $glow->id, 'platform' => 'instagram', 'external_account_id' => 'demo-glow-ig'],
            [
                'integration_connection_id' => $connection->id,
                'provider' => 'meta',
                'display_name' => 'Glow Beauty Studio',
                'username' => 'glowbeautystudio',
                'status' => 'active',
            ]
        );

        IntegrationLog::firstOrCreate(
            ['connection_id' => $connection->id, 'operation' => 'demo_sync'],
            [
                'provider' => 'meta',
                'status' => 'success',
                'started_at' => now()->subHour(),
                'completed_at' => now()->subHour()->addMinutes(2),
                'context' => ['note' => 'Sample sync for the client demo.'],
            ]
        );

        $run = MetricSyncRun::firstOrCreate(
            ['client_id' => $glow->id, 'provider' => 'meta', 'sync_type' => 'demo'],
            [
                'integration_connection_id' => $connection->id,
                'date_from' => now()->startOfMonth()->toDateString(),
                'date_to' => now()->toDateString(),
                'status' => 'completed',
                'rows_fetched' => 4,
                'rows_upserted' => 4,
                'rows_skipped' => 0,
                'started_at' => now()->subHour(),
                'completed_at' => now()->subHour()->addMinutes(2),
                'triggered_by' => $super->id,
            ]
        );

        $metrics = [
            'impressions' => 18400,
            'reach' => 9200,
            'clicks' => 640,
            'engagements' => 1100,
        ];
        foreach ($metrics as $key => $value) {
            MarketingMetricSnapshot::firstOrCreate(
                [
                    'client_id' => $glow->id,
                    'campaign_id' => $campaign->id,
                    'metric_key' => $key,
                    'metric_date' => now()->subDay()->toDateString(),
                    'source' => 'manual',
                ],
                [
                    'content_item_id' => $content?->id,
                    'social_account_id' => $account->id,
                    'integration_connection_id' => $connection->id,
                    'provider' => 'meta',
                    'metric_value' => $value,
                    'is_manual' => true,
                    'sync_run_id' => $run->id,
                ]
            );
        }

        CampaignKpi::firstOrCreate(
            ['campaign_id' => $campaign->id, 'metric_code' => 'reach'],
            [
                'client_id' => $glow->id,
                'target_value' => 15000,
                'target_type' => 'minimum',
                'period' => 'monthly',
                'period_start' => now()->startOfMonth()->toDateString(),
                'period_end' => now()->endOfMonth()->toDateString(),
                'is_active' => true,
            ]
        );

        $report = ClientPerformanceReport::firstOrCreate(
            ['client_id' => $glow->id, 'title' => 'October performance snapshot'],
            [
                'report_number' => 'RPT-DEMO-001',
                'status' => ClientPerformanceReport::STATUS_PUBLISHED,
                'period_start' => now()->startOfMonth()->toDateString(),
                'period_end' => now()->endOfMonth()->toDateString(),
                'summary' => 'Reach is building toward the festive booking target. The reel is the main driver.',
                'currency' => 'INR',
                'generated_at' => now(),
                'published_at' => now(),
                'created_by' => $super->id,
                'published_by' => $super->id,
            ]
        );
        ClientReportMetric::firstOrCreate(
            ['client_performance_report_id' => $report->id, 'metric_code' => 'reach'],
            [
                'section' => 'awareness',
                'label' => 'Reach',
                'metric_value' => 9200,
                'display_value' => '9,200',
                'unit' => 'people',
                'sort_order' => 1,
            ]
        );
    }

    protected function automation(User $super, User $am): void
    {
        $rule = AutomationRule::firstOrCreate(
            ['name' => 'Notify Priya when a lead is created'],
            [
                'description' => 'Sends Priya an in-app note for each new agency lead.',
                'trigger_type' => 'lead.created',
                'status' => 'active',
                'is_system' => false,
                'priority' => 10,
                'group_operator' => 'and',
                'max_actions_per_hour' => 20,
                'created_by' => $super->id,
                'updated_by' => $super->id,
                'activated_at' => now(),
            ]
        );
        AutomationCondition::firstOrCreate(
            ['automation_rule_id' => $rule->id, 'field' => 'lead_category', 'operator' => 'equals'],
            ['value' => 'agency_sales', 'sort_order' => 1]
        );
        $action = AutomationAction::firstOrCreate(
            ['automation_rule_id' => $rule->id, 'action_type' => 'send_notification'],
            [
                'config' => [
                    'title' => 'New lead',
                    'body' => 'A new agency lead needs a first call.',
                    'user_ids' => [$am->id],
                ],
                'delay_minutes' => 0,
                'continue_on_match' => false,
                'sort_order' => 1,
            ]
        );
        if (! AutomationRun::where('automation_rule_id', $rule->id)->exists()) {
            $run = AutomationRun::create([
                'automation_rule_id' => $rule->id,
                'trigger_type' => 'lead.created',
                'entity_type' => Lead::class,
                'entity_id' => Lead::where('email', 'meera@northwind.test')->value('id'),
                'status' => 'completed',
                'depth' => 0,
                'context' => ['demo' => true],
                'idempotency_key' => 'demo-lead-created-1',
                'started_at' => now()->subHours(2),
                'completed_at' => now()->subHours(2),
                'dry_run' => false,
            ]);
            AutomationActionRun::create([
                'automation_run_id' => $run->id,
                'automation_action_id' => $action->id,
                'action_type' => 'send_notification',
                'status' => 'completed',
                'idempotency_key' => 'demo-lead-created-1-action',
                'result' => ['notified' => $am->id],
                'started_at' => now()->subHours(2),
                'completed_at' => now()->subHours(2),
            ]);
        }

        if (! NotificationTemplate::where('key', 'demo_welcome')->exists()) {
            NotificationTemplate::create([
                'key' => 'demo_welcome',
                'category' => 'general',
                'name' => 'Demo welcome',
                'subject' => 'Update from {{company}}',
                'heading' => 'Hello {{name}}',
                'body' => 'This is a sample template used on the alerts screen.',
                'cta_label' => 'Open DigiCRM',
                'is_active' => true,
                'is_system' => false,
            ]);
        }

        app(NotificationDispatchService::class)->send(
            $super,
            'demo.ready',
            'general',
            'Demo workspace is ready',
            'Festive Launch for Glow Beauty Studio has a campaign, invoice, and reel to walk through.',
            [
                'priority' => 'normal',
                'route' => 'dashboard',
                'allow_mail' => false,
                'occurrence' => 'demo-showcase',
                'skip_if_deduped' => true,
            ]
        );
    }

    protected function step(string $label, callable $fn): mixed
    {
        try {
            $result = $fn();
            $this->command?->info('Demo: '.$label);
            return $result;
        } catch (Throwable $e) {
            $this->command?->warn('Demo '.$label.' skipped: '.$e->getMessage());

            return null;
        }
    }
}
