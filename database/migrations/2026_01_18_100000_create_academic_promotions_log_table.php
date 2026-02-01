<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_promotions_log', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Core Relationships
            $table->foreignUlid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUlid('from_academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignUlid('to_academic_year_id')->nullable()->constrained('academic_years')->cascadeOnDelete();

            // Action Details
            $table->enum('action', ['PROMOTE', 'RETAIN', 'GRADUATE', 'TRANSFER_OUT']);

            // Audit info
            $table->foreignUlid('executed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('executed_at');

            // Level change tracking (optional but helpful)
            $table->foreignId('from_level_id')->nullable()->constrained('levels')->nullOnDelete();
            $table->foreignId('to_level_id')->nullable()->constrained('levels')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_promotions_log');
    }
};
