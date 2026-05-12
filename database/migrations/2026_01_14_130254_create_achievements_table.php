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
        Schema::create('achievements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('title');
            $table->foreignUlid('school_id')->nullable()->constrained('schools')->cascadeOnDelete(); // Consolidation
            $table->foreignUlid('student_id')->nullable()->constrained()->nullOnDelete();
            $table->string('student_name')->nullable();
            $table->string('student_nis')->nullable();
            $table->date('date_achieved');
            $table->text('description');
            $table->json('images');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('achievements');
    }
};
