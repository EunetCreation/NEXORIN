<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;

#[Fillable([
    'company_id',
    'project_id',
    'revision',
    'status',
    'system_type',
    'graph',
    'source_snapshot',
    'warnings',
    'notes',
    'review_notes',
    'generated_by',
    'generated_at',
    'submitted_by',
    'submitted_at',
    'reviewed_by',
    'reviewed_at',
    'approved_by',
    'approved_at',
    'superseded_at',
    'drawing_number_prefix',
    'drawing_title',
    'client_name',
    'site_location',
    'issue_status',
    'document_metadata',
    'issue_history',
    'audit_trail',
    'export_manifest',
    'export_failures',
    'issued_by',
    'issued_at',
    'last_exported_at',
    'locked_at',
    'locked_by',
    'superseded_by_revision_id',
])]
class ProjectSldRevision extends Model
{
    public const STATUSES = [
        'draft',
        'needs_review',
        'checked',
        'approved',
        'superseded',
    ];

    protected $attributes = [
        'status' => 'draft',
        'issue_status' => 'draft',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $revision): void {
            $revision->assertImmutableMutationAllowed();
        });
    }

    /**
     * Protect issued and superseded records even when a future controller,
     * command, job, or maintenance script bypasses the current UI guards.
     */
    public function assertImmutableMutationAllowed(): void
    {
        if (! $this->exists) {
            return;
        }

        $originalIssueStatus = (string) ($this->getRawOriginal('issue_status') ?: 'draft');
        $originalStatus = (string) ($this->getRawOriginal('status') ?: 'draft');
        $originalLockedAt = $this->getRawOriginal('locked_at');
        $wasControlled = $originalStatus === 'superseded'
            || $originalLockedAt !== null
            || in_array($originalIssueStatus, ['issued_for_tender', 'issued_for_construction', 'as_built'], true);

        if (! $wasControlled) {
            return;
        }

        $dirty = array_keys($this->getDirty());
        if ($dirty === []) {
            return;
        }

        $allowed = [
            'audit_trail',
            'export_manifest',
            'export_failures',
            'last_exported_at',
            'updated_at',
        ];

        // Complete the formal lock exactly once after the issue transition.
        // Existing issue or lock evidence may not be replaced during closure.
        if (
            $originalLockedAt === null
            && in_array($originalIssueStatus, ['issued_for_tender', 'issued_for_construction', 'as_built'], true)
            && $this->locked_at !== null
            && $this->getRawOriginal('issued_by') === null
            && $this->getRawOriginal('issued_at') === null
            && $this->getRawOriginal('locked_by') === null
        ) {
            $allowed = array_merge($allowed, ['issued_by', 'issued_at', 'locked_by', 'locked_at']);
        }

        // A controlled record may receive supersession lineage once. After it
        // is superseded, the lineage itself is immutable evidence.
        if (
            $originalStatus !== 'superseded'
            && (string) $this->status === 'superseded'
            && $this->getRawOriginal('superseded_at') === null
            && $this->getRawOriginal('superseded_by_revision_id') === null
        ) {
            $allowed = array_merge($allowed, ['status', 'superseded_at', 'superseded_by_revision_id']);
        }

        $blocked = array_values(array_diff($dirty, array_unique($allowed)));
        if ($blocked !== []) {
            throw new \LogicException(
                'Issued or superseded SLD revisions are immutable. Blocked field(s): '.implode(', ', $blocked).'.'
            );
        }

        if (in_array('audit_trail', $dirty, true)) {
            $this->assertSingleAppendHistory('audit_trail', 200);
        }
        if (in_array('export_failures', $dirty, true)) {
            $this->assertSingleAppendHistory('export_failures', 50);
        }
        if (in_array('export_manifest', $dirty, true)) {
            $this->assertExportHistoryMutationAllowed();
        }
        if (in_array('last_exported_at', $dirty, true)) {
            $before = $this->getRawOriginal('last_exported_at');
            $after = $this->last_exported_at;
            if ($after === null || ($before !== null && $after->getTimestamp() < strtotime((string) $before))) {
                throw new \LogicException('Controlled SLD last-exported evidence cannot move backwards or be cleared.');
            }
        }
    }

    private function assertSingleAppendHistory(string $attribute, int $limit): void
    {
        $before = array_values(array_slice($this->originalArray($attribute), -$limit));
        $after = array_values(array_slice($this->currentArray($attribute), -$limit));

        $valid = count($before) < $limit
            ? count($after) === count($before) + 1 && array_slice($after, 0, count($before)) === $before
            : count($after) === $limit && array_slice($after, 0, $limit - 1) === array_slice($before, 1);

        if (! $valid) {
            throw new \LogicException("Controlled SLD {$attribute} evidence must append exactly one event without rewriting history.");
        }
    }

    private function assertExportHistoryMutationAllowed(): void
    {
        $before = $this->originalArray('export_manifest');
        $after = $this->currentArray('export_manifest');
        $beforeEvents = array_values(array_slice(is_array($before['events'] ?? null) ? $before['events'] : [], -50));
        $afterEvents = array_values(array_slice(is_array($after['events'] ?? null) ? $after['events'] : [], -50));

        if ($this->isSingleHistoryAppend($beforeEvents, $afterEvents, 50)) {
            $appended = $afterEvents[array_key_last($afterEvents)] ?? null;
            $latest = is_array($after['latest'] ?? null) ? $after['latest'] : [];
            $format = strtolower((string) ($latest['format'] ?? ''));
            $latestComparable = Arr::except($latest, ['document_control', 'release_assessment']);
            $latestByFormat = is_array($after['latest_by_format'] ?? null) ? $after['latest_by_format'] : [];
            $formatLatest = $format !== '' && is_array($latestByFormat[$format] ?? null)
                ? $latestByFormat[$format]
                : null;

            $otherFormatsBefore = is_array($before['latest_by_format'] ?? null) ? $before['latest_by_format'] : [];
            $otherFormatsAfter = is_array($after['latest_by_format'] ?? null) ? $after['latest_by_format'] : [];
            if ($format !== '') {
                unset($otherFormatsBefore[$format], $otherFormatsAfter[$format]);
            }

            $valid = ($after['version'] ?? null) === 'nexorin-sld-export-history-v3'
                && is_array($appended)
                && $latestComparable === $appended
                && $format !== ''
                && $formatLatest === $latest
                && $otherFormatsAfter === $otherFormatsBefore;

            if ($valid) {
                return;
            }
        }

        // Package verification may update only the nested verification result
        // of an existing export event. All other history data must remain exact.
        $beforeWithoutVerification = $this->withoutExportVerification($before);
        $afterWithoutVerification = $this->withoutExportVerification($after);
        $changedIndexes = collect($beforeEvents)
            ->keys()
            ->filter(fn (int $index): bool => ($beforeEvents[$index] ?? null) !== ($afterEvents[$index] ?? null))
            ->values();
        $changedEventId = $changedIndexes->count() === 1
            ? (string) data_get($afterEvents, $changedIndexes->first().'.event_id', '')
            : '';
        if (
            count($beforeEvents) === count($afterEvents)
            && $changedIndexes->count() === 1
            && $changedEventId !== ''
            && $this->verificationDuplicatesMatchEvent($before, $after, $changedEventId)
            && $beforeWithoutVerification === $afterWithoutVerification
            && $before !== $after
        ) {
            return;
        }

        throw new \LogicException('Controlled SLD export history may only append one export event or update package verification evidence.');
    }

    private function verificationDuplicatesMatchEvent(array $before, array $after, string $eventId): bool
    {
        if (($before['latest'] ?? null) !== ($after['latest'] ?? null)) {
            if ((string) data_get($after, 'latest.event_id', '') !== $eventId) {
                return false;
            }
        }

        $formats = array_unique(array_merge(
            array_keys(is_array($before['latest_by_format'] ?? null) ? $before['latest_by_format'] : []),
            array_keys(is_array($after['latest_by_format'] ?? null) ? $after['latest_by_format'] : [])
        ));
        foreach ($formats as $format) {
            $beforeEvent = $before['latest_by_format'][$format] ?? null;
            $afterEvent = $after['latest_by_format'][$format] ?? null;
            if ($beforeEvent !== $afterEvent && (string) data_get($afterEvent, 'event_id', '') !== $eventId) {
                return false;
            }
        }

        return true;
    }

    /** @param array<int, mixed> $before @param array<int, mixed> $after */
    private function isSingleHistoryAppend(array $before, array $after, int $limit): bool
    {
        return count($before) < $limit
            ? count($after) === count($before) + 1 && array_slice($after, 0, count($before)) === $before
            : count($after) === $limit && array_slice($after, 0, $limit - 1) === array_slice($before, 1);
    }

    /** @return array<string, mixed> */
    private function withoutExportVerification(array $history): array
    {
        $strip = static function (mixed $event): mixed {
            return is_array($event) ? Arr::except($event, ['verification']) : $event;
        };

        if (is_array($history['latest'] ?? null)) {
            $history['latest'] = $strip($history['latest']);
        }
        if (is_array($history['latest_by_format'] ?? null)) {
            $history['latest_by_format'] = collect($history['latest_by_format'])
                ->map($strip)
                ->all();
        }
        if (is_array($history['events'] ?? null)) {
            $history['events'] = collect($history['events'])
                ->map($strip)
                ->values()
                ->all();
        }

        return $history;
    }

    /** @return array<string, mixed> */
    private function originalArray(string $attribute): array
    {
        return $this->decodeArray($this->getRawOriginal($attribute));
    }

    /** @return array<string, mixed> */
    private function currentArray(string $attribute): array
    {
        return $this->decodeArray($this->getAttribute($attribute));
    }

    /** @return array<string, mixed> */
    private function decodeArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_revision_id');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query
            ->where('status', '!=', 'superseded')
            ->latest('revision')
            ->latest('id');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'needs_review' => 'Needs Engineer Review',
            'checked' => 'Engineer Checked',
            'approved' => 'Engineer Approved',
            'superseded' => 'Superseded Revision',
            default => 'Draft SLD',
        };
    }

    public function getStatusToneAttribute(): string
    {
        return match ($this->status) {
            'approved' => 'success',
            'checked' => 'success',
            'needs_review' => 'warning',
            'superseded' => 'muted',
            default => 'info',
        };
    }


    public function getIssueStatusLabelAttribute(): string
    {
        return app(\App\Services\Sld\SldDocumentControl::class)
            ->issueLabel((string) ($this->issue_status ?: 'draft'));
    }

    public function getIssueCodeAttribute(): string
    {
        return app(\App\Services\Sld\SldDocumentControl::class)
            ->issueCode((string) ($this->issue_status ?: 'draft'));
    }

    public function isIssued(): bool
    {
        return in_array($this->issue_status, ['issued_for_tender', 'issued_for_construction', 'as_built'], true);
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null || $this->isIssued();
    }

    public function isReadOnly(): bool
    {
        return $this->status === 'superseded' || $this->isLocked();
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved' && $this->approved_at !== null;
    }

    public function hasCriticalWarnings(): bool
    {
        return collect($this->warnings ?? [])
            ->contains(fn (array $warning): bool => ($warning['severity'] ?? null) === 'critical');
    }

    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'graph' => 'array',
            'source_snapshot' => 'array',
            'warnings' => 'array',
            'document_metadata' => 'array',
            'issue_history' => 'array',
            'audit_trail' => 'array',
            'export_manifest' => 'array',
            'export_failures' => 'array',
            'generated_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'superseded_at' => 'datetime',
            'issued_at' => 'datetime',
            'last_exported_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }
}
