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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->restrictOnDelete();

            $table->string('project_number', 70)->nullable();
            $table->string('name', 180);
            $table->string('building_type', 30)->index();
            $table->string('proposed_system', 30)
                ->default('undecided')
                ->index();

            $table->text('site_address');
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('description')->nullable();

            $table->string('status', 30)
                ->default('draft')
                ->index();

            $table->foreignId('assigned_engineer_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('assigned_sales_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('status_updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('status_changed_at')->nullable();
            $table->timestamp('archived_at')->nullable()->index();

            $table->timestamps();

            $table->unique(
                ['company_id', 'project_number'],
                'projects_company_number_unique'
            );

            $table->index(
                ['company_id', 'status'],
                'projects_company_status_index'
            );

            $table->index(
                ['company_id', 'customer_id'],
                'projects_company_customer_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
