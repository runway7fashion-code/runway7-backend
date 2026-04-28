<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DesignerMaterial;
use App\Models\Event;
use App\Models\MaterialInstruction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MaterialInstructionsController extends Controller
{
    public function index(): Response
    {
        // Sort by the canonical order defined in DesignerMaterial::MATERIALS
        $order = array_keys(DesignerMaterial::MATERIALS);
        $instructions = MaterialInstruction::all()
            ->sortBy(fn ($row) => array_search($row->material_name, $order, true))
            ->values()
            ->map(function ($row) {
                $meta = DesignerMaterial::MATERIALS[$row->material_name] ?? [];
                return [
                    'id'             => $row->id,
                    'material_name'  => $row->material_name,
                    'instructions'   => $row->instructions,
                    'upload_by'      => $meta['upload_by'] ?? null,
                    'flow'           => $meta['flow'] ?? null,
                    'updated_at'     => $row->updated_at?->toIso8601String(),
                ];
            });

        // Events that operations can manage default deadlines for
        $events = Event::whereIn('status', ['published', 'active', 'draft'])
            ->orderBy('start_date', 'desc')
            ->get(['id', 'name', 'start_date', 'materials_deadline_default'])
            ->map(function ($e) {
                $designerCount = DB::table('event_designer')->where('event_id', $e->id)->count();
                $withCustom = DB::table('event_designer')
                    ->where('event_id', $e->id)
                    ->whereNotNull('materials_deadline')
                    ->count();
                return [
                    'id'                          => $e->id,
                    'name'                        => $e->name,
                    'start_date'                  => $e->start_date?->toDateString(),
                    'materials_deadline_default'  => $e->materials_deadline_default?->toDateString(),
                    'designers_count'             => $designerCount,
                    'designers_with_custom'       => $withCustom,
                ];
            });

        return Inertia::render('Admin/Designers/MaterialInstructions', [
            'instructions' => $instructions,
            'events'       => $events,
        ]);
    }

    public function update(Request $request, MaterialInstruction $instruction)
    {
        $request->validate([
            'instructions' => 'nullable|string|max:5000',
        ]);

        $instruction->update([
            'instructions' => $request->instructions,
        ]);

        return back()->with('success', "Instructions for {$instruction->material_name} updated.");
    }

    /**
     * Update the default materials deadline for an event.
     * - 'overwrite' = false (default): only fills the deadline of designers that DON'T have a custom one (NULL).
     * - 'overwrite' = true: forces this deadline on every designer of the event (replacing custom values).
     */
    public function updateEventDeadline(Request $request, Event $event)
    {
        $data = $request->validate([
            'materials_deadline_default' => 'nullable|date',
            'overwrite'                  => 'boolean',
        ]);

        $deadline  = $data['materials_deadline_default'] ?? null;
        $overwrite = (bool) ($data['overwrite'] ?? false);

        DB::transaction(function () use ($event, $deadline, $overwrite) {
            $event->update(['materials_deadline_default' => $deadline]);

            if ($deadline === null) {
                // Clearing the default: don't touch per-designer values, the app will just have no fallback.
                return;
            }

            $query = DB::table('event_designer')->where('event_id', $event->id);
            if (!$overwrite) {
                $query->whereNull('materials_deadline');
            }
            $query->update(['materials_deadline' => $deadline]);
        });

        $msg = $deadline === null
            ? "Default deadline cleared for {$event->name}."
            : ($overwrite
                ? "Default deadline applied to ALL designers of {$event->name}."
                : "Default deadline applied to designers without a custom one in {$event->name}.");

        return back()->with('success', $msg);
    }
}
