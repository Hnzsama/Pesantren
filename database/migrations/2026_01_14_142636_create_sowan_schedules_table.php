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
        Schema::create('sowan_schedules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('school_id')->nullable()->constrained('schools')->cascadeOnDelete(); // Consolidation
            $table->string('guest_name');
            $table->dateTime('request_date');
            $table->text('topic');
            $table->enum('status', ['pending', 'confirmed', 'completed'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sowan_schedules');
    }
};
