<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('account_number', 40)->unique();

            $table->string('first_name', 80);
            $table->string('middle_name', 80)->nullable();
            $table->string('last_name', 80);
            $table->string('suffix', 20)->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->date('date_of_birth')->nullable();

            $table->string('contact_number', 30);
            $table->string('alternate_contact_number', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('photo_path')->nullable();

            $table->enum('customer_type', ['residential', 'business', 'government'])
                ->default('residential');
            $table->date('installation_date')->nullable();

            // Three independent axes, per MASTER_SPEC §8:
            //   status            - where the customer sits in their lifecycle
            //   account_status    - their billing standing
            //   connection_status - whether the line is physically up
            $table->enum('status', [
                'pending_installation', 'active', 'inactive', 'suspended', 'terminated',
            ])->default('pending_installation');

            $table->enum('account_status', ['good_standing', 'overdue', 'delinquent'])
                ->default('good_standing');

            $table->enum('connection_status', ['pending', 'connected', 'disconnected'])
                ->default('pending');

            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->index('status');
            $table->index('account_status');
            $table->index('connection_status');
            $table->index('last_name');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
