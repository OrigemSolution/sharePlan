<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('password_sharing_slot_members', function (Blueprint $table) {
            // Check if slot_id column exists and remove it if it does
            if (Schema::hasColumn('password_sharing_slot_members', 'slot_id')) {
                try {
                    // Try to drop foreign key constraint if it exists
                    $table->dropForeign(['slot_id']);
                } catch (\Exception $e) {
                    // Foreign key might not exist, continue
                }
                $table->dropColumn('slot_id');
            }
            
            // Ensure password_sharing_slot_id exists
            if (!Schema::hasColumn('password_sharing_slot_members', 'password_sharing_slot_id')) {
                $table->foreignId('password_sharing_slot_id')->after('id')->constrained('password_sharing_slots')->cascadeOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('password_sharing_slot_members', function (Blueprint $table) {
            // This migration is not easily reversible, so we'll leave it empty
            // If needed, you can manually restore the slot_id column
        });
    }
};
