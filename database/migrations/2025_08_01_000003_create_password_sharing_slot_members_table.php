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
        Schema::create('password_sharing_slot_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('password_sharing_slot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('member_name')->nullable();
            $table->string('member_email')->nullable();
            $table->string('member_phone')->nullable();
            $table->enum('payment_status', ['pending', 'paid'])->default('pending');
            $table->foreignId('payment_id')->nullable()->constrained('payments')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('password_sharing_slot_members');
    }
};
