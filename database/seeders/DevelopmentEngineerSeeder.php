<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DevelopmentEngineerSeeder extends Seeder
{
    /**
     * Create the local development Engineer account.
     */
    public function run(): void
    {
        /*
         * Never create development accounts
         * in a production environment.
         */
        if (! app()->environment('local')) {
            $this->command?->warn(
                'DevelopmentEngineerSeeder can only run in the local environment.'
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
                ->where('status', 'active')
                ->first();

            if (! $company) {
                throw new RuntimeException(
                    'The NEXORIN development company is unavailable.'
                );
            }

            $engineerRole = Role::query()
                ->where('code', 'engineer')
                ->where('scope', 'company')
                ->where('is_active', true)
                ->first();

            if (! $engineerRole) {
                throw new RuntimeException(
                    'The engineer role is unavailable.'
                );
            }

            /*
             * Create or update the development Engineer account.
             */
            $engineer = User::query()->updateOrCreate(
                [
                    'email' => 'engineer@nexorin.local',
                ],
                [
                    'name' => 'NEXORIN Engineer',
                    'password' => Hash::make('Engineer@2026!'),
                    'email_verified_at' => now(),
                ]
            );

            /*
             * Create the Engineer membership.
             */
            $membership = CompanyMembership::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'user_id' => $engineer->id,
                ],
                [
                    'status' => 'active',
                    'invited_by' => $superAdmin->id,
                    'joined_at' => now(),
                ]
            );

            /*
             * Assign only the Engineer role.
             */
            $membership->roles()->sync([
                $engineerRole->id => [
                    'assigned_by' => $superAdmin->id,
                ],
            ]);
        });

        $this->command?->info(
            'The NEXORIN development Engineer account is ready.'
        );
    }
}