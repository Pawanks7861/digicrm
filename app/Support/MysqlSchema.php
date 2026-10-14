<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * MySQL/MariaDB schema helpers for idempotent, recoverable migrations.
 * SQLite (tests) paths no-op engine/index introspection that is MySQL-specific.
 */
final class MysqlSchema
{
    public static function isMysqlFamily(): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        return in_array($driver, ['mysql', 'mariadb'], true);
    }

    public static function tableExists(string $table): bool
    {
        return Schema::hasTable($table);
    }

    public static function columnExists(string $table, string $column): bool
    {
        return Schema::hasColumn($table, $column);
    }

    public static function engine(string $table): ?string
    {
        if (! self::isMysqlFamily() || ! self::tableExists($table)) {
            return null;
        }

        $row = DB::selectOne(
            'SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        );

        return $row->engine ?? null;
    }

    public static function rowCount(string $table): int
    {
        if (! self::tableExists($table)) {
            return 0;
        }

        return (int) DB::table($table)->count();
    }

    public static function ensureInnoDB(string $table): void
    {
        if (! self::isMysqlFamily() || ! self::tableExists($table)) {
            return;
        }

        $engine = self::engine($table);
        if ($engine !== null && strcasecmp($engine, 'InnoDB') !== 0) {
            DB::statement('ALTER TABLE `'.$table.'` ENGINE=InnoDB');
        }
    }

    /**
     * @return list<string>
     */
    public static function indexNames(string $table): array
    {
        if (! self::tableExists($table)) {
            return [];
        }

        if (! self::isMysqlFamily()) {
            // SQLite: approximate via pragma
            $rows = DB::select('PRAGMA index_list('.$table.')');

            return array_values(array_filter(array_map(
                static fn ($r) => $r->name ?? null,
                $rows
            )));
        }

        $rows = DB::select(
            'SELECT DISTINCT INDEX_NAME AS name FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        );

        return array_values(array_map(static fn ($r) => (string) $r->name, $rows));
    }

    public static function hasIndex(string $table, string $indexName): bool
    {
        return in_array($indexName, self::indexNames($table), true);
    }

    /**
     * Whether any index on $table begins with $column (enough for InnoDB FK support).
     */
    public static function hasIndexLeadingWith(string $table, string $column): bool
    {
        if (! self::tableExists($table)) {
            return false;
        }

        if (! self::isMysqlFamily()) {
            return self::hasIndex($table, $table.'_'.$column.'_index')
                || self::hasIndex($table, $column);
        }

        $rows = DB::select(
            'SELECT INDEX_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND SEQ_IN_INDEX = 1',
            [$table, $column]
        );

        return count($rows) > 0;
    }

    public static function ensureIndex(string $table, string $indexName, array $columns): void
    {
        if (! self::tableExists($table) || self::hasIndex($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName, $columns) {
            $blueprint->index($columns, $indexName);
        });
    }

    public static function dropIndexIfExists(string $table, string $indexName): void
    {
        if (! self::tableExists($table) || ! self::hasIndex($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
            $blueprint->dropIndex($indexName);
        });
    }

    public static function dropUniqueIfExists(string $table, string $indexName): void
    {
        if (! self::tableExists($table) || ! self::hasIndex($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
            $blueprint->dropUnique($indexName);
        });
    }

    /**
     * @return list<array{name:string,column:string,referenced_table:string,referenced_column:string}>
     */
    public static function foreignKeys(string $table): array
    {
        if (! self::isMysqlFamily() || ! self::tableExists($table)) {
            return [];
        }

        $rows = DB::select(
            'SELECT CONSTRAINT_NAME AS name, COLUMN_NAME AS `column`,
                    REFERENCED_TABLE_NAME AS referenced_table,
                    REFERENCED_COLUMN_NAME AS referenced_column
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$table]
        );

        return array_map(static fn ($r) => [
            'name' => (string) $r->name,
            'column' => (string) $r->column,
            'referenced_table' => (string) $r->referenced_table,
            'referenced_column' => (string) $r->referenced_column,
        ], $rows);
    }

    public static function hasForeignKey(string $table, string $column, ?string $referencedTable = null): bool
    {
        foreach (self::foreignKeys($table) as $fk) {
            if ($fk['column'] !== $column) {
                continue;
            }
            if ($referencedTable === null || $fk['referenced_table'] === $referencedTable) {
                return true;
            }
        }

        return false;
    }

    public static function dropForeignKeyOnColumn(string $table, string $column): void
    {
        if (! self::isMysqlFamily()) {
            try {
                Schema::table($table, function (Blueprint $blueprint) use ($column) {
                    $blueprint->dropForeign([$column]);
                });
            } catch (\Throwable) {
                // ignore when absent
            }

            return;
        }

        foreach (self::foreignKeys($table) as $fk) {
            if ($fk['column'] === $column) {
                DB::statement('ALTER TABLE `'.$table.'` DROP FOREIGN KEY `'.$fk['name'].'`');
            }
        }
    }

    public static function hasUnique(string $table, array $columns): bool
    {
        if (! self::isMysqlFamily() || ! self::tableExists($table)) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $params = array_merge([$table], $columns);

        $rows = DB::select(
            "SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND NON_UNIQUE = 0
               AND COLUMN_NAME IN ($placeholders)
             GROUP BY INDEX_NAME",
            $params
        );

        $wanted = implode(',', $columns);
        foreach ($rows as $row) {
            if (($row->cols ?? '') === $wanted) {
                return true;
            }
        }

        return false;
    }

    /**
     * Refuse to continue when an existing table is incompatible and not safely repairable.
     */
    public static function assertEmptyOrMissing(string $table, string $context): void
    {
        if (! self::tableExists($table)) {
            return;
        }

        if (self::rowCount($table) > 0) {
            throw new RuntimeException(
                "Migration blocked: table `{$table}` already exists with data ({$context}). ".
                'Restore from backup or reconcile schema manually before re-running migrate.'
            );
        }
    }

    /**
     * Drop an empty incompatible table so a correct Schema::create can proceed.
     */
    public static function dropEmptyTable(string $table): void
    {
        if (! self::tableExists($table)) {
            return;
        }

        if (self::rowCount($table) > 0) {
            throw new RuntimeException(
                "Refusing to drop populated table `{$table}`. Manual recovery required."
            );
        }

        Schema::drop($table);
    }

    public static function makeClientIdNullable(string $table): void
    {
        if (! self::tableExists($table) || ! self::columnExists($table, 'client_id')) {
            return;
        }

        if (! self::isMysqlFamily()) {
            // SQLite tests: doctrine change() is enough when no FK engine quirks
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('client_id')->nullable()->change();
            });

            return;
        }

        $hadFk = self::hasForeignKey($table, 'client_id', 'clients');
        if ($hadFk) {
            self::dropForeignKeyOnColumn($table, 'client_id');
        }

        DB::statement('ALTER TABLE `'.$table.'` MODIFY `client_id` BIGINT UNSIGNED NULL');

        if ($hadFk) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
            });
        }
    }
}
