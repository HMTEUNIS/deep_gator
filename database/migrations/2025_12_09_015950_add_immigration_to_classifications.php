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
        $driver = DB::getDriverName();
        
        // Only modify ENUM for databases that support it (MySQL, MariaDB, PostgreSQL)
        if (in_array($driver, ['mysql', 'mariadb'])) {
            // Add 'Immigration' to articles.classification enum
            DB::statement("ALTER TABLE articles MODIFY COLUMN classification ENUM('Climate Change', 'Economic Justice', 'Reproductive Rights', 'LGBTQIA+', 'Immigration') NOT NULL");
            
            // Add 'Immigration' to stopwords.classification enum
            DB::statement("ALTER TABLE stopwords MODIFY COLUMN classification ENUM('Climate Change', 'Economic Justice', 'Reproductive Rights', 'LGBTQIA+', 'Immigration') NOT NULL");
        } elseif ($driver === 'pgsql') {
            // PostgreSQL uses CHECK constraints, but Laravel handles ENUMs differently
            // For PostgreSQL, we'd need to recreate the enum type or use a different approach
            // For now, we'll skip PostgreSQL as it requires more complex handling
        }
        // SQLite doesn't support ENUM types - they're stored as TEXT
        // The application-level validation will handle the constraint
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();
        
        if (in_array($driver, ['mysql', 'mariadb'])) {
            // Remove 'Immigration' from articles.classification enum
            // Note: This will fail if there are any articles with 'Immigration' classification
            DB::statement("ALTER TABLE articles MODIFY COLUMN classification ENUM('Climate Change', 'Economic Justice', 'Reproductive Rights', 'LGBTQIA+') NOT NULL");
            
            // Remove 'Immigration' from stopwords.classification enum
            // Note: This will fail if there are any stopwords with 'Immigration' classification
            DB::statement("ALTER TABLE stopwords MODIFY COLUMN classification ENUM('Climate Change', 'Economic Justice', 'Reproductive Rights', 'LGBTQIA+') NOT NULL");
        }
        // SQLite: No action needed - ENUMs are just TEXT
    }
};
