<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->nullable()->unique();
            $table->text('description')->nullable();
            $table->string('billing_cycle', 30)->default('monthly'); // monthly, quarterly, half_yearly, yearly, one_time, custom
            $table->decimal('base_price', 15, 2)->default(0);
            $table->string('currency', 10)->default('INR');
            $table->string('status', 20)->default('active'); // active, inactive
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('billing_cycle');
            $table->index('name');
        });

        Schema::create('service_package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_package_id')->constrained()->cascadeOnDelete();
            $table->string('service_name');
            $table->string('service_code', 50)->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('quantity')->default(0);
            $table->string('unit', 50)->default('Post');
            $table->decimal('overage_rate', 15, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_unlimited')->default(false);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['service_package_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_package_items');
        Schema::dropIfExists('service_packages');
    }
};
