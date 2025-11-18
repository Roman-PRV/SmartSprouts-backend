<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RenameImageIdToLevelIdInTrueFalseImageStatements extends Migration
{
    protected string $table = 'true_false_image_statements';

    protected string $oldColumn = 'image_id';

    protected string $newColumn = 'level_id';

    protected string $referencedTable = 'true_false_image_levels';

    protected string $referencedColumn = 'id';

    public function up()
    {
        if (! Schema::hasTable($this->table)) {
            return;
        }

        // If already renamed, skip
        if (! Schema::hasColumn($this->table, $this->oldColumn) && Schema::hasColumn($this->table, $this->newColumn)) {
            return;
        }

        // If old column doesn't exist, nothing to do
        if (! Schema::hasColumn($this->table, $this->oldColumn)) {
            return;
        }

        // Drop existing foreign key constraint referencing image_id (if any)
        $this->dropForeignKeyIfExists($this->table, $this->oldColumn);

        // Rename the column (requires doctrine/dbal for some drivers)
        Schema::table($this->table, function (Blueprint $table) {
            $table->renameColumn($this->oldColumn, $this->newColumn);
        });

        // Recreate foreign key constraint to referenced table
        Schema::table($this->table, function (Blueprint $table) {
            $table->foreign($this->newColumn)
                ->references($this->referencedColumn)
                ->on($this->referencedTable)
                ->cascadeOnDelete();
        });
    }

    public function down()
    {
        if (! Schema::hasTable($this->table)) {
            return;
        }

        // If already reverted, skip
        if (! Schema::hasColumn($this->table, $this->newColumn) && Schema::hasColumn($this->table, $this->oldColumn)) {
            return;
        }

        if (! Schema::hasColumn($this->table, $this->newColumn)) {
            return;
        }

        // Drop FK on level_id if exists
        $this->dropForeignKeyIfExists($this->table, $this->newColumn);

        // Rename back
        Schema::table($this->table, function (Blueprint $table) {
            $table->renameColumn($this->newColumn, $this->oldColumn);
        });

        // Recreate original FK to true_false_image_levels.id
        Schema::table($this->table, function (Blueprint $table) {
            $table->foreign($this->oldColumn)
                ->references($this->referencedColumn)
                ->on($this->referencedTable)
                ->cascadeOnDelete();
        });
    }

    /**
     * Drop a foreign key constraint on a table column if it exists.
     * Works for MySQL/Postgres; for SQLite foreign keys are managed differently.
     */
    protected function dropForeignKeyIfExists(string $table, string $column): void
    {
        // Attempt to drop by Laravel convention name first
        $convention = $table.'_'.$column.'_foreign';
        try {
            Schema::table($table, function (Blueprint $t) use ($convention, $column) {
                // use raw if constraint name exists; catch exceptions quietly
                if (Schema::hasColumn($t->getTable(), $column)) {
                    $t->dropForeign($convention);
                }
            });

            return;
        } catch (\Throwable $e) {
            // ignore and try to find actual FK name
        }

        // Fallback: query DB to find constraint name and drop it
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $dbName = DB::getDatabaseName();
            $rows = DB::selectOne(
                'SELECT CONSTRAINT_NAME as name FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
                [$dbName, $table, $column]
            );
            if (! empty($rows->name)) {
                $fk = $rows->name;
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fk}`");
            }
        } elseif ($driver === 'pgsql') {
            $row = DB::selectOne(
                "SELECT tc.constraint_name as name
                 FROM information_schema.table_constraints tc
                 JOIN information_schema.key_column_usage kcu
                   ON tc.constraint_name = kcu.constraint_name
                 WHERE tc.table_name = ? AND kcu.column_name = ? AND tc.constraint_type = 'FOREIGN KEY'",
                [$table, $column]
            );
            if (! empty($row->name)) {
                DB::statement(sprintf('ALTER TABLE "%s" DROP CONSTRAINT "%s"', $table, $row->name));
            }
        } else {
            // SQLite or other: attempt drop by convention and ignore errors
            try {
                Schema::table($table, function (Blueprint $t) use ($column) {
                    $t->dropForeign([$column]);
                });
            } catch (\Throwable $e) {
                // nothing we can do portably here
            }
        }
    }
}
