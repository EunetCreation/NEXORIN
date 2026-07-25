<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('material_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 80);
            $table->string('title', 180);
            $table->string('original_filename', 220)->nullable();
            $table->string('file_path', 255)->nullable();
            $table->string('external_url', 255)->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->string('revision', 60)->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('verification_status', 40)->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('verification_notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['material_id', 'document_type', 'is_active']);
            $table->index(['verification_status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_documents');
    }
};
