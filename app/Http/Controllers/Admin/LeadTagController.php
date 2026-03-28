<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeadTag;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LeadTagController extends Controller
{
    public function index()
    {
        $tags = LeadTag::withCount('leads')->orderBy('name')->get();

        return Inertia::render('Admin/Sales/Tags', [
            'tags' => $tags,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:100|unique:lead_tags,name',
            'color' => 'required|string|max:20',
        ]);

        LeadTag::create($validated);

        return back()->with('success', 'Tag creado.');
    }

    public function update(Request $request, LeadTag $tag)
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:100|unique:lead_tags,name,' . $tag->id,
            'color' => 'required|string|max:20',
        ]);

        $tag->update($validated);

        return back()->with('success', 'Tag actualizado.');
    }

    public function destroy(LeadTag $tag)
    {
        $tag->delete();
        return back()->with('success', 'Tag eliminado.');
    }
}
