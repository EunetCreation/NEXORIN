<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_solar_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('data_source', 30)->default('manual');
            $table->string('source_name', 150)->nullable();
            $table->string('source_reference', 255)->nullable();
            $table->date('source_date')->nullable();
            $table->decimal('elevation_m', 9, 2)->nullable();
            $table->string('timezone', 64)->default('Asia/Jakarta');
            $table->string('quality_status', 30)->default('incomplete');
            $table->text('notes')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'quality_status']);
        });

        Schema::create('monthly_solar_data', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_solar_profile_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('month');
            $table->decimal('ghi_daily_kwh_m2', 7, 3)->nullable();
            $table->decimal('poa_daily_kwh_m2', 7, 3)->nullable();
            $table->decimal('peak_sun_hours', 6, 2)->nullable();
            $table->decimal('ambient_temperature_c', 6, 2)->nullable();
            $table->timestamps();

            $table->unique(['project_solar_profile_id', 'month'], 'monthly_solar_profile_month_unique');
            $table->index(['company_id', 'month']);
        });

        Schema::create('location_solar_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_solar_profile_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 40);
            $table->string('previous_source', 30)->nullable();
            $table->string('new_source', 30)->nullable();
            $table->string('previous_status', 30)->nullable();
            $table->string('new_status', 30)->nullable();
            $table->text('summary')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['project_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_solar_histories');
        Schema::dropIfExists('monthly_solar_data');
        Schema::dropIfExists('project_solar_profiles');
    }
};
