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
        Schema::create('admin_audits', function (Blueprint $table) {
            $table->id();
            $table->string('audit_id')->unique();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->onDelete('set null');
            $table->string('admin_name')->nullable();
            $table->string('admin_email')->nullable();

            // Action details
            $table->string('action');
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('auditable_name')->nullable();

            // Data tracking
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable();

            // Request details
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('url')->nullable();
            $table->string('method')->nullable();

            // Risk assessment
            $table->enum('risk_level', ['low', 'medium', 'high', 'critical'])->default('low');
            $table->text('description')->nullable();
            $table->text('reason')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['admin_id', 'created_at']);
            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['action']);
            $table->index('audit_id');
            $table->index(['risk_level']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_audits');
    }
};
