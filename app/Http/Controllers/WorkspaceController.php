<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\Company\ActiveCompanyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function index(Request $request, ActiveCompanyResolver $resolver): View|RedirectResponse
    {
        $user = $request->user();
        $companies = $resolver->availableCompanies($user);

        if ($companies->count() === 1) {
            $resolver->activate($user, $companies->first(), $request);

            return redirect()->route('dashboard');
        }

        return view('auth.workspace-select', [
            'companies' => $companies,
            'currentUser' => $user,
        ]);
    }

    public function store(
        Request $request,
        ActiveCompanyResolver $resolver,
    ): RedirectResponse {
        $validated = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
        ]);

        $company = Company::query()->findOrFail($validated['company_id']);
        $resolver->activate($request->user(), $company, $request);

        return redirect()->intended(route('dashboard'));
    }
}
