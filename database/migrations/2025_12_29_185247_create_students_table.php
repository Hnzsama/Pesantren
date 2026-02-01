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
        Schema::create('students', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('school_id')->constrained()->cascadeOnDelete();
            $table->string('nis'); // Removed ->unique() - will be unique per school
            $table->string('full_name');
            $table->foreignUlid('parent_user_id')->nullable()->constrained('users')->cascadeOnDelete(); // Consolidation: made nullable
            $table->boolean('needs_parent_verification')->default(false); // Consolidation: added column
            $table->foreignId('level_id')->nullable()->constrained('levels')->nullOnDelete();
            $table->decimal('wallet_balance', 15, 2)->default(0);
            $table->string('wallet_pin')->nullable();
            $table->decimal('daily_cash_limit', 10, 2)->default(50000); // Only for CASH_WITHDRAWAL
            $table->boolean('allow_transaction')->default(true)->index();
            $table->enum('status', ['ACTIVE', 'INACTIVE', 'GRADUATED', 'DROPPED_OUT']);
            $table->enum('gender', ['L', 'P']);
            $table->date('birth_date')->nullable();
            $table->text('photo_url')->nullable();
            $table->timestamps();

            // Composite unique: NIS is unique only within the same school
            $table->unique(['nis', 'school_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
