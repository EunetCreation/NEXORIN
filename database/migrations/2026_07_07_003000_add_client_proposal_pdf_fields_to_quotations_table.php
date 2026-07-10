<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            if (! Schema::hasColumn('quotations', 'client_proposal_pdf_generated_at')) {
                $table->timestamp('client_proposal_pdf_generated_at')->nullable()->after('issued_at');
            }

            if (! Schema::hasColumn('quotations', 'client_proposal_pdf_snapshot_hash')) {
                $table->string('client_proposal_pdf_snapshot_hash', 64)->nullable()->after('client_proposal_pdf_generated_at');
            }

            if (! Schema::hasColumn('quotations', 'client_proposal_pdf_source_fingerprint')) {
                $table->string('client_proposal_pdf_source_fingerprint', 64)->nullable()->after('client_proposal_pdf_snapshot_hash');
            }

            if (! Schema::hasColumn('quotations', 'client_proposal_pdf_filename')) {
                $table->string('client_proposal_pdf_filename', 160)->nullable()->after('client_proposal_pdf_source_fingerprint');
            }

            if (! Schema::hasColumn('quotations', 'client_proposal_pdf_generated_by')) {
                $table->foreignId('client_proposal_pdf_generated_by')->nullable()->after('client_proposal_pdf_filename')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            if (Schema::hasColumn('quotations', 'client_proposal_pdf_generated_by')) {
                $table->dropConstrainedForeignId('client_proposal_pdf_generated_by');
            }

            foreach ([
                'client_proposal_pdf_generated_at',
                'client_proposal_pdf_snapshot_hash',
                'client_proposal_pdf_source_fingerprint',
                'client_proposal_pdf_filename',
            ] as $column) {
                if (Schema::hasColumn('quotations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
