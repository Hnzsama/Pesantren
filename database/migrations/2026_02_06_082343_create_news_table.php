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
        Schema::create('news', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('school_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignUlid('author_id')->constrained('users')->onDelete('cascade');
            $table->string('title');
            $table->longText('content');
            $table->json('images')->nullable();
            $table->string('target_role')->index()->comment('Target audience: admin, guardian, etc.');
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news');
    }
};
