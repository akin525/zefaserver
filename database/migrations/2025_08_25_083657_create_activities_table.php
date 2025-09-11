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
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Activity identification
            $table->string('reference')->unique(); // Unique transaction reference
            $table->string('batch_id')->nullable()->index(); // For grouping related activities

            // Activity type and category
            $table->enum('type', [
                'credit',           // Money coming in
                'debit'            // Money going out
            ]);

            $table->enum('category', [
                // Incoming transactions
                'deposit',
                'transfer_in',
                'refund',
                'cashback',
                'bonus',
                'referral_bonus',
                'loan_disbursement',

                // Interest and earnings
                'savings_interest',
                'boost_interest',
                'investment_return',
                'dividend',

                // Outgoing transactions
                'withdrawal',
                'transfer_out',
                'payment',
                'bill_payment',
                'airtime_purchase',
                'data_purchase',
                'loan_repayment',
                'fee',
                'charge',
                'penalty',

                // Internal movements
                'savings_lock',
                'savings_unlock',
                'investment',
                'investment_withdrawal',

                // Other activities
                'reversal',
                'adjustment',
                'correction'
            ]);

            $table->string('sub_category')->nullable(); // More specific categorization

            // Amount details
            $table->decimal('amount', 15, 2); // Main transaction amount
            $table->decimal('fee', 10, 2)->default(0.00); // Associated fees
            $table->decimal('net_amount', 15, 2); // Amount after fees
            $table->string('currency', 3)->default('NGN');

            // Balance tracking
            $table->decimal('balance_before', 15, 2)->nullable();
            $table->decimal('balance_after', 15, 2)->nullable();

            // Status tracking
            $table->enum('status', [
                'pending',
                'processing',
                'completed',
                'failed',
                'cancelled',
                'reversed'
            ])->default('pending');

            // Description and metadata
            $table->string('title'); // Short description
            $table->text('description')->nullable(); // Detailed description
            $table->json('metadata')->nullable(); // Additional data (recipient info, etc.)

            // External references
            $table->string('external_reference')->nullable(); // Bank/payment provider reference
            $table->string('provider')->nullable(); // Payment provider (paystack, flutterwave, etc.)
            $table->string('provider_response')->nullable(); // Provider response code

            // Related entities
            $table->string('related_type')->nullable(); // Polymorphic relation type
            $table->unsignedBigInteger('related_id')->nullable(); // Polymorphic relation id
            $table->foreignId('initiated_by')->nullable()->constrained('users')->onDelete('set null'); // Who initiated

            // Recipient information (for transfers)
            $table->string('recipient_type')->nullable(); // user, bank_account, etc.
            $table->string('recipient_id')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_account')->nullable();
            $table->string('recipient_bank')->nullable();

            // Timing
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            // Flags
            $table->boolean('is_visible')->default(true); // Show in user's activity feed
            $table->boolean('is_reversible')->default(false);
            $table->boolean('is_fee_transaction')->default(false); // Is this a fee transaction

            // Audit trail
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('device_id')->nullable();
            $table->json('audit_trail')->nullable(); // Status change history

            $table->timestamps();

            // Indexes for better performance
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'category']);
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index(['reference']);
            $table->index(['external_reference']);
            $table->index(['related_type', 'related_id']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
