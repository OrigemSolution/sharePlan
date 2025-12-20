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
        Schema::table('payments', function (Blueprint $table) {
            // Add password_sharing_slot_id column for password sharing functionality
            $table->unsignedBigInteger('password_sharing_slot_id')->nullable()->after('slot_id');
            $table->foreign('password_sharing_slot_id')->references('id')->on('password_sharing_slots')->onDelete('cascade');
            
            // Make slot_id nullable since it won't be used for password sharing
            $table->unsignedBigInteger('slot_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['password_sharing_slot_id']);
            $table->dropColumn('password_sharing_slot_id');
            
            // Revert slot_id to not nullable (if needed)
            // $table->unsignedBigInteger('slot_id')->nullable(false)->change();
        });
    }
};
