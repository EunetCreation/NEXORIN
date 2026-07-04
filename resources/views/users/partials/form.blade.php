@php
    $editing = isset($user);
    $isSelf = $editing && auth()->id() === $user->id;
    $defaultSuperAdmin = $editing
        ? $user->systemRoles->contains('code', 'super_admin')
        : false;
    $isSuperAdmin = (bool) old('is_super_admin', $defaultSuperAdmin);

    $membershipRows = old('memberships');

    if ($membershipRows === null && $editing) {
        $membershipRows = $user->companyMemberships
            ->map(fn ($membership) => [
                'company_id' => $membership->company_id,
                'role_id' => $membership->roles->first()?->id,
                'status' => $membership->status,
            ])
            ->values()
            ->all();
    }

    if ($membershipRows === null || $membershipRows === []) {
        $membershipRows = [[
            'company_id' => '',
            'role_id' => '',
            'status' => 'active',
        ]];
    }
@endphp

<div class="ui-user-form-stack">
    <section class="ui-form-section">
        <div class="ui-form-section-heading">
            <div>
                <h3>Account Information</h3>
                <p>
                    Configure the user's identity, login credentials, and account status.
                </p>
            </div>
        </div>

        <div class="ui-form-grid">
            <div class="ui-field">
                <label class="ui-label" for="name">Full name</label>
                <input
                    id="name"
                    class="ui-input @error('name') is-invalid @enderror"
                    type="text"
                    name="name"
                    value="{{ old('name', $user->name ?? '') }}"
                    maxlength="255"
                    autocomplete="name"
                    required
                >
                @error('name')
                    <div class="ui-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="ui-field">
                <label class="ui-label" for="email">Email address</label>
                <input
                    id="email"
                    class="ui-input @error('email') is-invalid @enderror"
                    type="email"
                    name="email"
                    value="{{ old('email', $user->email ?? '') }}"
                    maxlength="255"
                    autocomplete="email"
                    required
                >
                @error('email')
                    <div class="ui-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="ui-field">
                <label class="ui-label" for="password">
                    {{ $editing ? 'New password' : 'Temporary password' }}
                </label>
                <input
                    id="password"
                    class="ui-input @error('password') is-invalid @enderror"
                    type="password"
                    name="password"
                    autocomplete="new-password"
                    {{ $editing ? '' : 'required' }}
                >
                <div class="ui-help">
                    {{ $editing ? 'Leave blank to keep the current password.' : 'Use at least 10 characters with uppercase, lowercase, number, and symbol.' }}
                </div>
                @error('password')
                    <div class="ui-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="ui-field">
                <label class="ui-label" for="password_confirmation">
                    Confirm password
                </label>
                <input
                    id="password_confirmation"
                    class="ui-input"
                    type="password"
                    name="password_confirmation"
                    autocomplete="new-password"
                    {{ $editing ? '' : 'required' }}
                >
            </div>

            <div class="ui-field">
                <label class="ui-label" for="status">Account status</label>

                @if ($isSelf)
                    <input type="hidden" name="status" value="active">
                    <select id="status" class="ui-select" disabled>
                        <option value="active" selected>Active</option>
                    </select>
                    <div class="ui-help">
                        You cannot deactivate your own account.
                    </div>
                @else
                    <select
                        id="status"
                        class="ui-select @error('status') is-invalid @enderror"
                        name="status"
                        required
                    >
                        <option value="active" @selected(old('status', $user->status ?? 'active') === 'active')>
                            Active
                        </option>
                        <option value="inactive" @selected(old('status', $user->status ?? 'active') === 'inactive')>
                            Inactive
                        </option>
                    </select>
                @endif

                @error('status')
                    <div class="ui-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="ui-field">
                <label class="ui-label">Platform access</label>

                @if ($isSelf)
                    <input type="hidden" name="is_super_admin" value="1">
                    <label class="ui-switch-card is-locked">
                        <input type="checkbox" checked disabled>
                        <span class="ui-switch-control" aria-hidden="true"></span>
                        <span class="ui-switch-copy">
                            <strong>Super Admin</strong>
                            <small>Your own platform access cannot be removed.</small>
                        </span>
                    </label>
                @else
                    <input type="hidden" name="is_super_admin" value="0">
                    <label class="ui-switch-card">
                        <input
                            type="checkbox"
                            name="is_super_admin"
                            value="1"
                            @checked($isSuperAdmin)
                        >
                        <span class="ui-switch-control" aria-hidden="true"></span>
                        <span class="ui-switch-copy">
                            <strong>Super Admin</strong>
                            <small>Grant unrestricted access across all NEXORIN companies.</small>
                        </span>
                    </label>
                @endif

                @error('is_super_admin')
                    <div class="ui-error">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </section>

    <section class="ui-form-section">
        <div class="ui-form-section-heading ui-form-section-heading-inline">
            <div>
                <h3>Company Access</h3>
                <p>
                    Assign one company role for each company workspace available to this user.
                </p>
            </div>

            <button
                type="button"
                class="ui-button ui-button-secondary ui-button-compact"
                data-add-membership
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                    <path d="M12 5v14M5 12h14"/>
                </svg>
                Add Company Access
            </button>
        </div>

        @if ($errors->has('memberships'))
            <div class="ui-error ui-section-error">{{ $errors->first('memberships') }}</div>
        @endif

        <div
            class="ui-membership-editor"
            data-membership-container
            data-next-index="{{ count($membershipRows) }}"
        >
            @foreach ($membershipRows as $index => $membership)
                <article class="ui-membership-row" data-membership-row>
                    <div class="ui-membership-row-head">
                        <div>
                            <strong>Company Assignment</strong>
                            <span data-membership-number>#{{ $index + 1 }}</span>
                        </div>

                        <button
                            type="button"
                            class="ui-icon-button"
                            aria-label="Remove company assignment"
                            title="Remove company assignment"
                            data-remove-membership
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path d="M5 12h14"/>
                            </svg>
                        </button>
                    </div>

                    <div class="ui-membership-fields">
                        <div class="ui-field">
                            <label class="ui-label" for="membership-company-{{ $index }}">Company</label>
                            <select
                                id="membership-company-{{ $index }}"
                                class="ui-select @error("memberships.$index.company_id") is-invalid @enderror"
                                name="memberships[{{ $index }}][company_id]"
                            >
                                <option value="">Select company</option>
                                @foreach ($companies as $companyOption)
                                    <option
                                        value="{{ $companyOption->id }}"
                                        @selected((string) ($membership['company_id'] ?? '') === (string) $companyOption->id)
                                    >
                                        {{ $companyOption->name }} · {{ $companyOption->code }}{{ $companyOption->status === 'inactive' ? ' · Inactive' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error("memberships.$index.company_id")
                                <div class="ui-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="ui-field">
                            <label class="ui-label" for="membership-role-{{ $index }}">Company role</label>
                            <select
                                id="membership-role-{{ $index }}"
                                class="ui-select @error("memberships.$index.role_id") is-invalid @enderror"
                                name="memberships[{{ $index }}][role_id]"
                            >
                                <option value="">Select role</option>
                                @foreach ($companyRoles as $roleOption)
                                    <option
                                        value="{{ $roleOption->id }}"
                                        @selected((string) ($membership['role_id'] ?? '') === (string) $roleOption->id)
                                    >
                                        {{ $roleOption->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error("memberships.$index.role_id")
                                <div class="ui-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="ui-field">
                            <label class="ui-label" for="membership-status-{{ $index }}">Membership status</label>
                            <select
                                id="membership-status-{{ $index }}"
                                class="ui-select @error("memberships.$index.status") is-invalid @enderror"
                                name="memberships[{{ $index }}][status]"
                            >
                                <option value="active" @selected(($membership['status'] ?? 'active') === 'active')>Active</option>
                                <option value="inactive" @selected(($membership['status'] ?? 'active') === 'inactive')>Inactive</option>
                            </select>
                            @error("memberships.$index.status")
                                <div class="ui-error">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        <template data-membership-template>
            <article class="ui-membership-row" data-membership-row>
                <div class="ui-membership-row-head">
                    <div>
                        <strong>Company Assignment</strong>
                        <span data-membership-number></span>
                    </div>

                    <button
                        type="button"
                        class="ui-icon-button"
                        aria-label="Remove company assignment"
                        title="Remove company assignment"
                        data-remove-membership
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M5 12h14"/>
                        </svg>
                    </button>
                </div>

                <div class="ui-membership-fields">
                    <div class="ui-field">
                        <label class="ui-label" for="membership-company-__INDEX__">Company</label>
                        <select
                            id="membership-company-__INDEX__"
                            class="ui-select"
                            name="memberships[__INDEX__][company_id]"
                        >
                            <option value="">Select company</option>
                            @foreach ($companies as $companyOption)
                                <option value="{{ $companyOption->id }}">
                                    {{ $companyOption->name }} · {{ $companyOption->code }}{{ $companyOption->status === 'inactive' ? ' · Inactive' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="ui-field">
                        <label class="ui-label" for="membership-role-__INDEX__">Company role</label>
                        <select
                            id="membership-role-__INDEX__"
                            class="ui-select"
                            name="memberships[__INDEX__][role_id]"
                        >
                            <option value="">Select role</option>
                            @foreach ($companyRoles as $roleOption)
                                <option value="{{ $roleOption->id }}">{{ $roleOption->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="ui-field">
                        <label class="ui-label" for="membership-status-__INDEX__">Membership status</label>
                        <select
                            id="membership-status-__INDEX__"
                            class="ui-select"
                            name="memberships[__INDEX__][status]"
                        >
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </article>
        </template>
    </section>

    <div class="ui-form-actions">
        <a
            href="{{ $editing ? route('users.show', $user) : route('users.index') }}"
            class="ui-button ui-button-secondary"
        >
            Cancel
        </a>

        <button type="submit" class="ui-button ui-button-primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                <path d="M5 4h12l2 2v14H5z"/>
                <path d="M8 4v6h8V4M8 20v-6h8v6"/>
            </svg>
            {{ $editing ? 'Save Changes' : 'Create User' }}
        </button>
    </div>
</div>

@push('scripts')
    <script>
        (() => {
            const container = document.querySelector('[data-membership-container]');
            const template = document.querySelector('[data-membership-template]');
            const addButton = document.querySelector('[data-add-membership]');

            if (!container || !template || !addButton) {
                return;
            }

            let nextIndex = Number(container.dataset.nextIndex || 0);

            const updateNumbers = () => {
                container.querySelectorAll('[data-membership-row]').forEach((row, index) => {
                    const number = row.querySelector('[data-membership-number]');

                    if (number) {
                        number.textContent = `#${index + 1}`;
                    }
                });
            };

            const bindRemoveButton = (row) => {
                row.querySelector('[data-remove-membership]')?.addEventListener('click', () => {
                    row.remove();
                    updateNumbers();
                });
            };

            container.querySelectorAll('[data-membership-row]').forEach(bindRemoveButton);

            addButton.addEventListener('click', () => {
                const wrapper = document.createElement('div');
                wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(nextIndex));

                const row = wrapper.firstElementChild;

                if (!row) {
                    return;
                }

                container.appendChild(row);
                bindRemoveButton(row);
                nextIndex += 1;
                updateNumbers();
                row.querySelector('select')?.focus();
            });

            updateNumbers();
        })();
    </script>
@endpush
