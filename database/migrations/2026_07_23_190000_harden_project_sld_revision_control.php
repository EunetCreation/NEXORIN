<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('project_sld_revisions', function (Blueprint $table): void {
            $table->json('audit_trail')->nullable()->after('issue_history');
            $table->json('export_failures')->nullable()->after('export_manifest');
            $table->timestamp('locked_at')->nullable()->after('last_exported_at');
            $table->foreignId('locked_by')->nullable()->after('locked_at')->constrained('users')->nullOnDelete();
            $table->foreignId('superseded_by_revision_id')->nullable()->after('superseded_at')->constrained('project_sld_revisions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_sld_revisions', function (Blueprint $table): void {
            $table->dropForeign(['locked_by']);
            $table->dropForeign(['superseded_by_revision_id']);
            $table->dropColumn([
                'audit_trail',
                'export_failures',
                'locked_at',
                'locked_by',
                'superseded_by_revision_id',
            ]);
        });
    }
};
