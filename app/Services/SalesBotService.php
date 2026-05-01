<?php

namespace App\Services;

use App\Models\DesignerLead;
use App\Models\LeadActivity;
use App\Models\SalesBotMessage;
use App\Models\User;

class SalesBotService
{
    /**
     * In-app notification when a new lead is assigned to an advisor.
     */
    public function notifyNewLead(DesignerLead $lead, User $advisor): void
    {
        SalesBotMessage::create([
            'user_id'      => $advisor->id,
            'type'         => 'new_lead',
            'title'        => 'New prospect assigned',
            'message'      => "You were assigned {$lead->full_name} ({$lead->company_name}). Budget: {$lead->budget}.",
            'action_url'   => "/admin/sales/leads/{$lead->id}",
            'action_label' => 'View prospect',
        ]);
    }

    /**
     * Look at overdue activities and create in-app overdue messages (no email).
     * Email is handled separately by sendDailyOverdueDigest at 9 AM weekdays.
     */
    public function checkOverdueActivities(): int
    {
        $nowLima = now('America/Lima');
        $overdueActivities = LeadActivity::where('status', 'pending')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<', $nowLima->format('Y-m-d H:i:s'))
            ->whereNotNull('user_id')
            ->with(['lead:id,first_name,last_name,company_name', 'user:id,first_name,last_name,email'])
            ->get();

        $count = 0;
        $groupedByUser = $overdueActivities->groupBy('user_id');

        foreach ($groupedByUser as $userId => $activities) {
            $user = $activities->first()->user;
            if (!$user) continue;

            // Throttle in-app notification: avoid spamming the bell within 4 hours.
            $recentReminder = SalesBotMessage::where('user_id', $userId)
                ->where('type', 'overdue')
                ->where('created_at', '>', now()->subHours(4))
                ->exists();

            if ($recentReminder) continue;

            $activityList = $activities->map(fn($a) => "• {$a->title} ({$a->lead?->full_name})")->join("\n");

            SalesBotMessage::create([
                'user_id'      => $userId,
                'type'         => 'overdue',
                'title'        => "{$activities->count()} overdue activities",
                'message'      => "You have pending activities past their scheduled date:\n{$activityList}",
                'action_url'   => '/admin/sales/leads',
                'action_label' => 'View prospects',
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
            ->whereNotNull('user_id')
            ->with(['lead:id,first_name,last_name,company_name'])
            ->get();

        $count = 0;

        foreach ($upcoming as $activity) {
            // Don't send duplicate reminders for the same activity within 2 hours.
            $alreadyReminded = SalesBotMessage::where('user_id', $activity->user_id)
                ->where('type', 'reminder')
                ->where('message', 'like', "%{$activity->id}%")
                ->where('created_at', '>', now()->subHours(2))
                ->exists();

            if ($alreadyReminded) continue;

            $time = $activity->scheduled_at->format('g:i A');

            SalesBotMessage::create([
                'user_id'      => $activity->user_id,
                'type'         => 'reminder',
                'title'        => "Reminder: {$activity->title}",
                'message'      => "Scheduled at {$time}: {$activity->title}" .
                    ($activity->lead ? " — {$activity->lead->full_name} ({$activity->lead->company_name})" : '') .
                    " [ID:{$activity->id}]",
                'action_url'   => $activity->lead ? "/admin/sales/leads/{$activity->lead_id}" : '/admin/sales/calendar',
                'action_label' => 'View detail',
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * In-app alert for leads without contact in the last 48 hours.
     */
    public function checkStaleLeads(): int
    {
        $staleLeads = DesignerLead::whereIn('status', ['new', 'contacted', 'follow_up'])
            ->whereNotNull('assigned_to')
            ->where(function ($q) {
                $q->whereNull('last_contacted_at')
                  ->where('created_at', '<', now()->subHours(48));
            })
            ->orWhere(function ($q) {
                $q->whereNotNull('last_contacted_at')
                  ->where('last_contacted_at', '<', now()->subHours(48))
                  ->whereIn('status', ['new', 'contacted', 'follow_up'])
                  ->whereNotNull('assigned_to');
            })
            ->with('assignedTo:id,first_name,last_name')
            ->get();

        $count = 0;
        $groupedByAdvisor = $staleLeads->groupBy('assigned_to');

        foreach ($groupedByAdvisor as $advisorId => $leads) {
            $advisor = $leads->first()->assignedTo;
            if (!$advisor) continue;

            $recentAlert = SalesBotMessage::where('user_id', $advisorId)
                ->where('type', 'alert')
                ->where('created_at', '>', now()->subHours(12))
                ->exists();

            if ($recentAlert) continue;

            $leadList = $leads->map(fn($l) => "• {$l->full_name} ({$l->company_name})")->join("\n");

            SalesBotMessage::create([
                'user_id'      => $advisorId,
                'type'         => 'alert',
                'title'        => "{$leads->count()} prospects without contact in 48h+",
                'message'      => "These prospects need follow-up:\n{$leadList}",
                'action_url'   => '/admin/sales/leads?status=follow_up',
                'action_label' => 'View prospects',
            ]);

            $count += $leads->count();
        }

        return $count;
    }
}
