<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Departments table (Jenjang: MI, MTs, MA)
        Schema::create('departments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('school_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // e.g., "Madrasah Ibtidaiyah"
            $table->string('code')->nullable(); // e.g., "MI", "MTs"
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'code']);
        });

        // 2. Academic Years table (Tahun Ajaran)
        Schema::create('academic_years', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('school_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // e.g., "2024/2025"
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            // Only one active year per school approach handled often by code, but unique name per school is good
            $table->unique(['school_id', 'name']);
        });

        // 3. Update Levels table (Consolidated from 2026_01_17_100001_update_levels_table_for_academic_structure)
        Schema::table('levels', function (Blueprint $table) {
            $table->foreignUlid('department_id')->nullable()->after('school_id')->constrained('departments')->nullOnDelete();
            $table->integer('grade_level')->default(1)->after('department_id');
            
            // Update Unique Constraint
            $table->dropUnique(['name', 'school_id']);
        });
        
        Schema::table('levels', function (Blueprint $table) {
             $table->unique(['school_id', 'department_id', 'name']);
        });
    }

    public function down(): void
    {
		Schema::table('levels', function (Blueprint $table) {
             $table->dropForeign(['department_id']);
             $table->dropUnique(['school_id', 'department_id', 'name']);
             $table->dropColumn(['department_id', 'grade_level']);
             $table->unique(['name', 'school_id']);
        });

        Schema::dropIfExists('academic_years');
        Schema::dropIfExists('departments');
    }
};
