<?php

use App\Support\MysqlSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // InnoDB required for foreign keys to/from users (shared hosts may default MyISAM).
        MysqlSchema::ensureInnoDB('users');

        if (Schema::hasTable('clients')) {
            $this->recoverOrValidateClientsTable();
        } else {
            $this->createClientsTable();
        }

        MysqlSchema::ensureInnoDB('clients');

        if (! MysqlSchema::hasForeignKey('users', 'client_id', 'clients')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('client_id')->references('id')->on('clients')->nullOnDelete();
            });
        }
    }

    private function createClientsTable(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('mobile', 30)->nullable();
            $table->string('alternate_mobile', 30)->nullable();
            $table->string('website')->nullable();
            $table->string('industry')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable()->default('India');
            $table->string('gst_number', 30)->nullable();
            $table->string('pan', 20)->nullable();
            $table->json('social_urls')->nullable();
            $table->foreignId('account_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('renewal_date')->nullable();
            $table->string('status')->default('prospect');
            $table->text('notes')->nullable();
            $table->string('logo_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('company_name');
            $table->index('account_manager_id');
        });
    }

    private function recoverOrValidateClientsTable(): void
    {
        $required = ['id', 'company_name', 'account_manager_id', 'status'];
        $compatible = Schema::hasColumns('clients', $required);

        if ($compatible) {
            // Ensure FK to users exists when possible.
            if (
                MysqlSchema::isMysqlFamily()
                && ! MysqlSchema::hasForeignKey('clients', 'account_manager_id', 'users')
                && Schema::hasColumn('clients', 'account_manager_id')
            ) {
                MysqlSchema::ensureInnoDB('users');
                Schema::table('clients', function (Blueprint $table) {
                    $table->foreign('account_manager_id')->references('id')->on('users')->nullOnDelete();
                });
            }

            return;
        }

        if (MysqlSchema::rowCount('clients') > 0) {
            throw new \RuntimeException(
                'Migration blocked: `clients` exists with an incompatible/partial schema and contains rows. '.
                'Do not drop it automatically. Restore from backup or reconcile columns manually.'
            );
        }

        // Empty partial table left by errno 150 — safe to recreate.
        Schema::drop('clients');
        $this->createClientsTable();
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'client_id')) {
            MysqlSchema::dropForeignKeyOnColumn('users', 'client_id');
        }

        Schema::dropIfExists('clients');
    }
};
