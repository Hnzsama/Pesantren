<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_academics', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('level_id')->nullable()->constrained('levels')->nullOnDelete(); // The level this history record sits in
            $table->foreignUlid('academic_year_id')->constrained('academic_years')->cascadeOnDelete();

            // Results tracking
            $table->string('semester')->nullable(); // 'ODD', 'EVEN', or null
            $table->decimal('average_grade', 5, 2)->nullable();
            $table->integer('rank')->nullable();

            // Notes
            $table->text('notes')->nullable();

            // Promotion Status for this period
            // PROMOTED: moved to next level
            // RETAINED: stayed in same level
            // GRADUATED: finished the jenjang
            // ONGOING: currently active
            $table->enum('status', ['ONGOING', 'PROMOTED', 'RETAINED', 'GRADUATED', 'TRANSFERRED'])->default('ONGOING');

            $table->timestamps();

            // Ensure one record per student per year per semester? Or just per year?
            // Usually academic year + student.
            // But if we track semesterly, we need semester. Keep it flexible.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_academics');
    }
};
