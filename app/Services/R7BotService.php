<?php

namespace App\Services;

use App\Models\DesignerLead;
use App\Models\LeadActivity;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class R7BotService
{
    public function ask(User $user, string $message): string
    {
        // Check if there's a pending action waiting for confirmation
        $pendingAction = Cache::get("bot_pending_{$user->id}");
        if ($pendingAction) {
            return $this->handleConfirmation($user, $message, $pendingAction);
        }

        // Check if user is selecting from multiple leads
        $pendingSelection = Cache::get("bot_selection_{$user->id}");
        if ($pendingSelection) {
            return $this->handleSelection($user, $message, $pendingSelection);
        }

        $context = $this->buildContext($user);
        $leadsListContext = $this->buildLeadsList($user);

        $systemPrompt = <<<PROMPT
You are R7, the virtual assistant for the Runway 7 Fashion Week sales team.
Reply in English, briefly and directly.
Do not use markdown or special formatting, only plain text.

Advisor info:
- Name: {$user->first_name} {$user->last_name}
- Role: {$user->sales_type}

{$context}

{$leadsListContext}

IMPORTANT INSTRUCTIONS:
1. If the advisor wants to CREATE an activity (schedule a call, meeting, email) or a note, reply EXACTLY with this JSON format:
{"action":"create_activity","type":"call","lead_name":"lead name","title":"activity title","scheduled_at":"YYYY-MM-DD HH:mm","description":"optional description"}

Valid types are: call, email, meeting, note
For call, email and meeting: if no time is mentioned, use 10:00. If they say "tomorrow", compute the date.
For note: NEVER include scheduled_at. Notes are immediate records, not scheduled. The title must summarize the content and the description is the full note text.
Keep the description short (max 150 characters).

2. If the advisor asks for information or a question, reply normally in plain text.

3. NEVER mix JSON with text. If it is an action, reply with ONLY the JSON.
PROMPT;

        try {
            $response = Http::timeout(15)->post(
                'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . config('services.google_ai.key'),
                [
                    'contents' => [
                        ['role' => 'user', 'parts' => [['text' => $systemPrompt . "\n\nMensaje del asesor: " . $message]]],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.2,
                        'maxOutputTokens' => 1000,
                    ],
                ]
            );

            if (!$response->successful()) {
                return 'Could not reach the AI service. Please try again.';
            }

            $data = $response->json();
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

            // Check if response is a JSON action
            $text = trim($text);
            // Clean markdown code blocks if present
            if (str_starts_with($text, '```')) {
                $text = preg_replace('/^```json?\n?/', '', $text);
                $text = preg_replace('/\n?```$/', '', $text);
                $text = trim($text);
            }
            if (str_starts_with($text, '{') && str_contains($text, '"action"')) {
                $actionData = json_decode($text, true);
                // If JSON is incomplete, try to fix it
                if (!$actionData) {
                    $text = rtrim($text, ', ');
                    if (!str_ends_with($text, '}')) $text .= '"}';
                    $actionData = json_decode($text, true);
                }
                if ($actionData && ($actionData['action'] ?? '') === 'create_activity') {
                    return $this->prepareActivityCreation($user, $actionData);
                }
            }

            return $text ?: 'I could not process your request.';
        } catch (\Exception $e) {
            \Log::warning('R7 Bot AI error: ' . $e->getMessage());
            return 'The AI service is not available right now.';
        }
    }

    private function prepareActivityCreation(User $user, array $data): string
    {
        $leadName = $data['lead_name'] ?? '';
        $type = $data['type'] ?? 'call';
        $title = $data['title'] ?? '';
        $scheduledAt = $data['scheduled_at'] ?? '';
        $description = $data['description'] ?? '';

        $typeLabels = ['call' => 'Call', 'email' => 'Email', 'meeting' => 'Meeting', 'note' => 'Note'];
        $typeLabel = $typeLabels[$type] ?? $type;

        // Search for matching leads
        $isLeader = $user->isLeaderOf('sales');
        $query = DesignerLead::query();
        if (!$isLeader) {
            $query->where('assigned_to', $user->id);
        }

        $leads = $query->where(function ($q) use ($leadName) {
            $q->where('first_name', 'ilike', "%{$leadName}%")
              ->orWhere('last_name', 'ilike', "%{$leadName}%")
              ->orWhere('company_name', 'ilike', "%{$leadName}%")
              ->orWhereRaw("CONCAT(first_name, ' ', last_name) ILIKE ?", ["%{$leadName}%"]);
        })->limit(5)->get();

        if ($leads->isEmpty()) {
            return "I could not find any lead named \"{$leadName}\". Double-check the name and try again.";
        }

        if ($leads->count() === 1) {
            $lead = $leads->first();
            $actionData = [
                'lead_id'      => $lead->id,
                'type'         => $type,
                'title'        => $title ?: "{$typeLabel} with {$lead->first_name} {$lead->last_name}",
                'scheduled_at' => $scheduledAt,
                'description'  => $description,
            ];

            Cache::put("bot_pending_{$user->id}", $actionData, now()->addMinutes(5));

            $confirmation = "Found {$lead->first_name} {$lead->last_name} ({$lead->company_name} - {$lead->email}).\n\nDo you want to create this activity?\n- Type: {$typeLabel}\n- Lead: {$lead->first_name} {$lead->last_name}";
            if ($scheduledAt) {
                $dateFormatted = \Carbon\Carbon::parse($scheduledAt, 'America/Lima')->format('M d, Y g:i A');
                $confirmation .= "\n- Date: {$dateFormatted}";
            }
            if ($description) {
                $confirmation .= "\n- Content: {$description}";
            }
            $confirmation .= "\n\nReply 'yes' to confirm or 'no' to cancel.";

            return $confirmation;
        }

        // Multiple leads found
        $options = $leads->map(function ($lead, $idx) {
            return ($idx + 1) . ". {$lead->first_name} {$lead->last_name} ({$lead->company_name})";
        })->join("\n");

        Cache::put("bot_selection_{$user->id}", [
            'leads' => $leads->map(fn($l) => ['id' => $l->id, 'name' => "{$l->first_name} {$l->last_name}", 'company' => $l->company_name, 'email' => $l->email])->toArray(),
            'type' => $type,
            'title' => $title,
            'scheduled_at' => $scheduledAt,
            'description' => $description,
        ], now()->addMinutes(5));

        return "Found {$leads->count()} leads with that name:\n{$options}\n\nWhich one? Reply with the number.";
    }

    private function handleSelection(User $user, string $message, array $selection): string
    {
        Cache::forget("bot_selection_{$user->id}");

        $message = strtolower(trim($message));

        if (in_array($message, ['no', 'cancel', 'none'])) {
            return 'Got it, operation cancelled.';
        }

        $num = intval($message);
        if ($num < 1 || $num > count($selection['leads'])) {
            return 'Invalid number. Operation cancelled. Please try again.';
        }

        $lead = $selection['leads'][$num - 1];
        $type = $selection['type'];
        $typeLabels = ['call' => 'Call', 'email' => 'Email', 'meeting' => 'Meeting', 'note' => 'Note'];
        $typeLabel = $typeLabels[$type] ?? $type;

        $actionData = [
            'lead_id'      => $lead['id'],
            'type'         => $type,
            'title'        => $selection['title'] ?: "{$typeLabel} with {$lead['name']}",
            'scheduled_at' => $selection['scheduled_at'],
            'description'  => $selection['description'],
        ];

        Cache::put("bot_pending_{$user->id}", $actionData, now()->addMinutes(5));

        $confirmation = "You picked {$lead['name']} ({$lead['company']} - {$lead['email']}).\n\nDo you want to create this activity?\n- Type: {$typeLabel}\n- Lead: {$lead['name']}";
        if ($selection['scheduled_at']) {
            $dateFormatted = \Carbon\Carbon::parse($selection['scheduled_at'], 'America/Lima')->format('M d, Y g:i A');
            $confirmation .= "\n- Date: {$dateFormatted}";
        }
        if ($selection['description']) {
            $confirmation .= "\n- Content: {$selection['description']}";
        }
        $confirmation .= "\n\nReply 'yes' to confirm or 'no' to cancel.";

        return $confirmation;
    }

    private function handleConfirmation(User $user, string $message, array $action): string
    {
        Cache::forget("bot_pending_{$user->id}");

        $message = strtolower(trim($message));

        if (in_array($message, ['yes', 'y', 'confirm', 'ok', 'sure', 'sí', 'si'])) {
            try {
                LeadActivity::create([
                    'lead_id'      => $action['lead_id'],
                    'user_id'      => $user->id,
                    'type'         => $action['type'],
                    'title'        => $action['title'],
                    'description'  => $action['description'] ?? null,
                    'scheduled_at' => $action['scheduled_at'] ?: null,
                    'status'       => $action['scheduled_at'] ? 'pending' : 'completed',
                    'completed_at' => $action['scheduled_at'] ? null : now(),
                ]);

                $lead = DesignerLead::find($action['lead_id']);
                $typeLabels = ['call' => 'Call', 'email' => 'Email', 'meeting' => 'Meeting', 'note' => 'Note'];

                $typeName = $typeLabels[$action['type']] ?? 'Activity';
                $msg = "Done. {$typeName} created for {$lead->first_name} {$lead->last_name}.";
                if ($action['scheduled_at']) {
                    $msg .= " Scheduled for " . \Carbon\Carbon::parse($action['scheduled_at'], 'America/Lima')->format('M d, Y g:i A') . ".";
                }
                return $msg;
            } catch (\Exception $e) {
                \Log::warning('R7 Bot create activity error: ' . $e->getMessage());
                return 'Error creating the activity. Please try again.';
            }
        }

        return 'Got it, the activity was not created.';
    }

    private function buildLeadsList(User $user): string
    {
        $isLeader = $user->isLeaderOf('sales');
        $query = DesignerLead::query();
        if (!$isLeader) {
            $query->where('assigned_to', $user->id);
        }

        $leads = $query->select('id', 'first_name', 'last_name', 'company_name', 'status')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        if ($leads->isEmpty()) return '';

        $list = $leads->map(fn($l) => "- {$l->first_name} {$l->last_name} ({$l->company_name}) - Status: {$l->status}")->join("\n");

        return "ADVISOR LEADS:\n{$list}\n";
    }

    private function buildContext(User $user): string
    {
        $isLeader = $user->isLeaderOf('sales');
        $now = now('America/Lima');
        $today = $now->format('Y-m-d');

        $leadsQuery = DesignerLead::query();
        if (!$isLeader) {
            $leadsQuery->where('assigned_to', $user->id);
        }

        $totalLeads = (clone $leadsQuery)->count();
        $newLeads = (clone $leadsQuery)->where('status', 'new')->count();
        $qualifiedLeads = (clone $leadsQuery)->where('status', 'qualified')->count();
        $clientLeads = (clone $leadsQuery)->where('status', 'client')->count();
        $lostLeads = (clone $leadsQuery)->where('status', 'lost')->count();

        $activitiesToday = LeadActivity::whereNotNull('scheduled_at')->whereDate('scheduled_at', $today);
        if (!$isLeader) {
            $activitiesToday->where('user_id', $user->id);
        }
        $pendingToday = (clone $activitiesToday)->where('status', 'pending')->count();
        $completedToday = (clone $activitiesToday)->where('status', 'completed')->count();

        $overdue = LeadActivity::where('status', 'pending')->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<', $now->format('Y-m-d H:i:s'));
        if (!$isLeader) {
            $overdue->where('user_id', $user->id);
        }
        $overdueCount = $overdue->count();

        $upcoming = LeadActivity::where('status', 'pending')->whereNotNull('scheduled_at')
            ->whereDate('scheduled_at', $today)
            ->where('scheduled_at', '>=', $now->format('Y-m-d H:i:s'));
        if (!$isLeader) {
            $upcoming->where('user_id', $user->id);
        }
        $upcomingList = $upcoming->with('lead:id,first_name,last_name,company_name')
            ->orderBy('scheduled_at')->limit(5)->get()
            ->map(fn($a) => "- {$a->scheduled_at->format('g:i A')}: {$a->title} ({$a->lead?->first_name} {$a->lead?->last_name})")
            ->join("\n");

        $negotiatingCount = \DB::table('lead_events')->where('status', 'negotiating')->count();

        $context = "Current date and time: {$now->format('M d, Y g:i A')} (Lima, Peru)\n\n";
        $context .= "LEADS SUMMARY:\n";
        $context .= "- Total: {$totalLeads}, New: {$newLeads}, Qualified: {$qualifiedLeads}, Clients: {$clientLeads}, Lost: {$lostLeads}\n";
        $context .= "- Opportunities in negotiation: {$negotiatingCount}\n\n";
        $context .= "TODAY'S ACTIVITIES:\n";
        $context .= "- Pending: {$pendingToday}, Completed: {$completedToday}, Overdue: {$overdueCount}\n\n";

        if ($upcomingList) {
            $context .= "UPCOMING ACTIVITIES TODAY:\n{$upcomingList}\n\n";
        }

        return $context;
    }
}
