<?php

namespace App\Services\Quotation;

use App\Models\Quotation;
use App\Models\QuotationEvent;
use App\Models\User;
use Illuminate\Support\Arr;

class QuotationLifecycleService
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function recordEvent(
        Quotation $quotation,
        string $eventType,
        ?User $actor = null,
        ?string $statusFrom = null,
        ?string $statusTo = null,
        ?string $notes = null,
        ?string $channel = null,
        ?string $recipient = null,
        array $metadata = []
    ): QuotationEvent {
        return $quotation->events()->create([
            'company_id' => $quotation->company_id,
            'project_id' => $quotation->project_id,
            'event_type' => $eventType,
            'status_from' => $statusFrom,
            'status_to' => $statusTo,
            'actor_id' => $actor?->getKey(),
            'event_at' => now(),
            'channel' => $channel,
            'recipient' => $recipient,
            'notes' => $notes,
            'metadata' => $metadata,
        ]);
    }

    /**
     * @param  array<string, mixed>  $delivery
     */
    public function markSent(Quotation $quotation, User $actor, array $delivery = []): Quotation
    {
        $from = (string) $quotation->status;
        $method = (string) ($delivery['delivery_method'] ?? 'manual');
        $recipient = $this->nullableString($delivery['sent_to'] ?? null);
        $notes = $this->nullableString($delivery['delivery_notes'] ?? null);

        $quotation->forceFill([
            'status' => 'sent',
            'issued_by' => $actor->getKey(),
            'issued_at' => now(),
            'delivery_method' => $method,
            'sent_to' => $recipient,
            'delivery_notes' => $notes,
            'updated_by' => $actor->getKey(),
        ])->save();

        $quotation->versions()
            ->where('revision_number', $quotation->revision_number)
            ->update([
                'status' => 'sent',
                'updated_at' => now(),
            ]);

        $this->recordEvent(
            $quotation,
            'sent',
            $actor,
            $from,
            'sent',
            $notes,
            $method,
            $recipient,
            [
                'version_label' => $quotation->version_display,
                'pdf_filename' => $quotation->client_proposal_pdf_filename,
                'snapshot_hash' => $quotation->snapshot_hash,
            ]
        );

        return $quotation->refresh();
    }

    public function markAccepted(Quotation $quotation, User $actor, ?string $notes = null): Quotation
    {
        $from = (string) $quotation->status;
        $cleanNotes = $this->nullableString($notes);

        $quotation->forceFill([
            'status' => 'accepted',
            'accepted_by' => $actor->getKey(),
            'accepted_at' => now(),
            'acceptance_notes' => $cleanNotes,
            'updated_by' => $actor->getKey(),
        ])->save();

        $quotation->versions()
            ->where('revision_number', $quotation->revision_number)
            ->update([
                'status' => 'accepted',
                'updated_at' => now(),
            ]);

        $this->recordEvent($quotation, 'accepted', $actor, $from, 'accepted', $cleanNotes, null, null, [
            'version_label' => $quotation->version_display,
        ]);

        return $quotation->refresh();
    }

    public function markRejected(Quotation $quotation, User $actor, string $reason): Quotation
    {
        $from = (string) $quotation->status;
        $cleanReason = trim($reason);

        $quotation->forceFill([
            'status' => 'rejected',
            'rejected_by' => $actor->getKey(),
            'rejected_at' => now(),
            'rejection_reason' => $cleanReason,
            'updated_by' => $actor->getKey(),
        ])->save();

        $quotation->versions()
            ->where('revision_number', $quotation->revision_number)
            ->update([
                'status' => 'rejected',
                'updated_at' => now(),
            ]);

        $this->recordEvent($quotation, 'rejected', $actor, $from, 'rejected', $cleanReason, null, null, [
            'version_label' => $quotation->version_display,
        ]);

        return $quotation->refresh();
    }

    public function markCancelled(Quotation $quotation, User $actor, ?string $reason = null): Quotation
    {
        $from = (string) $quotation->status;
        $cleanReason = $this->nullableString($reason);

        $quotation->forceFill([
            'status' => 'cancelled',
            'cancelled_by' => $actor->getKey(),
            'cancelled_at' => now(),
            'cancellation_reason' => $cleanReason,
            'updated_by' => $actor->getKey(),
        ])->save();

        $quotation->versions()
            ->where('revision_number', $quotation->revision_number)
            ->update([
                'status' => 'cancelled',
                'updated_at' => now(),
            ]);

        $this->recordEvent($quotation, 'cancelled', $actor, $from, 'cancelled', $cleanReason, null, null, [
            'version_label' => $quotation->version_display,
        ]);

        return $quotation->refresh();
    }

    public function expireIfNeeded(Quotation $quotation, ?User $actor = null, ?string $reason = null): bool
    {
        if (! $quotation->shouldBeConsideredExpired()) {
            return false;
        }

        $from = (string) $quotation->status;
        $cleanReason = $this->nullableString($reason) ?: 'Quotation validity period has ended.';

        $quotation->forceFill([
            'status' => 'expired',
            'expired_at' => now(),
            'expiry_reason' => $cleanReason,
            'updated_by' => $actor?->getKey() ?? $quotation->updated_by,
        ])->save();

        $quotation->versions()
            ->where('revision_number', $quotation->revision_number)
            ->update([
                'status' => 'expired',
                'updated_at' => now(),
            ]);

        $this->recordEvent($quotation, 'expired', $actor, $from, 'expired', $cleanReason, null, null, [
            'valid_until' => optional($quotation->valid_until)->toDateString(),
            'version_label' => $quotation->version_display,
        ]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function recordGenerated(Quotation $quotation, User $actor, array $metadata = []): QuotationEvent
    {
        return $this->recordEvent(
            $quotation,
            ((int) $quotation->revision_number) > 0 ? 'revised' : 'generated',
            $actor,
            null,
            (string) $quotation->status,
            Arr::get($metadata, 'notes'),
            null,
            null,
            $metadata
        );
    }

    private function nullableString(mixed $value): ?string
    {
        $clean = trim((string) $value);

        return $clean !== '' ? $clean : null;
    }
}
