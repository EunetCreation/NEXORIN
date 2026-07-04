<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roof_site_surveys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->date('survey_date')->nullable();
            $table->foreignId('surveyed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('access_method', 40)->nullable();
            $table->string('access_condition', 30)->default('unknown');
            $table->string('mounting_preference', 40)->default('undecided');
            $table->string('structural_review_status', 30)->default('not_assessed');
            $table->text('structural_notes')->nullable();
            $table->boolean('fall_protection_available')->nullable();
            $table->boolean('lightning_protection_present')->nullable();
            $table->decimal('electrical_room_distance_m', 9, 2)->nullable();
            $table->decimal('estimated_cable_route_m', 9, 2)->nullable();
            $table->string('weather_condition', 80)->nullable();
            $table->string('quality_status', 30)->default('incomplete');
            $table->text('notes')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'quality_status']);
        });

        Schema::create('roof_surfaces', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('roof_site_survey_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('roof_type', 30);
            $table->string('roof_material', 40);
            $table->string('roof_condition', 20)->default('unknown');
            $table->decimal('gross_area_m2', 10, 2);
            $table->decimal('setback_area_m2', 10, 2)->default(0);
            $table->decimal('obstruction_area_m2', 10, 2)->default(0);
            $table->decimal('walkway_area_m2', 10, 2)->default(0);
            $table->decimal('tilt_deg', 6, 2);
            $table->decimal('azimuth_deg', 6, 2);
            $table->decimal('shading_percent', 6, 2)->default(0);
            $table->decimal('max_additional_load_kg_m2', 8, 2)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'sort_order']);
            $table->index(['company_id', 'roof_site_survey_id']);
        });

        Schema::create('roof_site_survey_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('roof_site_survey_id')->constrained()->cascadeOnDelete();
            $table->string('category', 30)->default('roof');
            $table->string('path', 255);
            $table->string('original_name', 255);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('caption', 255)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'category']);
        });

        Schema::create('roof_site_survey_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('roof_site_survey_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 40);
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
        Schema::dropIfExists('roof_site_survey_histories');
        Schema::dropIfExists('roof_site_survey_photos');
        Schema::dropIfExists('roof_surfaces');
        Schema::dropIfExists('roof_site_surveys');
    }
};
