<?php

namespace App\Services;

use App\Models\DesignerLead;
use App\Models\LeadActivity;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use App\Services\SalesBotService;

class LeadAssignmentService
{
    /**
     * Assign a lead to the next available sales advisor using round-robin.
     */
    public function assignRoundRobin(DesignerLead $lead): ?User
    {
        $availableAdvisors = User::where('role', 'sales')
            ->where('is_available', true)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get();

        if ($availableAdvisors->isEmpty()) {
            $this->checkUnassignedAlert();
            return null;
        }

        // Get the last assigned advisor ID from cache
        $lastAssignedId = Cache::get('lead_round_robin_last_id', 0);

        // Find the next advisor in rotation
        $nextAdvisor = $availableAdvisors->first(fn($a) => $a->id > $lastAssignedId)
            ?? $availableAdvisors->first();

        // Assign the lead
        $lead->update(['assigned_to' => $nextAdvisor->id]);

        // Save the last assigned ID
        Cache::put('lead_round_robin_last_id', $nextAdvisor->id, now()->addYear());

        // Log the assignment
        LeadActivity::create([
            'lead_id'     => $lead->id,
            'user_id'     => null,
            'type'        => 'assignment',
            'title'       => "Asignado automáticamente a {$nextAdvisor->first_name} {$nextAdvisor->last_name}",
            'description' => 'Asignación automática por round-robin',
            'status'      => 'completed',
            'completed_at'=> now(),
        ]);

        // Send bot message to advisor
        (new SalesBotService())->notifyNewLead($lead, $nextAdvisor);

        return $nextAdvisor;
    }

    /**
     * Check if there are 3+ unassigned leads and send alert email.
     */
    public function checkUnassignedAlert(): void
    {
        $unassignedCount = DesignerLead::whereNull('assigned_to')
            ->where('status', 'new')
            ->count();

        if ($unassignedCount >= 3) {
            $lastAlertSent = Cache::get('lead_unassigned_alert_sent');
            if (!$lastAlertSent) {
                Mail::raw(
                    "Hay {$unassignedCount} prospectos sin asignar en el sistema. Por favor revisa el panel de ventas.",
                    function ($message) use ($unassignedCount) {
                        $message->to('designers@runway7fashion.com')
                            ->subject("⚠️ {$unassignedCount} prospectos sin asignar en Runway 7");
                    }
                );
                Cache::put('lead_unassigned_alert_sent', true, now()->addHours(6));
            }
        }
    }

    /**
     * Auto-create a call activity from the lead's preferred contact time.
     */
    public function scheduleInitialCall(DesignerLead $lead): void
    {
        if (!$lead->preferred_contact_time || !$lead->assigned_to) {
            return;
        }

        // Parse the preferred time (e.g., "10am" → 10:00)
        $hour = (int) filter_var($lead->preferred_contact_time, FILTER_SANITIZE_NUMBER_INT);
        if (str_contains(strtolower($lead->preferred_contact_time), 'pm') && $hour < 12) {
            $hour += 12;
        }

        // Schedule for the next business day
        $scheduledDate = now()->addWeekday();
        $scheduledAt = $scheduledDate->setTime($hour, 0, 0);

        LeadActivity::create([
            'lead_id'      => $lead->id,
            'user_id'      => $lead->assigned_to,
            'type'         => 'call',
            'title'        => "Llamar a {$lead->full_name}",
            'description'  => "Hora preferida de contacto: {$lead->preferred_contact_time}. Empresa: {$lead->company_name}",
            'scheduled_at' => $scheduledAt,
            'status'       => 'pending',
        ]);
    }
}
