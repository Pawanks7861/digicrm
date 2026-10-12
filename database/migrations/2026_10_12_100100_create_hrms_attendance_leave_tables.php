<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->string('shift_type', 20)->default('fixed');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->boolean('is_overnight')->default(false);
            $table->unsignedSmallInteger('full_day_minutes')->default(480);
            $table->unsignedSmallInteger('half_day_minutes')->default(240);
            $table->unsignedSmallInteger('grace_minutes')->default(10);
            $table->string('timezone', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('employee_shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_shift_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'effective_from', 'effective_to']);
        });

        Schema::create('company_holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->string('name');
            $table->boolean('is_optional')->default(false);
            $table->timestamps();
            $table->unique(['company_id', 'branch_id', 'date']);
        });

        Schema::create('attendance_punches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('punch_type', 20);
            $table->timestamp('punched_at');
            $table->date('work_date');
            $table->string('source', 30)->default('web'); // web, mobile, import, biometric, manual
            $table->string('working_arrangement', 20)->default('office');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('idempotency_key', 80)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'idempotency_key']);
            $table->index(['employee_id', 'work_date', 'punched_at']);
            $table->index(['employee_id', 'punched_at']);
        });

        Schema::create('attendance_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->foreignId('work_shift_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 30)->default('exception');
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->unsignedInteger('break_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->boolean('is_late')->default(false);
            $table->boolean('is_early_departure')->default(false);
            $table->boolean('missing_checkout')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->string('working_arrangement', 20)->nullable();
            $table->foreignId('leave_request_id')->nullable();
            $table->json('source_snapshot')->nullable();
            $table->timestamp('derived_at')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'work_date']);
            $table->index(['company_id', 'work_date', 'status']);
        });

        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->json('proposed_punches');
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status']);
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->boolean('is_paid')->default(true);
            $table->boolean('requires_document')->default(false);
            $table->boolean('allow_negative_balance')->default(false);
            $table->boolean('counts_holidays')->default(false);
            $table->boolean('counts_weekly_offs')->default(false);
            $table->boolean('is_comp_off')->default(false);
            $table->decimal('annual_allocation', 8, 2)->nullable();
            $table->string('accrual_method', 30)->default('none'); // none, monthly, yearly
            $table->boolean('carry_forward')->default(false);
            $table->decimal('max_carry_forward', 8, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('rules')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('leave_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->string('entry_type', 30); // allocation, accrual, usage, reservation, reversal, expiry, adjustment, encashment
            $table->decimal('days', 8, 2);
            $table->decimal('balance_after', 8, 2)->nullable();
            $table->foreignId('leave_request_id')->nullable();
            $table->string('reference', 80)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('effective_at')->useCurrent();
            $table->timestamps();
            $table->unique(['company_id', 'reference']);
            $table->index(['employee_id', 'leave_type_id', 'entry_type']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('start_unit', 20)->default('full_day');
            $table->string('end_unit', 20)->default('full_day');
            $table->decimal('days', 8, 2);
            $table->string('status', 20)->default('pending');
            $table->text('reason')->nullable();
            $table->string('document_path')->nullable();
            $table->foreignId('comp_off_attendance_day_id')->nullable()->constrained('attendance_days')->nullOnDelete();
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->boolean('attendance_applied')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['employee_id', 'start_date', 'end_date']);
            $table->index(['company_id', 'status']);
        });

        Schema::table('attendance_days', function (Blueprint $table) {
            $table->foreign('leave_request_id')->references('id')->on('leave_requests')->nullOnDelete();
        });

        Schema::table('leave_ledger_entries', function (Blueprint $table) {
            $table->foreign('leave_request_id')->references('id')->on('leave_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leave_ledger_entries', function (Blueprint $table) {
            $table->dropForeign(['leave_request_id']);
        });
        Schema::table('attendance_days', function (Blueprint $table) {
            $table->dropForeign(['leave_request_id']);
        });
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_ledger_entries');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('attendance_corrections');
        Schema::dropIfExists('attendance_days');
        Schema::dropIfExists('attendance_punches');
        Schema::dropIfExists('company_holidays');
        Schema::dropIfExists('employee_shift_assignments');
        Schema::dropIfExists('work_shifts');
    }
};
