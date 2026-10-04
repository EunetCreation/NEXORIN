<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'controller' => $root.'/app/Http/Controllers/CustomerController.php',
    'request' => $root.'/app/Http/Requests/UpdateCustomerRequest.php',
    'view' => $root.'/resources/views/customers/edit.blade.php',
    'css' => $root.'/public/css/nexorin-customer-edit-v6.css',
    'js' => $root.'/public/js/nexorin-customer-edit-v6.js',
    'test' => $root.'/tests/Feature/Page009CustomerEditTest.php',
];

foreach ($files as $label => $path) {
    if (! is_file($path)) {
        fwrite(STDERR, "FAIL: missing {$label}: {$path}\n");
        exit(1);
    }
}

$controller = file_get_contents($files['controller']);
$request = file_get_contents($files['request']);
$view = file_get_contents($files['view']);
$js = file_get_contents($files['js']);
$css = file_get_contents($files['css']);
$test = file_get_contents($files['test']);

$checks = [
    'edit loads Page008 authoritative CRM relations' => str_contains($controller, "'profile.parentCustomer")
        && str_contains($controller, "'contacts'")
        && str_contains($controller, "'opportunities'")
        && str_contains($controller, "'documents'"),
    'eager-load relation callbacks accept Laravel Relation instances' => ! str_contains($controller, "'opportunities' => fn (Builder \$query)")
        && ! str_contains($controller, "'documents' => fn (Builder \$query)"),
    'update reuses authoritative CRM records' => str_contains($controller, 'updateCustomerCrmProfile')
        && str_contains($controller, 'syncCustomerContacts')
        && str_contains($controller, 'syncCustomerOpportunities')
        && str_contains($controller, 'persistCustomerDocuments'),
    'optimistic concurrency is enforced' => str_contains($request, 'baseline_updated_at')
        && str_contains($request, 'avoid overwriting a newer revision'),
    'tenant and permission defense in depth exists' => str_contains($request, "hasCompanyPermission(\$company, 'customer.update')")
        && str_contains($request, '(int) $customer->company_id === (int) $company->getKey()'),
    'contact removal is explicit' => str_contains($request, 'contacts_sync')
        && str_contains($request, "contacts.*._remove")
        && str_contains($controller, 'if ($remove)'),
    'opportunity assessment keeps No distinct from Not Assessed' => str_contains($request, "'not_assessed', 'no_opportunity', 'has_opportunity'")
        && str_contains($request, 'Set the opportunity assessment to Yes before adding a new opportunity record.'),
    'document edit is additive recorded evidence' => str_contains($controller, "'status' => 'recorded'")
        && str_contains($view, 'Existing evidence is not overwritten here.'),
    'no Save Draft claim on Page009' => ! str_contains($view, 'Save Draft'),
    'no V6 fake score/activity implementation copy' => ! str_contains($view, 'Relationship Score')
        && ! str_contains($view, 'Enterprise placeholder')
        && ! str_contains($view, 'synthetic activity')
        && ! str_contains($view, 'server-side'),
    'page-local visual surface exists' => str_contains($view, 'nexorin-customer-edit-v6.css')
        && str_contains($css, '.page-customer-edit-v6'),
    'dirty state and navigation protection exist' => str_contains($js, 'Unsaved changes')
        && str_contains($js, 'beforeunload')
        && str_contains($js, "setState('saving')"),
    'right rail is confined to the top context zone' => str_contains($view, 'class="nxce9-top-zone"')
        && str_contains($view, 'class="nxce9-lower-flow"')
        && str_contains($css, '.nxce9-rail{position:sticky')
        && ! str_contains($css, '.nxce9-rail{max-height:'),
    'documents moved to full-width lower flow' => str_contains($view, 'class="nxce9-card nxce9-documents-card"')
        && str_contains($view, 'class="nxce9-documents-layout"')
        && ! str_contains($view, 'id="documents" class="nxce9-rail-card"'),
    'reduced motion contract exists' => str_contains($css, '@media(prefers-reduced-motion:reduce)'),
    'Page009 feature tests exist' => substr_count($test, 'public function test_') >= 7,
];

$failed = array_keys(array_filter($checks, fn (bool $passed): bool => ! $passed));
foreach ($checks as $name => $passed) {
    echo ($passed ? 'PASS' : 'FAIL').": {$name}\n";
}

if ($failed !== []) {
    fwrite(STDERR, "\nPage009 static contract validation failed: ".implode('; ', $failed)."\n");
    exit(1);
}

echo "\nPage009 static contract validation PASS (".count($checks)." checks).\n";
