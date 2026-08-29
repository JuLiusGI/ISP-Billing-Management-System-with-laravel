<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Kept on nullOnDelete so removing a user never erases the trail.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('action', 80);
            $table->string('module', 60);

            // Polymorphic pointer at the affected record, when there is one.
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();

            $table->text('description')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            // Audit rows are immutable, so there is no updated_at.
            $table->timestamp('created_at')->nullable();

            $table->index('user_id');
            $table->index('module');
            $table->index('action');
            $table->index('created_at');
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
