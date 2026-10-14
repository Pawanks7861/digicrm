<?php

namespace App\Console\Commands;

use App\Support\MysqlSchema;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class DeployPreflightCommand extends Command
{
    protected $signature = 'deploy:preflight
                            {--expect-dir= : Absolute project path that must match getcwd()}
                            {--strict : Exit non-zero on warnings as well as failures}';

    protected $description = 'Validate DigiCRM readiness for MilesWeb/cPanel shared-hosting deploy (no secrets printed).';

    private int $failures = 0;

    private int $warnings = 0;

    public function handle(): int
    {
        $this->info('DigiCRM deploy preflight');
        $this->line('Project: '.base_path());

        $this->checkDirectory();
        $this->checkPhp();
        $this->checkExtensions();
        $this->checkEnv();
        $this->checkWritablePaths();
        $this->checkDiskSpace();
        $this->checkDatabase();
        $this->checkPartialMigrationStates();
        $this->checkFrontendArtifacts();
        $this->checkComposerAutoload();

        $this->newLine();
        if ($this->failures > 0) {
            $this->error("Preflight FAILED ({$this->failures} error(s), {$this->warnings} warning(s)).");

            return self::FAILURE;
        }

        if ($this->warnings > 0 && $this->option('strict')) {
            $this->error("Preflight FAILED under --strict ({$this->warnings} warning(s)).");

            return self::FAILURE;
        }

        $this->info("Preflight OK ({$this->warnings} warning(s)).");

        return self::SUCCESS;
    }

    private function checkDirectory(): void
    {
        $expect = $this->option('expect-dir');
        if ($expect && realpath($expect) !== realpath(base_path())) {
            $this->failMsg('Working directory mismatch. Expected '.$expect);
        } else {
            $this->ok('Project directory');
        }

        if ($this->isGitRepo() && $this->gitIsDirty()) {
            $this->warnMsg('Git working tree is dirty — commit or stash before git pull --ff-only.');
        }
    }

    private function checkPhp(): void
    {
        $version = PHP_VERSION;
        if (version_compare($version, '8.1.0', '<')) {
            $this->failMsg("PHP {$version} is too old; require ^8.1 (hosting target is 8.3).");
        } else {
            $this->ok("PHP {$version}");
        }

        if (version_compare($version, '8.3.0', '<')) {
            $this->warnMsg("CLI PHP is {$version}; MilesWeb target uses ea-php83. Align web + CLI versions in cPanel.");
        }
    }

    private function checkExtensions(): void
    {
        $required = ['pdo', 'mbstring', 'openssl', 'tokenizer', 'json', 'ctype', 'fileinfo', 'bcmath', 'xml'];
        if (in_array(config('database.default'), ['mysql', 'mariadb'], true)) {
            $required[] = 'pdo_mysql';
        }
        $missing = [];
        foreach ($required as $ext) {
            if (! extension_loaded($ext)) {
                $missing[] = $ext;
            }
        }

        if ($missing !== []) {
            $this->failMsg('Missing PHP extensions: '.implode(', ', $missing));
        } else {
            $this->ok('Required PHP extensions');
        }
    }

    private function checkEnv(): void
    {
        if (! File::exists(base_path('.env'))) {
            $this->failMsg('.env is missing. Copy .env.example only when absent, then set APP_KEY/DB_* without regenerating an existing key.');

            return;
        }

        $appKey = (string) config('app.key');
        if ($appKey === '' || $appKey === 'base64:') {
            $this->failMsg('APP_KEY is empty. Generate only for brand-new installs: php artisan key:generate');
        } else {
            $this->ok('APP_KEY present (value not shown)');
        }

        if (config('app.env') === 'production' && config('app.debug') === true) {
            $this->warnMsg('APP_DEBUG=true in production environment.');
        }

        $url = (string) config('app.url');
        if ($url === '' || str_contains($url, 'localhost')) {
            $this->warnMsg('APP_URL looks unset or localhost; production should be https://demo.buildify360.com');
        } else {
            $this->ok('APP_URL configured');
        }

        if (config('database.default') !== 'mysql' && config('database.default') !== 'mariadb') {
            $this->warnMsg('DB_CONNECTION is not mysql/mariadb (got '.config('database.default').').');
        }
    }

    private function checkWritablePaths(): void
    {
        foreach (['storage', 'storage/framework', 'storage/logs', 'bootstrap/cache'] as $path) {
            $full = base_path($path);
            if (! File::isDirectory($full)) {
                $this->failMsg("Missing directory: {$path}");
                continue;
            }
            if (! is_writable($full)) {
                $this->failMsg("Not writable: {$path} (fix ownership; do not use chmod 777)");
            }
        }
        $this->ok('storage and bootstrap/cache writable checks');
    }

    private function checkDiskSpace(): void
    {
        $free = @disk_free_space(base_path());
        if ($free !== false && $free < 200 * 1024 * 1024) {
            $this->warnMsg('Less than 200MB free disk on project volume.');
        } else {
            $this->ok('Disk space');
        }
    }

    private function checkDatabase(): void
    {
        try {
            DB::connection()->getPdo();
            $this->ok('Database connection');
        } catch (\Throwable $e) {
            $this->failMsg('Database connection failed: '.$e->getMessage());

            return;
        }

        if (MysqlSchema::isMysqlFamily()) {
            $engine = config('database.connections.mysql.engine') ?? 'InnoDB';
            if ($engine && strcasecmp((string) $engine, 'InnoDB') !== 0) {
                $this->warnMsg('MySQL engine config is not InnoDB ('.$engine.').');
            }

            try {
                $ver = DB::selectOne('SELECT VERSION() AS v');
                $this->ok('Database version: '.($ver->v ?? 'unknown'));
            } catch (\Throwable) {
                $this->warnMsg('Could not read database version.');
            }

            // Privilege smoke: CREATE TEMPORARY / SELECT on information_schema used by migrations.
            try {
                DB::select('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() LIMIT 1');
                $this->ok('information_schema readable');
            } catch (\Throwable $e) {
                $this->failMsg('Cannot read information_schema (needed for recoverable migrations): '.$e->getMessage());
            }
        }
    }

    private function checkPartialMigrationStates(): void
    {
        if (! Schema::hasTable('migrations')) {
            $this->warnMsg('migrations table missing — fresh install expected.');

            return;
        }

        $ran = collect(DB::table('migrations')->pluck('migration'));

        // Known partial: phase11 pending but extension columns already present.
        $phase11 = '2026_09_25_210000_create_phase11_performance_tables';
        if (! $ran->contains($phase11) && Schema::hasTable('marketing_metric_snapshots')) {
            if (Schema::hasColumn('marketing_metric_snapshots', 'campaign_id')
                || Schema::hasColumn('marketing_metric_snapshots', 'sync_run_id')
            ) {
                $this->warnMsg(
                    'Detected known partial phase11 state (extension columns present, migration not recorded). '.
                    'Updated migration will resume safely; take a DB backup before migrate --force.'
                );
            }

            if (MysqlSchema::hasIndex('marketing_metric_snapshots', 'mms_unique')
                && ! MysqlSchema::hasIndexLeadingWith('marketing_metric_snapshots', 'client_id')
            ) {
                // Should not happen if mms_unique exists (it leads with client_id), but keep as diagnostic.
                $this->warnMsg('mms_unique present; ensure alternate client_id index before drop (migration handles this).');
            }
        }

        // Permissions leftover without roles.
        if (Schema::hasTable('permissions') && ! Schema::hasTable('roles') && ! $ran->contains('2026_09_25_062849_create_permission_tables')) {
            $this->warnMsg('Partial permission schema detected (permissions without roles). Empty incompatible tables are repaired; populated ones abort.');
        }

        // clients partial without users FK readiness.
        if (Schema::hasTable('clients') && ! $ran->contains('2026_09_25_100100_create_clients_table')) {
            $this->warnMsg('clients table exists while create_clients migration is pending — empty partial tables are repaired; populated incompatible schemas abort.');
        }

        if (Schema::hasTable('users') && MysqlSchema::isMysqlFamily()) {
            $engine = MysqlSchema::engine('users');
            if ($engine && strcasecmp($engine, 'InnoDB') !== 0) {
                $this->warnMsg("users engine is {$engine}; migrations will convert to InnoDB before FK creation.");
            }
        }
    }

    private function checkFrontendArtifacts(): void
    {
        if (File::exists(public_path('hot'))) {
            $this->failMsg('public/hot exists — production would point Vite at a dev server. Delete public/hot after build.');
        } else {
            $this->ok('No public/hot file');
        }

        if (! File::exists(public_path('build/manifest.json'))) {
            $this->warnMsg('public/build/manifest.json missing — run npm ci --include=dev && npm run build before go-live.');
        } else {
            $this->ok('Vite manifest present');
        }
    }

    private function checkComposerAutoload(): void
    {
        if (! File::exists(base_path('vendor/autoload.php'))) {
            $this->failMsg('vendor/ missing — run composer install --no-dev --optimize-autoloader with ea-php83.');
        } else {
            $this->ok('Composer vendor present');
        }

        if (! class_exists(\Doctrine\DBAL\Connection::class) && ! interface_exists(\Doctrine\DBAL\Driver::class)) {
            // doctrine/dbal is required for some ->change() paths; phase12 now uses raw MODIFY but payroll still uses change().
            $this->warnMsg('doctrine/dbal not autoloadable; ensure production composer install includes require (not only require-dev).');
        }
    }

    private function isGitRepo(): bool
    {
        return File::isDirectory(base_path('.git'));
    }

    private function gitIsDirty(): bool
    {
        $out = [];
        $code = 0;
        @exec('git -C '.escapeshellarg(base_path()).' status --porcelain 2>&1', $out, $code);

        return $code === 0 && implode('', $out) !== '';
    }

    private function ok(string $msg): void
    {
        $this->line('<info>✓</info> '.$msg);
    }

    private function warnMsg(string $msg): void
    {
        $this->warnings++;
        $this->line('<comment>!</comment> '.$msg);
    }

    private function failMsg(string $msg): void
    {
        $this->failures++;
        $this->line('<error>×</error> '.$msg);
    }
}
