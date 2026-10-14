<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\CompanyHoliday;
use App\Services\Hr\CompanyOrganizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HolidayController extends Controller
{
    public function __construct(protected CompanyOrganizationService $organizations)
    {
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->can('hr.holidays.view') || $request->user()->can('hr.holidays.manage') || $request->user()->isSuperAdmin(), 403);
        $company = $this->organizations->ensureDefaultCompany();
        $year = (int) $request->get('year', now()->year);

        $holidays = CompanyHoliday::query()
            ->forCompany($company->id)
            ->whereYear('date', $year)
            ->orderBy('date')
            ->get();

        return Inertia::render('Hr/Holidays/Index', [
            'year' => $year,
            'holidays' => $holidays,
            'can' => ['manage' => $request->user()->can('hr.holidays.manage') || $request->user()->isSuperAdmin()],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('hr.holidays.manage') || $request->user()->isSuperAdmin(), 403);
        $company = $this->organizations->ensureDefaultCompany();
        $data = $this->validated($request);
        $exists = CompanyHoliday::query()
            ->where('company_id', $company->id)
            ->whereDate('date', $data['date'])
            ->where('branch_id', $data['branch_id'] ?? null)
            ->exists();
        if ($exists) {
            return back()->withErrors(['date' => 'A holiday already exists on that date for this branch.']);
        }
        CompanyHoliday::create(array_merge($data, ['company_id' => $company->id]));

        return back()->with('success', 'Holiday added. Locked payroll is not recalculated.');
    }

    public function update(Request $request, CompanyHoliday $holiday): RedirectResponse
    {
        abort_unless($request->user()->can('hr.holidays.manage') || $request->user()->isSuperAdmin(), 403);
        $company = $this->organizations->ensureDefaultCompany();
        abort_unless((int) $holiday->company_id === (int) $company->id, 404);
        $holiday->update($this->validated($request));

        return back()->with('success', 'Holiday updated. Locked payroll is not recalculated.');
    }

    public function destroy(Request $request, CompanyHoliday $holiday): RedirectResponse
    {
        abort_unless($request->user()->can('hr.holidays.manage') || $request->user()->isSuperAdmin(), 403);
        $company = $this->organizations->ensureDefaultCompany();
        abort_unless((int) $holiday->company_id === (int) $company->id, 404);
        $holiday->delete();

        return back()->with('success', 'Holiday removed.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->can('hr.holidays.view') || $request->user()->can('hr.holidays.manage') || $request->user()->isSuperAdmin(), 403);
        $company = $this->organizations->ensureDefaultCompany();
        $year = (int) $request->get('year', now()->year);
        $rows = CompanyHoliday::query()->forCompany($company->id)->whereYear('date', $year)->orderBy('date')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['date', 'name', 'category', 'is_paid', 'is_optional', 'description', 'is_recurring', 'is_active']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->date?->toDateString(), $row->name, $row->category, $row->is_paid ? 1 : 0,
                    $row->is_optional ? 1 : 0, $row->description, $row->is_recurring ? 1 : 0, $row->is_active ? 1 : 0,
                ]);
            }
            fclose($out);
        }, "holidays-{$year}.csv", ['Content-Type' => 'text/csv']);
    }

    public function import(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('hr.holidays.manage') || $request->user()->isSuperAdmin(), 403);
        $company = $this->organizations->ensureDefaultCompany();
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt']]);
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = fgetcsv($handle);
        $map = array_flip($header ?: []);
        $added = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $date = $row[$map['date'] ?? 0] ?? null;
            $name = $row[$map['name'] ?? 1] ?? null;
            if (! $date || ! $name) {
                continue;
            }
            $holiday = CompanyHoliday::firstOrCreate(
                ['company_id' => $company->id, 'branch_id' => null, 'date' => $date],
                [
                    'name' => $name,
                    'category' => $row[$map['category'] ?? 2] ?? 'public',
                    'is_paid' => (bool) ($row[$map['is_paid'] ?? 3] ?? true),
                    'is_optional' => (bool) ($row[$map['is_optional'] ?? 4] ?? false),
                    'description' => $row[$map['description'] ?? 5] ?? null,
                    'is_active' => true,
                ]
            );
            if ($holiday->wasRecentlyCreated) {
                $added++;
            }
        }
        fclose($handle);

        return back()->with('success', $added.' holidays imported. Existing dates were left unchanged.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'date' => ['required', 'date'],
            'category' => ['required', 'in:public,company,regional,optional,restricted,custom,weekly_off'],
            'is_paid' => ['boolean'],
            'is_optional' => ['boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_recurring' => ['boolean'],
            'is_active' => ['boolean'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
        ]);
    }
}
