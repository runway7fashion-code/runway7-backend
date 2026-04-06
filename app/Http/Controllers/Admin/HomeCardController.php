<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\HomeCard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class HomeCardController extends Controller
{
    public function index(Request $request): Response
    {
        $query = HomeCard::with('event:id,name');

        if ($request->filled('search')) {
            $query->where('title', 'ilike', '%' . $request->search . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('role')) {
            $query->whereJsonContains('target_roles', $request->role);
        }

        if ($request->filled('action_type')) {
            $query->where('action_type', $request->action_type);
        }

        $cards = $query->orderBy('order')->orderByDesc('created_at')->get();
        $events = Event::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/HomeCards/Index', [
            'cards'   => $cards,
            'events'  => $events,
            'filters' => $request->only(['search', 'status', 'role', 'action_type']),
        ]);
    }

    public function create(): Response
    {
        $events = Event::orderBy('start_date', 'desc')->get(['id', 'name']);
        $nextOrder = (HomeCard::max('order') ?? 0) + 1;

        return Inertia::render('Admin/HomeCards/Create', [
            'events'    => $events,
            'nextOrder' => $nextOrder,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'        => 'required|string|max:255',
            'image'        => 'required|image|max:5120',
            'action_type'  => 'required|in:url,video,mailto',
            'action_value' => 'required|string|max:500',
            'target_roles' => 'nullable|array',
            'event_id'     => 'nullable|exists:events,id',
            'order'        => 'required|integer|min:0',
            'status'       => 'required|in:active,inactive',
            'starts_at'    => 'nullable|date',
            'ends_at'      => 'nullable|date|after_or_equal:starts_at',
        ]);

        $order = (int) $request->input('order', 0);
        HomeCard::where('order', '>=', $order)->increment('order');

        $imagePath = $request->file('image')->store('home-cards', 'public');

        HomeCard::create([
            'title'        => $request->title,
            'image_url'    => $imagePath,
            'action_type'  => $request->action_type,
            'action_value' => $request->action_value,
            'target_roles' => $request->target_roles ?: null,
            'event_id'     => $request->event_id,
            'order'        => $order,
            'status'       => $request->status,
            'starts_at'    => $request->starts_at,
            'ends_at'      => $request->ends_at,
        ]);

        return redirect()->route('admin.home-cards.index')
            ->with('success', 'Home Card created successfully.');
    }

    public function edit(HomeCard $homeCard): Response
    {
        $events = Event::orderBy('start_date', 'desc')->get(['id', 'name']);

        return Inertia::render('Admin/HomeCards/Edit', [
            'card'   => $homeCard,
            'events' => $events,
        ]);
    }

    public function update(Request $request, HomeCard $homeCard)
    {
        $request->validate([
            'title'        => 'required|string|max:255',
            'action_type'  => 'required|in:url,video,mailto',
            'action_value' => 'required|string|max:500',
            'target_roles' => 'nullable|array',
            'event_id'     => 'nullable|exists:events,id',
            'order'        => 'required|integer|min:0',
            'status'       => 'required|in:active,inactive',
            'starts_at'    => 'nullable|date',
            'ends_at'      => 'nullable|date|after_or_equal:starts_at',
        ]);

        $newOrder = (int) $request->input('order', 0);

        if ($homeCard->order !== $newOrder) {
            HomeCard::where('id', '!=', $homeCard->id)
                ->where('order', '>', $homeCard->order)
                ->decrement('order');

            HomeCard::where('id', '!=', $homeCard->id)
                ->where('order', '>=', $newOrder)
                ->increment('order');
        }

        $homeCard->update([
            'title'        => $request->title,
            'action_type'  => $request->action_type,
            'action_value' => $request->action_value,
            'target_roles' => $request->target_roles ?: null,
            'event_id'     => $request->event_id,
            'order'        => $newOrder,
            'status'       => $request->status,
            'starts_at'    => $request->starts_at,
            'ends_at'      => $request->ends_at,
        ]);

        return redirect()->route('admin.home-cards.index')
            ->with('success', 'Home Card updated.');
    }

    public function destroy(HomeCard $homeCard)
    {
        if ($homeCard->image_url) {
            Storage::disk('public')->delete($homeCard->image_url);
        }

        $deletedOrder = $homeCard->order;
        $homeCard->delete();

        HomeCard::where('order', '>', $deletedOrder)->decrement('order');

        return redirect()->route('admin.home-cards.index')
            ->with('success', 'Home Card deleted.');
    }

    public function uploadImage(Request $request, HomeCard $homeCard)
    {
        $request->validate([
            'image' => 'required|image|max:5120',
        ]);

        if ($homeCard->image_url) {
            Storage::disk('public')->delete($homeCard->image_url);
        }

        $path = $request->file('image')->store('home-cards', 'public');
        $homeCard->update(['image_url' => $path]);

        return back()->with('success', 'Image updated.');
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'order'         => 'required|array',
            'order.*.id'    => 'required|exists:home_cards,id',
            'order.*.order' => 'required|integer|min:0',
        ]);

        foreach ($request->order as $item) {
            HomeCard::where('id', $item['id'])->update(['order' => $item['order']]);
        }

        return back()->with('success', 'Order updated.');
    }
}
