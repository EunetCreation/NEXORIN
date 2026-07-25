<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'company_id',
    'customer_id',
    'project_number',
    'name',
    'building_type',
    'proposed_system',
    'site_address',
    'city',
    'province',
    'postal_code',
    'latitude',
    'longitude',
    'description',
    'status',
    'assigned_engineer_id',
    'assigned_sales_id',
    'created_by',
    'updated_by',
    'status_updated_by',
    'status_changed_at',
    'archived_at',
])]
class Project extends Model
{
    public const STATUSES = [
        'draft',
        'survey',
        'engineering',
        'quotation',
        'approved',
        'completed',
        'cancelled',
    ];

    public const BUILDING_TYPES = [
        'residential',
        'commercial',
    ];

    public const PROPOSED_SYSTEMS = [
        'undecided',
        'on_grid',
        'hybrid',
    ];

    /**
     * Get the company that owns the project.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the customer associated with the project.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the assigned engineer.
     */
    public function assignedEngineer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_engineer_id');
    }

    /**
     * Get the assigned sales user.
     */
    public function assignedSales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_sales_id');
    }

    /**
     * Get the user who created the project.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the project.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the user who last changed the project status.
     */
    public function statusUpdater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_updated_by');
    }


    /**
     * Get the project load analysis.
     */
    public function loadAnalysis(): HasOne
    {
        return $this->hasOne(LoadAnalysis::class);
    }

    /**
     * Get the project location and solar profile.
     */
    public function solarProfile(): HasOne
    {
        return $this->hasOne(ProjectSolarProfile::class);
    }

    /**
     * Get the project roof and site survey.
     */
    public function roofSiteSurvey(): HasOne
    {
        return $this->hasOne(RoofSiteSurvey::class);
    }

    /**
     * Get the project system recommendation.
     */
    public function systemRecommendation(): HasOne
    {
        return $this->hasOne(SystemRecommendation::class);
    }

    /** Get the project technical calculation. */
    public function technicalCalculation(): HasOne
    {
        return $this->hasOne(TechnicalCalculation::class);
    }

    /** Get the project's SLD revisions, newest first. */
    public function sldRevisions(): HasMany
    {
        return $this->hasMany(ProjectSldRevision::class)
            ->latest('revision')
            ->latest('id');
    }

    /** Get the current, non-superseded SLD revision. */
    public function currentSldRevision(): HasOne
    {
        return $this->hasOne(ProjectSldRevision::class)
            ->where('status', '!=', 'superseded')
            ->latestOfMany('revision');
    }

    /** Get technical calculation overrides attached to this project. */
    public function overrides(): HasMany
    {
        return $this->hasMany(TechnicalCalculationOverride::class);
    }

    /** Get the project BOM/BOQ draft. */
    public function billOfMaterial(): HasOne
    {
        return $this->hasOne(BillOfMaterial::class);
    }

    /** Get the project financial analysis. */
    public function financialAnalysis(): HasOne
    {
        return $this->hasOne(FinancialAnalysis::class);
    }

    /** Get the project quotation. */
    public function quotation(): HasOne
    {
        return $this->hasOne(Quotation::class);
    }

    /** Get all client release approvals for the project. */
    public function releaseApprovals(): HasMany
    {
        return $this->hasMany(ProjectReleaseApproval::class)
            ->latest('approved_at')
            ->latest('id');
    }

    /** Get the currently active client release approval. */
    public function activeReleaseApproval(): HasOne
    {
        return $this->hasOne(ProjectReleaseApproval::class)
            ->where('status', 'active')
            ->latestOfMany('approved_at');
    }

    /**
     * Get roof surfaces recorded for the project.
     */
    public function roofSurfaces(): HasMany
    {
        return $this->hasMany(RoofSurface::class);
    }

    /**
     * Get electricity bills recorded for the project.
     */
    public function electricityBills(): HasMany
    {
        return $this->hasMany(ElectricityBill::class);
    }

    /**
     * Get appliance load records for the project.
     */
    public function loadAppliances(): HasMany
    {
        return $this->hasMany(LoadAppliance::class);
    }

    /**
     * Get the project status history.
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(ProjectStatusHistory::class)
            ->latest('changed_at')
            ->latest('id');
    }

    /**
     * Determine whether the project has been archived.
     */
    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Determine whether a portal user owns the project.
     */
    public function isOwnedBy(User|int $user): bool
    {
        $userId = $user instanceof User
            ? $user->getKey()
            : $user;

        return $this->customer()
            ->where('portal_user_id', $userId)
            ->exists();
    }

    /**
     * Determine whether the requested status transition is allowed.
     */
    public function canTransitionTo(string $status): bool
    {
        if ($status === $this->status) {
            return true;
        }

        return in_array(
            $status,
            $this->allowedNextStatuses(),
            true
        );
    }

    /**
     * Get the allowed next workflow statuses.
     *
     * @return array<int, string>
     */
    public function allowedNextStatuses(): array
    {
        return match ($this->status) {
            'draft' => ['survey', 'cancelled'],
            'survey' => ['draft', 'engineering', 'cancelled'],
            'engineering' => ['survey', 'quotation', 'cancelled'],
            'quotation' => ['engineering', 'approved', 'cancelled'],
            'approved' => ['quotation', 'completed', 'cancelled'],
            'completed' => [],
            'cancelled' => ['draft'],
            default => [],
        };
    }

    /**
     * Get a human-readable status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'survey' => 'Survey',
            'engineering' => 'Engineering',
            'quotation' => 'Quotation',
            'approved' => 'Approved',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }

    /**
     * Get a human-readable building type.
     */
    public function getBuildingTypeLabelAttribute(): string
    {
        return match ($this->building_type) {
            'commercial' => 'Commercial',
            default => 'Residential',
        };
    }

    /**
     * Get a human-readable proposed system.
     */
    public function getProposedSystemLabelAttribute(): string
    {
        return match ($this->proposed_system) {
            'on_grid' => 'On-grid',
            'hybrid' => 'Hybrid',
            default => 'Undecided',
        };
    }

    /**
     * Scope the query to a specific company.
     */
    public function scopeForCompany(
        Builder $query,
        Company|int $company
    ): Builder {
        $companyId = $company instanceof Company
            ? $company->getKey()
            : $company;

        return $query->where('company_id', $companyId);
    }

    /**
     * Scope the query to non-archived projects.
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    /**
     * Scope the query to projects owned by a portal user.
     */
    public function scopeOwnedBy(
        Builder $query,
        User|int $user
    ): Builder {
        $userId = $user instanceof User
            ? $user->getKey()
            : $user;

        return $query->whereHas(
            'customer',
            fn (Builder $customerQuery) => $customerQuery
                ->where('portal_user_id', $userId)
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'status_changed_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }
}
