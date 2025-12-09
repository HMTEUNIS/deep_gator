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
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->string('link')->unique();
            $table->enum('classification', ['Climate Change', 'Economic Justice', 'Reproductive Rights', 'LGBTQIA+', 'Immigration']);
            $table->string('image_url')->nullable();
            $table->string('source_feed');
            $table->timestamp('published_at')->nullable();
            $table->text('summary')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('classification');
            $table->index('expires_at');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
