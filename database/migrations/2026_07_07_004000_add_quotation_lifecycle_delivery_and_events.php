<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            if (! Schema::hasColumn('quotations', 'delivery_method')) {
                $table->string('delivery_method', 40)->nullable()->after('issued_at');
            }

            if (! Schema::hasColumn('quotations', 'sent_to')) {
                $table->string('sent_to', 190)->nullable()->after('delivery_method');
            }

            if (! Schema::hasColumn('quotations', 'delivery_notes')) {
                $table->text('delivery_notes')->nullable()->after('sent_to');
            }

            if (! Schema::hasColumn('quotations', 'accepted_by')) {
                $table->foreignId('accepted_by')->nullable()->after('delivery_notes')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('quotations', 'accepted_at')) {
                $table->timestamp('accepted_at')->nullable()->after('accepted_by');
            }

            if (! Schema::hasColumn('quotations', 'acceptance_notes')) {
                $table->text('acceptance_notes')->nullable()->after('accepted_at');
            }

            if (! Schema::hasColumn('quotations', 'rejected_by')) {
                $table->foreignId('rejected_by')->nullable()->after('acceptance_notes')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('quotations', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            }

            if (! Schema::hasColumn('quotations', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('rejected_at');
            }

            if (! Schema::hasColumn('quotations', 'cancelled_by')) {
                $table->foreignId('cancelled_by')->nullable()->after('rejection_reason')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('quotations', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
            }

            if (! Schema::hasColumn('quotations', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('cancelled_at');
            }

            if (! Schema::hasColumn('quotations', 'expired_at')) {
                $table->timestamp('expired_at')->nullable()->after('cancellation_reason');
            }

            if (! Schema::hasColumn('quotations', 'expiry_reason')) {
                $table->text('expiry_reason')->nullable()->after('expired_at');
            }
        });

        if (! Schema::hasTable('quotation_events')) {
            Schema::create('quotation_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
                $table->string('event_type', 60);
                $table->string('status_from', 40)->nullable();
                $table->string('status_to', 40)->nullable();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('event_at')->nullable();
                $table->string('channel', 40)->nullable();
                $table->string('recipient', 190)->nullable();
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['quotation_id', 'event_at']);
                $table->index(['company_id', 'event_type']);
                $table->index(['project_id', 'event_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_events');

        Schema::table('quotations', function (Blueprint $table): void {
            foreach (['accepted_by', 'rejected_by', 'cancelled_by'] as $column) {
                if (Schema::hasColumn('quotations', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }

            foreach ([
                'delivery_method',
                'sent_to',
                'delivery_notes',
                'accepted_at',
                'acceptance_notes',
                'rejected_at',
                'rejection_reason',
                'cancelled_at',
                'cancellation_reason',
                'expired_at',
                'expiry_reason',
            ] as $column) {
                if (Schema::hasColumn('quotations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
