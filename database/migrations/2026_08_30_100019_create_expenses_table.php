<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_reference', 40)->unique();

            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();

            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->date('expense_date');

            $table->enum('payment_method', ['cash', 'bank_transfer', 'gcash', 'online', 'other'])
                ->default('cash');

            $table->string('vendor', 160)->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->index('expense_date');
            $table->index('expense_category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
