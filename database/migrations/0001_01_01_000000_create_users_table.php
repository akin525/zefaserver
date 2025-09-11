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

            $table->string('phone')->unique();
            $table->string('email')->nullable()->unique();

            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('verification_type')->nullable(); // 'bvn' or 'nin'
            $table->string('verification_number')->nullable();
            $table->enum('verification_status', ['pending', 'verified', 'rejected', 'identity_verify'])->default('pending');

            $table->string('document_type')->nullable(); // id_card, passport, driving_license
            $table->string('document_path')->nullable();
            $table->string('scan_path')->nullable();

            $table->string('password')->nullable();
            $table->string('pin')->nullable(); // hashed 4-digit PIN

            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();

            // Optional: last device summary (not for multiple devices)
            $table->string('last_device_name')->nullable();
            $table->string('last_device_type')->nullable();
            $table->string('last_device_ip')->nullable();
            $table->timestamp('last_device_verified_at')->nullable();

            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');

    }
};
