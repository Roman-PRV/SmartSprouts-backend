<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Convert existing Spanish text data to JSON format {"es": "text"}
        // Only update if there are rows in the table

        if (DB::connection()->getDriverName() !== 'sqlite') {
            if (DB::table('true_false_image_levels')->count() > 0) {
                DB::statement("
                    UPDATE true_false_image_levels 
                    SET title = JSON_OBJECT('es', title)
                ");
            }

            if (DB::table('true_false_text_levels')->count() > 0) {
                DB::statement("
                    UPDATE true_false_text_levels 
                    SET title = JSON_OBJECT('es', title),
                        text = JSON_OBJECT('es', text)
                ");
            }

            if (DB::table('true_false_image_statements')->count() > 0) {
                DB::statement("
                    UPDATE true_false_image_statements 
                    SET statement = JSON_OBJECT('es', statement),
                        explanation = CASE 
                            WHEN explanation IS NOT NULL THEN JSON_OBJECT('es', explanation)
                            ELSE NULL
                        END
                ");
            }

            if (DB::table('true_false_text_statements')->count() > 0) {
                DB::statement("
                    UPDATE true_false_text_statements 
                    SET statement = JSON_OBJECT('es', statement),
                        explanation = CASE 
                            WHEN explanation IS NOT NULL THEN JSON_OBJECT('es', explanation)
                            ELSE NULL
                        END
                ");
            }
        }

        // Step 2: Change column types to JSON (only for MySQL, SQLite stores JSON as TEXT)
        if (DB::connection()->getDriverName() !== 'sqlite') {
            Schema::table('true_false_image_levels', function (Blueprint $table) {
                $table->json('title')->change();
            });

            Schema::table('true_false_text_levels', function (Blueprint $table) {
                $table->json('title')->change();
                $table->json('text')->change();
            });

            Schema::table('true_false_image_statements', function (Blueprint $table) {
                $table->json('statement')->change();
                $table->json('explanation')->nullable()->change();
            });

            Schema::table('true_false_text_statements', function (Blueprint $table) {
                $table->json('statement')->change();
                $table->json('explanation')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Step 1: Change column types back to VARCHAR/TEXT (only for MySQL)
        if (DB::connection()->getDriverName() !== 'sqlite') {
            Schema::table('true_false_image_levels', function (Blueprint $table) {
                $table->string('title')->change();
            });

            Schema::table('true_false_text_levels', function (Blueprint $table) {
                $table->string('title')->change();
                $table->text('text')->change();
            });

            Schema::table('true_false_image_statements', function (Blueprint $table) {
                $table->text('statement')->change();
                $table->text('explanation')->nullable()->change();
            });

            Schema::table('true_false_text_statements', function (Blueprint $table) {
                $table->text('statement')->change();
                $table->text('explanation')->nullable()->change();
            });
        }

        // Step 2: Extract Spanish text from JSON back to plain text
        if (DB::connection()->getDriverName() !== 'sqlite') {
            if (DB::table('true_false_image_levels')->count() > 0) {
                DB::statement("
                    UPDATE true_false_image_levels 
                    SET title = JSON_UNQUOTE(JSON_EXTRACT(title, '$.es'))
                ");
            }

            if (DB::table('true_false_text_levels')->count() > 0) {
                DB::statement("
                    UPDATE true_false_text_levels 
                    SET title = JSON_UNQUOTE(JSON_EXTRACT(title, '$.es')),
                        text = JSON_UNQUOTE(JSON_EXTRACT(text, '$.es'))
                ");
            }

            if (DB::table('true_false_image_statements')->count() > 0) {
                DB::statement("
                    UPDATE true_false_image_statements 
                    SET statement = JSON_UNQUOTE(JSON_EXTRACT(statement, '$.es')),
                        explanation = CASE 
                            WHEN explanation IS NOT NULL THEN JSON_UNQUOTE(JSON_EXTRACT(explanation, '$.es'))
                            ELSE NULL
                        END
                ");
            }

            if (DB::table('true_false_text_statements')->count() > 0) {
                DB::statement("
                    UPDATE true_false_text_statements 
                    SET statement = JSON_UNQUOTE(JSON_EXTRACT(statement, '$.es')),
                        explanation = CASE 
                            WHEN explanation IS NOT NULL THEN JSON_UNQUOTE(JSON_EXTRACT(explanation, '$.es'))
                            ELSE NULL
                        END
                ");
            }
        }
    }
};
