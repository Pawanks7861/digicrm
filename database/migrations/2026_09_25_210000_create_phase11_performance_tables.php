<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_metric_snapshots', function (Blueprint $table) {
            $table->foreignId('campaign_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
            $table->foreignId('content_item_id')->nullable()->after('campaign_id')->constrained()->nullOnDelete();
            $table->foreignId('social_account_id')->nullable()->after('content_item_id')->constrained()->nullOnDelete();
            $table->foreignId('integration_connection_id')->nullable()->after('social_account_id')->constrained('integration_connections')->nullOnDelete();
            $table->string('provider', 50)->nullable()->after('source');
            $table->string('external_entity_type', 50)->nullable()->after('provider');
            $table->string('external_entity_id', 191)->nullable()->after('external_entity_type');
            $table->string('currency', 3)->nullable()->after('metric_value');
            $table->unsignedBigInteger('sync_run_id')->nullable()->after('is_manual');
            $table->json('metadata')->nullable()->after('dimensions');
        });

        // Prefer application-level upsert; keep uniqueness loose for SQLite NULL handling.
        Schema::table('marketing_metric_snapshots', function (Blueprint $table) {
            $table->dropUnique('mms_unique');
        });

        Schema::table('marketing_metric_snapshots', function (Blueprint $table) {
            $table->index(['metric_date']);
            $table->index(['client_id', 'metric_date']);
            $table->index(['campaign_id']);
            $table->index(['content_item_id']);
            $table->index(['provider', 'metric_key']);
            $table->index(['client_id', 'provider', 'external_entity_id', 'metric_key', 'metric_date', 'source'], 'mms_upsert_lookup');
        });

        Schema::create('metric_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('integration_connection_id')->nullable()->constrained('integration_connections')->nullOnDelete();
            $table->string('provider', 50);
            $table->string('sync_type', 30)->default('incremental'); // initial, incremental, backfill, manual
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->string('status', 30)->default('pending'); // pending, running, completed, failed, partial
            $table->unsignedInteger('rows_fetched')->default(0);
            $table->unsignedInteger('rows_upserted')->default(0);
            $table->unsignedInteger('rows_skipped')->default(0);
            $table->json('errors')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['client_id', 'provider', 'created_at']);
            $table->index(['status']);
        });

        Schema::table('marketing_metric_snapshots', function (Blueprint $table) {
            $table->foreign('sync_run_id')->references('id')->on('metric_sync_runs')->nullOnDelete();
        });

        Schema::create('external_marketing_entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('content_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('integration_connection_id')->nullable()->constrained('integration_connections')->nullOnDelete();
            $table->string('provider', 50);
            $table->string('entity_type', 50); // ad_campaign|ad_set|ad|ga4_property|youtube_video|social_post
            $table->string('external_id', 191);
            $table->string('external_name')->nullable();
            $table->string('status', 30)->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['client_id', 'provider', 'entity_type', 'external_id'], 'eme_unique');
            $table->index(['campaign_id']);
            $table->index(['content_item_id']);
        });

        Schema::create('campaign_kpis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('metric_code', 80);
            $table->decimal('target_value', 16, 4);
            $table->string('target_type', 20)->default('min'); // min, max, reference
            $table->string('period', 30)->default('month'); // day, week, month, campaign, custom
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['campaign_id', 'metric_code']);
        });

        Schema::create('client_performance_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('report_number', 40)->nullable();
            $table->string('status', 30)->default('draft'); // draft, internal_review, approved, published, archived
            $table->date('period_start');
            $table->date('period_end');
            $table->json('section_config')->nullable();
            $table->text('summary')->nullable();
            $table->text('notes_internal')->nullable();
            $table->string('currency', 3)->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['client_id', 'status']);
            $table->index(['period_start', 'period_end']);
        });

        Schema::create('client_report_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_performance_report_id')->constrained()->cascadeOnDelete();
            $table->string('section', 80)->nullable();
            $table->string('metric_code', 80);
            $table->string('label')->nullable();
            $table->decimal('metric_value', 16, 4)->nullable();
            $table->string('display_value')->nullable(); // frozen display incl. N/A
            $table->string('unit', 30)->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('aggregation', 30)->nullable();
            $table->string('formula_label')->nullable();
            $table->json('dimensions')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['client_performance_report_id', 'metric_code']);
        });

        Schema::create('marketing_attributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('content_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_type', 40); // direct_provider, utm_match, manual, crm_source, unknown
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('provider', 50)->nullable();
            $table->string('external_id', 191)->nullable();
            $table->date('metric_date')->nullable();
            $table->unsignedInteger('leads_count')->default(0);
            $table->unsignedInteger('conversions_count')->default(0);
            $table->decimal('conversion_value', 16, 4)->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('provenance_label')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['client_id', 'metric_date']);
            $table->index(['campaign_id']);
        });

        Schema::create('report_template_settings', function (Blueprint $table) {
            $table->id();
            $table->json('default_sections')->nullable();
            $table->text('footer_text')->nullable();
            $table->string('primary_color', 20)->nullable();
            $table->string('accent_color', 20)->nullable();
            $table->boolean('show_ad_spend_default')->default(true);
            $table->boolean('show_conversion_value_default')->default(true);
            $table->timestamps();
        });

        Schema::table('client_portal_settings', function (Blueprint $table) {
            $table->boolean('show_performance')->default(false)->after('show_reports');
            $table->boolean('show_ad_spend')->default(false)->after('show_performance');
            $table->boolean('show_conversion_value')->default(false)->after('show_ad_spend');
        });
    }

    public function down(): void
    {
        Schema::table('client_portal_settings', function (Blueprint $table) {
            $table->dropColumn(['show_performance', 'show_ad_spend', 'show_conversion_value']);
        });

        Schema::dropIfExists('report_template_settings');
        Schema::dropIfExists('marketing_attributions');
        Schema::dropIfExists('client_report_metrics');
        Schema::dropIfExists('client_performance_reports');
        Schema::dropIfExists('campaign_kpis');
        Schema::dropIfExists('external_marketing_entities');

        Schema::table('marketing_metric_snapshots', function (Blueprint $table) {
            $table->dropForeign(['sync_run_id']);
            $table->dropForeign(['campaign_id']);
            $table->dropForeign(['content_item_id']);
            $table->dropForeign(['social_account_id']);
            $table->dropForeign(['integration_connection_id']);
            $table->dropIndex('mms_upsert_lookup');
            $table->dropColumn([
                'campaign_id', 'content_item_id', 'social_account_id', 'integration_connection_id',
                'provider', 'external_entity_type', 'external_entity_id', 'currency', 'sync_run_id', 'metadata',
            ]);
            $table->unique(['client_id', 'source', 'metric_key', 'metric_date'], 'mms_unique');
        });

        Schema::dropIfExists('metric_sync_runs');
    }
};
