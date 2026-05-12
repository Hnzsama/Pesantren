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
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('school_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_code')->unique();
            $table->foreignUlid('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('student_name')->nullable();
            $table->string('student_nis')->nullable();
            $table->foreignUlid('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('operator_id')->nullable()->constrained('users');
            $table->foreignUlid('pic_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type');
            $table->string('category');
            $table->decimal('amount', 15, 2);
            $table->decimal('payment_fee', 10, 2)->default(0);
            $table->decimal('admin_fee', 10, 2)->default(0);
            $table->string('description');
            $table->string('payment_method')->nullable();
            $table->string('status')->default('UNPAID');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
