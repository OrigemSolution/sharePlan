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
            // Make service_id nullable to allow password services
            $table->unsignedBigInteger('service_id')->nullable()->change();
            // Add new nullable password_service_id referencing password_services
            $table->unsignedBigInteger('password_service_id')->nullable()->after('service_id');
            $table->foreign('password_service_id')->references('id')->on('password_services')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['password_service_id']);
            $table->dropColumn('password_service_id');
            $table->unsignedBigInteger('service_id')->nullable(false)->change();
        });
    }
};