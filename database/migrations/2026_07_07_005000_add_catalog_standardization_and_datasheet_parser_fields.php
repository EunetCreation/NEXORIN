<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table): void {
            $table->string('catalog_status', 40)->default('draft')->after('default_scope_mode');
            $table->string('compliance_status', 40)->default('needs_review')->after('catalog_status');
            $table->timestamp('approved_at')->nullable()->after('compliance_status');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('last_reviewed_at')->nullable()->after('approved_by');
            $table->foreignId('last_reviewed_by')->nullable()->after('last_reviewed_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('material_datasheets', function (Blueprint $table): void {
            $table->string('original_filename', 220)->nullable()->after('title');
            $table->string('mime_type', 120)->nullable()->after('file_path');
            $table->unsignedBigInteger('file_size_bytes')->nullable()->after('mime_type');
            $table->string('extraction_status', 40)->default('pending')->after('is_active');
            $table->string('parser_version', 40)->nullable()->after('extraction_status');
            $table->decimal('parse_confidence', 5, 2)->nullable()->after('parser_version');
            $table->longText('parsed_text')->nullable()->after('parse_confidence');
            $table->json('parsed_fields')->nullable()->after('parsed_text');
            $table->json('parse_warnings')->nullable()->after('parsed_fields');
            $table->timestamp('parsed_at')->nullable()->after('parse_warnings');
            $table->timestamp('reviewed_at')->nullable()->after('parsed_at');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('applied_at')->nullable()->after('reviewed_by');
            $table->foreignId('applied_by')->nullable()->after('applied_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('material_certificates', function (Blueprint $table): void {
            $table->string('verification_status', 40)->default('pending')->after('is_active');
            $table->timestamp('verified_at')->nullable()->after('verification_status');
            $table->foreignId('verified_by')->nullable()->after('verified_at')->constrained('users')->nullOnDelete();
            $table->text('verification_notes')->nullable()->after('verified_by');
        });
    }

    public function down(): void
    {
        Schema::table('material_certificates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn([
                'verification_status',
                'verified_at',
                'verification_notes',
            ]);
        });

        Schema::table('material_datasheets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('applied_by');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'original_filename',
                'mime_type',
                'file_size_bytes',
                'extraction_status',
                'parser_version',
                'parse_confidence',
                'parsed_text',
                'parsed_fields',
                'parse_warnings',
                'parsed_at',
                'reviewed_at',
                'applied_at',
            ]);
        });

        Schema::table('materials', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('last_reviewed_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn([
                'catalog_status',
                'compliance_status',
                'approved_at',
                'last_reviewed_at',
            ]);
        });
    }
};
