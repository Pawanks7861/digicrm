<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientPackage;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_account_manager_can_open_an_assigned_clients_invoice_and_pdf(): void
    {
        $am = $this->accountManager();
        $invoice = $this->invoiceFor($am, $this->clientFor($am));

        $this->actingAs($am)->get(route('invoices.show', $invoice))->assertOk();
        $this->actingAs($am)->get(route('invoices.pdf', $invoice))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_account_manager_cannot_open_another_clients_invoice_or_pdf(): void
    {
        $am = $this->accountManager();
        $own = $this->invoiceFor($am, $this->clientFor($am));
        $foreign = $this->invoiceFor($am, Client::factory()->create());

        $this->actingAs($am)->get(route('invoices.show', $foreign))->assertForbidden();
        $this->actingAs($am)->get(route('invoices.pdf', $foreign))->assertForbidden();

        $this->actingAs($am)->get(route('invoices.index'))
            ->assertOk()
            ->assertSee($own->invoice_number)
            ->assertDontSee($foreign->invoice_number);
    }

    public function test_account_manager_cannot_mutate_another_clients_invoice(): void
    {
        $am = $this->accountManager();
        $am->givePermissionTo(['invoices.create', 'invoices.edit', 'invoices.send', 'invoices.cancel']);
        $ownClient = $this->clientFor($am);
        $otherClient = Client::factory()->create();
        $own = $this->invoiceFor($am, $ownClient);
        $toSend = $this->invoiceFor($am, $ownClient);
        $toCancel = $this->invoiceFor($am, $ownClient);
        $foreign = $this->invoiceFor($am, $otherClient);
        $package = ClientPackage::create([
            'client_id' => $otherClient->id,
            'package_name' => 'Foreign retainer',
            'start_date' => now()->toDateString(),
            'agreed_price' => 1000,
        ]);

        $this->actingAs($am)->get(route('invoices.edit', $own))->assertOk();
        $this->actingAs($am)->get(route('invoices.edit', $foreign))->assertForbidden();
        $this->actingAs($am)->put(route('invoices.update', $foreign), $this->payload($otherClient))->assertForbidden();
        $this->actingAs($am)->post(route('invoices.send', $foreign))->assertForbidden();
        $this->actingAs($am)->post(route('invoices.cancel', $foreign))->assertForbidden();
        $this->actingAs($am)->post(route('invoices.store'), $this->payload($otherClient))->assertForbidden();
        $this->actingAs($am)->post(route('client-packages.invoice', $package))->assertForbidden();

        $this->assertSame('draft', $foreign->fresh()->status);
        $this->assertSame(0, Invoice::where('client_id', $otherClient->id)->where('id', '!=', $foreign->id)->count());

        $this->actingAs($am)->post(route('invoices.send', $toSend))->assertRedirect();
        $this->actingAs($am)->post(route('invoices.cancel', $toCancel))->assertRedirect();
        $this->actingAs($am)->post(route('invoices.store'), $this->payload($ownClient))
            ->assertRedirect();

        $this->assertSame('sent', $toSend->fresh()->status);
        $this->assertSame('cancelled', $toCancel->fresh()->status);
        $this->assertTrue(Invoice::where('client_id', $ownClient->id)->where('notes', 'Assigned retainer')->exists());
    }

    public function test_super_admin_can_open_any_invoice(): void
    {
        $am = $this->accountManager();
        $admin = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $admin->assignRole('super_admin');
        $foreign = $this->invoiceFor($am, Client::factory()->create());

        $this->actingAs($admin)->get(route('invoices.show', $foreign))->assertOk();
        $this->actingAs($admin)->get(route('invoices.pdf', $foreign))->assertOk();
    }

    protected function accountManager(): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $user->assignRole('account_manager');

        return $user;
    }

    protected function clientFor(User $manager): Client
    {
        return Client::factory()->forManager($manager)->create();
    }

    protected function invoiceFor(User $actor, Client $client): Invoice
    {
        return app(InvoiceService::class)->create($client, [
            'tax_treatment' => 'exempt',
            'items' => [
                ['name' => 'Retainer', 'quantity' => 1, 'unit_rate' => 1000],
            ],
        ], $actor);
    }

    protected function payload(Client $client): array
    {
        return [
            'client_id' => $client->id,
            'tax_treatment' => 'exempt',
            'notes' => 'Assigned retainer',
            'items' => [
                ['name' => 'Retainer', 'quantity' => 1, 'unit_rate' => 1000],
            ],
        ];
    }
}
