<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DevelopmentCompanySeeder extends Seeder
{
    /**
     * Create the initial development company and owner membership.
     */
    public function run(): void
    {
        /*
         * Never create development data
         * in a production environment.
         */
        if (! app()->environment('local')) {
            $this->command?->warn(
                'DevelopmentCompanySeeder can only run in the local environment.'
            );

            return;
        }

        DB::transaction(function (): void {
            $user = User::query()
                ->where('email', 'admin@nexorin.local')
                ->first();

            if (! $user) {
                throw new RuntimeException(
                    'The development Super Admin account is unavailable. Run SuperAdminSeeder first.'
                );
            }

            $companyOwnerRole = Role::query()
                ->where('code', 'company_owner')
                ->where('scope', 'company')
                ->where('is_active', true)
                ->first();

            if (! $companyOwnerRole) {
                throw new RuntimeException(
                    'The company_owner role is unavailable. Run RbacSeeder first.'
                );
            }

            /*
             * Create or restore the initial development company.
             */
            $company = Company::withTrashed()
                ->firstOrNew([
                    'slug' => 'nexorin-energy',
                ]);

            $company->fill([
                'name' => 'NEXORIN Energy',
                'code' => 'NXR',
                'registration_number' => null,
                'email' => 'company@nexorin.local',
                'phone' => null,
                'address' => null,
                'logo_path' => null,
                'status' => 'active',
                'created_by' => $user->id,
            ]);

            $company->save();

            if ($company->trashed()) {
                $company->restore();
            }

            /*
             * Create the user's active company membership.
             */
            $membership = CompanyMembership::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'user_id' => $user->id,
                ],
                [
                    'status' => 'active',
                    'invited_by' => $user->id,
                    'joined_at' => now(),
                ]
            );

            /*
             * Assign the Company Owner role to the membership.
             */
            $membership->roles()->syncWithoutDetaching([
                $companyOwnerRole->id => [
                    'assigned_by' => $user->id,
                ],
            ]);
        });

        $this->command?->info(
            'The NEXORIN development company and owner membership are ready.'
        );
    }
}