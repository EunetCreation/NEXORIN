<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DevelopmentRbacUsersSeeder extends Seeder
{
    /**
     * Create local accounts for manual RBAC testing.
     */
    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn(
                'DevelopmentRbacUsersSeeder can only run in the local environment.'
            );

            return;
        }

        DB::transaction(function (): void {
            $superAdmin = User::query()
                ->where('email', 'admin@nexorin.local')
                ->first();

            if (! $superAdmin) {
                throw new RuntimeException(
                    'The development Super Admin account is unavailable.'
                );
            }

            $company = Company::query()
                ->where('slug', 'nexorin-energy')
                ->first();

            if (! $company) {
                throw new RuntimeException(
                    'The NEXORIN development company is unavailable.'
                );
            }

            $accounts = [
                [
                    'role' => 'company_admin',
                    'name' => 'NEXORIN Company Admin',
                    'email' => 'company.admin@nexorin.local',
                    'password' => 'CompanyAdmin@2026!',
                ],
                [
                    'role' => 'engineer',
                    'name' => 'NEXORIN Engineer',
                    'email' => 'engineer@nexorin.local',
                    'password' => 'Engineer@2026!',
                ],
                [
                    'role' => 'sales',
                    'name' => 'NEXORIN Sales',
                    'email' => 'sales@nexorin.local',
                    'password' => 'Sales@2026!',
                ],
                [
                    'role' => 'customer',
                    'name' => 'NEXORIN Customer',
                    'email' => 'customer@nexorin.local',
                    'password' => 'Customer@2026!',
                ],
            ];

            $customerPortalUser = null;

            foreach ($accounts as $account) {
                $role = Role::query()
                    ->where('code', $account['role'])
                    ->where('scope', 'company')
                    ->where('is_active', true)
                    ->first();

                if (! $role) {
                    throw new RuntimeException(
                        "The {$account['role']} role is unavailable."
                    );
                }

                $user = User::query()->updateOrCreate(
                    ['email' => $account['email']],
                    [
                        'name' => $account['name'],
                        'password' => Hash::make($account['password']),
                        'email_verified_at' => now(),
                        'status' => 'active',
                    ]
                );

                $membership = CompanyMembership::query()->updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'user_id' => $user->id,
                    ],
                    [
                        'status' => 'active',
                        'invited_by' => $superAdmin->id,
                        'joined_at' => now(),
                    ]
                );

                $membership->roles()->sync([
                    $role->id => [
                        'assigned_by' => $superAdmin->id,
                    ],
                ]);

                if ($account['role'] === 'customer') {
                    $customerPortalUser = $user;
                }
            }

            if ($customerPortalUser) {
                $customer = Customer::query()->updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'portal_user_id' => $customerPortalUser->id,
                    ],
                    [
                        'name' => 'NEXORIN Demo Customer',
                        'type' => 'residential',
                        'email' => $customerPortalUser->email,
                        'phone' => '+62 812-0000-2026',
                        'address' => 'Pekanbaru, Riau',
                        'city' => 'Pekanbaru',
                        'province' => 'Riau',
                        'notes' => 'Development customer profile for ownership testing.',
                        'status' => 'active',
                        'archived_at' => null,
                        'created_by' => $superAdmin->id,
                        'updated_by' => $superAdmin->id,
                    ]
                );

                if (! $customer->customer_number) {
                    $customer->update([
                        'customer_number' => sprintf(
                            '%s-CUS-%05d',
                            strtoupper($company->code),
                            $customer->id
                        ),
                    ]);
                }
            }
        });

        $this->command?->info(
            'The NEXORIN RBAC development accounts are ready.'
        );
    }
}
