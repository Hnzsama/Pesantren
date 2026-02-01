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
        Schema::create('semesters', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('academic_year_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignUlid('school_id')->constrained()->cascadeOnDelete();

            $table->enum('type', ['GANJIL', 'GENAP']);
            $table->date('start_date');
            $table->date('end_date');

            $table->timestamps();

            $table->unique(['academic_year_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('semesters');
    }
};
