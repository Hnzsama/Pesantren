<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('activity_level', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('level_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        // Migrate existing data
        $activities = DB::table('activities')->whereNotNull('level_id')->get();
        foreach ($activities as $activity) {
            DB::table('activity_level')->insert([
                'activity_id' => $activity->id,
                'level_id' => $activity->level_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // We can drop level_id from activities table later, or keep it for now but we'll use the pivot
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_level');
    }
};
