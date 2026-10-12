<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 40)->unique();
            $table->string('campaign_type', 50)->default('social_media');
            $table->text('objective')->nullable();
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('budget', 15, 2)->nullable();
            $table->decimal('internal_cost', 15, 2)->nullable();
            $table->decimal('client_charge', 15, 2)->nullable();
            $table->string('currency', 10)->default('INR');
            $table->foreignId('campaign_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('draft');
            $table->string('priority', 20)->default('normal');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_id', 'status']);
            $table->index('campaign_manager_id');
            $table->index(['start_date', 'end_date']);
            $table->index('priority');
        });

        Schema::create('campaign_deliverables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_package_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_package_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('deliverable_type', 50);
            $table->string('platform', 40)->nullable();
            $table->text('description')->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->string('unit', 50)->default('Post');
            $table->string('priority', 20)->default('normal');
            $table->date('internal_due_date')->nullable();
            $table->date('client_due_date')->nullable();
            $table->string('status', 40)->default('brief_pending');
            $table->string('scope_status', 40)->default('out_of_package');
            $table->foreignId('package_usage_transaction_id')->nullable()->constrained('package_usage_transactions')->nullOnDelete();
            $table->timestamp('scope_consumed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['campaign_id', 'status']);
            $table->index(['client_id', 'status']);
            $table->index('client_package_item_id');
            $table->index(['internal_due_date', 'client_due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_deliverables');
        Schema::dropIfExists('campaigns');
    }
};
