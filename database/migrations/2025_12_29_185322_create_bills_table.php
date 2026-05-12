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
        Schema::create('bills', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('school_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_code')->unique();
            $table->foreignUlid('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('student_name')->nullable();
            $table->string('student_nis')->nullable();
            $table->string('student_class')->nullable();
            $table->foreignUlid('bill_category_id')->nullable()->constrained('bill_categories')->cascadeOnDelete();
            $table->foreignUlid('event_id')->nullable()->constrained('events')->cascadeOnDelete();
            $table->string('title');
            $table->string('bill_code')->nullable()->index(); // For bulk tracking
            $table->decimal('amount', 15, 2);
            $table->decimal('payment_fee', 10, 2)->default(0);
            $table->decimal('admin_fee', 10, 2)->default(0);
            $table->string('status'); // Removed enum to avoid migration issues later
            $table->date('due_date');
            $table->dateTime('paid_at')->nullable();
            $table->string('payment_method')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
