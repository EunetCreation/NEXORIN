<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('material_datasheets', function (Blueprint $table): void {
            $table->string('sha256', 64)->nullable()->after('file_size_bytes');
            $table->unsignedInteger('page_count')->nullable()->after('sha256');
            $table->string('extraction_method', 60)->nullable()->after('page_count');
            $table->string('detected_category_code', 80)->nullable()->after('extraction_method');
            $table->decimal('category_match_score', 5, 2)->nullable()->after('detected_category_code');
            $table->json('extraction_meta')->nullable()->after('parse_warnings');

            $table->index(['material_id', 'sha256']);
            $table->index(['material_id', 'extraction_status']);
        });

        Schema::create('material_datasheet_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('material_datasheet_id')->constrained('material_datasheets')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('applied');
            $table->string('parser_version', 60)->nullable();
            $table->json('field_decisions');
            $table->json('before_specs');
            $table->json('after_specs');
            $table->json('material_snapshot')->nullable();
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('applied_at');
            $table->foreignId('rolled_back_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rolled_back_at')->nullable();
            $table->text('rollback_note')->nullable();
            $table->timestamps();

            $table->index(['material_id', 'status']);
            $table->index(['material_datasheet_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_datasheet_applications');

        Schema::table('material_datasheets', function (Blueprint $table): void {
            $table->dropIndex(['material_id', 'sha256']);
            $table->dropIndex(['material_id', 'extraction_status']);
            $table->dropColumn([
                'sha256',
                'page_count',
                'extraction_method',
                'detected_category_code',
                'category_match_score',
                'extraction_meta',
            ]);
        });
    }
};
