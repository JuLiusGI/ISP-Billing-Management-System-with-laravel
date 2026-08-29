<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internet_plans', function (Blueprint $table) {
            $table->id();
            $table->string('plan_code', 40)->unique();
            $table->string('name', 120);

            $table->unsignedInteger('download_speed');
            $table->unsignedInteger('upload_speed');
            $table->enum('speed_unit', ['Kbps', 'Mbps', 'Gbps'])->default('Mbps');

            // Money is DECIMAL everywhere. Never float.
            $table->decimal('monthly_price', 12, 2);
            $table->decimal('installation_fee', 12, 2)->default(0);
            $table->decimal('activation_fee', 12, 2)->default(0);

            $table->enum('billing_cycle', ['monthly', 'quarterly', 'semi_annual', 'annual'])
                ->default('monthly');

            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            $table->softDeletes();
            $table->timestamps();

            $table->index('is_active');
            $table->index('billing_cycle');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internet_plans');
    }
};
