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
        Schema::create('video_lectures', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('title');
            $table->foreignUlid('school_id')->nullable()->constrained('schools')->cascadeOnDelete(); // Consolidation
            $table->string('youtube_url');
            $table->text('description')->nullable();
            $table->date('published_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_lectures');
    }
};
