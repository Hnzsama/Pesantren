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
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('school_id')->nullable()->constrained()->cascadeOnDelete(); // Optional (null for superadmin)
            $table->foreignUlid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sender_name');
            $table->string('email'); // Wajib
            $table->string('phone'); // Wajib
            $table->enum('category', ['fasilitas', 'akademik', 'layanan']);
            $table->text('message');
            $table->text('admin_reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
