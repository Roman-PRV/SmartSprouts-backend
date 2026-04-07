<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables and their JSON columns that need a default empty object.
     */
    protected array $tablesConfig = [
        'true_false_image_statements' => ['statement', 'explanation', 'statement_audio_url', 'explanation_audio_url'],
        'true_false_text_statements' => ['statement', 'explanation', 'statement_audio_url', 'explanation_audio_url'],
        'true_false_text_levels' => ['title', 'text', 'text_audio_url'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        foreach ($this->tablesConfig as $tableName => $columns) {
            // Update existing NULL records so we don't end up with mixed data.
            // We rely on Eloquent Model's $attributes for future inserts (especially for SQLite tests),
            // but we also enforce a DB-level default for MySQL to protect against raw SQL inserts.
            foreach ($columns as $column) {
                if (Schema::hasColumn($tableName, $column)) {
                    DB::table($tableName)->whereNull($column)->update([$column => '{}']);

                    if ($driver === 'mysql') {
                        DB::statement("ALTER TABLE `{$tableName}` ALTER COLUMN `{$column}` SET DEFAULT (JSON_OBJECT())");
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        foreach ($this->tablesConfig as $tableName => $columns) {
            foreach ($columns as $column) {
                if (Schema::hasColumn($tableName, $column)) {
                    if ($driver === 'mysql') {
                        DB::statement("ALTER TABLE `{$tableName}` ALTER COLUMN `{$column}` DROP DEFAULT");
                    }
                }
            }
        }
    }
};
