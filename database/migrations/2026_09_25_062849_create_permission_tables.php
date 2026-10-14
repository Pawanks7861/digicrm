<?php

use App\Support\MysqlSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Recovers empty incompatible leftover `permissions` tables (e.g. MyISAM,
     * missing unique on name+guard_name) seen on shared hosting. Populated
     * incompatible tables abort with an actionable error.
     */
    public function up(): void
    {
        $teams = config('permission.teams');
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $pivotRole = $columnNames['role_pivot_key'] ?? 'role_id';
        $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';

        if (empty($tableNames)) {
            throw new \Exception('Error: config/permission.php not loaded. Run [php artisan config:clear] and try again.');
        }
        if ($teams && empty($columnNames['team_foreign_key'] ?? null)) {
            throw new \Exception('Error: team_foreign_key on config/permission.php not loaded. Run [php artisan config:clear] and try again.');
        }

        $this->ensurePermissionsTable($tableNames['permissions']);
        $this->ensureRolesTable($tableNames['roles'], $teams, $columnNames);
        $this->ensureModelHasPermissions($tableNames, $columnNames, $pivotPermission, $teams);
        $this->ensureModelHasRoles($tableNames, $columnNames, $pivotRole, $teams);
        $this->ensureRoleHasPermissions($tableNames, $pivotRole, $pivotPermission);

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    private function ensurePermissionsTable(string $table): void
    {
        if (! Schema::hasTable($table)) {
            Schema::create($table, function (Blueprint $blueprint) {
                $blueprint->bigIncrements('id');
                $blueprint->string('name', 125);
                $blueprint->string('guard_name', 125);
                $blueprint->timestamps();
                $blueprint->unique(['name', 'guard_name']);
            });
            MysqlSchema::ensureInnoDB($table);

            return;
        }

        if ($this->permissionsSchemaIsCompatible($table)) {
            MysqlSchema::ensureInnoDB($table);

            return;
        }

        if (MysqlSchema::rowCount($table) > 0) {
            throw new \RuntimeException(
                "Migration blocked: `{$table}` exists with an incompatible schema and contains rows. ".
                'Expected InnoDB (or convertible engine), unsigned bigint id, name/guard_name varchar(125), '.
                'and unique(name, guard_name). Reconcile manually or restore from backup before migrate.'
            );
        }

        // Empty incompatible leftover (common after a partial Spatie install) — safe to recreate.
        Schema::drop($table);
        Schema::create($table, function (Blueprint $blueprint) {
            $blueprint->bigIncrements('id');
            $blueprint->string('name', 125);
            $blueprint->string('guard_name', 125);
            $blueprint->timestamps();
            $blueprint->unique(['name', 'guard_name']);
        });
        MysqlSchema::ensureInnoDB($table);
    }

    private function permissionsSchemaIsCompatible(string $table): bool
    {
        if (! Schema::hasColumns($table, ['id', 'name', 'guard_name'])) {
            return false;
        }

        if (MysqlSchema::isMysqlFamily()) {
            // Unique constraint is required for Spatie.
            if (! MysqlSchema::hasUnique($table, ['name', 'guard_name'])) {
                return false;
            }
        }

        return true;
    }

    private function ensureRolesTable(string $table, $teams, array $columnNames): void
    {
        if (Schema::hasTable($table)) {
            if (MysqlSchema::rowCount($table) === 0 && ! Schema::hasColumns($table, ['id', 'name', 'guard_name'])) {
                Schema::drop($table);
            } else {
                MysqlSchema::ensureInnoDB($table);

                return;
            }
        }

        Schema::create($table, function (Blueprint $blueprint) use ($teams, $columnNames) {
            $blueprint->bigIncrements('id');
            if ($teams || config('permission.testing')) {
                $blueprint->unsignedBigInteger($columnNames['team_foreign_key'])->nullable();
                $blueprint->index($columnNames['team_foreign_key'], 'roles_team_foreign_key_index');
            }
            $blueprint->string('name', 125);
            $blueprint->string('guard_name', 125);
            $blueprint->timestamps();
            if ($teams || config('permission.testing')) {
                $blueprint->unique([$columnNames['team_foreign_key'], 'name', 'guard_name']);
            } else {
                $blueprint->unique(['name', 'guard_name']);
            }
        });
        MysqlSchema::ensureInnoDB($table);
    }

    private function ensureModelHasPermissions(array $tableNames, array $columnNames, string $pivotPermission, $teams): void
    {
        $table = $tableNames['model_has_permissions'];
        if (Schema::hasTable($table)) {
            MysqlSchema::ensureInnoDB($table);

            return;
        }

        Schema::create($table, function (Blueprint $blueprint) use ($tableNames, $columnNames, $pivotPermission, $teams) {
            $blueprint->unsignedBigInteger($pivotPermission);
            $blueprint->string('model_type');
            $blueprint->unsignedBigInteger($columnNames['model_morph_key']);
            $blueprint->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_permissions_model_id_model_type_index');

            $blueprint->foreign($pivotPermission)
                ->references('id')
                ->on($tableNames['permissions'])
                ->onDelete('cascade');
            if ($teams) {
                $blueprint->unsignedBigInteger($columnNames['team_foreign_key']);
                $blueprint->index($columnNames['team_foreign_key'], 'model_has_permissions_team_foreign_key_index');
                $blueprint->primary(
                    [$columnNames['team_foreign_key'], $pivotPermission, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_permissions_permission_model_type_primary'
                );
            } else {
                $blueprint->primary(
                    [$pivotPermission, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_permissions_permission_model_type_primary'
                );
            }
        });
        MysqlSchema::ensureInnoDB($table);
    }

    private function ensureModelHasRoles(array $tableNames, array $columnNames, string $pivotRole, $teams): void
    {
        $table = $tableNames['model_has_roles'];
        if (Schema::hasTable($table)) {
            MysqlSchema::ensureInnoDB($table);

            return;
        }

        Schema::create($table, function (Blueprint $blueprint) use ($tableNames, $columnNames, $pivotRole, $teams) {
            $blueprint->unsignedBigInteger($pivotRole);
            $blueprint->string('model_type');
            $blueprint->unsignedBigInteger($columnNames['model_morph_key']);
            $blueprint->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_roles_model_id_model_type_index');

            $blueprint->foreign($pivotRole)
                ->references('id')
                ->on($tableNames['roles'])
                ->onDelete('cascade');
            if ($teams) {
                $blueprint->unsignedBigInteger($columnNames['team_foreign_key']);
                $blueprint->index($columnNames['team_foreign_key'], 'model_has_roles_team_foreign_key_index');
                $blueprint->primary(
                    [$columnNames['team_foreign_key'], $pivotRole, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_roles_role_model_type_primary'
                );
            } else {
                $blueprint->primary(
                    [$pivotRole, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_roles_role_model_type_primary'
                );
            }
        });
        MysqlSchema::ensureInnoDB($table);
    }

    private function ensureRoleHasPermissions(array $tableNames, string $pivotRole, string $pivotPermission): void
    {
        $table = $tableNames['role_has_permissions'];
        if (Schema::hasTable($table)) {
            MysqlSchema::ensureInnoDB($table);

            return;
        }

        Schema::create($table, function (Blueprint $blueprint) use ($tableNames, $pivotRole, $pivotPermission) {
            $blueprint->unsignedBigInteger($pivotPermission);
            $blueprint->unsignedBigInteger($pivotRole);

            $blueprint->foreign($pivotPermission)
                ->references('id')
                ->on($tableNames['permissions'])
                ->onDelete('cascade');

            $blueprint->foreign($pivotRole)
                ->references('id')
                ->on($tableNames['roles'])
                ->onDelete('cascade');

            $blueprint->primary([$pivotPermission, $pivotRole], 'role_has_permissions_permission_id_role_id_primary');
        });
        MysqlSchema::ensureInnoDB($table);
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');

        if (empty($tableNames)) {
            throw new \Exception('Error: config/permission.php not found and defaults could not be merged. Please publish the package configuration before proceeding, or drop the tables manually.');
        }

        Schema::dropIfExists($tableNames['role_has_permissions']);
        Schema::dropIfExists($tableNames['model_has_roles']);
        Schema::dropIfExists($tableNames['model_has_permissions']);
        Schema::dropIfExists($tableNames['roles']);
        Schema::dropIfExists($tableNames['permissions']);
    }
};
