<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            if (! Schema::hasColumn('quotations', 'version_label')) {
                $table->string('version_label', 40)->nullable()->after('revision_number');
            }

            if (! Schema::hasColumn('quotations', 'snapshot_hash')) {
                $table->string('snapshot_hash', 64)->nullable()->after('source_snapshot');
            }

            if (! Schema::hasColumn('quotations', 'source_fingerprint')) {
                $table->string('source_fingerprint', 64)->nullable()->after('snapshot_hash');
            }

            if (! Schema::hasColumn('quotations', 'client_snapshot')) {
                $table->json('client_snapshot')->nullable()->after('source_fingerprint');
            }

            if (! Schema::hasColumn('quotations', 'internal_snapshot')) {
                $table->json('internal_snapshot')->nullable()->after('client_snapshot');
            }

            if (! Schema::hasColumn('quotations', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('prepared_by')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('quotations', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }

            if (! Schema::hasColumn('quotations', 'approval_notes')) {
                $table->text('approval_notes')->nullable()->after('approved_at');
            }
        });

        Schema::create('quotation_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('quote_number', 70);
            $table->unsignedSmallInteger('revision_number')->default(0);
            $table->string('version_label', 40)->nullable();
            $table->string('status', 40)->default('draft');
            $table->string('currency', 10)->default('IDR');
            $table->string('title', 180)->nullable();
            $table->date('valid_until')->nullable();
            $table->string('snapshot_hash', 64)->nullable();
            $table->string('source_fingerprint', 64)->nullable();
            $table->json('summary')->nullable();
            $table->json('customer_snapshot')->nullable();
            $table->json('project_snapshot')->nullable();
            $table->json('technical_summary')->nullable();
            $table->json('financial_summary')->nullable();
            $table->json('commercial_summary')->nullable();
            $table->json('terms')->nullable();
            $table->json('warnings')->nullable();
            $table->json('source_snapshot')->nullable();
            $table->json('client_snapshot')->nullable();
            $table->json('internal_snapshot')->nullable();
            $table->json('items_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['quotation_id', 'revision_number']);
            $table->index(['company_id', 'status']);
            $table->index(['project_id', 'revision_number']);
            $table->index(['snapshot_hash']);
            $table->index(['source_fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_versions');

        Schema::table('quotations', function (Blueprint $table): void {
            if (Schema::hasColumn('quotations', 'approved_by')) {
                $table->dropConstrainedForeignId('approved_by');
            }

            foreach (['version_label', 'snapshot_hash', 'source_fingerprint', 'client_snapshot', 'internal_snapshot', 'approved_at', 'approval_notes'] as $column) {
                if (Schema::hasColumn('quotations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
