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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slot_member_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 8, 2);
            $table->string('payment_method');
            $table->string('payment_processor_id'); //External payment reference 
            $table->enum('status', [ 'pending', 'completed', 'failed', 'refunded']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
