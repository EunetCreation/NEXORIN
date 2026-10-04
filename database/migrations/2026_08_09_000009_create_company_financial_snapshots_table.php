<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('company_financial_snapshots')) {
            return;
        }

        Schema::create('company_financial_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('period_end');
            $table->string('currency', 3)->default('USD');
            $table->decimal('annual_revenue', 15, 2)->nullable();
            $table->decimal('ebitda_margin', 7, 2)->nullable();
            $table->decimal('current_ratio', 9, 2)->nullable();
            $table->decimal('total_assets', 15, 2)->nullable();
            $table->decimal('net_profit', 15, 2)->nullable();
            $table->decimal('debt_to_equity', 9, 2)->nullable();
            $table->foreignId('source_document_id')->nullable()->constrained('company_documents')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'period_end']);
            $table->index(['company_id', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_financial_snapshots');
    }
};
