<?php

namespace App\Http\Controllers\Admin\Sponsorship;

use App\Http\Controllers\Controller;
use App\Models\Sponsorship\PackageBenefit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PackageBenefitController extends Controller
{
    public function index()
    {
        $benefits = PackageBenefit::withCount('packages')->orderBy('name')->get();

        return Inertia::render('Admin/Sponsorship/Benefits', [
            'benefits' => $benefits,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeLider();

        $validated = $request->validate([
            'name'      => 'required|string|max:255|unique:sponsorship_package_benefits,name',
            'is_active' => 'boolean',
        ]);

        PackageBenefit::create($validated);

        return back()->with('success', 'Benefit created.');
    }

    public function update(Request $request, PackageBenefit $benefit)
    {
        $this->authorizeLider();

        $validated = $request->validate([
            'name'      => 'required|string|max:255|unique:sponsorship_package_benefits,name,' . $benefit->id,
            'is_active' => 'boolean',
        ]);

        $benefit->update($validated);

        return back()->with('success', 'Benefit updated.');
    }

    public function destroy(PackageBenefit $benefit)
    {
        $this->authorizeLider();

        $benefit->packages()->detach();
        $benefit->delete();
        return back()->with('success', 'Benefit deleted.');
    }

    private function authorizeLider(): void
    {
        $user = auth()->user();
        $isAdmin = $user && $user->role === 'admin';
        $isSponsorshipLider = $user && $user->role === 'sponsorship' && $user->sponsorship_type === 'lider';
        if (!$isAdmin && !$isSponsorshipLider) {
            abort(403, 'Only leaders or admins can manage benefits.');
        }
    }
}
