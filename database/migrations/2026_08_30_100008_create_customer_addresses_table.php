<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            $table->enum('type', ['service', 'billing'])->default('service');

            $table->string('house_building', 120)->nullable();
            $table->string('street', 120)->nullable();
            $table->string('barangay', 120);
            $table->string('municipality_city', 120);
            $table->string('province', 120);
            $table->string('postal_code', 20)->nullable();

            $table->boolean('is_primary')->default(true);
            $table->timestamps();

            $table->index(['customer_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
