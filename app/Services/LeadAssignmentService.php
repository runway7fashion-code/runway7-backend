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
     * Assign a lead to the next available sales advisor using dual round-robin.
     * USA leads have their own rotation, other countries have a separate one.
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

        // Determine which round-robin queue to use
        $isUSA = strtolower(trim($lead->country ?? '')) === 'united states';
        $cacheKey = $isUSA ? 'lead_round_robin_usa_last_id' : 'lead_round_robin_other_last_id';

        // Get the last assigned advisor ID for this queue
        $lastAssignedId = Cache::get($cacheKey, 0);

        // Find the next advisor in rotation
        $nextAdvisor = $availableAdvisors->first(fn($a) => $a->id > $lastAssignedId)
            ?? $availableAdvisors->first();

        // Assign the lead
        $lead->update(['assigned_to' => $nextAdvisor->id]);

        // Save the last assigned ID for this queue
        Cache::put($cacheKey, $nextAdvisor->id, now()->addYear());

        // Log the assignment
        $queueLabel = $isUSA ? 'USA priority' : 'standard';
        LeadActivity::create([
            'lead_id'     => $lead->id,
            'user_id'     => null,
            'type'        => 'assignment',
            'title'       => "Auto-assigned to {$nextAdvisor->first_name} {$nextAdvisor->last_name}",
            'description' => "Round-robin ({$queueLabel})",
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
            ->whereIn('status', ['new', 'qualified'])
            ->count();

        if ($unassignedCount >= 3) {
            $lastAlertSent = Cache::get('lead_unassigned_alert_sent');
            if (!$lastAlertSent) {
                Mail::raw(
                    "There are {$unassignedCount} unassigned prospects in the system. Please review the sales panel.",
                    function ($message) use ($unassignedCount) {
                        $message->to('designers@runway7fashion.com')
                            ->subject("⚠️ {$unassignedCount} unassigned prospects in Runway 7");
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
            'title'        => "Call {$lead->full_name}",
            'description'  => "Preferred contact time: {$lead->preferred_contact_time}. Company: {$lead->company_name}",
            'scheduled_at' => $scheduledAt,
            'status'       => 'pending',
        ]);
    }
}
