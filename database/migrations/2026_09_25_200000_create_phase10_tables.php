<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_connections', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50);
            $table->string('name')->nullable();
            $table->string('status', 30)->default('not_connected'); // not_connected, connected, needs_reauth, error, disabled
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_health_check_at')->nullable();
            $table->string('health_status', 30)->nullable();
            $table->text('health_message')->nullable();
            $table->text('access_token_encrypted')->nullable();
            $table->text('refresh_token_encrypted')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->text('credentials_encrypted')->nullable(); // JSON blob encrypted
            $table->json('meta')->nullable(); // non-secret display info
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
            $table->unique(['provider', 'client_id']);
        });

        Schema::create('integration_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50);
            $table->foreignId('connection_id')->nullable()->constrained('integration_connections')->nullOnDelete();
            $table->string('operation', 80);
            $table->string('status', 30); // success, failed, skipped
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->text('safe_error')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();
            $table->index(['provider', 'created_at']);
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50);
            $table->string('external_event_id', 191)->nullable();
            $table->string('event_type', 100)->nullable();
            $table->string('payload_hash', 64)->nullable();
            $table->text('payload_encrypted')->nullable();
            $table->string('status', 30)->default('received'); // received, processing, processed, failed, ignored
            $table->timestamp('received_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'external_event_id']);
        });

        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('trigger_type', 80);
            $table->string('status', 20)->default('draft'); // draft, active, disabled
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('priority')->default(100);
            $table->json('trigger_config')->nullable();
            $table->string('group_operator', 10)->default('and'); // and, or
            $table->unsignedInteger('max_actions_per_hour')->default(50);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'trigger_type']);
        });

        Schema::create('automation_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_rule_id')->constrained()->cascadeOnDelete();
            $table->string('field', 100);
            $table->string('operator', 40);
            $table->text('value')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('automation_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_rule_id')->constrained()->cascadeOnDelete();
            $table->string('action_type', 60);
            $table->json('config')->nullable();
            $table->unsignedInteger('delay_minutes')->default(0);
            $table->boolean('continue_on_match')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_rule_id')->constrained()->cascadeOnDelete();
            $table->string('trigger_type', 80);
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('status', 30)->default('pending'); // pending, running, completed, failed, skipped
            $table->unsignedTinyInteger('depth')->default(0);
            $table->json('context')->nullable();
            $table->string('idempotency_key', 191)->nullable()->unique();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error')->nullable();
            $table->boolean('dry_run')->default(false);
            $table->timestamps();
            $table->index(['automation_rule_id', 'created_at']);
        });

        Schema::create('automation_action_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('automation_action_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action_type', 60);
            $table->string('status', 30)->default('pending');
            $table->string('idempotency_key', 191)->nullable()->unique();
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('integration_connection_id')->nullable()->constrained('integration_connections')->nullOnDelete();
            $table->string('provider', 50);
            $table->string('platform', 50)->nullable();
            $table->string('external_account_id')->nullable();
            $table->string('display_name')->nullable();
            $table->string('username')->nullable();
            $table->string('status', 30)->default('active');
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['client_id', 'provider', 'external_account_id']);
        });

        Schema::create('content_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_revision_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 50)->nullable();
            $table->string('status', 30)->default('queued'); // queued, publishing, published, failed, cancelled
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('external_post_id')->nullable();
            $table->text('error')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'scheduled_at']);
        });

        Schema::create('marketing_metric_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('source', 50); // manual, google_analytics, google_ads, meta
            $table->string('metric_key', 80);
            $table->decimal('metric_value', 16, 4)->nullable();
            $table->date('metric_date');
            $table->json('dimensions')->nullable();
            $table->boolean('is_manual')->default(false);
            $table->timestamps();
            $table->unique(['client_id', 'source', 'metric_key', 'metric_date'], 'mms_unique');
        });

        Schema::table('follow_ups', function (Blueprint $table) {
            $table->string('google_calendar_event_id')->nullable()->after('outcome');
            $table->timestamp('google_calendar_synced_at')->nullable()->after('google_calendar_event_id');
            $table->boolean('sync_to_google_calendar')->default(false)->after('google_calendar_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('follow_ups', function (Blueprint $table) {
            $table->dropColumn(['google_calendar_event_id', 'google_calendar_synced_at', 'sync_to_google_calendar']);
        });
        Schema::dropIfExists('marketing_metric_snapshots');
        Schema::dropIfExists('content_publications');
        Schema::dropIfExists('social_accounts');
        Schema::dropIfExists('automation_action_runs');
        Schema::dropIfExists('automation_runs');
        Schema::dropIfExists('automation_actions');
        Schema::dropIfExists('automation_conditions');
        Schema::dropIfExists('automation_rules');
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('integration_logs');
        Schema::dropIfExists('integration_connections');
    }
};
