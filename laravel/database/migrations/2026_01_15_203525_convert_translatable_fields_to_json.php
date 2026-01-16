<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Convert existing Spanish text data to JSON format {"es": "text"}
        // Only update if there are rows in the table

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

        // Step 2: Change column types to JSON (only for MySQL, SQLite stores JSON as TEXT)
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE true_false_image_levels MODIFY COLUMN title JSON');

            DB::statement('ALTER TABLE true_false_text_levels MODIFY COLUMN title JSON');
            DB::statement('ALTER TABLE true_false_text_levels MODIFY COLUMN text JSON');

            DB::statement('ALTER TABLE true_false_image_statements MODIFY COLUMN statement JSON');
            DB::statement('ALTER TABLE true_false_image_statements MODIFY COLUMN explanation JSON');

            DB::statement('ALTER TABLE true_false_text_statements MODIFY COLUMN statement JSON');
            DB::statement('ALTER TABLE true_false_text_statements MODIFY COLUMN explanation JSON');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Step 1: Change column types back to VARCHAR/TEXT (only for MySQL)
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE true_false_image_levels MODIFY COLUMN title VARCHAR(255)');

            DB::statement('ALTER TABLE true_false_text_levels MODIFY COLUMN title VARCHAR(255)');
            DB::statement('ALTER TABLE true_false_text_levels MODIFY COLUMN text TEXT');

            DB::statement('ALTER TABLE true_false_image_statements MODIFY COLUMN statement TEXT');
            DB::statement('ALTER TABLE true_false_image_statements MODIFY COLUMN explanation TEXT');

            DB::statement('ALTER TABLE true_false_text_statements MODIFY COLUMN statement TEXT');
            DB::statement('ALTER TABLE true_false_text_statements MODIFY COLUMN explanation TEXT');
        }

        // Step 2: Extract Spanish text from JSON back to plain text
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
};
