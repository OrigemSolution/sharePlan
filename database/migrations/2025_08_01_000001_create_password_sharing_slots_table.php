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
        Schema::create('password_sharing_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('password_service_id')->constrained('password_services')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // creator
            $table->integer('guest_limit'); // number of guests allowed
            $table->integer('current_members')->default(1); // includes creator
            $table->integer('duration'); // months
            $table->enum('status', ['open', 'completed', 'cancelled']);
            $table->enum('payment_status', ['pending', 'paid'])->default('pending');
            $table->string('payment_reference')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('password_sharing_slots');
    }
};