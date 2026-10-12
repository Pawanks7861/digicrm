<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('email_enabled')->default(true);
            $table->boolean('push_enabled')->default(true);
            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('sounds_enabled_default')->default(true);
            $table->unsignedTinyInteger('quiet_hours_start')->nullable(); // 0-23
            $table->unsignedTinyInteger('quiet_hours_end')->nullable();
            $table->string('default_timezone')->default('Asia/Kolkata');
            $table->json('invoice_reminder_offsets')->nullable(); // e.g. [-3,0,1,7] days
            $table->json('renewal_reminder_offsets')->nullable(); // e.g. [30,15,7,0]
            $table->json('task_reminder_offsets')->nullable();
            $table->json('followup_reminder_offsets')->nullable();
            $table->boolean('digest_enabled')->default(true);
            $table->string('digest_time', 5)->default('08:00');
            $table->string('mail_from_name')->nullable();
            $table->string('mail_from_address')->nullable();
            $table->text('smtp_host')->nullable();
            $table->unsignedInteger('smtp_port')->nullable();
            $table->string('smtp_encryption')->nullable();
            $table->string('smtp_username')->nullable();
            $table->text('smtp_password_encrypted')->nullable();
            $table->string('vapid_public_key')->nullable();
            $table->text('vapid_private_key_encrypted')->nullable();
            $table->string('vapid_subject')->nullable();
            $table->timestamp('last_test_email_at')->nullable();
            $table->timestamp('last_test_push_at')->nullable();
            $table->timestamp('last_scheduler_run_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 40);
            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('email_enabled')->default(true);
            $table->boolean('push_enabled')->default(true);
            $table->boolean('sound_enabled')->default(true);
            $table->string('digest_mode', 20)->default('immediate'); // immediate, daily, off
            $table->timestamps();
            $table->unique(['user_id', 'category']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notifications_paused')->default(false)->after('can_comment_portal');
            $table->timestamp('notifications_paused_until')->nullable()->after('notifications_paused');
            $table->boolean('quiet_hours_enabled')->default(false)->after('notifications_paused_until');
            $table->unsignedTinyInteger('quiet_hours_start')->nullable()->after('quiet_hours_enabled');
            $table->unsignedTinyInteger('quiet_hours_end')->nullable()->after('quiet_hours_start');
            $table->string('notification_timezone')->nullable()->after('quiet_hours_end');
            $table->boolean('notification_sounds_enabled')->default(true)->after('notification_timezone');
            $table->boolean('can_view_finance')->default(false)->after('can_comment_portal');
            $table->boolean('can_receive_finance_notifications')->default(false)->after('can_view_finance');
            $table->boolean('can_accept_quotations')->default(false)->after('can_receive_finance_notifications');
        });

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            $table->string('endpoint_hash', 64);
            $table->text('p256dh');
            $table->text('auth');
            $table->string('content_encoding')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('device_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'endpoint_hash']);
            $table->index(['user_id', 'is_active']);
        });

        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('category', 40);
            $table->string('name');
            $table->string('subject');
            $table->string('heading')->nullable();
            $table->text('body');
            $table->string('cta_label')->nullable();
            $table->string('footer')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(true);
            $table->timestamps();
        });

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->uuid('notification_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 20);
            $table->string('category', 40)->nullable();
            $table->string('event_key', 80)->nullable();
            $table->string('destination')->nullable();
            $table->string('status', 20)->default('queued');
            $table->string('dedupe_key')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'channel', 'created_at']);
            $table->unique('dedupe_key');
        });

        Schema::create('notification_digests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('digest_date');
            $table->json('items');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'digest_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_digests');
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('push_subscriptions');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'notifications_paused', 'notifications_paused_until',
                'quiet_hours_enabled', 'quiet_hours_start', 'quiet_hours_end',
                'notification_timezone', 'notification_sounds_enabled',
                'can_view_finance', 'can_receive_finance_notifications', 'can_accept_quotations',
            ]);
        });
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notification_settings');
    }
};
