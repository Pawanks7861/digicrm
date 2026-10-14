<?php

/**
 * Disposable MySQL migration verification for DigiCRM.
 * Invoked inside a PHP container with DB_* env pointing at a throwaway server.
 *
 * Usage (example):
 *   php scripts/mysql-migration-verify.php
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\MysqlSchema;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
/** @var Kernel $kernel */
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

function step(string $msg): void
{
    echo "==> {$msg}\n";
}

function migrateForce(): void
{
    global $kernel;
    $code = $kernel->call('migrate', ['--force' => true]);
    if ($code !== 0) {
        throw new RuntimeException('migrate failed with code '.$code);
    }
}

function resetDatabase(string $name): void
{
    DB::statement('DROP DATABASE IF EXISTS `'.$name.'`');
    DB::statement('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    config(['database.connections.mysql.database' => $name]);
    DB::purge('mysql');
    DB::reconnect('mysql');
    DB::statement('USE `'.$name.'`');
}

if (! MysqlSchema::isMysqlFamily()) {
    fwrite(STDERR, "SKIP: DB_CONNECTION is not mysql/mariadb\n");
    exit(0);
}

$base = 'digicrm_verify_'.getmypid();

// --- A: fresh + rerun ---
step('A: fresh migrate + rerun');
resetDatabase($base.'_a');
migrateForce();
migrateForce();
if (! Schema::hasTable('marketing_metric_snapshots') || ! Schema::hasTable('metric_sync_runs')) {
    throw new RuntimeException('A failed: expected phase11 tables');
}
if (MysqlSchema::hasIndex('marketing_metric_snapshots', 'mms_unique')) {
    throw new RuntimeException('A failed: mms_unique should be dropped after phase11');
}

// --- B: empty MyISAM permissions leftover ---
step('B: empty incompatible permissions');
resetDatabase($base.'_b');
DB::statement('CREATE TABLE permissions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(191) NOT NULL,
  guard_name VARCHAR(191) NOT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
) ENGINE=MyISAM');
migrateForce();
if (! Schema::hasTable('roles') || ! Schema::hasTable('model_has_roles')) {
    throw new RuntimeException('B failed: roles tables missing');
}
if (strcasecmp((string) MysqlSchema::engine('permissions'), 'InnoDB') !== 0) {
    throw new RuntimeException('B failed: permissions not InnoDB');
}
if (! MysqlSchema::hasUnique('permissions', ['name', 'guard_name'])) {
    throw new RuntimeException('B failed: unique(name,guard_name) missing');
}

// --- C: users MyISAM before clients FK ---
step('C: users MyISAM then full migrate');
resetDatabase($base.'_c');
$kernel->call('migrate', [
    '--path' => 'database/migrations/2014_10_12_000000_create_users_table.php',
    '--force' => true,
]);
DB::statement('ALTER TABLE users ENGINE=MyISAM');
migrateForce();
if (strcasecmp((string) MysqlSchema::engine('users'), 'InnoDB') !== 0) {
    throw new RuntimeException('C failed: users still not InnoDB');
}
if (! Schema::hasTable('clients')) {
    throw new RuntimeException('C failed: clients missing');
}

// --- D: partial phase11 ---
step('D: partial phase11 recovery with data');
resetDatabase($base.'_d');
// Migrate everything before phase11
$files = glob(base_path('database/migrations/*.php'));
sort($files);
foreach ($files as $file) {
    $name = basename($file, '.php');
    if (str_contains($name, '210000_create_phase11')) {
        break;
    }
    $code = $kernel->call('migrate', [
        '--path' => 'database/migrations/'.basename($file),
        '--force' => true,
    ]);
    if ($code !== 0) {
        throw new RuntimeException('D setup migrate failed on '.$name);
    }
}

DB::table('users')->insert([
    'name' => 'Tester',
    'email' => 'partial@example.com',
    'password' => bcrypt('password'),
    'created_at' => now(),
    'updated_at' => now(),
]);
$userId = (int) DB::table('users')->where('email', 'partial@example.com')->value('id');
DB::table('clients')->insert([
    'company_name' => 'Partial Co',
    'status' => 'active',
    'created_at' => now(),
    'updated_at' => now(),
]);
$clientId = (int) DB::table('clients')->where('company_name', 'Partial Co')->value('id');
DB::table('marketing_metric_snapshots')->insert([
    'client_id' => $clientId,
    'source' => 'manual',
    'metric_key' => 'impressions',
    'metric_value' => 100,
    'metric_date' => '2026-01-01',
    'is_manual' => 1,
    'created_at' => now(),
    'updated_at' => now(),
]);

// Simulate first successful Schema::table block (columns + FKs), leave mms_unique
Schema::table('marketing_metric_snapshots', function (Blueprint $table) {
    $table->unsignedBigInteger('campaign_id')->nullable()->after('client_id');
    $table->unsignedBigInteger('content_item_id')->nullable()->after('campaign_id');
    $table->unsignedBigInteger('social_account_id')->nullable()->after('content_item_id');
    $table->unsignedBigInteger('integration_connection_id')->nullable()->after('social_account_id');
    $table->string('provider', 50)->nullable()->after('source');
    $table->string('external_entity_type', 50)->nullable()->after('provider');
    $table->string('external_entity_id', 191)->nullable()->after('external_entity_type');
    $table->string('currency', 3)->nullable()->after('metric_value');
    $table->unsignedBigInteger('sync_run_id')->nullable()->after('is_manual');
    $table->json('metadata')->nullable()->after('dimensions');
});
Schema::table('marketing_metric_snapshots', function (Blueprint $table) {
    $table->foreign('campaign_id')->references('id')->on('campaigns')->nullOnDelete();
    $table->foreign('content_item_id')->references('id')->on('content_items')->nullOnDelete();
    $table->foreign('social_account_id')->references('id')->on('social_accounts')->nullOnDelete();
    $table->foreign('integration_connection_id')->references('id')->on('integration_connections')->nullOnDelete();
});

if (! MysqlSchema::hasIndex('marketing_metric_snapshots', 'mms_unique')) {
    throw new RuntimeException('D setup failed: expected mms_unique still present');
}

migrateForce();

$count = (int) DB::table('marketing_metric_snapshots')->count();
if ($count !== 1) {
    throw new RuntimeException('D failed: expected 1 snapshot row, got '.$count);
}
if (MysqlSchema::hasIndex('marketing_metric_snapshots', 'mms_unique')) {
    throw new RuntimeException('D failed: mms_unique still present');
}
if (! Schema::hasTable('metric_sync_runs') || ! Schema::hasColumn('client_portal_settings', 'show_performance')) {
    throw new RuntimeException('D failed: phase11 incomplete');
}

// --- E: completed re-run ---
step('E: migrate with nothing pending');
migrateForce();

// --- F: incompatible permissions with rows ---
step('F: refuse populated incompatible permissions');
resetDatabase($base.'_f');
DB::statement('CREATE TABLE permissions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(191) NOT NULL,
  guard_name VARCHAR(191) NOT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
) ENGINE=MyISAM');
DB::table('permissions')->insert([
    'name' => 'x',
    'guard_name' => 'web',
    'created_at' => now(),
    'updated_at' => now(),
]);
$refused = false;
$message = '';
try {
    $code = $kernel->call('migrate', ['--force' => true]);
    $message = 'exit='.$code;
    $refused = $code !== 0 && ! Schema::hasTable('roles');
} catch (Throwable $e) {
    $message = $e->getMessage();
    $refused = str_contains($message, 'Migration blocked')
        || str_contains($message, 'incompatible')
        || ! Schema::hasTable('roles');
    echo '   caught: '.$message."\n";
}
if (! $refused || Schema::hasTable('roles')) {
    throw new RuntimeException('F failed: expected refusal for populated incompatible permissions ('.$message.')');
}
echo "   refused as expected\n";

step('ALL SCENARIOS PASSED');
exit(0);
