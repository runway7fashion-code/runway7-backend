<?php

namespace App\Http\Controllers\Admin\Sponsorship;

use App\Http\Controllers\Controller;
use App\Models\Sponsorship\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::orderBy('name')->get();

        return Inertia::render('Admin/Sponsorship/Categories', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255|unique:sponsorship_categories,name',
            'is_active' => 'boolean',
        ]);

        Category::create($validated);

        return back()->with('success', 'Categoría creada.');
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255|unique:sponsorship_categories,name,' . $category->id,
            'is_active' => 'boolean',
        ]);

        $category->update($validated);

        return back()->with('success', 'Categoría actualizada.');
    }

    public function destroy(Category $category)
    {
        // NOTE: cuando creemos sponsorship_leads, validar que no esté en uso.
        $category->delete();
        return back()->with('success', 'Categoría eliminada.');
    }
}
