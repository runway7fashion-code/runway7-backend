<?php

namespace App\Http\Controllers\Admin\Sponsorship;

use App\Http\Controllers\Controller;
use App\Models\Sponsorship\Tag;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TagController extends Controller
{
    public function index()
    {
        $tags = Tag::orderBy('name')->get();

        return Inertia::render('Admin/Sponsorship/Tags', [
            'tags' => $tags,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:100|unique:sponsorship_tags,name',
            'color' => 'required|string|max:20',
        ]);

        Tag::create($validated);

        return back()->with('success', 'Tag creado.');
    }

    public function update(Request $request, Tag $tag)
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:100|unique:sponsorship_tags,name,' . $tag->id,
            'color' => 'required|string|max:20',
        ]);

        $tag->update($validated);

        return back()->with('success', 'Tag actualizado.');
    }

    public function destroy(Tag $tag)
    {
        $tag->delete();
        return back()->with('success', 'Tag eliminado.');
    }
}
