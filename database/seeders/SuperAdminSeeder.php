<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    /**
     * Create the local development Super Admin account.
     */
    public function run(): void
    {
        /*
         * Never create this development account
         * in a production environment.
         */
        if (! app()->environment('local')) {
            $this->command?->warn(
                'SuperAdminSeeder can only run in the local environment.'
            );

            return;
        }

        DB::transaction(function (): void {
            $superAdminRoleId = DB::table('roles')
                ->where('code', 'super_admin')
                ->value('id');

            if (! $superAdminRoleId) {
                throw new RuntimeException(
                    'The super_admin role is unavailable. Run RbacSeeder first.'
                );
            }

            /*
             * Create the user when the email does not exist.
             */
            $user = User::query()->firstOrCreate(
                [
                    'email' => 'admin@nexorin.local',
                ],
                [
                    'name' => 'NEXORIN Super Admin',
                    'password' => Hash::make('Nexorin@2026!'),
                    'email_verified_at' => now(),
                ]
            );

            /*
             * Assign the system-level Super Admin role.
             */
            DB::table('system_user_roles')->upsert(
                [
                    [
                        'user_id' => $user->id,
                        'role_id' => $superAdminRoleId,
                        'assigned_by' => $user->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ],
                [
                    'user_id',
                    'role_id',
                ],
                [
                    'assigned_by',
                    'updated_at',
                ]
            );
        });

        $this->command?->info(
            'The NEXORIN Super Admin account is ready.'
        );
    }
}