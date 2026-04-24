<?php

namespace App\Http\Controllers\Admin\Sponsorship;

use App\Http\Controllers\Controller;
use App\Models\Sponsorship\Package;
use App\Models\Sponsorship\PackageBenefit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PackageController extends Controller
{
    public function index()
    {
        $packages = Package::with('benefits:id,name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Sponsorship/Packages/Index', [
            'packages' => $packages,
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Sponsorship/Packages/Create', [
            'benefits' => PackageBenefit::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'price'            => 'required|numeric|min:0',
            'assistants_count' => 'required|integer|min:0',
            'description'      => 'nullable|string',
            'is_active'        => 'boolean',
            'benefit_ids'      => 'nullable|array',
            'benefit_ids.*'    => 'exists:sponsorship_package_benefits,id',
        ]);

        DB::transaction(function () use ($validated) {
            $package = Package::create([
                'name'             => $validated['name'],
                'price'            => $validated['price'],
                'assistants_count' => $validated['assistants_count'],
                'description'      => $validated['description'] ?? null,
                'is_active'        => $validated['is_active'] ?? true,
            ]);

            if (!empty($validated['benefit_ids'])) {
                $package->benefits()->sync($validated['benefit_ids']);
            }
        });

        return redirect()->route('admin.sponsorship.packages.index')
            ->with('success', 'Package created.');
    }

    public function edit(Package $package)
    {
        return Inertia::render('Admin/Sponsorship/Packages/Edit', [
            'package'         => $package->load('benefits:id,name'),
            'benefits'        => PackageBenefit::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'selectedBenefitIds' => $package->benefits()->pluck('sponsorship_package_benefits.id'),
        ]);
    }

    public function update(Request $request, Package $package)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'price'            => 'required|numeric|min:0',
            'assistants_count' => 'required|integer|min:0',
            'description'      => 'nullable|string',
            'is_active'        => 'boolean',
            'benefit_ids'      => 'nullable|array',
            'benefit_ids.*'    => 'exists:sponsorship_package_benefits,id',
        ]);

        DB::transaction(function () use ($validated, $package) {
            $package->update([
                'name'             => $validated['name'],
                'price'            => $validated['price'],
                'assistants_count' => $validated['assistants_count'],
                'description'      => $validated['description'] ?? null,
                'is_active'        => $validated['is_active'] ?? true,
            ]);

            $package->benefits()->sync($validated['benefit_ids'] ?? []);
        });

        return redirect()->route('admin.sponsorship.packages.index')
            ->with('success', 'Package updated.');
    }

    public function destroy(Package $package)
    {
        // NOTE: cuando creemos sponsorship_registrations, validar que no esté en uso.
        $package->benefits()->detach();
        $package->delete();
        return back()->with('success', 'Package deleted.');
    }
}
