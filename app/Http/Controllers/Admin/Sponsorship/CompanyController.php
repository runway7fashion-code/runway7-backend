<?php

namespace App\Http\Controllers\Admin\Sponsorship;

use App\Http\Controllers\Controller;
use App\Models\Sponsorship\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $query = Company::query()->with('creator:id,first_name,last_name');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'ilike', "%{$s}%")
                  ->orWhere('industry', 'ilike', "%{$s}%")
                  ->orWhere('country', 'ilike', "%{$s}%");
            });
        }

        $companies = $query->orderBy('name')->paginate(30)->withQueryString();

        return Inertia::render('Admin/Sponsorship/Companies/Index', [
            'companies' => $companies,
            'filters'   => $request->only(['search']),
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
            return back()->withErrors(['name' => 'Ya existe una empresa con ese nombre.'])->withInput();
        }

        $company = Company::create([
            'name'               => $validated['name'],
            'created_by_user_id' => auth()->id(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['company' => $company], 201);
        }

        return redirect()->route('admin.sponsorship.companies.edit', $company)
            ->with('success', 'Empresa creada. Completa los datos.');
    }

    public function edit(Company $company)
    {
        return Inertia::render('Admin/Sponsorship/Companies/Edit', [
            'company' => $company->load('creator:id,first_name,last_name'),
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
            return back()->withErrors(['name' => 'Ya existe otra empresa con ese nombre.'])->withInput();
        }

        $company->update($validated);

        return back()->with('success', 'Empresa actualizada.');
    }

    public function destroy(Company $company)
    {
        $company->delete();
        return redirect()->route('admin.sponsorship.companies.index')
            ->with('success', 'Empresa eliminada.');
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
