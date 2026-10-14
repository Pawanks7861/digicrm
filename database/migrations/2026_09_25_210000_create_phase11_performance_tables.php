<?php

use App\Support\MysqlSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11 performance tables.
 *
 * Recoverable against the known partial state where columns/FKs were added to
 * marketing_metric_snapshots but dropUnique(mms_unique) failed because it was
 * the only index supporting marketing_metric_snapshots_client_id_foreign.
 *
 * Each step checks actual schema; presence of one column is never treated as
 * proof that later operations completed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketing_metric_snapshots')) {
            throw new \RuntimeException(
                'Migration blocked: marketing_metric_snapshots is missing. '.
                'Run earlier migrations (phase10) first.'
            );
        }

        $this->assertCompatibleBaseSnapshotsTable();
        $this->addSnapshotExtensionColumns();
        $this->replaceMmsUniqueSafely();
        $this->ensureSnapshotSecondaryIndexes();
        $this->ensureMetricSyncRunsTable();
        $this->ensureSyncRunForeignKey();
        $this->ensureExternalMarketingEntitiesTable();
        $this->ensureCampaignKpisTable();
        $this->ensureClientPerformanceReportsTable();
        $this->ensureClientReportMetricsTable();
        $this->ensureMarketingAttributionsTable();
        $this->ensureReportTemplateSettingsTable();
        $this->ensurePortalPerformanceFlags();
    }

    private function assertCompatibleBaseSnapshotsTable(): void
    {
        $required = ['id', 'client_id', 'source', 'metric_key', 'metric_value', 'metric_date'];
        if (! Schema::hasColumns('marketing_metric_snapshots', $required)) {
            throw new \RuntimeException(
                'Migration blocked: marketing_metric_snapshots exists but is missing required base columns '.
                '('.implode(', ', $required).'). Manual schema reconciliation required.'
            );
        }
    }

    private function addSnapshotExtensionColumns(): void
    {
        $table = 'marketing_metric_snapshots';

        $definitions = [
            'campaign_id' => function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('campaign_id')->nullable()->after('client_id');
            },
            'content_item_id' => function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('content_item_id')->nullable()->after('campaign_id');
            },
            'social_account_id' => function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('social_account_id')->nullable()->after('content_item_id');
            },
            'integration_connection_id' => function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('integration_connection_id')->nullable()->after('social_account_id');
            },
            'provider' => function (Blueprint $blueprint) {
                $blueprint->string('provider', 50)->nullable()->after('source');
            },
            'external_entity_type' => function (Blueprint $blueprint) {
                $blueprint->string('external_entity_type', 50)->nullable()->after('provider');
            },
            'external_entity_id' => function (Blueprint $blueprint) {
                $blueprint->string('external_entity_id', 191)->nullable()->after('external_entity_type');
            },
            'currency' => function (Blueprint $blueprint) {
                $blueprint->string('currency', 3)->nullable()->after('metric_value');
            },
            'sync_run_id' => function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('sync_run_id')->nullable()->after('is_manual');
            },
            'metadata' => function (Blueprint $blueprint) {
                $blueprint->json('metadata')->nullable()->after('dimensions');
            },
        ];

        foreach ($definitions as $column => $callback) {
            if (! Schema::hasColumn($table, $column)) {
                Schema::table($table, $callback);
            }
        }

        $this->ensureNullableFk($table, 'campaign_id', 'campaigns');
        $this->ensureNullableFk($table, 'content_item_id', 'content_items');
        $this->ensureNullableFk($table, 'social_account_id', 'social_accounts');
        $this->ensureNullableFk($table, 'integration_connection_id', 'integration_connections');
    }

    private function ensureNullableFk(string $table, string $column, string $referenced): void
    {
        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        if (MysqlSchema::hasForeignKey($table, $column, $referenced)) {
            return;
        }

        if (! Schema::hasTable($referenced)) {
            throw new \RuntimeException(
                "Migration blocked: cannot add FK {$table}.{$column} → {$referenced} because `{$referenced}` is missing."
            );
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $referenced) {
            $blueprint->foreign($column)->references('id')->on($referenced)->nullOnDelete();
        });
    }

    /**
     * Ensure a dedicated client_id index exists before dropping mms_unique,
     * which InnoDB may be using as the sole supporting index for the client_id FK.
     */
    private function replaceMmsUniqueSafely(): void
    {
        $table = 'marketing_metric_snapshots';

        if (! MysqlSchema::hasIndex($table, 'mms_unique')) {
            return;
        }

        if (! $this->hasAlternateClientIdLeadingIndex($table)) {
            MysqlSchema::ensureIndex($table, 'mms_client_id_idx', ['client_id']);
        }

        MysqlSchema::dropUniqueIfExists($table, 'mms_unique');
    }

    private function hasAlternateClientIdLeadingIndex(string $table): bool
    {
        if (! MysqlSchema::isMysqlFamily()) {
            foreach ([
                'mms_client_id_idx',
                'marketing_metric_snapshots_client_id_index',
                'marketing_metric_snapshots_client_id_metric_date_index',
            ] as $name) {
                if (MysqlSchema::hasIndex($table, $name)) {
                    return true;
                }
            }

            return false;
        }

        $rows = \Illuminate\Support\Facades\DB::select(
            'SELECT INDEX_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND SEQ_IN_INDEX = 1',
            [$table, 'client_id']
        );

        foreach ($rows as $row) {
            if (($row->INDEX_NAME ?? '') !== 'mms_unique') {
                return true;
            }
        }

        return false;
    }

    private function ensureSnapshotSecondaryIndexes(): void
    {
        $table = 'marketing_metric_snapshots';

        MysqlSchema::ensureIndex($table, 'marketing_metric_snapshots_metric_date_index', ['metric_date']);
        MysqlSchema::ensureIndex($table, 'marketing_metric_snapshots_client_id_metric_date_index', ['client_id', 'metric_date']);
        MysqlSchema::ensureIndex($table, 'marketing_metric_snapshots_campaign_id_index', ['campaign_id']);
        MysqlSchema::ensureIndex($table, 'marketing_metric_snapshots_content_item_id_index', ['content_item_id']);
        MysqlSchema::ensureIndex($table, 'marketing_metric_snapshots_provider_metric_key_index', ['provider', 'metric_key']);

        // Keep upsert lookup under common 767/3072 utf8mb4 limits by using a shorter key set.
        // Full uniqueness remains application-level (as before).
        if (! MysqlSchema::hasIndex($table, 'mms_upsert_lookup')) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->index(
                    ['client_id', 'provider', 'metric_key', 'metric_date', 'source'],
                    'mms_upsert_lookup'
                );
            });
        }
    }

    private function ensureMetricSyncRunsTable(): void
    {
        if (Schema::hasTable('metric_sync_runs')) {
            $this->assertTableHasColumns('metric_sync_runs', [
                'id', 'client_id', 'integration_connection_id', 'provider', 'status',
            ]);
            MysqlSchema::ensureInnoDB('metric_sync_runs');

            return;
        }

        Schema::create('metric_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('integration_connection_id')->nullable()->constrained('integration_connections')->nullOnDelete();
            $table->string('provider', 50);
            $table->string('sync_type', 30)->default('incremental');
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->string('status', 30)->default('pending');
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
    }

    private function ensureSyncRunForeignKey(): void
    {
        if (! Schema::hasColumn('marketing_metric_snapshots', 'sync_run_id')) {
            throw new \RuntimeException('sync_run_id missing on marketing_metric_snapshots after column ensure step.');
        }

        if (MysqlSchema::hasForeignKey('marketing_metric_snapshots', 'sync_run_id', 'metric_sync_runs')) {
            return;
        }

        Schema::table('marketing_metric_snapshots', function (Blueprint $table) {
            $table->foreign('sync_run_id')->references('id')->on('metric_sync_runs')->nullOnDelete();
        });
    }

    private function ensureExternalMarketingEntitiesTable(): void
    {
        if (Schema::hasTable('external_marketing_entities')) {
            $this->assertTableHasColumns('external_marketing_entities', [
                'id', 'client_id', 'provider', 'entity_type', 'external_id',
            ]);

            return;
        }

        Schema::create('external_marketing_entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('content_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('integration_connection_id')->nullable()->constrained('integration_connections')->nullOnDelete();
            $table->string('provider', 50);
            $table->string('entity_type', 50);
            $table->string('external_id', 191);
            $table->string('external_name')->nullable();
            $table->string('status', 30)->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['client_id', 'provider', 'entity_type', 'external_id'], 'eme_unique');
            $table->index(['campaign_id']);
            $table->index(['content_item_id']);
        });
    }

    private function ensureCampaignKpisTable(): void
    {
        if (Schema::hasTable('campaign_kpis')) {
            $this->assertTableHasColumns('campaign_kpis', ['id', 'client_id', 'campaign_id', 'metric_code']);

            return;
        }

        Schema::create('campaign_kpis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('metric_code', 80);
            $table->decimal('target_value', 16, 4);
            $table->string('target_type', 20)->default('min');
            $table->string('period', 30)->default('month');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['campaign_id', 'metric_code']);
        });
    }

    private function ensureClientPerformanceReportsTable(): void
    {
        if (Schema::hasTable('client_performance_reports')) {
            $this->assertTableHasColumns('client_performance_reports', [
                'id', 'client_id', 'title', 'status', 'period_start', 'period_end',
            ]);

            return;
        }

        Schema::create('client_performance_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('report_number', 40)->nullable();
            $table->string('status', 30)->default('draft');
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
    }

    private function ensureClientReportMetricsTable(): void
    {
        if (Schema::hasTable('client_report_metrics')) {
            $this->assertTableHasColumns('client_report_metrics', [
                'id', 'client_performance_report_id', 'metric_code',
            ]);

            return;
        }

        Schema::create('client_report_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_performance_report_id')->constrained()->cascadeOnDelete();
            $table->string('section', 80)->nullable();
            $table->string('metric_code', 80);
            $table->string('label')->nullable();
            $table->decimal('metric_value', 16, 4)->nullable();
            $table->string('display_value')->nullable();
            $table->string('unit', 30)->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('aggregation', 30)->nullable();
            $table->string('formula_label')->nullable();
            $table->json('dimensions')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['client_performance_report_id', 'metric_code'], 'crm_report_metric_code_idx');
        });
    }

    private function ensureMarketingAttributionsTable(): void
    {
        if (Schema::hasTable('marketing_attributions')) {
            $this->assertTableHasColumns('marketing_attributions', [
                'id', 'client_id', 'source_type',
            ]);

            return;
        }

        Schema::create('marketing_attributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('content_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_type', 40);
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
    }

    private function ensureReportTemplateSettingsTable(): void
    {
        if (Schema::hasTable('report_template_settings')) {
            return;
        }

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
    }

    private function ensurePortalPerformanceFlags(): void
    {
        if (! Schema::hasTable('client_portal_settings')) {
            throw new \RuntimeException(
                'Migration blocked: client_portal_settings missing; cannot add performance portal flags.'
            );
        }

        $columns = [
            'show_performance' => function (Blueprint $blueprint) {
                $blueprint->boolean('show_performance')->default(false)->after('show_reports');
            },
            'show_ad_spend' => function (Blueprint $blueprint) {
                $blueprint->boolean('show_ad_spend')->default(false)->after('show_performance');
            },
            'show_conversion_value' => function (Blueprint $blueprint) {
                $blueprint->boolean('show_conversion_value')->default(false)->after('show_ad_spend');
            },
        ];

        foreach ($columns as $name => $callback) {
            if (! Schema::hasColumn('client_portal_settings', $name)) {
                Schema::table('client_portal_settings', $callback);
            }
        }
    }

    private function assertTableHasColumns(string $table, array $columns): void
    {
        $missing = array_values(array_filter($columns, static fn ($c) => ! Schema::hasColumn($table, $c)));
        if ($missing !== []) {
            throw new \RuntimeException(
                "Migration blocked: `{$table}` exists but is missing columns: ".implode(', ', $missing).
                '. Manual reconciliation required; refusing to mark migration complete.'
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('client_portal_settings')) {
            $drop = array_values(array_filter(
                ['show_performance', 'show_ad_spend', 'show_conversion_value'],
                static fn ($c) => Schema::hasColumn('client_portal_settings', $c)
            ));
            if ($drop !== []) {
                Schema::table('client_portal_settings', function (Blueprint $table) use ($drop) {
                    $table->dropColumn($drop);
                });
            }
        }

        Schema::dropIfExists('report_template_settings');
        Schema::dropIfExists('marketing_attributions');
        Schema::dropIfExists('client_report_metrics');
        Schema::dropIfExists('client_performance_reports');
        Schema::dropIfExists('campaign_kpis');
        Schema::dropIfExists('external_marketing_entities');

        if (Schema::hasTable('marketing_metric_snapshots')) {
            MysqlSchema::dropForeignKeyOnColumn('marketing_metric_snapshots', 'sync_run_id');
            MysqlSchema::dropForeignKeyOnColumn('marketing_metric_snapshots', 'campaign_id');
            MysqlSchema::dropForeignKeyOnColumn('marketing_metric_snapshots', 'content_item_id');
            MysqlSchema::dropForeignKeyOnColumn('marketing_metric_snapshots', 'social_account_id');
            MysqlSchema::dropForeignKeyOnColumn('marketing_metric_snapshots', 'integration_connection_id');

            foreach ([
                'mms_upsert_lookup',
                'marketing_metric_snapshots_metric_date_index',
                'marketing_metric_snapshots_client_id_metric_date_index',
                'marketing_metric_snapshots_campaign_id_index',
                'marketing_metric_snapshots_content_item_id_index',
                'marketing_metric_snapshots_provider_metric_key_index',
                'mms_client_id_idx',
            ] as $index) {
                MysqlSchema::dropIndexIfExists('marketing_metric_snapshots', $index);
            }

            $columns = array_values(array_filter([
                'campaign_id', 'content_item_id', 'social_account_id', 'integration_connection_id',
                'provider', 'external_entity_type', 'external_entity_id', 'currency', 'sync_run_id', 'metadata',
            ], static fn ($c) => Schema::hasColumn('marketing_metric_snapshots', $c)));

            if ($columns !== []) {
                Schema::table('marketing_metric_snapshots', function (Blueprint $table) use ($columns) {
                    $table->dropColumn($columns);
                });
            }

            // Restore mms_unique only after client_id-supporting indexes from phase11 are gone;
            // client_id FK still needs an index — recreate unique which leads with client_id.
            if (! MysqlSchema::hasIndex('marketing_metric_snapshots', 'mms_unique')) {
                // Ensure FK still has index coverage while unique is recreated.
                if (! MysqlSchema::hasIndexLeadingWith('marketing_metric_snapshots', 'client_id')) {
                    MysqlSchema::ensureIndex('marketing_metric_snapshots', 'mms_client_id_tmp', ['client_id']);
                }

                Schema::table('marketing_metric_snapshots', function (Blueprint $table) {
                    $table->unique(['client_id', 'source', 'metric_key', 'metric_date'], 'mms_unique');
                });

                MysqlSchema::dropIndexIfExists('marketing_metric_snapshots', 'mms_client_id_tmp');
            }
        }

        Schema::dropIfExists('metric_sync_runs');
    }
};
