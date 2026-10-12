<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_cost_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('hourly_cost', 12, 2);
            $table->string('currency', 3)->default('INR');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'effective_from', 'effective_to']);
        });

        Schema::create('operational_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('cost_type', 40)->default('other');
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('INR');
            $table->date('incurred_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'incurred_at']);
            $table->index(['campaign_id', 'incurred_at']);
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->boolean('standalone_charge')->default(false)->after('client_charge');
        });

        Schema::table('campaign_deliverables', function (Blueprint $table) {
            if (! Schema::hasColumn('campaign_deliverables', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('status');
                $table->index(['status', 'client_due_date', 'completed_at']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('campaign_deliverables', function (Blueprint $table) {
            if (Schema::hasColumn('campaign_deliverables', 'completed_at')) {
                $table->dropColumn('completed_at');
            }
        });
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('standalone_charge');
        });
        Schema::dropIfExists('operational_costs');
        Schema::dropIfExists('user_cost_rates');
    }
};
