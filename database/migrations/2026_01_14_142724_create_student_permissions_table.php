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
        Schema::create('student_permissions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('school_id')->nullable()->constrained('schools')->cascadeOnDelete(); // Consolidation
            $table->foreignUlid('student_id')->nullable()->constrained('students')->cascadeOnDelete(); // Consolidation
            $table->string('student_name'); // Kept for history? Or should be removed if student_id is mandatory? Leaving as is to be safe.
            $table->text('reason');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('admin_note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_permissions');
    }
};
