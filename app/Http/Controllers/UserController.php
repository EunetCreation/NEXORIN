<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Display the user directory.
     */
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'company' => [
                'nullable',
                'integer',
                Rule::exists('companies', 'id'),
            ],
            'role' => [
                'nullable',
                'integer',
                Rule::exists('roles', 'id'),
            ],
            'sort' => [
                'nullable',
                Rule::in([
                    'name',
                    'email',
                    'status',
                    'created_at',
                ]),
            ],
            'direction' => [
                'nullable',
                Rule::in(['asc', 'desc']),
            ],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $status = $validated['status'] ?? null;

        $companyId = isset($validated['company'])
            ? (int) $validated['company']
            : null;

        $roleId = isset($validated['role'])
            ? (int) $validated['role']
            : null;

        $sort = $validated['sort'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';

        /*
         * PostgreSQL uses ILIKE for case-insensitive searches.
         * SQLite and MySQL can use LIKE.
         */
        $searchOperator = DB::connection()->getDriverName() === 'pgsql'
            ? 'ilike'
            : 'like';

        $users = User::query()
            ->with([
                'systemRoles:id,code,name',
                'companyMemberships' => fn ($query) => $query
                    ->with([
                        'company:id,name,code,status',
                        'roles:id,code,name',
                    ])
                    ->latest('id'),
            ])
            ->withCount([
                'companyMemberships',
                'activeCompanyMemberships',
            ])
            ->when(
                $search !== '',
                function (
                    Builder $query
                ) use (
                    $search,
                    $searchOperator
                ): void {
                    $query->where(
                        function (
                            Builder $userQuery
                        ) use (
                            $search,
                            $searchOperator
                        ): void {
                            $userQuery
                                ->where(
                                    'name',
                                    $searchOperator,
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'email',
                                    $searchOperator,
                                    "%{$search}%"
                                );
                        }
                    );
                }
            )
            ->when(
                $status,
                fn (Builder $query) => $query
                    ->where('status', $status)
            )
            ->when(
                $companyId,
                fn (Builder $query) => $query
                    ->whereHas(
                        'companyMemberships',
                        fn (
                            Builder $membershipQuery
                        ) => $membershipQuery
                            ->where('company_id', $companyId)
                    )
            )
            ->when(
                $roleId,
                fn (Builder $query) => $query
                    ->where(
                        function (
                            Builder $roleQuery
                        ) use (
                            $roleId
                        ): void {
                            $roleQuery
                                ->whereHas(
                                    'systemRoles',
                                    fn (
                                        Builder $systemRoleQuery
                                    ) => $systemRoleQuery
                                        ->whereKey($roleId)
                                )
                                ->orWhereHas(
                                    'companyMemberships.roles',
                                    fn (
                                        Builder $companyRoleQuery
                                    ) => $companyRoleQuery
                                        ->whereKey($roleId)
                                );
                        }
                    )
            )
            ->orderBy($sort, $direction)
            ->paginate(10)
            ->withQueryString();

        $stats = [
            'total' => User::query()->count(),

            'active' => User::query()
                ->active()
                ->count(),

            'inactive' => User::query()
                ->where('status', 'inactive')
                ->count(),

            'super_admins' => User::query()
                ->whereHas(
                    'systemRoles',
                    fn (Builder $query) => $query
                        ->where('roles.code', 'super_admin')
                        ->where('roles.is_active', true)
                )
                ->count(),
        ];

        return view('users.index', [
            'users' => $users,

            'stats' => $stats,

            'companies' => Company::query()
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                    'code',
                    'status',
                ]),

            'roles' => Role::query()
                ->active()
                ->orderBy('scope')
                ->orderBy('name')
                ->get([
                    'id',
                    'code',
                    'name',
                    'scope',
                ]),

            'filters' => [
                'search' => $search,
                'status' => $status,
                'company' => $companyId,
                'role' => $roleId,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    /**
     * Show the form for creating a user.
     */
    public function create(): View
    {
        return view(
            'users.create',
            $this->formOptions()
        );
    }

    /**
     * Store a newly created user.
     */
    public function store(
        StoreUserRequest $request
    ): RedirectResponse {
        $user = DB::transaction(
            function () use ($request): User {
                $data = $request->validated();

                $user = User::query()->create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'email_verified_at' => now(),
                    'password' => Hash::make(
                        $data['password']
                    ),
                    'status' => $data['status'],
                ]);

                $this->syncAccess(
                    $user,
                    $data,
                    $request->user()
                );

                return $user;
            }
        );

        return redirect()
            ->route('users.show', $user)
            ->with(
                'success',
                'User created successfully.'
            );
    }

    /**
     * Display a user profile.
     */
    public function show(User $user): View
    {
        $user->load([
            'systemRoles:id,code,name,description',

            'companyMemberships' => fn ($query) => $query
                ->with([
                    'company:id,name,code,status',
                    'roles:id,code,name,description',
                    'inviter:id,name,email',
                ])
                ->orderByDesc('status')
                ->latest('joined_at')
                ->latest('id'),
        ]);

        $user->loadCount([
            'companyMemberships',
            'activeCompanyMemberships',
        ]);

        return view('users.show', [
            'user' => $user,
        ]);
    }

    /**
     * Show the form for editing a user.
     */
    public function edit(User $user): View
    {
        $user->load([
            'systemRoles:id,code,name',

            'companyMemberships' => fn ($query) => $query
                ->with('roles:id,code,name')
                ->orderBy('company_id'),
        ]);

        return view('users.edit', [
            'user' => $user,
            ...$this->formOptions(),
        ]);
    }

    /**
     * Update a user.
     */
    public function update(
        UpdateUserRequest $request,
        User $user
    ): RedirectResponse {
        $data = $request->validated();
        $actor = $request->user();
        $shouldBeSuperAdmin = (bool) $data['is_super_admin'];

        if (
            $actor->is($user)
            && $data['status'] !== 'active'
        ) {
            return back()
                ->withInput()
                ->with(
                    'error',
                    'You cannot deactivate your own account.'
                );
        }

        if (
            $actor->is($user)
            && ! $shouldBeSuperAdmin
        ) {
            return back()
                ->withInput()
                ->with(
                    'error',
                    'You cannot remove your own Super Admin access.'
                );
        }

        if (
            $this->isLastActiveSuperAdmin($user)
            && (
                $data['status'] !== 'active'
                || ! $shouldBeSuperAdmin
            )
        ) {
            return back()
                ->withInput()
                ->with(
                    'error',
                    'At least one active Super Admin must remain.'
                );
        }

        DB::transaction(
            function () use (
                $data,
                $user,
                $actor
            ): void {
                $accountData = [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'status' => $data['status'],
                ];

                if (filled($data['password'] ?? null)) {
                    $accountData['password'] = Hash::make(
                        $data['password']
                    );
                }

                $user->update($accountData);

                $this->syncAccess(
                    $user,
                    $data,
                    $actor
                );
            }
        );

        return redirect()
            ->route('users.show', $user)
            ->with(
                'success',
                'User updated successfully.'
            );
    }

    /**
     * Update a user status from the user directory.
     */
    public function updateStatus(
        Request $request,
        User $user
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => [
                'required',
                Rule::in([
                    'active',
                    'inactive',
                ]),
            ],
        ]);

        if (
            $request->user()->is($user)
            && $validated['status'] === 'inactive'
        ) {
            return back()->with(
                'error',
                'You cannot deactivate your own account.'
            );
        }

        if (
            $validated['status'] === 'inactive'
            && $this->isLastActiveSuperAdmin($user)
        ) {
            return back()->with(
                'error',
                'At least one active Super Admin must remain.'
            );
        }

        $user->update([
            'status' => $validated['status'],
        ]);

        return back()->with(
            'success',
            $validated['status'] === 'active'
                ? 'User activated successfully.'
                : 'User deactivated successfully.'
        );
    }

    /**
     * Get form option collections.
     *
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'companies' => Company::query()
                ->orderByDesc('status')
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                    'code',
                    'status',
                ]),

            'companyRoles' => Role::query()
                ->active()
                ->where('scope', 'company')
                ->orderBy('name')
                ->get([
                    'id',
                    'code',
                    'name',
                    'description',
                ]),
        ];
    }

    /**
     * Synchronize system roles and company memberships.
     *
     * @param array<string, mixed> $data
     */
    private function syncAccess(
        User $user,
        array $data,
        User $actor
    ): void {
        $superAdminRole = Role::query()
            ->where('code', 'super_admin')
            ->where('scope', 'system')
            ->where('is_active', true)
            ->firstOrFail();

        if ((bool) $data['is_super_admin']) {
            $user->systemRoles()->syncWithoutDetaching([
                $superAdminRole->getKey() => [
                    'assigned_by' => $actor->getKey(),
                ],
            ]);
        } else {
            $user->systemRoles()->detach(
                $superAdminRole->getKey()
            );
        }

        $memberships = collect(
            $data['memberships'] ?? []
        );

        $companyIds = $memberships
            ->pluck('company_id')
            ->map(
                fn (mixed $companyId): int => (int) $companyId
            )
            ->all();

        $membershipsToDelete = $user
            ->companyMemberships();

        if ($companyIds !== []) {
            $membershipsToDelete
                ->whereNotIn(
                    'company_id',
                    $companyIds
                );
        }

        $membershipsToDelete->delete();

        foreach ($memberships as $membershipData) {
            $membership = CompanyMembership::query()
                ->firstOrNew([
                    'company_id' => (int) $membershipData['company_id'],
                    'user_id' => $user->getKey(),
                ]);

            if (! $membership->exists) {
                $membership->invited_by = $actor->getKey();
            }

            $membership->status = $membershipData['status'];

            if (
                $membershipData['status'] === 'active'
                && $membership->joined_at === null
            ) {
                $membership->joined_at = now();
            }

            $membership->save();

            $membership->roles()->sync([
                (int) $membershipData['role_id'] => [
                    'assigned_by' => $actor->getKey(),
                ],
            ]);
        }
    }

    /**
     * Determine whether the user is the final active Super Admin.
     */
    private function isLastActiveSuperAdmin(
        User $user
    ): bool {
        if (
            ! $user->isActive()
            || ! $user->isSuperAdmin()
        ) {
            return false;
        }

        return User::query()
            ->active()
            ->whereHas(
                'systemRoles',
                fn (Builder $query) => $query
                    ->where('roles.code', 'super_admin')
                    ->where('roles.is_active', true)
            )
            ->count() <= 1;
    }
}