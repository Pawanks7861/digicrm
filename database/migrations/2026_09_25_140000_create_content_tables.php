<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_package_items', function (Blueprint $table) {
            $table->unsignedInteger('included_revisions')->nullable()->after('is_unlimited');
        });

        Schema::table('client_package_items', function (Blueprint $table) {
            $table->unsignedInteger('included_revisions')->nullable()->after('is_unlimited');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('can_approve_content')->default(true)->after('is_active');
        });

        Schema::table('internal_reviews', function (Blueprint $table) {
            $table->unsignedBigInteger('content_revision_id')->nullable()->after('reviewable_id');
            $table->index('content_revision_id');
        });

        Schema::create('content_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_deliverable_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('content_type', 40);
            $table->string('platform', 30)->nullable();
            $table->text('caption')->nullable();
            $table->text('description')->nullable();
            $table->text('hashtags')->nullable();
            $table->string('cta')->nullable();
            $table->string('target_url')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('published_url')->nullable();
            $table->string('status', 40)->default('draft');
            $table->unsignedBigInteger('current_revision_id')->nullable();
            $table->unsignedInteger('client_revision_cycles')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_id', 'status']);
            $table->index(['campaign_id', 'status']);
            $table->index('campaign_deliverable_id');
            $table->index('platform');
            $table->index('scheduled_at');
            $table->index('published_at');
            $table->index('content_type');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('content_item_id')->nullable()->after('campaign_deliverable_id')->constrained()->nullOnDelete();
        });

        Schema::create('content_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            $table->foreignId('client_file_id')->nullable()->constrained('client_files')->nullOnDelete();
            $table->string('original_name')->nullable();
            $table->string('path')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->text('caption_snapshot')->nullable();
            $table->text('hashtags_snapshot')->nullable();
            $table->string('cta_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('active');
            $table->boolean('is_client_cycle')->default(false);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['content_item_id', 'revision_number']);
            $table->index('content_item_id');
        });

        Schema::table('content_items', function (Blueprint $table) {
            $table->foreign('current_revision_id')->references('id')->on('content_revisions')->nullOnDelete();
        });

        Schema::create('client_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_revision_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('requested_at');
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('pending');
            $table->text('feedback')->nullable();
            $table->string('feedback_category', 40)->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status']);
            $table->index(['content_item_id', 'status']);
            $table->index('content_revision_id');
            $table->index('requested_at');
            $table->index('expires_at');
        });

        Schema::create('content_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_revision_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('comment');
            $table->string('visibility', 20)->default('internal');
            $table->string('comment_type', 20)->default('comment');
            $table->timestamps();

            $table->index(['content_item_id', 'visibility']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_comments');
        Schema::dropIfExists('client_approvals');

        Schema::table('content_items', function (Blueprint $table) {
            $table->dropForeign(['current_revision_id']);
        });

        Schema::dropIfExists('content_revisions');
        Schema::dropIfExists('content_items');

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('content_item_id');
        });

        Schema::table('internal_reviews', function (Blueprint $table) {
            $table->dropIndex(['content_revision_id']);
            $table->dropColumn('content_revision_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('can_approve_content');
        });

        Schema::table('client_package_items', function (Blueprint $table) {
            $table->dropColumn('included_revisions');
        });

        Schema::table('service_package_items', function (Blueprint $table) {
            $table->dropColumn('included_revisions');
        });
    }
};
