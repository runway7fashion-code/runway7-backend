<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class BannerController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Banner::with('event:id,name');

        if ($request->filled('search')) {
            $query->where('title', 'ilike', '%' . $request->search . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('role')) {
            $query->whereJsonContains('target_roles', $request->role);
        }

        if ($request->filled('event_id')) {
            $query->where('event_id', $request->event_id);
        }

        $banners = $query->orderBy('order')->orderByDesc('created_at')->get();

        $events = Event::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Banners/Index', [
            'banners' => $banners,
            'events'  => $events,
            'filters' => $request->only(['search', 'status', 'role', 'event_id']),
        ]);
    }

    public function create(): Response
    {
        $events = Event::orderBy('start_date', 'desc')->get(['id', 'name']);
        $nextOrder = (Banner::max('order') ?? 0) + 1;

        return Inertia::render('Admin/Banners/Create', [
            'events'    => $events,
            'nextOrder' => $nextOrder,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'        => 'required|string|max:255',
            'image'        => 'required|image|max:5120',
            'link_url'     => 'nullable|url|max:500',
            'target_roles' => 'nullable|array',
            'event_id'     => 'nullable|exists:events,id',
            'order'        => 'required|integer|min:0',
            'status'       => 'required|in:active,inactive',
            'starts_at'    => 'required|date',
            'ends_at'      => 'required|date|after_or_equal:starts_at',
        ]);

        $order = (int) $request->input('order', 0);

        // Desplazar banners existentes que tengan orden >= al nuevo
        Banner::where('order', '>=', $order)->increment('order');

        $imagePath = $request->file('image')->store('banners', 'public');

        Banner::create([
            'title'        => $request->title,
            'image_url'    => $imagePath,
            'link_url'     => $request->link_url,
            'target_roles' => $request->target_roles ?: null,
            'event_id'     => $request->event_id,
            'order'        => $order,
            'status'       => $request->status,
            'starts_at'    => $request->starts_at,
            'ends_at'      => $request->ends_at,
        ]);

        return redirect()->route('admin.banners.index')
            ->with('success', 'Banner creado exitosamente.');
    }

    public function edit(Banner $banner): Response
    {
        $events = Event::orderBy('start_date', 'desc')->get(['id', 'name']);

        return Inertia::render('Admin/Banners/Edit', [
            'banner' => $banner,
            'events' => $events,
        ]);
    }

    public function update(Request $request, Banner $banner)
    {
        $request->validate([
            'title'        => 'required|string|max:255',
            'link_url'     => 'nullable|url|max:500',
            'target_roles' => 'nullable|array',
            'event_id'     => 'nullable|exists:events,id',
            'order'        => 'required|integer|min:0',
            'status'       => 'required|in:active,inactive',
            'starts_at'    => 'required|date',
            'ends_at'      => 'required|date|after_or_equal:starts_at',
        ]);

        $newOrder = (int) $request->input('order', 0);

        if ($banner->order !== $newOrder) {
            // Cerrar hueco en la posición antigua
            Banner::where('id', '!=', $banner->id)
                ->where('order', '>', $banner->order)
                ->decrement('order');

            // Abrir hueco en la posición nueva
            Banner::where('id', '!=', $banner->id)
                ->where('order', '>=', $newOrder)
                ->increment('order');
        }

        $banner->update([
            'title'        => $request->title,
            'link_url'     => $request->link_url,
            'target_roles' => $request->target_roles ?: null,
            'event_id'     => $request->event_id,
            'order'        => $newOrder,
            'status'       => $request->status,
            'starts_at'    => $request->starts_at,
            'ends_at'      => $request->ends_at,
        ]);

        return redirect()->route('admin.banners.index')
            ->with('success', 'Banner actualizado.');
    }

    public function destroy(Banner $banner)
    {
        if ($banner->image_url) {
            Storage::disk('public')->delete($banner->image_url);
        }

        $deletedOrder = $banner->order;
        $banner->delete();

        // Cerrar hueco en el orden
        Banner::where('order', '>', $deletedOrder)->decrement('order');

        return redirect()->route('admin.banners.index')
            ->with('success', 'Banner eliminado.');
    }

    public function uploadImage(Request $request, Banner $banner)
    {
        $request->validate([
            'image' => 'required|image|max:5120',
        ]);

        if ($banner->image_url) {
            Storage::disk('public')->delete($banner->image_url);
        }

        $path = $request->file('image')->store('banners', 'public');
        $banner->update(['image_url' => $path]);

        return back()->with('success', 'Imagen del banner actualizada.');
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'order'     => 'required|array',
            'order.*.id'    => 'required|exists:banners,id',
            'order.*.order' => 'required|integer|min:0',
        ]);

        foreach ($request->order as $item) {
            Banner::where('id', $item['id'])->update(['order' => $item['order']]);
        }

        return back()->with('success', 'Orden actualizado.');
    }
}
