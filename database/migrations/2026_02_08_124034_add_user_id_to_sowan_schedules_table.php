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
        Schema::table('sowan_schedules', function (Blueprint $table) {
            $table->foreignUlid('user_id')->nullable()->after('school_id')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sowan_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
