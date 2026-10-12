<?php

namespace Tests\Feature;

use App\Models\BillingSetting;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\PackageOverage;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\ServicePackage;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\ClientPackageService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\QuotationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase8CommercialTest extends TestCase
{
    use RefreshDatabase;

    protected TaxRate $gst18;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->gst18 = TaxRate::where('percentage', 18)->firstOrFail();
        BillingSetting::current()->update(['state' => 'Gujarat', 'legal_name' => 'Digi Agency Pvt Ltd']);
    }

    protected function user(string $role, array $attrs = []): User
    {
        $user = User::factory()->create(array_merge([
            'email_verified_at' => now(),
            'is_active' => true,
        ], $attrs));
        $user->assignRole($role);

        return $user;
    }

    protected function clientWithState(string $state): Client
    {
        return Client::factory()->create([
            'state' => $state,
            'billing_state' => $state,
            'company_name' => 'Client '.$state,
        ]);
    }

    public function test_quotation_totals_with_gst(): void
    {
        $admin = $this->user('super_admin');
        $client = $this->clientWithState('Gujarat');

        $q = app(QuotationService::class)->create($client, [
            'tax_treatment' => 'intra',
            'items' => [
                ['name' => 'Service A', 'quantity' => 1, 'unit_rate' => 10000, 'tax_rate_id' => $this->gst18->id],
                ['name' => 'Service B', 'quantity' => 1, 'unit_rate' => 5000, 'tax_rate_id' => $this->gst18->id],
            ],
        ], $admin);

        $this->assertEquals('15000.00', $q->subtotal);
        $this->assertEquals('15000.00', $q->taxable_amount);
        $this->assertEquals('2700.00', $q->tax_amount);
        $this->assertEquals('17700.00', $q->grand_total);
        $this->assertEquals('1350.00', $q->cgst_amount);
        $this->assertEquals('1350.00', $q->sgst_amount);
        $this->assertEquals('0.00', $q->igst_amount);
    }

    public function test_intra_state_gst_split(): void
    {
        $admin = $this->user('super_admin');
        $client = $this->clientWithState('Gujarat');
        $q = app(QuotationService::class)->create($client, [
            'tax_treatment' => 'auto',
            'items' => [
                ['name' => 'Work', 'quantity' => 1, 'unit_rate' => 100000, 'tax_rate_id' => $this->gst18->id],
            ],
        ], $admin);

        $this->assertEquals('9000.00', $q->cgst_amount);
        $this->assertEquals('9000.00', $q->sgst_amount);
        $this->assertEquals('0.00', $q->igst_amount);
        $this->assertEquals('118000.00', $q->grand_total);
        $this->assertEquals('9.000', $q->items->first()->cgst_percent);
    }

    public function test_inter_state_igst(): void
    {
        $admin = $this->user('super_admin');
        $client = $this->clientWithState('Maharashtra');
        $q = app(QuotationService::class)->create($client, [
            'tax_treatment' => 'auto',
            'items' => [
                ['name' => 'Work', 'quantity' => 1, 'unit_rate' => 100000, 'tax_rate_id' => $this->gst18->id],
            ],
        ], $admin);

        $this->assertEquals('0.00', $q->cgst_amount);
        $this->assertEquals('0.00', $q->sgst_amount);
        $this->assertEquals('18000.00', $q->igst_amount);
        $this->assertEquals('118000.00', $q->grand_total);
    }

    public function test_discount_before_tax(): void
    {
        $admin = $this->user('super_admin');
        $client = $this->clientWithState('Gujarat');
        $q = app(QuotationService::class)->create($client, [
            'tax_treatment' => 'intra',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'items' => [
                ['name' => 'Work', 'quantity' => 1, 'unit_rate' => 100000, 'tax_rate_id' => $this->gst18->id],
            ],
        ], $admin);

        $this->assertEquals('100000.00', $q->subtotal);
        $this->assertEquals('10000.00', $q->discount_amount);
        $this->assertEquals('90000.00', $q->taxable_amount);
        $this->assertEquals('16200.00', $q->tax_amount);
        $this->assertEquals('106200.00', $q->grand_total);
    }

    public function test_quotation_revision_preserves_original(): void
    {
        $admin = $this->user('super_admin');
        $client = $this->clientWithState('Gujarat');
        $svc = app(QuotationService::class);
        $r1 = $svc->create($client, [
            'tax_treatment' => 'exempt',
            'items' => [['name' => 'A', 'quantity' => 1, 'unit_rate' => 1000, 'tax_rate_id' => null]],
        ], $admin);
        $svc->send($r1, $admin);
        $originalTotal = $r1->grand_total;
        $originalId = $r1->id;

        $r2 = $svc->revise($r1->fresh(), [
            'tax_treatment' => 'exempt',
            'items' => [['name' => 'A', 'quantity' => 2, 'unit_rate' => 1000]],
        ], $admin);

        $this->assertEquals(2, $r2->revision_number);
        $this->assertEquals('2000.00', $r2->grand_total);
        $this->assertEquals('1000.00', Quotation::find($originalId)->grand_total);
        $this->assertEquals($originalTotal, Quotation::find($originalId)->grand_total);
        $this->assertEquals('superseded', Quotation::find($originalId)->status);
    }

    public function test_quotation_conversion_idempotent(): void
    {
        $admin = $this->user('super_admin');
        $client = $this->clientWithState('Gujarat');
        $svc = app(QuotationService::class);
        $q = $svc->create($client, [
            'tax_treatment' => 'intra',
            'items' => [['name' => 'A', 'quantity' => 1, 'unit_rate' => 10000, 'tax_rate_id' => $this->gst18->id]],
        ], $admin);
        $svc->send($q, $admin);
        $svc->accept($q->fresh(), $admin);

        $inv1 = app(InvoiceService::class)->convertFromQuotation($q->fresh(), $admin);
        $inv2 = app(InvoiceService::class)->convertFromQuotation($q->fresh(), $admin);

        $this->assertEquals($inv1->id, $inv2->id);
        $this->assertEquals(1, Invoice::where('quotation_id', $q->id)->count());
        $this->assertEquals('converted', $q->fresh()->status);
        $this->assertEquals($q->grand_total, $inv1->grand_total);
    }

    public function test_partial_and_full_payment_and_reversal(): void
    {
        $admin = $this->user('super_admin');
        $client = $this->clientWithState('Gujarat');
        $inv = app(InvoiceService::class)->create($client, [
            'tax_treatment' => 'exempt',
            'items' => [['name' => 'A', 'quantity' => 1, 'unit_rate' => 100000]],
        ], $admin);
        app(InvoiceService::class)->send($inv, $admin);

        $pay = app(PaymentService::class);
        $p1 = $pay->record($inv->fresh(), [
            'amount' => 40000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'upi',
        ], $admin);
        $inv = $inv->fresh();
        $this->assertEquals('40000.00', $inv->amount_paid);
        $this->assertEquals('60000.00', $inv->balance_due);
        $this->assertEquals('partially_paid', $inv->status);

        $p2 = $pay->record($inv->fresh(), [
            'amount' => 60000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'neft',
        ], $admin);
        $inv = $inv->fresh();
        $this->assertEquals('100000.00', $inv->amount_paid);
        $this->assertEquals('0.00', $inv->balance_due);
        $this->assertEquals('paid', $inv->status);
        $this->assertNotNull($inv->paid_at);

        $pay->reverse($p2, $admin, 'Bank bounce');
        $inv = $inv->fresh();
        $this->assertEquals('40000.00', $inv->amount_paid);
        $this->assertEquals('60000.00', $inv->balance_due);
        $this->assertEquals('partially_paid', $inv->status);
        $this->assertEquals('reversed', $p2->fresh()->status);
    }

    public function test_overpayment_blocked(): void
    {
        $admin = $this->user('super_admin');
        $client = $this->clientWithState('Gujarat');
        $inv = app(InvoiceService::class)->create($client, [
            'tax_treatment' => 'exempt',
            'items' => [['name' => 'A', 'quantity' => 1, 'unit_rate' => 10000]],
        ], $admin);
        app(InvoiceService::class)->send($inv, $admin);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(PaymentService::class)->record($inv->fresh(), [
            'amount' => 15000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ], $admin);
    }

    public function test_overdue_computed(): void
    {
        $admin = $this->user('super_admin');
        $client = $this->clientWithState('Gujarat');
        $inv = app(InvoiceService::class)->create($client, [
            'tax_treatment' => 'exempt',
            'due_date' => now()->subDay()->toDateString(),
            'items' => [['name' => 'A', 'quantity' => 1, 'unit_rate' => 5000]],
        ], $admin);
        app(InvoiceService::class)->send($inv, $admin);
        $this->assertTrue($inv->fresh()->is_overdue);
        $this->assertEquals('overdue', $inv->fresh()->display_status);

        app(PaymentService::class)->record($inv->fresh(), [
            'amount' => 5000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'upi',
        ], $admin);
        $this->assertFalse($inv->fresh()->is_overdue);
        $this->assertEquals('paid', $inv->fresh()->status);
    }

    public function test_overage_cannot_be_billed_twice(): void
    {
        $admin = $this->user('super_admin');
        $client = $this->clientWithState('Gujarat');
        $master = ServicePackage::factory()->withDefaultItems()->create();
        $cp = app(ClientPackageService::class)->assign($client, [
            'service_package_id' => $master->id,
            'start_date' => now()->toDateString(),
            'agreed_price' => 10000,
            'currency' => 'INR',
            'billing_cycle' => 'monthly',
        ], $admin);

        $overage = PackageOverage::create([
            'client_id' => $client->id,
            'client_package_id' => $cp->id,
            'client_package_item_id' => $cp->items()->first()->id,
            'requested_quantity' => 1,
            'included_remaining' => 0,
            'overage_quantity' => 1,
            'overage_rate' => 2000,
            'extra_amount' => 2000,
            'decision' => 'chargeable',
            'billing_status' => 'unbilled',
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        $inv = app(InvoiceService::class)->create($client, [
            'tax_treatment' => 'exempt',
            'items' => [['name' => 'Base', 'quantity' => 1, 'unit_rate' => 100]],
        ], $admin);
        app(InvoiceService::class)->addOverage($inv, $overage, $admin);
        $this->assertEquals('added_to_invoice', $overage->fresh()->billing_status);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(InvoiceService::class)->addOverage($inv->fresh(), $overage->fresh(), $admin);
    }

    public function test_team_and_admin_finance_isolation(): void
    {
        $team = $this->user('team_member');
        $admin = $this->user('admin');

        $this->actingAs($team)->get(route('quotations.index'))->assertForbidden();
        $this->actingAs($team)->get(route('invoices.index'))->assertForbidden();
        $this->actingAs($team)->get(route('finance.receivables'))->assertForbidden();

        $this->actingAs($admin)->get(route('quotations.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('finance.receivables'))->assertForbidden();
        $this->actingAs($admin)->get(route('finance.billing-settings'))->assertForbidden();
    }

    public function test_client_portal_idor_and_settings(): void
    {
        $admin = $this->user('super_admin');
        $a = $this->clientWithState('Gujarat');
        $b = $this->clientWithState('Gujarat');
        $userA = $this->user('client', ['client_id' => $a->id]);
        $userB = $this->user('client', ['client_id' => $b->id]);

        $q = app(QuotationService::class)->create($a, [
            'tax_treatment' => 'exempt',
            'items' => [['name' => 'A', 'quantity' => 1, 'unit_rate' => 1000]],
        ], $admin);
        app(QuotationService::class)->send($q, $admin);

        $settings = \App\Models\ClientPortalSetting::forClient($a);
        $settings->update(['show_quotations' => false]);
        $this->actingAs($userA)->get(route('portal.quotations'))->assertForbidden();

        $settings->update(['show_quotations' => true, 'allow_quotation_acceptance' => true]);
        $this->actingAs($userA)->get(route('portal.quotations.show', $q))->assertOk();
        $this->actingAs($userB)->get(route('portal.quotations.show', $q))->assertForbidden();
        $this->actingAs($userB)->post(route('portal.quotations.accept', $q), ['confirm' => true])->assertForbidden();
        $this->actingAs($userA)->get(route('portal.quotations.pdf', $q))->assertOk();
    }

    public function test_package_invoice_generation(): void
    {
        $admin = $this->user('super_admin');
        $client = $this->clientWithState('Gujarat');
        $master = ServicePackage::factory()->withDefaultItems()->create();
        $cp = app(ClientPackageService::class)->assign($client, [
            'service_package_id' => $master->id,
            'start_date' => now()->toDateString(),
            'agreed_price' => 35000,
            'currency' => 'INR',
            'billing_cycle' => 'monthly',
        ], $admin);

        $inv = app(InvoiceService::class)->createFromPackage($cp, $admin, [
            'period_label' => 'October 2026',
            'tax_treatment' => 'intra',
        ]);

        $this->assertEquals($cp->id, $inv->client_package_id);
        $this->assertEquals('October 2026', $inv->period_label);
        $this->assertEquals('35000.00', $inv->subtotal);
        $this->assertStringContainsString('INV-', $inv->invoice_number);
    }
}
