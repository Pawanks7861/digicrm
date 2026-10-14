<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_shifts', function (Blueprint $table) {
            $table->string('category', 32)->default('general')->after('name');
            $table->unsignedSmallInteger('lunch_break_minutes')->default(0)->after('break_minutes');
            $table->unsignedSmallInteger('tea_break_minutes')->default(0)->after('lunch_break_minutes');
            $table->json('weekly_off_days')->nullable()->after('grace_minutes');
            $table->boolean('overtime_eligible')->default(false)->after('weekly_off_days');
            $table->string('half_day_rule', 16)->default('hours')->after('overtime_eligible');
            $table->unsignedSmallInteger('late_half_day_cutoff_minutes')->nullable()->after('half_day_rule');
            $table->date('effective_from')->nullable()->after('late_half_day_cutoff_minutes');
        });

        Schema::table('employee_shift_assignments', function (Blueprint $table) {
            $table->foreignId('assigned_by')->nullable()->after('effective_to')->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable()->after('assigned_by');
        });

        Schema::create('department_shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_shift_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['department_id', 'effective_from'], 'dept_shift_effective_idx');
        });

        Schema::table('attendance_days', function (Blueprint $table) {
            $table->unsignedSmallInteger('scheduled_minutes')->default(0)->after('worked_minutes');
            $table->unsignedSmallInteger('elapsed_minutes')->default(0)->after('scheduled_minutes');
            $table->unsignedSmallInteger('unpaid_break_minutes')->default(0)->after('break_minutes');
            $table->unsignedSmallInteger('net_minutes')->default(0)->after('unpaid_break_minutes');
            $table->unsignedSmallInteger('late_minutes')->default(0)->after('is_late');
            $table->unsignedSmallInteger('early_minutes')->default(0)->after('is_early_departure');
        });

        Schema::table('company_holidays', function (Blueprint $table) {
            $table->string('category', 32)->default('public')->after('name');
            $table->boolean('is_paid')->default(true)->after('is_optional');
            $table->foreignId('department_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
            $table->text('description')->nullable()->after('is_paid');
            $table->boolean('is_recurring')->default(false)->after('description');
            $table->boolean('is_active')->default(true)->after('is_recurring');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->date('probation_start')->nullable()->after('exit_date');
            $table->date('probation_end')->nullable()->after('probation_start');
            $table->string('probation_status', 32)->nullable()->after('probation_end');
            $table->date('confirmation_date')->nullable()->after('probation_status');
            $table->string('confirmation_status', 32)->nullable()->after('confirmation_date');
        });

        Schema::table('leave_types', function (Blueprint $table) {
            $table->unsignedSmallInteger('min_notice_days')->default(0)->after('max_carry_forward');
            $table->unsignedSmallInteger('max_consecutive_days')->nullable()->after('min_notice_days');
            $table->boolean('allow_half_day')->default(true)->after('max_consecutive_days');
            $table->boolean('probation_eligible')->default(true)->after('allow_half_day');
        });

        Schema::table('salary_components', function (Blueprint $table) {
            $table->boolean('pf_applicable')->default(false)->after('is_taxable');
            $table->boolean('esi_applicable')->default(false)->after('pf_applicable');
            $table->boolean('prorate')->default(true)->after('esi_applicable');
            $table->string('rounding', 16)->default('half_up')->after('prorate');
            $table->boolean('show_on_payslip')->default(true)->after('rounding');
            $table->boolean('is_active')->default(true)->after('show_on_payslip');
        });

        Schema::create('overtime_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->decimal('multiplier', 8, 4)->default(1);
            $table->unsignedSmallInteger('minimum_minutes')->default(0);
            $table->unsignedSmallInteger('rounding_minutes')->default(1);
            $table->unsignedSmallInteger('max_daily_minutes')->nullable();
            $table->boolean('requires_approval')->default(true);
            $table->boolean('comp_off_allowed')->default(false);
            $table->unsignedSmallInteger('hourly_divisor_days')->default(30);
            $table->unsignedSmallInteger('hourly_divisor_hours')->default(8);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('overtime_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_day_id')->constrained()->cascadeOnDelete();
            $table->foreignId('overtime_policy_id')->nullable()->constrained()->nullOnDelete();
            $table->date('work_date');
            $table->unsignedSmallInteger('minutes')->default(0);
            $table->unsignedSmallInteger('approved_minutes')->default(0);
            $table->decimal('hourly_rate', 14, 2)->default(0);
            $table->decimal('multiplier', 8, 4)->default(1);
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('status', 32)->default('pending');
            $table->foreignId('payroll_run_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payroll_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique('attendance_day_id');
            $table->index(['company_id', 'status', 'work_date']);
        });

        Schema::create('employee_bank_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('account_name')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('ifsc', 20)->nullable();
            $table->boolean('is_primary')->default(true);
            $table->timestamps();
            $table->unique('employee_id');
        });

        Schema::create('employee_statutory_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->boolean('pf_applicable')->default(false);
            $table->string('pf_number')->nullable();
            $table->boolean('esi_applicable')->default(false);
            $table->string('esi_number')->nullable();
            $table->timestamps();
            $table->unique('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_statutory_profiles');
        Schema::dropIfExists('employee_bank_details');
        Schema::dropIfExists('overtime_entries');
        Schema::dropIfExists('overtime_policies');
        Schema::dropIfExists('department_shift_assignments');

        Schema::table('salary_components', function (Blueprint $table) {
            $table->dropColumn(['pf_applicable', 'esi_applicable', 'prorate', 'rounding', 'show_on_payslip', 'is_active']);
        });
        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn(['min_notice_days', 'max_consecutive_days', 'allow_half_day', 'probation_eligible']);
        });
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['probation_start', 'probation_end', 'probation_status', 'confirmation_date', 'confirmation_status']);
        });
        Schema::table('company_holidays', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn(['category', 'is_paid', 'description', 'is_recurring', 'is_active']);
        });
        Schema::table('attendance_days', function (Blueprint $table) {
            $table->dropColumn(['scheduled_minutes', 'elapsed_minutes', 'unpaid_break_minutes', 'net_minutes', 'late_minutes', 'early_minutes']);
        });
        Schema::table('employee_shift_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_by');
            $table->dropColumn('notes');
        });
        Schema::table('work_shifts', function (Blueprint $table) {
            $table->dropColumn([
                'category', 'lunch_break_minutes', 'tea_break_minutes', 'weekly_off_days',
                'overtime_eligible', 'half_day_rule', 'late_half_day_cutoff_minutes', 'effective_from',
            ]);
        });
    }
};
