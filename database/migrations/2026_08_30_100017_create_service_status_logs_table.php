<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_status_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            // Stored as plain strings rather than an enum so the log survives
            // future changes to the subscription status vocabulary.
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);

            $table->string('reason')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();

            // True when the scheduler changed the status rather than a person.
            $table->boolean('is_automatic')->default(false);

            $table->timestamps();

            $table->index(['subscription_id', 'created_at']);
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_status_logs');
    }
};
