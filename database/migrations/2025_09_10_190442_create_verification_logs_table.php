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
        Schema::create('verification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('verification_type'); // bvn, nin, voters, passport, drivers-license
            $table->string('verification_number')->nullable();
            $table->string('identifier');
            $table->json('request_payload');
            $table->json('response_data');
            $table->boolean('verification_success')->default(false);
            $table->string('status_message')->nullable();
            $table->string('reference_id')->nullable(); // From API response
            $table->decimal('cost', 10, 2)->nullable(); // If API returns cost
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'verification_type']);
            $table->index('verification_number');
            $table->index('verification_success');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verification_logs');
    }
};
