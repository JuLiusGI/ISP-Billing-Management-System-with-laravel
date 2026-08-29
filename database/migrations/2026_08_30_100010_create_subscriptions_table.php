<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('subscription_code', 40)->unique();

            // restrictOnDelete: a subscription carries billing history, so the
            // customer or plan behind it must never vanish underneath it.
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('internet_plan_id')->constrained()->restrictOnDelete();

            $table->date('start_date');
            $table->date('activation_date')->nullable();
            $table->date('expiration_date')->nullable();

            // Day of month this subscription is billed on (1-31).
            $table->unsignedTinyInteger('billing_day')->default(1);

            // Copied from the plan at subscription time and never updated when
            // the plan's price changes, so historical billing stays accurate.
            $table->decimal('monthly_rate', 12, 2);
            $table->decimal('installation_fee', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);

            $table->enum('status', ['pending', 'active', 'suspended', 'expired', 'cancelled'])
                ->default('pending');

            $table->enum('connection_type', ['fiber', 'wireless', 'dsl', 'other'])
                ->default('fiber');
            $table->string('static_ip', 45)->nullable();

            // PPPoE / RADIUS username, reserved for the future network integration.
            $table->string('username', 80)->nullable()->unique();

            $table->text('service_notes')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index('customer_id');
            $table->index('status');
            $table->index('billing_day');
            $table->index('expiration_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
