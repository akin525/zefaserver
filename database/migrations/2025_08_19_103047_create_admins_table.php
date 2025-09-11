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
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('image')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('role')->nullable();
            $table->json('admin_roles')->nullable();
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->timestamp('login_time')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->boolean('pass_changed')->default(false);
            $table->boolean('has_temp_password')->default(false);
            $table->string('invitation_token')->nullable();
            $table->timestamp('invitation_token_expiry')->nullable();

            // Permission fields
            $table->boolean('view_user')->default(false);
            $table->boolean('manage_user')->default(false);
            $table->boolean('view_admin')->default(false);
            $table->boolean('reporting')->default(false);
            $table->boolean('view_transaction')->default(false);
            $table->boolean('manage_transaction')->default(false);
            $table->boolean('view_payout')->default(false);
            $table->boolean('manage_payout')->default(false);
            $table->boolean('manage_fees')->default(false);
            $table->boolean('view_settlement')->default(false);
            $table->boolean('view_refund')->default(false);
            $table->boolean('manage_refund')->default(false);
            $table->boolean('view_kyc')->default(false);
            $table->boolean('manage_kyc')->default(false);

            $table->timestamps();

            // Indexes
            $table->index(['status']);
            $table->index(['role']);
            $table->index(['invitation_token']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
