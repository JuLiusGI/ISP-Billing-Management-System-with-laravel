<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();

            // Settings page section: company, billing, service, notifications.
            $table->string('group', 40);

            $table->string('key', 100)->unique();
            $table->text('value')->nullable();

            // Drives casting on read so callers get real ints/bools/arrays.
            $table->enum('type', ['string', 'integer', 'decimal', 'boolean', 'json'])
                ->default('string');

            $table->string('label');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index('group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
