<?php

use App\Http\Controllers\CompanyController;
use App\Http\Controllers\BillOfMaterialController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ElectricityBillController;
use App\Http\Controllers\FinancialAnalysisController;
use App\Http\Controllers\LoadAnalysisController;
use App\Http\Controllers\LoadApplianceController;
use App\Http\Controllers\LocationSolarController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectReportController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\RoofSiteSurveyController;
use App\Http\Controllers\RoofSiteSurveyPhotoController;
use App\Http\Controllers\RoofSurfaceController;
use App\Http\Controllers\SystemRecommendationController;
use App\Http\Controllers\TechnicalCalculationController;
use App\Http\Controllers\UserController;
use App\Models\Company;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
})->name('home');

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'user.active'])->group(function () {
    Route::view('/dashboard', 'dashboard')->name('dashboard');

    Route::middleware('system.super_admin')->group(function () {
        Route::patch(
            '/companies/{company}/status',
            [CompanyController::class, 'updateStatus']
        )->name('companies.status');

        Route::resource('companies', CompanyController::class);

        Route::patch(
            '/users/{user}/status',
            [UserController::class, 'updateStatus']
        )->name('users.status');

        Route::resource('users', UserController::class)
            ->except('destroy');
    });

    /*
    |----------------------------------------------------------------------
    | Customer and Project Management
    |----------------------------------------------------------------------
    */

    Route::prefix('/companies/{company}')
        ->name('companies.')
        ->middleware('company.access')
        ->scopeBindings()
        ->group(function () {
            Route::get('/customers', [CustomerController::class, 'index'])
                ->middleware('company.permission:customer.view')
                ->name('customers.index');

            Route::get('/customers/create', [CustomerController::class, 'create'])
                ->middleware('company.permission:customer.create')
                ->name('customers.create');

            Route::post('/customers', [CustomerController::class, 'store'])
                ->middleware('company.permission:customer.create')
                ->name('customers.store');

            Route::get('/customers/{customer}', [CustomerController::class, 'show'])
                ->middleware('company.permission:customer.view')
                ->name('customers.show');

            Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])
                ->middleware('company.permission:customer.update')
                ->name('customers.edit');

            Route::put('/customers/{customer}', [CustomerController::class, 'update'])
                ->middleware('company.permission:customer.update')
                ->name('customers.update');

            Route::patch('/customers/{customer}/archive', [CustomerController::class, 'archive'])
                ->middleware('company.permission:customer.delete')
                ->name('customers.archive');

            Route::get('/load-analyses', [LoadAnalysisController::class, 'index'])
                ->middleware('company.permission:load_analysis.view,load_analysis.view_own')
                ->name('load-analyses.index');

            Route::get('/projects/{project}/load-analysis', [LoadAnalysisController::class, 'show'])
                ->middleware('company.permission:load_analysis.view,load_analysis.manage,load_analysis.view_own,load_analysis.manage_own')
                ->name('load-analyses.show');

            Route::put('/projects/{project}/load-analysis', [LoadAnalysisController::class, 'update'])
                ->middleware('company.permission:load_analysis.manage,load_analysis.manage_own')
                ->name('load-analyses.update');

            Route::post('/projects/{project}/load-analysis/bills', [ElectricityBillController::class, 'store'])
                ->middleware('company.permission:load_analysis.manage,load_analysis.manage_own')
                ->name('load-analyses.bills.store');

            Route::delete('/projects/{project}/load-analysis/bills/{electricityBill}', [ElectricityBillController::class, 'destroy'])
                ->middleware('company.permission:load_analysis.manage,load_analysis.manage_own')
                ->name('load-analyses.bills.destroy');

            Route::get('/projects/{project}/load-analysis/appliances/create', [LoadApplianceController::class, 'create'])
                ->middleware('company.permission:load_analysis.manage,load_analysis.manage_own')
                ->name('load-analyses.appliances.create');

            Route::post('/projects/{project}/load-analysis/appliances', [LoadApplianceController::class, 'store'])
                ->middleware('company.permission:load_analysis.manage,load_analysis.manage_own')
                ->name('load-analyses.appliances.store');

            Route::get('/projects/{project}/load-analysis/appliances/{loadAppliance}/edit', [LoadApplianceController::class, 'edit'])
                ->middleware('company.permission:load_analysis.manage,load_analysis.manage_own')
                ->name('load-analyses.appliances.edit');

            Route::put('/projects/{project}/load-analysis/appliances/{loadAppliance}', [LoadApplianceController::class, 'update'])
                ->middleware('company.permission:load_analysis.manage,load_analysis.manage_own')
                ->name('load-analyses.appliances.update');

            Route::delete('/projects/{project}/load-analysis/appliances/{loadAppliance}', [LoadApplianceController::class, 'destroy'])
                ->middleware('company.permission:load_analysis.manage,load_analysis.manage_own')
                ->name('load-analyses.appliances.destroy');

            Route::get('/location-solar-data', [LocationSolarController::class, 'index'])
                ->middleware('company.permission:location_solar.view,location_solar.view_own')
                ->name('location-solar.index');

            Route::get('/projects/{project}/location-solar-data', [LocationSolarController::class, 'show'])
                ->middleware('company.permission:location_solar.view,location_solar.manage,location_solar.view_own,location_solar.manage_own')
                ->name('location-solar.show');

            Route::put('/projects/{project}/location-solar-data', [LocationSolarController::class, 'update'])
                ->middleware('company.permission:location_solar.manage,location_solar.manage_own')
                ->name('location-solar.update');

            Route::patch('/projects/{project}/location-solar-data/verify', [LocationSolarController::class, 'verify'])
                ->middleware('company.permission:location_solar.verify')
                ->name('location-solar.verify');

            Route::get('/roof-site-surveys', [RoofSiteSurveyController::class, 'index'])
                ->middleware('company.permission:roof_site.view,roof_site.view_own')
                ->name('roof-site.index');

            Route::get('/projects/{project}/roof-site-survey', [RoofSiteSurveyController::class, 'show'])
                ->middleware('company.permission:roof_site.view,roof_site.manage,roof_site.view_own,roof_site.manage_own')
                ->name('roof-site.show');

            Route::put('/projects/{project}/roof-site-survey', [RoofSiteSurveyController::class, 'update'])
                ->middleware('company.permission:roof_site.manage,roof_site.manage_own')
                ->name('roof-site.update');

            Route::patch('/projects/{project}/roof-site-survey/verify', [RoofSiteSurveyController::class, 'verify'])
                ->middleware('company.permission:roof_site.verify')
                ->name('roof-site.verify');

            Route::get('/projects/{project}/roof-site-survey/surfaces/create', [RoofSurfaceController::class, 'create'])
                ->middleware('company.permission:roof_site.manage,roof_site.manage_own')
                ->name('roof-site.surfaces.create');

            Route::post('/projects/{project}/roof-site-survey/surfaces', [RoofSurfaceController::class, 'store'])
                ->middleware('company.permission:roof_site.manage,roof_site.manage_own')
                ->name('roof-site.surfaces.store');

            Route::get('/projects/{project}/roof-site-survey/surfaces/{roofSurface}/edit', [RoofSurfaceController::class, 'edit'])
                ->middleware('company.permission:roof_site.manage,roof_site.manage_own')
                ->name('roof-site.surfaces.edit');

            Route::put('/projects/{project}/roof-site-survey/surfaces/{roofSurface}', [RoofSurfaceController::class, 'update'])
                ->middleware('company.permission:roof_site.manage,roof_site.manage_own')
                ->name('roof-site.surfaces.update');

            Route::delete('/projects/{project}/roof-site-survey/surfaces/{roofSurface}', [RoofSurfaceController::class, 'destroy'])
                ->middleware('company.permission:roof_site.manage,roof_site.manage_own')
                ->name('roof-site.surfaces.destroy');

            Route::post('/projects/{project}/roof-site-survey/photos', [RoofSiteSurveyPhotoController::class, 'store'])
                ->middleware('company.permission:roof_site.manage,roof_site.manage_own')
                ->name('roof-site.photos.store');

            Route::delete('/projects/{project}/roof-site-survey/photos/{surveyPhoto}', [RoofSiteSurveyPhotoController::class, 'destroy'])
                ->middleware('company.permission:roof_site.manage,roof_site.manage_own')
                ->name('roof-site.photos.destroy');

            Route::get('/system-recommendations', [SystemRecommendationController::class, 'index'])
                ->middleware('company.permission:recommendation.view,recommendation.view_own')
                ->name('recommendations.index');

            Route::get('/projects/{project}/system-recommendation', [SystemRecommendationController::class, 'show'])
                ->middleware('company.permission:recommendation.view,recommendation.run,recommendation.override,recommendation.view_own')
                ->name('recommendations.show');

            Route::put('/projects/{project}/system-recommendation', [SystemRecommendationController::class, 'run'])
                ->middleware('company.permission:recommendation.run')
                ->name('recommendations.run');

            Route::patch('/projects/{project}/system-recommendation/override', [SystemRecommendationController::class, 'override'])
                ->middleware('company.permission:recommendation.override')
                ->name('recommendations.override');

            Route::delete('/projects/{project}/system-recommendation/override', [SystemRecommendationController::class, 'resetOverride'])
                ->middleware('company.permission:recommendation.override')
                ->name('recommendations.override.reset');

            Route::get('/technical-calculations', [TechnicalCalculationController::class, 'index'])
                ->middleware('company.permission:calculation.view,calculation.view_own')
                ->name('technical-calculations.index');

            Route::get('/projects/{project}/technical-calculation', [TechnicalCalculationController::class, 'show'])
                ->middleware('company.permission:calculation.view,calculation.run,calculation.revise,calculation.verify,calculation.view_own')
                ->name('technical-calculations.show');

            Route::put('/projects/{project}/technical-calculation', [TechnicalCalculationController::class, 'run'])
                ->middleware('company.permission:calculation.run,calculation.revise')
                ->name('technical-calculations.run');

            Route::patch('/projects/{project}/technical-calculation/verify', [TechnicalCalculationController::class, 'verify'])
                ->middleware('company.permission:calculation.verify')
                ->name('technical-calculations.verify');

            Route::post('/projects/{project}/technical-calculation/overrides', [TechnicalCalculationController::class, 'storeOverride'])
                ->middleware('company.permission:calculation.revise')
                ->name('technical-calculations.overrides.store');

            Route::delete('/projects/{project}/technical-calculation/overrides/{override}', [TechnicalCalculationController::class, 'resetOverride'])
                ->middleware('company.permission:calculation.revise')
                ->name('technical-calculations.overrides.reset');

            Route::get('/boms', [BillOfMaterialController::class, 'index'])
                ->middleware('company.permission:calculation.view,calculation.view_own')
                ->name('boms.index');

            Route::get('/projects/{project}/bom', [BillOfMaterialController::class, 'show'])
                ->middleware('company.permission:calculation.view,calculation.run,calculation.revise,calculation.view_own')
                ->name('boms.show');

            Route::post('/projects/{project}/bom/generate', [BillOfMaterialController::class, 'generate'])
                ->middleware('company.permission:calculation.run,calculation.revise')
                ->name('boms.generate');

            Route::post('/projects/{project}/bom/pricing', [BillOfMaterialController::class, 'price'])
                ->middleware('company.permission:price.cost.update,price.selling.update')
                ->name('boms.price');


            Route::get('/financial-analyses', [FinancialAnalysisController::class, 'index'])
                ->middleware('company.permission:calculation.view,price.selling.view,quotation.view,calculation.view_own,quotation.view_own')
                ->name('financial-analyses.index');

            Route::get('/projects/{project}/financial-analysis', [FinancialAnalysisController::class, 'show'])
                ->middleware('company.permission:calculation.view,price.selling.view,quotation.view,quotation.create,price.selling.update,calculation.view_own,quotation.view_own')
                ->name('financial-analyses.show');

            Route::post('/projects/{project}/financial-analysis/calculate', [FinancialAnalysisController::class, 'calculate'])
                ->middleware('company.permission:price.selling.update,quotation.create')
                ->name('financial-analyses.calculate');

            Route::get('/quotations', [QuotationController::class, 'index'])
                ->middleware('company.permission:quotation.view,quotation.view_own')
                ->name('quotations.index');

            Route::get('/projects/{project}/quotation', [QuotationController::class, 'show'])
                ->middleware('company.permission:quotation.view,quotation.create,quotation.update,quotation.send,quotation.view_own')
                ->name('quotations.show');

            Route::get('/projects/{project}/quotation/pdf', [QuotationController::class, 'pdf'])
                ->middleware('company.permission:quotation.view,quotation.create,quotation.update,quotation.send,quotation.view_own')
                ->name('quotations.pdf');

            Route::get('/projects/{project}/report/pdf', [ProjectReportController::class, 'pdf'])
                ->middleware('company.permission:project.view,project.view_own,calculation.view,calculation.view_own,price.selling.view,quotation.view,quotation.view_own')
                ->name('projects.report.pdf');

            Route::post('/projects/{project}/quotation/generate', [QuotationController::class, 'generate'])
                ->middleware('company.permission:quotation.create,quotation.update')
                ->name('quotations.generate');

            Route::patch('/projects/{project}/quotation/issue', [QuotationController::class, 'issue'])
                ->middleware('company.permission:quotation.send')
                ->name('quotations.issue');

            Route::get('/projects', [ProjectController::class, 'index'])
                ->middleware('company.permission:project.view,project.view_own')
                ->name('projects.index');

            Route::get('/projects/create', [ProjectController::class, 'create'])
                ->middleware('company.permission:project.create,project.create_own')
                ->name('projects.create');

            Route::post('/projects', [ProjectController::class, 'store'])
                ->middleware('company.permission:project.create,project.create_own')
                ->name('projects.store');

            Route::get('/projects/{project}', [ProjectController::class, 'show'])
                ->middleware('company.permission:project.view,project.view_own')
                ->name('projects.show');

            Route::get('/projects/{project}/edit', [ProjectController::class, 'edit'])
                ->middleware('company.permission:project.update')
                ->name('projects.edit');

            Route::put('/projects/{project}', [ProjectController::class, 'update'])
                ->middleware('company.permission:project.update')
                ->name('projects.update');

            Route::patch('/projects/{project}/archive', [ProjectController::class, 'archive'])
                ->middleware('company.permission:project.archive')
                ->name('projects.archive');
        });

    /*
    |----------------------------------------------------------------------
    | RBAC Development Routes
    |----------------------------------------------------------------------
    |
    | These routes verify company access and role permissions before the
    | related operational modules are implemented. Remove them after each
    | module has its own controller authorization and feature tests.
    |
    */

    Route::get(
        '/companies/{company}/access-test',
        function (Company $company) {
            return response()->json([
                'message' => 'Company access granted.',
                'company' => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'code' => $company->code,
                    'status' => $company->status,
                ],
                'user' => [
                    'id' => auth()->id(),
                    'name' => auth()->user()->name,
                ],
            ]);
        }
    )
        ->middleware('company.access')
        ->name('companies.access-test');

    Route::prefix('/companies/{company}/permission-tests')
        ->name('companies.permission-tests.')
        ->middleware('company.access')
        ->group(function () {
            Route::get('/project-create', function (Company $company) {
                return response()->json([
                    'message' => 'Project creation permission granted.',
                    'permission' => 'project.create',
                    'company' => $company->only(['id', 'name', 'code']),
                    'user' => auth()->user()->only(['id', 'name', 'email']),
                ]);
            })
                ->middleware('company.permission:project.create')
                ->name('project-create');

            Route::get('/calculation-verify', function (Company $company) {
                return response()->json([
                    'message' => 'Calculation verification permission granted.',
                    'permission' => 'calculation.verify',
                    'company' => $company->only(['id', 'name', 'code']),
                    'user' => auth()->user()->only(['id', 'name', 'email']),
                ]);
            })
                ->middleware('company.permission:calculation.verify')
                ->name('calculation-verify');

            Route::get('/cost-price', function (Company $company) {
                return response()->json([
                    'message' => 'Cost price permission granted.',
                    'permission' => 'price.cost.view',
                    'company' => $company->only(['id', 'name', 'code']),
                    'user' => auth()->user()->only(['id', 'name', 'email']),
                ]);
            })
                ->middleware('company.permission:price.cost.view')
                ->name('cost-price');

            Route::get('/quotation-create', function (Company $company) {
                return response()->json([
                    'message' => 'Quotation creation permission granted.',
                    'permission' => 'quotation.create',
                    'company' => $company->only(['id', 'name', 'code']),
                    'user' => auth()->user()->only(['id', 'name', 'email']),
                ]);
            })
                ->middleware('company.permission:quotation.create')
                ->name('quotation-create');

            Route::get('/owned-project', function (Company $company) {
                return response()->json([
                    'message' => 'Owned project permission granted.',
                    'permission' => 'project.view_own',
                    'company' => $company->only(['id', 'name', 'code']),
                    'user' => auth()->user()->only(['id', 'name', 'email']),
                ]);
            })
                ->middleware('company.permission:project.view_own')
                ->name('owned-project');

            Route::get('/calculation-approval', function (Company $company) {
                return response()->json([
                    'message' => 'Calculation approval permission granted.',
                    'permission' => 'calculation.approve',
                    'company' => $company->only(['id', 'name', 'code']),
                    'user' => auth()->user()->only(['id', 'name', 'email']),
                ]);
            })
                ->middleware('company.permission:calculation.approve')
                ->name('calculation-approval');
        });

    /*
     * Backward-compatible routes used by earlier manual tests.
     */
    Route::get(
        '/companies/{company}/permission-test',
        function (Company $company) {
            return response()->json([
                'message' => 'Company permission granted.',
                'permission' => 'project.create',
                'company' => $company->only(['id', 'name', 'code']),
                'user' => auth()->user()->only(['id', 'name', 'email']),
            ]);
        }
    )
        ->middleware([
            'company.access',
            'company.permission:project.create',
        ])
        ->name('companies.permission-test');

    Route::get(
        '/companies/{company}/approval-test',
        function (Company $company) {
            return response()->json([
                'message' => 'Calculation approval permission granted.',
                'permission' => 'calculation.approve',
                'company' => $company->only(['id', 'name', 'code']),
                'user' => auth()->user()->only(['id', 'name', 'email']),
            ]);
        }
    )
        ->middleware([
            'company.access',
            'company.permission:calculation.approve',
        ])
        ->name('companies.approval-test');
});
