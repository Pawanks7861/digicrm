<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('client_portal_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('portal_enabled')->default(true);
            $table->boolean('show_campaigns')->default(true);
            $table->boolean('show_content')->default(true);
            $table->boolean('show_calendar')->default(false);
            $table->boolean('show_files')->default(true);
            $table->boolean('show_requests')->default(true);
            $table->boolean('show_approvals')->default(true);
            $table->boolean('show_package_usage')->default(false);
            $table->boolean('show_reports')->default(false);
            $table->boolean('show_invoices')->default(false);
            $table->boolean('allow_request_creation')->default(true);
            $table->boolean('allow_file_upload')->default(false);
            $table->boolean('allow_comments')->default(true);
            $table->boolean('allow_content_approval')->default(true);
            $table->timestamps();
        });

        Schema::create('client_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number', 30)->unique();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('request_type', 40);
            $table->text('description');
            $table->string('platform', 30)->nullable();
            $table->string('priority', 20)->default('normal');
            $table->date('requested_due_date')->nullable();
            $table->date('internal_due_date')->nullable();
            $table->string('status', 40)->default('submitted');
            $table->string('scope_status', 40)->default('unchecked');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_deliverable_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_package_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_package_item_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('unit', 50)->nullable();
            $table->string('reference_url')->nullable();
            $table->decimal('estimated_overage_amount', 15, 2)->nullable();
            $table->text('resolution_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamp('scope_checked_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_id', 'status']);
            $table->index('scope_status');
            $table->index('assigned_to');
            $table->index('requested_due_date');
            $table->index('priority');
        });

        Schema::create('client_request_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('comment');
            $table->string('visibility', 20)->default('internal');
            $table->timestamps();

            $table->index(['client_request_id', 'visibility']);
        });

        Schema::create('client_request_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_file_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['client_request_id', 'client_file_id']);
        });

        Schema::create('client_communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('communication_type', 30);
            $table->string('direction', 20)->default('outbound');
            $table->string('subject')->nullable();
            $table->text('notes');
            $table->timestamp('occurred_at');
            $table->boolean('follow_up_required')->default(false);
            $table->timestamp('next_follow_up_at')->nullable();
            $table->string('visibility', 20)->default('internal');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'occurred_at']);
        });

        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('followupable');
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('notes')->nullable();
            $table->string('follow_up_type', 40)->default('general');
            $table->timestamp('due_at');
            $table->string('priority', 20)->default('normal');
            $table->string('status', 20)->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('snoozed_until')->nullable();
            $table->text('outcome')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status']);
            $table->index(['assigned_to', 'due_at']);
            $table->index('due_at');
        });

        Schema::table('campaign_deliverables', function (Blueprint $table) {
            $table->foreignId('client_request_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('client_request_id')->nullable()->after('content_item_id')->constrained()->nullOnDelete();
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->foreignId('client_request_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('can_submit_requests')->default(true)->after('can_approve_content');
            $table->boolean('can_comment_portal')->default(true)->after('can_submit_requests');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['can_submit_requests', 'can_comment_portal']);
        });
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_request_id');
        });
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_request_id');
        });
        Schema::table('campaign_deliverables', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_request_id');
        });
        Schema::dropIfExists('follow_ups');
        Schema::dropIfExists('client_communications');
        Schema::dropIfExists('client_request_files');
        Schema::dropIfExists('client_request_comments');
        Schema::dropIfExists('client_requests');
        Schema::dropIfExists('client_portal_settings');
        Schema::dropIfExists('notifications');
    }
};
