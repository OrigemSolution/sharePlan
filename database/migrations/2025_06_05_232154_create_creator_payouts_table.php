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
        Schema::create('creator_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); //creator ID
            $table->decimal('total_amount', 8, 2);
            $table->decimal('platform_fee', 8, 2);
            $table->decimal('net_amount', 8, 2);
            $table->string('currency');
            $table->string('payout_method');
            $table->string('payout_reference');
            $table->enum('status', [ 'pending', 'processing', 'completed', 'failed']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('creator_payouts');
    }
};
