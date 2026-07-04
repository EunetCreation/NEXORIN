<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('customer_number', 60)->nullable();
            $table->string('name', 150);
            $table->string('type', 30)->default('residential')->index();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('portal_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('status', 20)
                ->default('active')
                ->index();

            $table->timestamp('archived_at')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(
                ['company_id', 'customer_number'],
                'customers_company_number_unique'
            );

            $table->unique(
                ['company_id', 'portal_user_id'],
                'customers_company_portal_user_unique'
            );

            $table->index(
                ['company_id', 'status'],
                'customers_company_status_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
