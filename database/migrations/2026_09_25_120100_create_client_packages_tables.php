<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_package_id')->nullable()->constrained()->nullOnDelete();
            $table->string('package_name'); // snapshot name
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('billing_cycle', 30)->default('monthly');
            $table->decimal('agreed_price', 15, 2)->default(0);
            $table->string('currency', 10)->default('INR');
            $table->string('status', 20)->default('upcoming'); // upcoming, active, expired, paused, cancelled
            $table->boolean('auto_renew')->default(false);
            $table->string('renewal_type', 20)->default('manual'); // manual, auto
            $table->text('notes')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('renewed_from_id')->nullable()->constrained('client_packages')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_id', 'status']);
            $table->index(['start_date', 'end_date']);
            $table->index('service_package_id');
        });

        Schema::create('client_package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_package_item_id')->nullable()->constrained('service_package_items')->nullOnDelete();
            $table->string('service_name');
            $table->string('service_code', 50)->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('included_quantity')->default(0);
            $table->string('unit', 50)->default('Post');
            $table->decimal('overage_rate', 15, 2)->default(0);
            $table->boolean('is_unlimited')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('client_package_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_package_items');
        Schema::dropIfExists('client_packages');
    }
};
