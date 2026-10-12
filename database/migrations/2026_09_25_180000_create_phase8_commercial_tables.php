<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('percentage', 8, 3)->default(0);
            $table->string('tax_type', 30)->default('gst'); // gst, exempt, other
            $table->decimal('cgst_percentage', 8, 3)->default(0);
            $table->decimal('sgst_percentage', 8, 3)->default(0);
            $table->decimal('igst_percentage', 8, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestamps();
        });

        Schema::create('billing_settings', function (Blueprint $table) {
            $table->id();
            $table->string('legal_name');
            $table->string('trading_name')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('pin', 20)->nullable();
            $table->string('country')->default('India');
            $table->string('gstin', 20)->nullable();
            $table->string('pan', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->string('quotation_prefix')->default('QUO');
            $table->string('invoice_prefix')->default('INV');
            $table->string('payment_prefix')->default('PAY');
            $table->string('bank_name')->nullable();
            $table->string('account_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('ifsc')->nullable();
            $table->string('upi_id')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('signatory_name')->nullable();
            $table->text('default_terms')->nullable();
            $table->string('default_currency', 3)->default('INR');
            $table->timestamps();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->string('billing_legal_name')->nullable()->after('company_name');
            $table->text('billing_address')->nullable()->after('address');
            $table->string('billing_city')->nullable()->after('city');
            $table->string('billing_state')->nullable()->after('state');
            $table->string('billing_pin', 20)->nullable()->after('country');
            $table->string('billing_email')->nullable()->after('email');
            $table->string('accounts_contact')->nullable()->after('contact_person');
            $table->string('purchase_order_number')->nullable()->after('pan');
        });

        Schema::table('client_portal_settings', function (Blueprint $table) {
            $table->boolean('show_quotations')->default(false)->after('show_invoices');
            $table->boolean('show_payments')->default(false)->after('show_quotations');
            $table->boolean('allow_quotation_acceptance')->default(true)->after('allow_content_approval');
        });

        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('quotation_number');
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_contact_id')->nullable()->constrained('client_contacts')->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('currency', 3)->default('INR');
            $table->date('issue_date');
            $table->date('valid_until')->nullable();
            $table->string('status', 40)->default('draft');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->string('discount_type', 20)->nullable(); // fixed, percent, null
            $table->decimal('discount_value', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('taxable_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('cgst_amount', 15, 2)->default(0);
            $table->decimal('sgst_amount', 15, 2)->default(0);
            $table->decimal('igst_amount', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->string('tax_treatment', 20)->default('auto'); // auto, intra, inter, exempt
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->text('internal_notes')->nullable();
            $table->unsignedInteger('revision_number')->default(1);
            $table->foreignId('parent_quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->foreignId('accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->string('client_message')->nullable();
            $table->string('accepted_ip', 45)->nullable();
            $table->foreignId('converted_invoice_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('quotation_number');
            $table->index(['client_id', 'status']);
            $table->index('valid_until');
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->string('item_type', 40)->default('custom'); // package, overage, campaign, request, custom
            $table->nullableMorphs('source');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('hsn_sac', 20)->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->string('unit', 40)->default('Nos');
            $table->decimal('unit_rate', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();
            $table->decimal('tax_percent', 8, 3)->default(0);
            $table->decimal('cgst_percent', 8, 3)->default(0);
            $table->decimal('sgst_percent', 8, 3)->default(0);
            $table->decimal('igst_percent', 8, 3)->default(0);
            $table->decimal('line_subtotal', 15, 2)->default(0);
            $table->decimal('taxable_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('cgst_amount', 15, 2)->default(0);
            $table->decimal('sgst_amount', 15, 2)->default(0);
            $table->decimal('igst_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number');
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_contact_id')->nullable()->constrained('client_contacts')->nullOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_package_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('currency', 3)->default('INR');
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->string('status', 40)->default('draft');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->string('discount_type', 20)->nullable();
            $table->decimal('discount_value', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('taxable_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('cgst_amount', 15, 2)->default(0);
            $table->decimal('sgst_amount', 15, 2)->default(0);
            $table->decimal('igst_amount', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('balance_due', 15, 2)->default(0);
            $table->string('tax_treatment', 20)->default('auto');
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->text('internal_notes')->nullable();
            // Billing snapshots (issued)
            $table->string('seller_name')->nullable();
            $table->text('seller_address')->nullable();
            $table->string('seller_state')->nullable();
            $table->string('seller_gstin')->nullable();
            $table->string('buyer_name')->nullable();
            $table->text('buyer_address')->nullable();
            $table->string('buyer_state')->nullable();
            $table->string('buyer_gstin')->nullable();
            $table->string('period_label')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('invoice_number');
            $table->index(['client_id', 'status']);
            $table->index('due_date');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->foreign('converted_invoice_id')->references('id')->on('invoices')->nullOnDelete();
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('item_type', 40)->default('custom');
            $table->nullableMorphs('source');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('hsn_sac', 20)->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->string('unit', 40)->default('Nos');
            $table->decimal('unit_rate', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();
            $table->decimal('tax_percent', 8, 3)->default(0);
            $table->decimal('cgst_percent', 8, 3)->default(0);
            $table->decimal('sgst_percent', 8, 3)->default(0);
            $table->decimal('igst_percent', 8, 3)->default(0);
            $table->decimal('line_subtotal', 15, 2)->default(0);
            $table->decimal('taxable_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('cgst_amount', 15, 2)->default(0);
            $table->decimal('sgst_amount', 15, 2)->default(0);
            $table->decimal('igst_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number');
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('currency', 3)->default('INR');
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->string('payment_method', 40);
            $table->string('transaction_reference')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('recorded'); // recorded, reversed
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('payment_number');
            $table->index(['client_id', 'payment_date']);
            $table->index(['invoice_id', 'status']);
        });

        Schema::table('package_overages', function (Blueprint $table) {
            $table->foreignId('quotation_id')->nullable()->after('usage_transaction_id')->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->after('quotation_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('package_overages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
            $table->dropConstrainedForeignId('quotation_id');
        });
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('converted_invoice_id');
        });
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
        Schema::table('client_portal_settings', function (Blueprint $table) {
            $table->dropColumn(['show_quotations', 'show_payments', 'allow_quotation_acceptance']);
        });
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'billing_legal_name', 'billing_address', 'billing_city', 'billing_state',
                'billing_pin', 'billing_email', 'accounts_contact', 'purchase_order_number',
            ]);
        });
        Schema::dropIfExists('billing_settings');
        Schema::dropIfExists('tax_rates');
    }
};
