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
        // 1. Products Table
        Schema::create('products', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->enum('type', ['PRODUCT', 'SERVICE'])->default('PRODUCT');
            $table->decimal('price', 15, 2);
            $table->integer('stock')->nullable()->comment('Only for PRODUCT type');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['school_id', 'type']);
            $table->index(['school_id', 'sku']);
        });

        // 2. Product Movements Table
        Schema::create('product_movements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('school_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['IN', 'OUT', 'ADJUSTMENT']);
            $table->integer('quantity');
            $table->integer('stock_before');
            $table->integer('stock_after');
            $table->enum('source', ['POS', 'OPNAME', 'MANUAL']);
            $table->string('reference_id')->nullable()->comment('Invoice code or opname ID');
            $table->string('note')->nullable();
            $table->foreignUlid('pic_user_id')->constrained('users');
            $table->timestamps();

            $table->index(['school_id', 'product_id']);
            $table->index(['type', 'source']);
        });

        // 3. Stock Opnames Table
        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('school_id')->constrained()->cascadeOnDelete();
            $table->timestamp('opname_date')->useCurrent();
            $table->enum('status', ['DRAFT', 'FINAL'])->default('DRAFT');
            $table->text('note')->nullable();
            $table->foreignUlid('pic_user_id')->constrained('users');
            $table->timestamps();
        });

        // 4. Stock Opname Items Table
        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('stock_opname_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained()->cascadeOnDelete();
            $table->integer('system_stock');
            $table->integer('physical_stock');
            $table->integer('difference');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_opname_items');
        Schema::dropIfExists('stock_opnames');
        Schema::dropIfExists('product_movements');
        Schema::dropIfExists('products');
    }
};
