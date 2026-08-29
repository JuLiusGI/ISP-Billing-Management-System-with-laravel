<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_reference', 40)->unique();

            $table->foreignId('customer_id')->constrained()->restrictOnDelete();

            $table->date('payment_date');
            $table->decimal('amount', 12, 2);

            // Running total of what has been applied to invoices. The remainder
            // (amount - allocated_amount) is the customer's unapplied credit,
            // which is how overpayments are represented.
            $table->decimal('allocated_amount', 12, 2)->default(0);

            $table->enum('payment_method', ['cash', 'bank_transfer', 'gcash', 'online', 'other'])
                ->default('cash');

            // External reference: bank slip, GCash transaction id, and so on.
            $table->string('reference_number', 80)->nullable();

            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();

            // Financial records are reversed, never deleted.
            $table->enum('status', ['completed', 'reversed', 'cancelled'])->default('completed');
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reversal_reason')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index('customer_id');
            $table->index('payment_date');
            $table->index('status');
            $table->index('payment_method');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
