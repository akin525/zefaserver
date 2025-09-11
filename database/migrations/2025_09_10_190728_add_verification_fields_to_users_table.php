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
        Schema::table('users', function (Blueprint $table) {
            $table->enum('verification_status', ['pending', 'identity_verify', 'failed'])->default('pending');
            $table->string('verification_number')->nullable();
            $table->string('verification_type')->nullable();
            $table->timestamp('last_verified_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['verification_status', 'verification_number', 'verification_type', 'last_verified_at']);
        });
    }
};
