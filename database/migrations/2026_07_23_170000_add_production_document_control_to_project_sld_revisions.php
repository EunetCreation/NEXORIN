<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('project_sld_revisions', function (Blueprint $table): void {
            $table->string('drawing_number_prefix', 90)->nullable()->after('system_type');
            $table->string('drawing_title', 180)->nullable()->after('drawing_number_prefix');
            $table->string('client_name', 180)->nullable()->after('drawing_title');
            $table->string('site_location', 240)->nullable()->after('client_name');
            $table->string('issue_status', 40)->default('draft')->index()->after('status');
            $table->json('document_metadata')->nullable()->after('source_snapshot');
            $table->json('issue_history')->nullable()->after('document_metadata');
            $table->json('export_manifest')->nullable()->after('issue_history');
            $table->foreignId('issued_by')->nullable()->after('approved_by')->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable()->after('approved_at');
            $table->timestamp('last_exported_at')->nullable()->after('issued_at');
        });
    }

    public function down(): void
    {
        Schema::table('project_sld_revisions', function (Blueprint $table): void {
            $table->dropForeign(['issued_by']);
            $table->dropIndex(['issue_status']);
            $table->dropColumn([
                'drawing_number_prefix',
                'drawing_title',
                'client_name',
                'site_location',
                'issue_status',
                'document_metadata',
                'issue_history',
                'export_manifest',
                'issued_by',
                'issued_at',
                'last_exported_at',
            ]);
        });
    }
};
