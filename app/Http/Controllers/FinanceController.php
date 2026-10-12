<?php

namespace App\Http\Controllers;

use App\Models\BillingSetting;
use App\Models\TaxRate;
use App\Services\ActivityLogger;
use App\Services\ReceivablesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinanceController extends Controller
{
    public function __construct(
        protected ReceivablesService $receivables,
        protected ActivityLogger $activityLogger
    ) {
        $this->middleware('agency');
    }

    public function receivables(Request $request): Response
    {
        abort_unless($request->user()->can('receivables.view') || $request->user()->hasRole('super_admin'), 403);

        return Inertia::render('Finance/Receivables', [
            'dashboard' => $this->receivables->dashboard(),
        ]);
    }

    public function renewals(Request $request): Response
    {
        abort_unless($request->user()->can('renewals.view') || $request->user()->hasRole('super_admin'), 403);

        return Inertia::render('Finance/Renewals', [
            'renewals' => $this->receivables->renewals((int) $request->get('days', 30)),
            'days' => (int) $request->get('days', 30),
        ]);
    }

    public function unbilledOverages(Request $request): Response
    {
        abort_unless($request->user()->can('invoices.view') || $request->user()->can('quotations.view') || $request->user()->hasRole('super_admin'), 403);

        return Inertia::render('Finance/UnbilledOverages', [
            'overages' => $this->receivables->unbilledOverages(),
        ]);
    }

    public function billingSettings(Request $request): Response
    {
        abort_unless($request->user()->can('billing_settings.view') || $request->user()->can('billing_settings.manage') || $request->user()->hasRole('super_admin'), 403);

        return Inertia::render('Finance/BillingSettings', [
            'settings' => BillingSetting::current(),
            'taxRates' => TaxRate::orderBy('name')->get(),
            'canManage' => $request->user()->can('billing_settings.manage') || $request->user()->hasRole('super_admin'),
        ]);
    }

    public function updateBillingSettings(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('billing_settings.manage') || $request->user()->hasRole('super_admin'), 403);
        $data = $request->validate([
            'legal_name' => ['required', 'string', 'max:255'],
            'trading_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'pin' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'gstin' => ['nullable', 'string', 'max:20'],
            'pan' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'website' => ['nullable', 'string', 'max:255'],
            'quotation_prefix' => ['nullable', 'string', 'max:10'],
            'invoice_prefix' => ['nullable', 'string', 'max:10'],
            'payment_prefix' => ['nullable', 'string', 'max:10'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'account_name' => ['nullable', 'string', 'max:120'],
            'account_number' => ['nullable', 'string', 'max:60'],
            'ifsc' => ['nullable', 'string', 'max:20'],
            'upi_id' => ['nullable', 'string', 'max:80'],
            'signatory_name' => ['nullable', 'string', 'max:120'],
            'default_terms' => ['nullable', 'string'],
            'default_currency' => ['nullable', 'string', 'size:3'],
        ]);

        $settings = BillingSetting::current();
        $settings->update($data);
        $this->activityLogger->log('billing_settings_updated', 'finance', $settings);

        return back()->with('success', 'Billing settings saved.');
    }

    public function storeTaxRate(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('billing_settings.manage') || $request->user()->hasRole('super_admin'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'percentage' => ['required', 'numeric', 'min:0'],
            'tax_type' => ['required', 'in:gst,exempt,other'],
            'cgst_percentage' => ['nullable', 'numeric', 'min:0'],
            'sgst_percentage' => ['nullable', 'numeric', 'min:0'],
            'igst_percentage' => ['nullable', 'numeric', 'min:0'],
        ]);
        TaxRate::create([
            ...$data,
            'cgst_percentage' => $data['cgst_percentage'] ?? ($data['percentage'] / 2),
            'sgst_percentage' => $data['sgst_percentage'] ?? ($data['percentage'] / 2),
            'igst_percentage' => $data['igst_percentage'] ?? $data['percentage'],
            'is_active' => true,
        ]);

        return back()->with('success', 'Tax rate added.');
    }
}
