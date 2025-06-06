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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->default(1);
            $table->string('name');
            $table->string('email')->unique();
            $table->string('whatsapp_phone');
            $table->string('phone')->nullable();
            $table->string('bank');
            $table->string('account_no');
            $table->string('account_name');
            $table->enum ('status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
