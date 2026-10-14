<?php

use App\Support\MysqlSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 60)->unique();
            $table->string('channel', 40)->default('other');
            $table->string('lead_category', 30)->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('lost_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 60)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('sales_pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 60)->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedTinyInteger('probability')->default(0);
            $table->boolean('is_won')->default(false);
            $table->boolean('is_lost')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('color', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('lead_capture_forms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('form_key', 64)->unique();
            $table->string('lead_category', 30);
            $table->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_source_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->json('allowed_origins')->nullable();
            $table->text('hmac_secret_encrypted')->nullable();
            $table->boolean('require_consent')->default(true);
            $table->text('consent_text')->nullable();
            $table->unsignedInteger('min_fill_seconds')->default(3);
            $table->string('honeypot_field', 60)->default('website_url');
            $table->json('fields')->nullable();
            $table->string('success_message')->nullable();
            $table->string('redirect_url', 500)->nullable();
            $table->unsignedInteger('submissions_count')->default(0);
            $table->timestamp('last_submission_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lead_form_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40)->default('meta');
            $table->foreignId('integration_connection_id')->nullable()->constrained('integration_connections')->nullOnDelete();
            $table->string('page_id', 100)->nullable();
            $table->string('form_id', 100);
            $table->string('form_name')->nullable();
            $table->string('lead_category', 30);
            $table->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_source_id')->nullable()->constrained()->nullOnDelete();
            $table->json('field_map')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['provider', 'form_id']);
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('lead_number', 30)->unique();
            $table->string('lead_category', 30);
            $table->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('prospect_client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('converted_client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('email')->nullable();
            $table->string('mobile', 40)->nullable();
            $table->string('mobile_normalized', 40)->nullable();
            $table->string('alternate_mobile', 40)->nullable();
            $table->string('industry')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('interested_service')->nullable();
            $table->text('requirement')->nullable();
            $table->decimal('estimated_budget', 15, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->foreignId('lead_source_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_detail')->nullable();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('content_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->string('priority', 20)->default('normal');
            $table->string('status', 30)->default('new');
            $table->timestamp('first_contacted_at')->nullable();
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamp('next_follow_up_at')->nullable();
            $table->timestamp('qualified_at')->nullable();
            $table->timestamp('uncontacted_alerted_at')->nullable();
            $table->foreignId('lost_reason_id')->nullable()->constrained()->nullOnDelete();
            $table->text('lost_notes')->nullable();
            $table->foreignId('possible_duplicate_of_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->string('duplicate_status', 30)->nullable();
            $table->foreignId('merged_into_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->string('provider', 40)->nullable();
            $table->string('provider_lead_id', 191)->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('gclid')->nullable();
            $table->string('fbclid')->nullable();
            $table->string('external_campaign_id', 191)->nullable();
            $table->string('external_form_id', 191)->nullable();
            $table->text('landing_page_url')->nullable();
            $table->text('referrer')->nullable();
            $table->timestamp('source_timestamp')->nullable();
            $table->boolean('consent_given')->default(false);
            $table->text('consent_text')->nullable();
            $table->timestamp('consent_at')->nullable();
            $table->foreignId('lead_capture_form_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['provider', 'provider_lead_id']);
            $table->index(['lead_category', 'client_id', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index('email');
            $table->index('mobile_normalized');
            $table->index('campaign_id');
            $table->index('created_at');
        });

        Schema::create('lead_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->foreignId('lost_reason_id')->nullable()->constrained()->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['lead_id', 'created_at']);
            $table->index(['to_status', 'created_at']);
        });

        Schema::create('lead_touchpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('touch_type', 30);
            $table->string('provenance', 30)->default('unknown');
            $table->foreignId('lead_source_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_detail')->nullable();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('content_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_capture_form_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 40)->nullable();
            $table->string('provider_lead_id', 191)->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('gclid')->nullable();
            $table->string('fbclid')->nullable();
            $table->string('external_campaign_id', 191)->nullable();
            $table->string('external_form_id', 191)->nullable();
            $table->text('landing_page_url')->nullable();
            $table->text('referrer')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['lead_id', 'occurred_at']);
        });

        Schema::create('lead_assignment_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->string('match_category', 30)->nullable();
            $table->foreignId('match_lead_source_id')->nullable()->constrained('lead_sources')->nullOnDelete();
            $table->foreignId('match_capture_form_id')->nullable()->constrained('lead_capture_forms')->nullOnDelete();
            $table->foreignId('match_campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->foreignId('match_client_id')->nullable()->constrained('clients')->cascadeOnDelete();
            $table->string('strategy', 40);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('user_pool')->nullable();
            $table->unsignedInteger('rr_pointer')->default(0);
            $table->foreignId('last_assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['is_active', 'priority']);
        });

        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->string('opportunity_number', 30)->unique();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->foreignId('sales_pipeline_stage_id')->constrained()->restrictOnDelete();
            $table->decimal('estimated_value', 15, 2)->default(0);
            $table->string('currency', 3)->default('INR');
            $table->unsignedTinyInteger('probability')->nullable();
            $table->date('expected_close_date')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('open');
            $table->timestamp('won_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->foreignId('lost_reason_id')->nullable()->constrained()->nullOnDelete();
            $table->text('lost_notes')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'sales_pipeline_stage_id']);
            $table->index('owner_id');
        });

        Schema::create('lead_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->string('visibility', 20)->default('internal');
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime', 120)->nullable();
            $table->unsignedBigInteger('attachment_size')->nullable();
            $table->timestamps();
            $table->index(['lead_id', 'created_at']);
        });

        if (Schema::hasTable('quotations')) {
            if (! Schema::hasColumn('quotations', 'lead_id')) {
                Schema::table('quotations', function (Blueprint $table) {
                    $table->foreignId('lead_id')->nullable()->after('client_request_id')->constrained()->nullOnDelete();
                });
            }
            if (! Schema::hasColumn('quotations', 'opportunity_id')) {
                Schema::table('quotations', function (Blueprint $table) {
                    $table->foreignId('opportunity_id')->nullable()->after('lead_id')->constrained()->nullOnDelete();
                });
            }
        }

        // Drop FK → MODIFY → re-add FK (change() alone fails on MySQL with active FKs).
        MysqlSchema::makeClientIdNullable('follow_ups');
        MysqlSchema::makeClientIdNullable('client_communications');

        if (Schema::hasTable('client_communications') && ! Schema::hasColumn('client_communications', 'communicable_type')) {
            Schema::table('client_communications', function (Blueprint $table) {
                $table->nullableMorphs('communicable');
            });
        }

        if (Schema::hasTable('client_portal_settings')) {
            foreach ([
                'show_leads',
                'show_lead_contact_details',
                'show_lead_source',
                'show_lead_reports',
                'allow_lead_updates',
            ] as $flag) {
                if (! Schema::hasColumn('client_portal_settings', $flag)) {
                    Schema::table('client_portal_settings', function (Blueprint $table) use ($flag) {
                        $table->boolean($flag)->default(false);
                    });
                }
            }
        }

        (new \Database\Seeders\LeadDefaultsSeeder)->run();
    }

    public function down(): void
    {
        if (Schema::hasTable('client_portal_settings')) {
            $drop = array_values(array_filter(
                ['show_leads', 'show_lead_contact_details', 'show_lead_source', 'show_lead_reports', 'allow_lead_updates'],
                static fn ($c) => Schema::hasColumn('client_portal_settings', $c)
            ));
            if ($drop !== []) {
                Schema::table('client_portal_settings', function (Blueprint $table) use ($drop) {
                    $table->dropColumn($drop);
                });
            }
        }

        if (Schema::hasTable('client_communications') && Schema::hasColumn('client_communications', 'communicable_type')) {
            Schema::table('client_communications', function (Blueprint $table) {
                $table->dropMorphs('communicable');
            });
        }

        // Revert nullable client_id only when no orphan nulls would violate NOT NULL.
        // Leave columns nullable on rollback to avoid data loss; document as known limitation.

        if (Schema::hasTable('quotations')) {
            if (Schema::hasColumn('quotations', 'opportunity_id')) {
                Schema::table('quotations', function (Blueprint $table) {
                    $table->dropConstrainedForeignId('opportunity_id');
                });
            }
            if (Schema::hasColumn('quotations', 'lead_id')) {
                Schema::table('quotations', function (Blueprint $table) {
                    $table->dropConstrainedForeignId('lead_id');
                });
            }
        }

        Schema::dropIfExists('lead_notes');
        Schema::dropIfExists('opportunities');
        Schema::dropIfExists('lead_assignment_rules');
        Schema::dropIfExists('lead_touchpoints');
        Schema::dropIfExists('lead_status_histories');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('lead_form_mappings');
        Schema::dropIfExists('lead_capture_forms');
        Schema::dropIfExists('sales_pipeline_stages');
        Schema::dropIfExists('lost_reasons');
        Schema::dropIfExists('lead_sources');
    }
};
