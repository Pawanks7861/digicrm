<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_document_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->boolean('is_required')->default(false);
            $table->string('applies_to_employment_type', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_document_type_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('disk', 40)->default('local');
            $table->string('path');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 20)->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->date('expires_on')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['employee_id', 'status']);
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->decimal('max_amount', 14, 2)->nullable();
            $table->boolean('requires_receipt')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('expense_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_category_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('INR');
            $table->date('expense_date');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('receipt_path')->nullable();
            $table->string('receipt_disk', 40)->default('local');
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->string('payment_status', 20)->default('unpaid'); // unpaid, scheduled, paid, via_payroll
            $table->string('payment_reference')->nullable();
            $table->foreignId('payroll_item_id')->nullable();
            $table->string('duplicate_hash', 64)->nullable();
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'status']);
            $table->index(['employee_id', 'expense_date']);
            $table->index('duplicate_hash');
        });

        Schema::create('salary_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('salary_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_structure_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->string('component_type', 20); // earning, deduction, employer_contribution
            $table->string('calc_type', 20)->default('fixed'); // fixed, percentage, formula
            $table->decimal('amount', 14, 2)->nullable();
            $table->decimal('percentage', 8, 4)->nullable();
            $table->string('percentage_of', 40)->nullable();
            $table->string('formula_key', 60)->nullable(); // whitelisted key only
            $table->boolean('is_taxable')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('employee_salary_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_structure_id')->constrained()->cascadeOnDelete();
            $table->decimal('ctc_monthly', 14, 2)->nullable();
            $table->decimal('gross_monthly', 14, 2)->nullable();
            $table->json('component_overrides')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'effective_from', 'effective_to']);
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('run_code', 40);
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 20)->default('draft');
            $table->string('currency', 3)->default('INR');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('input_snapshot')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'run_code']);
            $table->unique(['company_id', 'period_year', 'period_month', 'status'], 'payroll_runs_period_status_unique');
        });

        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('payable_days')->default(0);
            $table->unsignedSmallInteger('lop_days')->default(0);
            $table->decimal('ctc', 14, 2)->default(0);
            $table->decimal('gross', 14, 2)->default(0);
            $table->decimal('employer_contributions', 14, 2)->default(0);
            $table->decimal('deductions', 14, 2)->default(0);
            $table->decimal('net_pay', 14, 2)->default(0);
            $table->json('calculation')->nullable();
            $table->string('payment_status', 20)->default('unpaid');
            $table->timestamps();
            $table->unique(['payroll_run_id', 'employee_id']);
        });

        Schema::table('expense_claims', function (Blueprint $table) {
            $table->foreign('payroll_item_id')->references('id')->on('payroll_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expense_claims', function (Blueprint $table) {
            $table->dropForeign(['payroll_item_id']);
        });
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('employee_salary_assignments');
        Schema::dropIfExists('salary_components');
        Schema::dropIfExists('salary_structures');
        Schema::dropIfExists('expense_claims');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('employee_document_types');
    }
};
