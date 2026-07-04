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
        Schema::create('load_analyses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('project_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();
            $table->string('source_mode', 20)->default('combined');
            $table->unsignedInteger('connected_power_va')->nullable();
            $table->string('supply_phase', 20)->default('single_phase');
            $table->string('tariff_group', 50)->nullable();
            $table->decimal('electricity_rate_per_kwh', 14, 2)->nullable();
            $table->unsignedSmallInteger('billing_days')->default(30);
            $table->unsignedTinyInteger('daytime_start_hour')->default(6);
            $table->unsignedTinyInteger('daytime_end_hour')->default(18);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'project_id']);
        });

        Schema::create('electricity_bills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('project_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('load_analysis_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->date('billing_month');
            $table->decimal('energy_kwh', 14, 3);
            $table->decimal('bill_amount', 16, 2)->nullable();
            $table->decimal('peak_demand_kw', 12, 3)->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['load_analysis_id', 'billing_month']);
            $table->index(['company_id', 'project_id']);
        });

        Schema::create('load_appliances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('project_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('load_analysis_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('category', 100)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('rated_power_w', 14, 3);
            $table->decimal('operating_hours_per_day', 5, 2);
            $table->decimal('usage_factor', 5, 4)->default(1);
            $table->unsignedTinyInteger('start_hour')->default(8);
            $table->decimal('starting_multiplier', 6, 2)->default(1);
            $table->boolean('is_critical')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'project_id']);
            $table->index(['load_analysis_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('load_appliances');
        Schema::dropIfExists('electricity_bills');
        Schema::dropIfExists('load_analyses');
    }
};
