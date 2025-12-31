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
        Schema::table('password_sharing_slot_members', function (Blueprint $table) {
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
            if (Schema::hasColumn('password_sharing_slot_members', 'password_sharing_slot_id')) {
                $table->dropForeign(['password_sharing_slot_id']);
                $table->dropColumn('password_sharing_slot_id');
            }
        });
    }
};
