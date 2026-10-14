<?php

namespace Database\Seeders;

use App\Models\AttendancePunch;
use App\Models\Client;
use App\Models\ClientApproval;
use App\Models\ClientBrandProfile;
use App\Models\ClientCommunication;
use App\Models\ClientFile;
use App\Models\ClientPerformanceReport;
use App\Models\ContentItem;
use App\Models\ContentRevision;
use App\Models\IntegrationConnection;
use App\Models\SocialAccount;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\Hr\AttendanceService;
use App\Services\Hr\CompanyOrganizationService;
use App\Services\Hr\PayrollService;
use App\Services\InvoiceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DemoClientTabSeeder extends Seeder
{
    public function run(): void
    {
        $super = User::where('email', 'superadmin@digicrm.test')->first();
        $am = User::where('email', 'am@digicrm.test')->first();
        $abc = Client::where('company_name', 'ABC Developers')->first();
        if (! $super || ! $abc) {
            return;
        }

        Auth::login($super);

        if (! $abc->notes) {
            $abc->update(['notes' => 'Internal: waiting on exterior photos before the teaser posts.']);
        }

        ClientBrandProfile::updateOrCreate(
            ['client_id' => $abc->id],
            [
                'brand_description' => 'A residential developer launching a new tower this quarter.',
                'target_audience' => 'Home buyers looking for 2 and 3 BHK homes.',
                'tone_of_voice' => 'Clear and confident.',
            ]
        );

        ClientCommunication::firstOrCreate(
            ['client_id' => $abc->id, 'subject' => 'Site visit follow-up'],
            [
                'user_id' => $am?->id ?? $super->id,
                'communication_type' => 'call',
                'direction' => 'outbound',
                'notes' => 'Asked the client to share exterior photos for the teaser.',
                'occurred_at' => now()->subDays(2),
                'follow_up_required' => false,
                'visibility' => 'internal',
                'created_by' => $am?->id ?? $super->id,
            ]
        );

        $path = 'clients/abc-site-brief.txt';
        Storage::disk('public')->put($path, "ABC Developers\nTeaser needs the tower elevation and a location map.\n");
        ClientFile::firstOrCreate(
            ['client_id' => $abc->id, 'original_name' => 'Site brief.txt'],
            [
                'folder' => 'Documents',
                'stored_name' => 'abc-site-brief.txt',
                'path' => $path,
                'mime_type' => 'text/plain',
                'size' => Storage::disk('public')->size($path),
                'visibility' => 'internal',
                'version' => 1,
                'uploaded_by' => $super->id,
            ]
        );

        $content = ContentItem::firstOrCreate(
            ['client_id' => $abc->id, 'title' => 'Tower teaser post'],
            [
                'content_type' => 'static',
                'platform' => 'instagram',
                'caption' => 'A first look at the new tower. Book a site visit this month.',
                'status' => 'sent_to_client',
                'created_by' => $super->id,
                'updated_by' => $super->id,
            ]
        );
        $revision = ContentRevision::firstOrCreate(
            ['content_item_id' => $content->id, 'revision_number' => 1],
            [
                'original_name' => 'tower-teaser.txt',
                'path' => $path,
                'mime_type' => 'text/plain',
                'size' => Storage::disk('public')->size($path),
                'caption_snapshot' => $content->caption,
                'notes' => 'Demo revision for the client tab.',
                'status' => 'pending_approval',
                'is_client_cycle' => true,
                'uploaded_by' => $super->id,
            ]
        );
        ClientApproval::firstOrCreate(
            ['content_item_id' => $content->id, 'content_revision_id' => $revision->id],
            [
                'client_id' => $abc->id,
                'requested_by' => $super->id,
                'requested_at' => now()->subDay(),
                'status' => 'pending',
                'expires_at' => now()->addDays(5),
            ]
        );

        if (! \App\Models\Invoice::where('client_id', $abc->id)->exists()) {
            app(InvoiceService::class)->create($abc, [
                'tax_treatment' => 'exempt',
                'period_label' => now()->format('F Y').' retainer',
                'notes' => 'Demo invoice for the client tab.',
                'items' => [
                    ['name' => 'Social retainer', 'quantity' => 1, 'unit_rate' => 25000],
                ],
            ], $super);
        }

        ClientPerformanceReport::firstOrCreate(
            ['client_id' => $abc->id, 'report_number' => 'RPT-ABC-001'],
            [
                'title' => 'October site-launch snapshot',
                'status' => ClientPerformanceReport::STATUS_DRAFT,
                'period_start' => now()->startOfMonth()->toDateString(),
                'period_end' => now()->endOfMonth()->toDateString(),
                'summary' => 'Sample report so the Reports tab has a record to open.',
                'currency' => 'INR',
                'created_by' => $super->id,
            ]
        );

        $connection = IntegrationConnection::firstOrCreate(
            ['provider' => 'meta', 'name' => 'Demo Meta — ABC Developers'],
            [
                'status' => 'connected',
                'connected_by' => $super->id,
                'connected_at' => now()->subDays(2),
                'health_status' => 'ok',
                'health_message' => 'Sample connection. No live token is stored.',
                'client_id' => $abc->id,
                'is_enabled' => true,
                'meta' => ['demo' => true],
            ]
        );
        SocialAccount::firstOrCreate(
            ['client_id' => $abc->id, 'provider' => 'meta', 'username' => 'abcdevelopers'],
            [
                'integration_connection_id' => $connection->id,
                'platform' => 'instagram',
                'external_account_id' => 'demo-abc',
                'display_name' => 'ABC Developers',
                'status' => 'connected',
                'meta' => ['demo' => true],
            ]
        );

        $this->attendance($super);

        $company = app(CompanyOrganizationService::class)->ensureDefaultCompany();
        $existingRun = PayrollRun::query()
            ->where('company_id', $company->id)
            ->where('period_year', (int) now()->year)
            ->where('period_month', (int) now()->month)
            ->whereIn('status', ['review', 'approved', 'locked'])
            ->exists();
        if (! $existingRun) {
            app(PayrollService::class)->generateDraft($company->id, (int) now()->year, (int) now()->month, $super);
        }
    }

    protected function attendance(User $actor): void
    {
        $service = app(AttendanceService::class);
        $employees = Employee::query()->whereIn('employment_status', ['active', 'probation', 'on_notice'])->get();
        $dates = collect(range(1, 8))
            ->map(fn (int $daysAgo) => now()->subDays($daysAgo))
            ->filter(fn ($date) => $date->isWeekday())
            ->take(5)
            ->push(now())
            ->unique(fn ($date) => $date->toDateString());

        foreach ($employees as $employee) {
            foreach ($dates as $date) {
                $day = $date->toDateString();
                if (AttendancePunch::query()->where('employee_id', $employee->id)->whereDate('work_date', $day)->exists()) {
                    continue;
                }
                $late = $date->day === 8;
                $in = $late ? '10:05:00' : '09:32:00';
                $out = $date->day === 9 ? '16:00:00' : '18:32:00';
                foreach (['check_in' => $in, 'check_out' => $out] as $type => $time) {
                    $service->punch($employee, $type, $actor, [
                        'punched_at' => $day.' '.$time,
                        'working_arrangement' => 'office',
                        'idempotency_key' => 'demo-att-'.$employee->id.'-'.$day.'-'.$type,
                        'source' => 'import',
                        'notes' => 'Demo attendance',
                    ]);
                }
            }
        }

        $openDays = \App\Models\AttendanceDay::query()
            ->whereDate('work_date', now()->toDateString())
            ->where('missing_checkout', true)
            ->get();
        foreach ($openDays as $openDay) {
            $employee = Employee::find($openDay->employee_id);
            if (! $employee) {
                continue;
            }
            $last = AttendancePunch::query()
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', $openDay->work_date)
                ->orderByDesc('punched_at')
                ->first();
            $at = $last ? \Carbon\Carbon::parse($last->punched_at)->addHours(8) : now();
            if ($at->greaterThan(now())) {
                $at = now();
            }
            if ($last && $at->lte(\Carbon\Carbon::parse($last->punched_at))) {
                continue;
            }
            $service->punch($employee, 'check_out', $actor, [
                'punched_at' => $at->timezone('Asia/Kolkata')->format('Y-m-d H:i:s'),
                'working_arrangement' => 'office',
                'idempotency_key' => 'demo-att-close-'.$employee->id.'-'.$openDay->work_date->toDateString(),
                'source' => 'import',
                'notes' => 'Demo attendance',
            ]);
        }
    }
}
