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
        Schema::create('withdrawals', function (Blueprint $table) {
            // Primary Key (ULID)
            $table->char('id', 26)->primary();
            
            // Foreign Keys
            $table->char('school_id', 26);
            $table->char('requested_by', 26);
            $table->char('approved_by', 26)->nullable();
            
            // Financial Fields
            $table->decimal('amount', 15, 2)->comment('Total dana yang ditarik dari balance');
            
            // Bank Account Details
            $table->string('bank_name');
            $table->string('account_number');
            $table->string('account_holder_name');
            
            // Status & State Management
            $table->enum('status', ['REQUESTED', 'APPROVED', 'REJECTED', 'PAID'])
                  ->default('REQUESTED')
                  ->index();
            
            $table->enum('source_type', ['REVENUE', 'TRUST_FUND'])
                  ->default('REVENUE')
                  ->comment('Source wallet: REVENUE = bill income, TRUST_FUND = santri top-up pool');
            
            // Minor Improvements
            $table->timestamp('locked_at')->nullable()->comment('Mencegah race condition saat processing');
            $table->decimal('requested_balance_snapshot', 15, 2)->nullable()->comment('Snapshot saldo saat request untuk audit');
            
            // Timestamps & Audit Trail
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->string('payment_proof')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign Key Constraints
            $table->foreign('school_id')
                  ->references('id')
                  ->on('schools')
                  ->onDelete('cascade');
            
            $table->foreign('requested_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
            
            $table->foreign('approved_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
            
            // Additional Indexes for Performance
            $table->index('school_id');
            $table->index('requested_by');
            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'source_type', 'status']);
            $table->index('paid_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
