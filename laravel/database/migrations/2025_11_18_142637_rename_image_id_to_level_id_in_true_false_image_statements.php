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

        if (! Schema::hasColumn($this->table, $this->oldColumn)) {
            return;
        }

        if (Schema::hasColumn($this->table, $this->newColumn)) {
            return;
        }

        $this->dropForeignKeyIfExists($this->table, $this->oldColumn);

        Schema::table($this->table, function (Blueprint $table) {
            $table->renameColumn($this->oldColumn, $this->newColumn);
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

        if (! Schema::hasColumn($this->table, $this->newColumn)) {
            return;
        }

        $this->dropForeignKeyIfExists($this->table, $this->newColumn);

        Schema::table($this->table, function (Blueprint $table) {
            $table->renameColumn($this->newColumn, $this->oldColumn);
            $table->foreign($this->oldColumn)
                ->references($this->referencedColumn)
                ->on($this->referencedTable)
                ->cascadeOnDelete();
        });
    }

    protected function dropForeignKeyIfExists(string $table, string $column): void
    {
        $convention = $table . '_' . $column . '_foreign';

        try {
            Schema::table($table, function (Blueprint $t) use ($table, $convention, $column) {
                $t->dropForeign($convention);
            });

            return;
        } catch (\Throwable $e) {
            // fallback
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $dbName = DB::getDatabaseName();
            $row = DB::selectOne(
                'SELECT CONSTRAINT_NAME as name
                 FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
                [$dbName, $table, $column]
            );
            if (! empty($row->name)) {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$row->name}`");
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
            try {
                Schema::table($table, function (Blueprint $t) use ($column) {
                    $t->dropForeign([$column]);
                });
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }
}
