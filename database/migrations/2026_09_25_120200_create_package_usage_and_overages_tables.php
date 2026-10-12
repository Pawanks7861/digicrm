<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_usage_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_package_item_id')->constrained()->cascadeOnDelete();
            $table->date('usage_date');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('quantity', 12, 2);
            $table->string('direction', 20); // consume, restore, adjustment
            $table->nullableMorphs('source'); // source_type, source_id
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_package_item_id', 'period_start', 'period_end'], 'usage_item_period_idx');
            $table->index(['client_id', 'usage_date']);
            $table->index('client_package_id');
        });

        Schema::create('package_overages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_package_item_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('source');
            $table->decimal('requested_quantity', 12, 2);
            $table->decimal('included_remaining', 12, 2)->default(0);
            $table->decimal('overage_quantity', 12, 2);
            $table->decimal('overage_rate', 15, 2)->default(0);
            $table->decimal('extra_amount', 15, 2)->default(0);
            $table->string('decision', 30)->default('pending'); // pending, chargeable, complimentary, rejected, quotation_required
            $table->string('billing_status', 30)->default('unbilled'); // unbilled, added_to_quotation, added_to_invoice, paid, waived
            $table->text('decision_notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('usage_transaction_id')->nullable()->constrained('package_usage_transactions')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'decision']);
            $table->index('client_package_id');
            $table->index('billing_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_overages');
        Schema::dropIfExists('package_usage_transactions');
    }
};
