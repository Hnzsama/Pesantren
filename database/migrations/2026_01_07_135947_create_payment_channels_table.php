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
        Schema::create('payment_channels', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // e.g., 'bca_va', 'gopay'
            $table->string('name'); // e.g., 'BCA Virtual Account'
            $table->enum('type', ['VIRTUAL_ACCOUNT', 'E_WALLET', 'RETAIL_STORE', 'CARD', 'QRIS']);
            $table->decimal('fee_flat', 10, 2)->default(0);
            $table->decimal('fee_percent', 5, 2)->default(0); // e.g., 2.50 for 2.5%
            $table->boolean('is_active')->default(true);
            $table->string('icon')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_channels');
    }
};
