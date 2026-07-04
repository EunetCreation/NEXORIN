<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RbacSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $now = now();

            $roles = [
                $this->role(
                    'super_admin',
                    'Super Admin',
                    'system',
                    'Manages the complete NEXORIN platform.',
                    true,
                    $now
                ),
                $this->role(
                    'company_owner',
                    'Company Owner',
                    'company',
                    'Owns the company workspace and has full company access.',
                    true,
                    $now
                ),
                $this->role(
                    'company_admin',
                    'Company Admin',
                    'company',
                    'Manages company settings, members, catalogs, suppliers, and pricing.',
                    true,
                    $now
                ),
                $this->role(
                    'engineer',
                    'Engineer',
                    'company',
                    'Creates projects, runs technical calculations, and verifies engineering results.',
                    true,
                    $now
                ),
                $this->role(
                    'sales',
                    'Sales',
                    'company',
                    'Manages customers, projects, quotations, and selling prices.',
                    true,
                    $now
                ),
                $this->role(
                    'customer',
                    'Customer',
                    'company',
                    'Views owned projects, estimates, quotations, and reports.',
                    true,
                    $now
                ),
                $this->role(
                    'reviewer',
                    'Reviewer',
                    'company',
                    'Reviews calculations and provides approval decisions.',
                    true,
                    $now
                ),
                $this->role(
                    'viewer',
                    'Viewer',
                    'company',
                    'Views company information without making changes.',
                    true,
                    $now
                ),
            ];

            DB::table('roles')->upsert(
                $roles,
                ['code'],
                [
                    'name',
                    'scope',
                    'description',
                    'is_system',
                    'is_active',
                    'updated_at',
                ]
            );

            $permissions = [
                $this->permission('company.view', 'View Company', 'company', 'view', 'View the company profile.', $now),
                $this->permission('company.update', 'Update Company', 'company', 'update', 'Update the company profile.', $now),
                $this->permission('company.settings.manage', 'Manage Company Settings', 'company', 'settings_manage', 'Manage operational company settings.', $now),
                $this->permission('company.manage_members', 'Manage Company Members', 'company', 'manage_members', 'Manage company memberships.', $now),

                $this->permission('member.view', 'View Members', 'member', 'view', 'View company members.', $now),
                $this->permission('member.invite', 'Invite Members', 'member', 'invite', 'Invite users into the company.', $now),
                $this->permission('member.update_role', 'Update Member Roles', 'member', 'update_role', 'Assign or update company roles.', $now),
                $this->permission('member.remove', 'Remove Members', 'member', 'remove', 'Remove users from the company.', $now),

                $this->permission('customer.view', 'View Customers', 'customer', 'view', 'View customer records.', $now),
                $this->permission('customer.create', 'Create Customers', 'customer', 'create', 'Create customer records.', $now),
                $this->permission('customer.update', 'Update Customers', 'customer', 'update', 'Update customer records.', $now),
                $this->permission('customer.delete', 'Delete Customers', 'customer', 'delete', 'Delete customer records.', $now),

                $this->permission('project.view', 'View Projects', 'project', 'view', 'View company projects.', $now),
                $this->permission('project.create', 'Create Projects', 'project', 'create', 'Create PLTS projects.', $now),
                $this->permission('project.update', 'Update Projects', 'project', 'update', 'Update project information.', $now),
                $this->permission('project.delete', 'Delete Projects', 'project', 'delete', 'Delete projects.', $now),
                $this->permission('project.archive', 'Archive Projects', 'project', 'archive', 'Archive projects.', $now),
                $this->permission('project.view_own', 'View Owned Projects', 'project', 'view_own', 'View projects owned by the current customer.', $now),
                $this->permission('project.create_own', 'Create Owned Projects', 'project', 'create_own', 'Create a project for the current customer.', $now),

                $this->permission('load_analysis.view', 'View Load Analyses', 'load_analysis', 'view', 'View company project load analyses.', $now),
                $this->permission('load_analysis.manage', 'Manage Load Analyses', 'load_analysis', 'manage', 'Manage electricity bills, appliances, and load profiles.', $now),
                $this->permission('load_analysis.view_own', 'View Owned Load Analyses', 'load_analysis', 'view_own', 'View load analyses for projects owned by the current customer.', $now),
                $this->permission('load_analysis.manage_own', 'Manage Owned Load Analyses', 'load_analysis', 'manage_own', 'Manage load inputs for projects owned by the current customer.', $now),

                $this->permission('location_solar.view', 'View Location and Solar Data', 'location_solar', 'view', 'View company project location and solar datasets.', $now),
                $this->permission('location_solar.manage', 'Manage Location and Solar Data', 'location_solar', 'manage', 'Manage project coordinates, source metadata, and monthly solar values.', $now),
                $this->permission('location_solar.verify', 'Verify Location and Solar Data', 'location_solar', 'verify', 'Verify a complete project location and solar dataset.', $now),
                $this->permission('location_solar.view_own', 'View Owned Location and Solar Data', 'location_solar', 'view_own', 'View location and solar data for projects owned by the current customer.', $now),
                $this->permission('location_solar.manage_own', 'Manage Owned Location and Solar Data', 'location_solar', 'manage_own', 'Manage location and solar inputs for projects owned by the current customer.', $now),

                $this->permission('roof_site.view', 'View Roof and Site Surveys', 'roof_site', 'view', 'View company project roof and site surveys.', $now),
                $this->permission('roof_site.manage', 'Manage Roof and Site Surveys', 'roof_site', 'manage', 'Manage roof surfaces, access, safety, structural notes, and survey evidence.', $now),
                $this->permission('roof_site.verify', 'Verify Roof and Site Surveys', 'roof_site', 'verify', 'Verify a complete roof and site survey dataset.', $now),
                $this->permission('roof_site.view_own', 'View Owned Roof and Site Surveys', 'roof_site', 'view_own', 'View roof and site surveys for projects owned by the current customer.', $now),
                $this->permission('roof_site.manage_own', 'Manage Owned Roof and Site Surveys', 'roof_site', 'manage_own', 'Manage roof and site survey inputs for projects owned by the current customer.', $now),

                $this->permission('recommendation.view', 'View System Recommendations', 'recommendation', 'view', 'View system recommendation inputs, scores, reasons, constraints, and history.', $now),
                $this->permission('recommendation.run', 'Run System Recommendations', 'recommendation', 'run', 'Run the explainable on-grid and hybrid recommendation engine.', $now),
                $this->permission('recommendation.override', 'Override System Recommendations', 'recommendation', 'override', 'Apply or remove a documented engineer override.', $now),
                $this->permission('recommendation.view_own', 'View Owned System Recommendations', 'recommendation', 'view_own', 'View recommendations for projects owned by the current customer.', $now),

                $this->permission('calculation.view', 'View Calculations', 'calculation', 'view', 'View technical calculation results.', $now),
                $this->permission('calculation.run', 'Run Calculations', 'calculation', 'run', 'Run the PLTS calculation engine.', $now),
                $this->permission('calculation.revise', 'Revise Calculations', 'calculation', 'revise', 'Create calculation revisions.', $now),
                $this->permission('calculation.submit', 'Submit Calculations', 'calculation', 'submit', 'Submit calculations for review.', $now),
                $this->permission('calculation.verify', 'Verify Calculations', 'calculation', 'verify', 'Verify technical calculation results.', $now),
                $this->permission('calculation.approve', 'Approve Calculations', 'calculation', 'approve', 'Approve calculation results.', $now),
                $this->permission('calculation.reject', 'Reject Calculations', 'calculation', 'reject', 'Reject calculations or request revisions.', $now),
                $this->permission('calculation.view_own', 'View Owned Estimates', 'calculation', 'view_own', 'View estimates for projects owned by the current customer.', $now),

                $this->permission('compatibility.view', 'View Compatibility Results', 'compatibility', 'view', 'View component compatibility results.', $now),
                $this->permission('compatibility.run', 'Run Compatibility Checks', 'compatibility', 'run', 'Run component compatibility checks.', $now),

                $this->permission('equipment.view', 'View Equipment', 'equipment', 'view', 'View the PLTS equipment catalog.', $now),
                $this->permission('equipment.create', 'Create Equipment', 'equipment', 'create', 'Add equipment to the catalog.', $now),
                $this->permission('equipment.update', 'Update Equipment', 'equipment', 'update', 'Update equipment information.', $now),
                $this->permission('equipment.delete', 'Delete Equipment', 'equipment', 'delete', 'Delete equipment from the catalog.', $now),

                $this->permission('supplier.view', 'View Suppliers', 'supplier', 'view', 'View supplier records.', $now),
                $this->permission('supplier.create', 'Create Suppliers', 'supplier', 'create', 'Create supplier records.', $now),
                $this->permission('supplier.update', 'Update Suppliers', 'supplier', 'update', 'Update supplier records.', $now),
                $this->permission('supplier.delete', 'Delete Suppliers', 'supplier', 'delete', 'Delete supplier records.', $now),

                $this->permission('price.cost.view', 'View Cost Prices', 'price', 'cost_view', 'View confidential supplier and cost prices.', $now),
                $this->permission('price.cost.update', 'Update Cost Prices', 'price', 'cost_update', 'Update confidential supplier and cost prices.', $now),
                $this->permission('price.selling.view', 'View Selling Prices', 'price', 'selling_view', 'View customer-facing selling prices.', $now),
                $this->permission('price.selling.update', 'Update Selling Prices', 'price', 'selling_update', 'Update customer-facing selling prices.', $now),

                $this->permission('quotation.view', 'View Quotations', 'quotation', 'view', 'View quotations.', $now),
                $this->permission('quotation.create', 'Create Quotations', 'quotation', 'create', 'Create quotations.', $now),
                $this->permission('quotation.update', 'Update Quotations', 'quotation', 'update', 'Update quotations.', $now),
                $this->permission('quotation.send', 'Send Quotations', 'quotation', 'send', 'Send quotations to customers.', $now),
                $this->permission('quotation.view_own', 'View Owned Quotations', 'quotation', 'view_own', 'View quotations belonging to the current customer.', $now),

                $this->permission('report.view', 'View Reports', 'report', 'view', 'View company reports.', $now),
                $this->permission('report.export', 'Export Reports', 'report', 'export', 'Export company reports.', $now),
                $this->permission('report.view_own', 'View Owned Reports', 'report', 'view_own', 'View reports for projects owned by the current customer.', $now),

                $this->permission('audit.view', 'View Audit Log', 'audit', 'view', 'View company activity history.', $now),
            ];

            DB::table('permissions')->upsert(
                $permissions,
                ['code'],
                [
                    'name',
                    'module',
                    'action',
                    'description',
                    'is_active',
                    'updated_at',
                ]
            );

            $roleIds = DB::table('roles')
                ->whereIn('code', array_column($roles, 'code'))
                ->pluck('id', 'code');

            $permissionIds = DB::table('permissions')
                ->whereIn('code', array_column($permissions, 'code'))
                ->pluck('id', 'code');

            $allPermissions = array_column($permissions, 'code');

            $rolePermissionMap = [
                'super_admin' => $allPermissions,
                'company_owner' => $allPermissions,

                'company_admin' => [
                    'company.view',
                    'company.update',
                    'company.settings.manage',
                    'company.manage_members',
                    'member.view',
                    'member.invite',
                    'member.update_role',
                    'member.remove',
                    'customer.view',
                    'customer.create',
                    'customer.update',
                    'customer.delete',
                    'project.view',
                    'project.create',
                    'project.update',
                    'project.delete',
                    'project.archive',
                    'load_analysis.view',
                    'load_analysis.manage',
                    'location_solar.view',
                    'location_solar.manage',
                    'location_solar.verify',
                    'roof_site.view',
                    'roof_site.manage',
                    'roof_site.verify',
                    'recommendation.view',
                    'recommendation.run',
                    'recommendation.override',
                    'calculation.view',
                    'calculation.run',
                    'calculation.revise',
                    'calculation.verify',
                    'compatibility.view',
                    'compatibility.run',
                    'equipment.view',
                    'equipment.create',
                    'equipment.update',
                    'equipment.delete',
                    'supplier.view',
                    'supplier.create',
                    'supplier.update',
                    'supplier.delete',
                    'price.cost.view',
                    'price.cost.update',
                    'price.selling.view',
                    'price.selling.update',
                    'quotation.view',
                    'quotation.create',
                    'quotation.update',
                    'quotation.send',
                    'report.view',
                    'report.export',
                    'audit.view',
                ],

                'engineer' => [
                    'company.view',
                    'member.view',
                    'customer.view',
                    'project.view',
                    'project.create',
                    'project.update',
                    'project.archive',
                    'load_analysis.view',
                    'load_analysis.manage',
                    'location_solar.view',
                    'location_solar.manage',
                    'location_solar.verify',
                    'roof_site.view',
                    'roof_site.manage',
                    'roof_site.verify',
                    'recommendation.view',
                    'recommendation.run',
                    'recommendation.override',
                    'calculation.view',
                    'calculation.run',
                    'calculation.revise',
                    'calculation.submit',
                    'calculation.verify',
                    'compatibility.view',
                    'compatibility.run',
                    'equipment.view',
                    'supplier.view',
                    'report.view',
                    'report.export',
                ],

                'sales' => [
                    'company.view',
                    'member.view',
                    'customer.view',
                    'customer.create',
                    'customer.update',
                    'project.view',
                    'project.create',
                    'project.update',
                    'project.archive',
                    'load_analysis.view',
                    'load_analysis.manage',
                    'location_solar.view',
                    'location_solar.manage',
                    'roof_site.view',
                    'roof_site.manage',
                    'recommendation.view',
                    'calculation.view',
                    'calculation.run',
                    'calculation.revise',
                    'calculation.verify',
                    'compatibility.view',
                    'compatibility.run',
                    'equipment.view',
                    'price.selling.view',
                    'quotation.view',
                    'quotation.create',
                    'quotation.update',
                    'quotation.send',
                    'report.view',
                    'report.export',
                ],

                'customer' => [
                    'company.view',
                    'project.view_own',
                    'project.create_own',
                    'load_analysis.view_own',
                    'load_analysis.manage_own',
                    'location_solar.view_own',
                    'location_solar.manage_own',
                    'roof_site.view_own',
                    'roof_site.manage_own',
                    'recommendation.view_own',
                    'calculation.view_own',
                    'quotation.view_own',
                    'report.view_own',
                ],

                'reviewer' => [
                    'company.view',
                    'member.view',
                    'project.view',
                    'load_analysis.view',
                    'location_solar.view',
                    'roof_site.view',
                    'roof_site.verify',
                    'recommendation.view',
                    'calculation.view',
                    'calculation.verify',
                    'calculation.approve',
                    'calculation.reject',
                    'compatibility.view',
                    'equipment.view',
                    'report.view',
                    'report.export',
                ],

                'viewer' => [
                    'company.view',
                    'member.view',
                    'customer.view',
                    'project.view',
                    'load_analysis.view',
                    'location_solar.view',
                    'roof_site.view',
                    'recommendation.view',
                    'calculation.view',
                    'compatibility.view',
                    'equipment.view',
                    'supplier.view',
                    'price.selling.view',
                    'quotation.view',
                    'report.view',
                ],
            ];

            DB::table('role_permissions')
                ->whereIn('role_id', $roleIds->values())
                ->delete();

            $rolePermissionRows = [];

            foreach ($rolePermissionMap as $roleCode => $permissionCodes) {
                foreach ($permissionCodes as $permissionCode) {
                    $rolePermissionRows[] = [
                        'role_id' => $roleIds[$roleCode],
                        'permission_id' => $permissionIds[$permissionCode],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            DB::table('role_permissions')->insert($rolePermissionRows);
        });
    }

    /**
     * Create a role data array.
     */
    private function role(
        string $code,
        string $name,
        string $scope,
        string $description,
        bool $isSystem,
        mixed $timestamp
    ): array {
        return [
            'code' => $code,
            'name' => $name,
            'scope' => $scope,
            'description' => $description,
            'is_system' => $isSystem,
            'is_active' => true,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }

    /**
     * Create a permission data array.
     */
    private function permission(
        string $code,
        string $name,
        string $module,
        string $action,
        string $description,
        mixed $timestamp
    ): array {
        return [
            'code' => $code,
            'name' => $name,
            'module' => $module,
            'action' => $action,
            'description' => $description,
            'is_active' => true,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }
}
