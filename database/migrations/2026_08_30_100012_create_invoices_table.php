<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 40)->unique();

            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('billing_cycle_id')->nullable()->constrained()->nullOnDelete();

            $table->date('billing_period_start')->nullable();
            $table->date('billing_period_end')->nullable();
            $table->date('invoice_date');
            $table->date('due_date');

            // Totals are stored rather than derived so a historical invoice keeps
            // the figures it was issued with, whatever happens to plan pricing.
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('charges_total', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('balance_due', 12, 2)->default(0);

            $table->enum('status', [
                'draft', 'unpaid', 'partially_paid', 'paid', 'overdue', 'cancelled', 'void',
            ])->default('draft');

            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();

            $table->softDeletes();
            $table->timestamps();

            // Duplicate protection for automated billing: one invoice per
            // subscription per period. NULL subscription_id (ad-hoc invoices) is
            // exempt, since MySQL/MariaDB permit repeated NULLs in a unique index.
            $table->unique(['subscription_id', 'billing_period_start'], 'invoices_subscription_period_unique');

            $table->index('customer_id');
            $table->index('status');
            $table->index('due_date');
            $table->index('invoice_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
