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
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('price');
            $table->decimal('selling_price', 15, 2)->after('type')->nullable();
            $table->decimal('purchase_price', 15, 2)->after('selling_price')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['selling_price', 'purchase_price']);
            $table->decimal('price', 15, 2)->after('type');
        });
    }
};
