<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_cycles', function (Blueprint $table) {
            $table->id();

            // Human label for the period, e.g. "August 2026".
            $table->string('name', 60);

            $table->date('period_start');
            $table->date('period_end');
            $table->date('due_date');

            $table->enum('status', ['open', 'processing', 'closed'])->default('open');

            $table->timestamp('generated_at')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // One cycle per period keeps repeated runs of the invoice generator idempotent.
            $table->unique(['period_start', 'period_end']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_cycles');
    }
};
