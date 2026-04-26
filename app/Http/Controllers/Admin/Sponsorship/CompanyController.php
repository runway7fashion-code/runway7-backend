<?php

namespace App\Http\Controllers\Admin\Sponsorship;

use App\Http\Controllers\Controller;
use App\Models\Sponsorship\Company;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $query = Company::query()
            ->with('creator:id,first_name,last_name')
            ->withCount('leads');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'ilike', "%{$s}%")
                  ->orWhere('industry', 'ilike', "%{$s}%")
                  ->orWhere('country', 'ilike', "%{$s}%");
            });
        }

        if ($request->filled('industry')) {
            $query->where('industry', $request->industry);
        }
        if ($request->filled('country')) {
            $query->where('country', $request->country);
        }
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', Carbon::parse($request->date_from)->startOfDay());
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
        }

        $companies = $query->orderBy('name')->paginate(30)->withQueryString();

        // Distinct industries y countries para los selects de filtro
        $industries = Company::whereNotNull('industry')->where('industry', '!=', '')
            ->distinct()->orderBy('industry')->pluck('industry')->values();
        $countries  = Company::whereNotNull('country')->where('country', '!=', '')
            ->distinct()->orderBy('country')->pluck('country')->values();

        return Inertia::render('Admin/Sponsorship/Companies/Index', [
            'companies'  => $companies,
            'totalCount' => Company::count(),
            'industries' => $industries,
            'countries'  => $countries,
            'filters'    => $request->only(['search', 'industry', 'country', 'date_from', 'date_to']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        // Unique case-insensitive check against non-trashed rows
        $exists = Company::whereRaw('LOWER(name) = ?', [mb_strtolower($validated['name'])])->exists();
        if ($exists) {
            return back()->withErrors(['name' => 'A company with that name already exists.'])->withInput();
        }

        $company = Company::create([
            'name'               => $validated['name'],
            'created_by_user_id' => auth()->id(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['company' => $company], 201);
        }

        return redirect()->route('admin.sponsorship.companies.edit', $company)
            ->with('success', 'Company created. Please complete the details.');
    }

    public function edit(Company $company)
    {
        return Inertia::render('Admin/Sponsorship/Companies/Edit', [
            'company'   => $company->load('creator:id,first_name,last_name'),
            'countries' => \App\Models\Country::where('is_active', true)
                ->orderBy('order')->orderBy('name')
                ->get(['name', 'code', 'flag']),
        ]);
    }

    public function update(Request $request, Company $company)
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'website'   => ['nullable', 'string', 'max:255'],
            'instagram' => ['nullable', 'string', 'max:255'],
            'logo'      => ['nullable', 'string', 'max:500'],
            'industry'  => ['nullable', 'string', 'max:255'],
            'country'   => ['nullable', 'string', 'max:100'],
            'notes'     => ['nullable', 'string'],
        ]);

        $duplicate = Company::whereRaw('LOWER(name) = ?', [mb_strtolower($validated['name'])])
            ->where('id', '!=', $company->id)
            ->exists();
        if ($duplicate) {
            return back()->withErrors(['name' => 'Another company with that name already exists.'])->withInput();
        }

        $company->update($validated);

        return back()->with('success', 'Company updated.');
    }

    public function destroy(Company $company)
    {
        $company->delete();
        return redirect()->route('admin.sponsorship.companies.index')
            ->with('success', 'Company deleted.');
    }

    /**
     * Autosuggest endpoint for the lead form.
     */
    public function search(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json(['companies' => []]);
        }

        $companies = Company::where('name', 'ilike', "%{$q}%")
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name']);

        return response()->json(['companies' => $companies]);
    }
}
