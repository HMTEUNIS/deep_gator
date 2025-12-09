<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stopwords', function (Blueprint $table) {
            $table->id();
            $table->string('word');
            $table->enum('classification', ['Climate Change', 'Economic Justice', 'Reproductive Rights', 'LGBTQIA+', 'Immigration']);
            $table->foreignId('source_article_id')->nullable()->constrained('articles')->onDelete('cascade');
            $table->timestamps();

            $table->index('word');
            $table->index('classification');
            $table->unique(['word', 'classification']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stopwords');
    }
};
