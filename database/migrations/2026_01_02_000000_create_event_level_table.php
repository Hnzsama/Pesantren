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
        // Schema::create('event_level', function (Blueprint $table) {
        //     $table->id();
        //     $table->foreignUlid('event_id')->constrained()->cascadeOnDelete();
        //     $table->foreignId('level_id')->constrained()->cascadeOnDelete();
        //     $table->timestamps();

        //     $table->unique(['event_id', 'level_id']);
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_level');
    }
};
