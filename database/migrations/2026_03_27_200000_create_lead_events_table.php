<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('designer_leads')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['lead_id', 'event_id']);
        });

        // Migrate existing data: move event_id from designer_leads to lead_events
        $leads = DB::table('designer_leads')->whereNotNull('event_id')->get();
        foreach ($leads as $lead) {
            DB::table('lead_events')->insert([
                'lead_id'    => $lead->id,
                'event_id'   => $lead->event_id,
                'created_at' => $lead->created_at,
                'updated_at' => $lead->updated_at,
            ]);
        }

        // Remove duplicates: keep the oldest lead per email, merge events
        $duplicates = DB::table('designer_leads')
            ->select('email', DB::raw('MIN(id) as keep_id'))
            ->groupBy('email')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            $allLeads = DB::table('designer_leads')->where('email', $dup->email)->orderBy('id')->get();
            $keepId = $allLeads->first()->id;

            foreach ($allLeads->skip(1) as $extraLead) {
                // Move events from duplicate to the original
                $existingEvents = DB::table('lead_events')->where('lead_id', $keepId)->pluck('event_id')->toArray();
                $extraEvents = DB::table('lead_events')->where('lead_id', $extraLead->id)->get();

                foreach ($extraEvents as $ev) {
                    if (!in_array($ev->event_id, $existingEvents)) {
                        DB::table('lead_events')->where('id', $ev->id)->update(['lead_id' => $keepId]);
                    } else {
                        DB::table('lead_events')->where('id', $ev->id)->delete();
                    }
                }

                // Move activities from duplicate to the original
                DB::table('lead_activities')->where('lead_id', $extraLead->id)->update(['lead_id' => $keepId]);

                // Delete duplicate lead
                DB::table('designer_leads')->where('id', $extraLead->id)->delete();
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_events');
    }
};
