<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();

            // Dot-namespaced ability, e.g. "invoices.create".
            $table->string('name', 100)->unique();

            $table->string('display_name', 100);

            // Groups abilities for the permission matrix UI, e.g. "Billing".
            $table->string('module', 60);

            $table->string('description')->nullable();
            $table->timestamps();

            $table->index('module');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
