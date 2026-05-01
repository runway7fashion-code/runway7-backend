<?php

namespace App\Services;

use App\Models\Sponsorship\BotMessage;
use App\Models\Sponsorship\Lead;
use App\Models\Sponsorship\LeadActivity;
use App\Models\User;

class SponsorshipBotService
{
    /**
     * In-app notification when a new sponsorship lead is assigned to an advisor.
     */
    public function notifyNewLead(Lead $lead, User $advisor): void
    {
        $companyName = $lead->company?->name ?? '—';
        BotMessage::create([
            'user_id'      => $advisor->id,
            'type'         => 'new_lead',
            'title'        => 'New sponsorship lead assigned',
            'message'      => "You were assigned {$lead->first_name} {$lead->last_name} ({$companyName}).",
            'action_url'   => "/admin/sponsorship/leads/{$lead->id}",
            'action_label' => 'View lead',
        ]);
    }

    /**
     * Look at overdue activities and create in-app overdue messages (no email).
     */
    public function checkOverdueActivities(): int
    {
        $nowLima = now('America/Lima');
        $overdueActivities = LeadActivity::where('status', 'pending')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<', $nowLima->format('Y-m-d H:i:s'))
            ->whereNotNull('assigned_to_user_id')
            ->with(['lead.company:id,name', 'assignedTo:id,first_name,last_name,email'])
            ->get();

        $count = 0;
        $groupedByUser = $overdueActivities->groupBy('assigned_to_user_id');

        foreach ($groupedByUser as $userId => $activities) {
            $user = $activities->first()->assignedTo;
            if (!$user) continue;

            $recentReminder = BotMessage::where('user_id', $userId)
                ->where('type', 'overdue')
                ->where('created_at', '>', now()->subHours(4))
                ->exists();

            if ($recentReminder) continue;

            $activityList = $activities->map(function ($a) {
                $name = $a->lead?->first_name . ' ' . $a->lead?->last_name;
                return "• {$a->title} ({$name})";
            })->join("\n");

            BotMessage::create([
                'user_id'      => $userId,
                'type'         => 'overdue',
                'title'        => "{$activities->count()} overdue activities",
                'message'      => "You have pending sponsorship activities past their scheduled date:\n{$activityList}",
                'action_url'   => '/admin/sponsorship/leads',
                'action_label' => 'View leads',
            ]);

            $count += $activities->count();
        }

        return $count;
    }

    /**
     * In-app reminder for activities scheduled in the next hour.
     */
    public function checkUpcomingActivities(): int
    {
        $nowLima = now('America/Lima');
        $upcoming = LeadActivity::where('status', 'pending')
            ->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [$nowLima->format('Y-m-d H:i:s'), $nowLima->copy()->addHour()->format('Y-m-d H:i:s')])
            ->whereNotNull('assigned_to_user_id')
            ->with(['lead.company:id,name'])
            ->get();

        $count = 0;

        foreach ($upcoming as $activity) {
            $alreadyReminded = BotMessage::where('user_id', $activity->assigned_to_user_id)
                ->where('type', 'reminder')
                ->where('message', 'like', "%{$activity->id}%")
                ->where('created_at', '>', now()->subHours(2))
                ->exists();

            if ($alreadyReminded) continue;

            $time = $activity->scheduled_at->format('g:i A');
            $leadName = $activity->lead
                ? trim($activity->lead->first_name . ' ' . $activity->lead->last_name)
                : '';
            $companyPart = $activity->lead?->company?->name ? " ({$activity->lead->company->name})" : '';

            BotMessage::create([
                'user_id'      => $activity->assigned_to_user_id,
                'type'         => 'reminder',
                'title'        => "Reminder: {$activity->title}",
                'message'      => "Scheduled at {$time}: {$activity->title}"
                    . ($leadName ? " — {$leadName}{$companyPart}" : '')
                    . " [ID:{$activity->id}]",
                'action_url'   => $activity->lead ? "/admin/sponsorship/leads/{$activity->lead_id}" : '/admin/sponsorship/leads',
                'action_label' => 'View detail',
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * In-app alert for sponsorship leads without contact in 48+ hours.
     * Active statuses: nuevo, contactado, interesado, contrato.
     */
    public function checkStaleLeads(): int
    {
        $activeStatuses = ['nuevo', 'contactado', 'interesado', 'contrato'];

        $staleLeads = Lead::whereIn('status', $activeStatuses)
            ->whereNotNull('assigned_to_user_id')
            ->where(function ($q) use ($activeStatuses) {
                $q->whereNull('last_contacted_at')
                  ->where('created_at', '<', now()->subHours(48));
            })
            ->orWhere(function ($q) use ($activeStatuses) {
                $q->whereNotNull('last_contacted_at')
                  ->where('last_contacted_at', '<', now()->subHours(48))
                  ->whereIn('status', $activeStatuses)
                  ->whereNotNull('assigned_to_user_id');
            })
            ->with(['assignedTo:id,first_name,last_name', 'company:id,name'])
            ->get();

        $count = 0;
        $groupedByAdvisor = $staleLeads->groupBy('assigned_to_user_id');

        foreach ($groupedByAdvisor as $advisorId => $leads) {
            $advisor = $leads->first()->assignedTo;
            if (!$advisor) continue;

            $recentAlert = BotMessage::where('user_id', $advisorId)
                ->where('type', 'alert')
                ->where('created_at', '>', now()->subHours(12))
                ->exists();

            if ($recentAlert) continue;

            $leadList = $leads->map(function ($l) {
                $name = trim($l->first_name . ' ' . $l->last_name);
                $company = $l->company?->name ?? '—';
                return "• {$name} ({$company})";
            })->join("\n");

            BotMessage::create([
                'user_id'      => $advisorId,
                'type'         => 'alert',
                'title'        => "{$leads->count()} leads without contact in 48h+",
                'message'      => "These leads need follow-up:\n{$leadList}",
                'action_url'   => '/admin/sponsorship/leads',
                'action_label' => 'View leads',
            ]);

            $count += $leads->count();
        }

        return $count;
    }
}
