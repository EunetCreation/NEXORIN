<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

$activePermission = new Permission(['code' => 'calculation.view', 'is_active' => true]);
$inactivePermission = new Permission(['code' => 'price.cost.view', 'is_active' => false]);
$role = new Role(['code' => 'engineer', 'scope' => 'company', 'is_active' => true]);
$role->setRelation('permissions', new Collection([$activePermission, $inactivePermission]));

$inactiveRole = new Role(['code' => 'former_admin', 'scope' => 'company', 'is_active' => false]);
$inactiveRole->setRelation('permissions', new Collection([
    new Permission(['code' => 'equipment.update', 'is_active' => true]),
]));

$membership = new CompanyMembership(['company_id' => 7, 'status' => 'active']);
$membership->setRelation('roles', new Collection([$role, $inactiveRole]));

$user = new User(['status' => 'active']);
$user->setRelation('systemRoles', new Collection());
$user->setRelation('companyMemberships', new Collection([$membership]));

$company = new Company();
$company->id = 7;

$checks = [
    'super admin remains denied' => $user->isSuperAdmin() === false,
    'active membership is resolved' => $user->membershipForCompany($company) === $membership,
    'active permission remains allowed' => $user->hasCompanyPermission($company, 'calculation.view'),
    'inactive permission remains denied' => ! $user->hasCompanyPermission($company, 'price.cost.view'),
    'permission on inactive role remains denied' => ! $user->hasCompanyPermission($company, 'equipment.update'),
    'any permission preserves semantics' => $user->hasAnyCompanyPermission($company, ['equipment.update', 'calculation.view']),
    'all permission preserves semantics' => ! $user->hasAllCompanyPermissions($company, ['calculation.view', 'equipment.update']),
];

$failures = array_keys(array_filter($checks, static fn (bool $passed): bool => ! $passed));
if ($failures !== []) {
    fwrite(STDERR, "PAGE-002 AUTHORIZATION CACHE TEST: FAIL\n".implode("\n", $failures)."\n");
    exit(1);
}

echo "PAGE-002 AUTHORIZATION CACHE TEST: PASS\n";
