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
        Schema::create('student_reports', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('student_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignUlid('semester_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignUlid('school_id')->constrained()->cascadeOnDelete();

            $table->text('file_path'); // Consolidation: changed from string to text
            $table->foreignUlid('uploaded_by')->constrained('users');
            $table->timestamps();

            $table->unique(['student_id', 'semester_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_reports');
    }
};
