<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DesignerCategory;
use App\Models\DesignerPackage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DesignerSettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Settings/Designers', [
            'categories' => DesignerCategory::ordered()->get(),
            'packages'   => DesignerPackage::ordered()->get(),
        ]);
    }

    // --- Categorías ---

    public function storeCategory(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:designer_categories,name',
        ]);

        DesignerCategory::create([
            'name'  => $request->name,
            'slug'  => Str::slug($request->name),
            'order' => (DesignerCategory::max('order') ?? 0) + 1,
        ]);

        return back()->with('success', 'Categoría creada.');
    }

    public function updateCategory(Request $request, DesignerCategory $category)
    {
        $request->validate([
            'name'      => 'sometimes|string|max:255|unique:designer_categories,name,' . $category->id,
            'order'     => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        $data = $request->only(['name', 'order', 'is_active']);
        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $category->update($data);

        return back()->with('success', 'Categoría actualizada.');
    }

    public function destroyCategory(DesignerCategory $category)
    {
        if ($category->designerProfiles()->exists()) {
            return back()->with('error', 'No se puede eliminar: hay diseñadores usando esta categoría.');
        }

        $category->delete();

        return back()->with('success', 'Categoría eliminada.');
    }

    // --- Paquetes ---

    public function storePackage(Request $request)
    {
        $request->validate([
            'name'               => 'required|string|max:255|unique:designer_packages,name',
            'description'        => 'nullable|string',
            'price'              => 'required|numeric|min:0',
            'default_looks'      => 'required|integer|min:1',
            'default_assistants' => 'required|integer|min:0',
            'features'           => 'nullable|array',
        ]);

        DesignerPackage::create([
            'name'               => $request->name,
            'slug'               => Str::slug($request->name),
            'description'        => $request->description,
            'price'              => $request->price,
            'default_looks'      => $request->default_looks,
            'default_assistants' => $request->default_assistants,
            'features'           => $request->features,
            'order'              => (DesignerPackage::max('order') ?? 0) + 1,
        ]);

        return back()->with('success', 'Paquete creado.');
    }

    public function updatePackage(Request $request, DesignerPackage $package)
    {
        $request->validate([
            'name'               => 'sometimes|string|max:255|unique:designer_packages,name,' . $package->id,
            'description'        => 'nullable|string',
            'price'              => 'sometimes|numeric|min:0',
            'default_looks'      => 'sometimes|integer|min:1',
            'default_assistants' => 'sometimes|integer|min:0',
            'features'           => 'nullable|array',
            'order'              => 'sometimes|integer|min:0',
            'is_active'          => 'sometimes|boolean',
        ]);

        $data = $request->only(['name', 'description', 'price', 'default_looks', 'default_assistants', 'features', 'order', 'is_active']);
        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $package->update($data);

        return back()->with('success', 'Paquete actualizado.');
    }

    public function destroyPackage(DesignerPackage $package)
    {
        $package->delete();

        return back()->with('success', 'Paquete eliminado.');
    }
}
