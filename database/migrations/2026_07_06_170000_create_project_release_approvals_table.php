
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_release_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('active')->index();
            $table->string('release_status', 80)->default('Ready for Client Release');
            $table->string('gate_hash', 96)->index();
            $table->json('gate_snapshot')->nullable();
            $table->json('gate_result')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->text('revoke_reason')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'project_id', 'status'], 'pra_company_project_status_idx');
            $table->index(['project_id', 'gate_hash'], 'pra_project_gate_hash_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_release_approvals');
    }
};
