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
Eres R7, el asistente virtual del equipo de ventas de Runway 7 Fashion Week.
Respondes en español, de forma breve y directa.
No uses markdown ni formato especial, solo texto plano.

Datos del asesor:
- Nombre: {$user->first_name} {$user->last_name}
- Rol: {$user->sales_type}

{$context}

{$leadsListContext}

INSTRUCCIONES IMPORTANTES:
1. Si el asesor quiere CREAR una actividad (agendar llamada, reunion, email) o una nota, responde EXACTAMENTE con este formato JSON:
{"action":"create_activity","type":"call","lead_name":"nombre del lead","title":"titulo de la actividad","scheduled_at":"YYYY-MM-DD HH:mm","description":"descripcion opcional"}

Los tipos validos son: call, email, meeting, note
Para call, email y meeting: si no menciona hora, usa 10:00. Si dice "manana", calcula la fecha.
Para note: NUNCA pongas scheduled_at. Las notas son registros inmediatos, no se programan. El titulo debe resumir el contenido y la descripcion es el contenido completo de la nota.
La descripcion debe ser breve (maximo 150 caracteres).

2. Si el asesor pide informacion o hace una pregunta, responde normalmente en texto plano.

3. NUNCA mezcles JSON con texto. Si es una accion, responde SOLO el JSON.
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
                return 'Error al conectar con el servicio de IA. Intenta de nuevo.';
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

            return $text ?: 'No pude procesar tu consulta.';
        } catch (\Exception $e) {
            \Log::warning('R7 Bot AI error: ' . $e->getMessage());
            return 'El servicio de IA no está disponible en este momento.';
        }
    }

    private function prepareActivityCreation(User $user, array $data): string
    {
        $leadName = $data['lead_name'] ?? '';
        $type = $data['type'] ?? 'call';
        $title = $data['title'] ?? '';
        $scheduledAt = $data['scheduled_at'] ?? '';
        $description = $data['description'] ?? '';

        $typeLabels = ['call' => 'Llamada', 'email' => 'Email', 'meeting' => 'Reunión', 'note' => 'Nota'];
        $typeLabel = $typeLabels[$type] ?? $type;

        // Search for matching leads
        $isLeader = $user->role === 'admin' || $user->sales_type === 'lider';
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
            return "No encontré ningún lead con el nombre \"{$leadName}\". Verifica el nombre e intenta de nuevo.";
        }

        if ($leads->count() === 1) {
            $lead = $leads->first();
            $actionData = [
                'lead_id'      => $lead->id,
                'type'         => $type,
                'title'        => $title ?: "{$typeLabel} con {$lead->first_name} {$lead->last_name}",
                'scheduled_at' => $scheduledAt,
                'description'  => $description,
            ];

            Cache::put("bot_pending_{$user->id}", $actionData, now()->addMinutes(5));

            $confirmation = "Encontré a {$lead->first_name} {$lead->last_name} ({$lead->company_name} - {$lead->email}).\n\n¿Confirmas crear esta actividad?\n- Tipo: {$typeLabel}\n- Lead: {$lead->first_name} {$lead->last_name}";
            if ($scheduledAt) {
                $dateFormatted = \Carbon\Carbon::parse($scheduledAt, 'America/Lima')->format('d M Y, g:i A');
                $confirmation .= "\n- Fecha: {$dateFormatted}";
            }
            if ($description) {
                $confirmation .= "\n- Contenido: {$description}";
            }
            $confirmation .= "\n\nResponde 'sí' para confirmar o 'no' para cancelar.";

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

        return "Encontré {$leads->count()} leads con ese nombre:\n{$options}\n\n¿A cuál te refieres? Responde con el número.";
    }

    private function handleSelection(User $user, string $message, array $selection): string
    {
        Cache::forget("bot_selection_{$user->id}");

        $message = strtolower(trim($message));

        if (in_array($message, ['no', 'cancelar', 'ninguno'])) {
            return 'Entendido, operación cancelada.';
        }

        $num = intval($message);
        if ($num < 1 || $num > count($selection['leads'])) {
            return 'Número no válido. Operación cancelada. Intenta de nuevo.';
        }

        $lead = $selection['leads'][$num - 1];
        $type = $selection['type'];
        $typeLabels = ['call' => 'Llamada', 'email' => 'Email', 'meeting' => 'Reunión', 'note' => 'Nota'];
        $typeLabel = $typeLabels[$type] ?? $type;

        $actionData = [
            'lead_id'      => $lead['id'],
            'type'         => $type,
            'title'        => $selection['title'] ?: "{$typeLabel} con {$lead['name']}",
            'scheduled_at' => $selection['scheduled_at'],
            'description'  => $selection['description'],
        ];

        Cache::put("bot_pending_{$user->id}", $actionData, now()->addMinutes(5));

        $confirmation = "Seleccionaste a {$lead['name']} ({$lead['company']} - {$lead['email']}).\n\n¿Confirmas crear esta actividad?\n- Tipo: {$typeLabel}\n- Lead: {$lead['name']}";
        if ($selection['scheduled_at']) {
            $dateFormatted = \Carbon\Carbon::parse($selection['scheduled_at'], 'America/Lima')->format('d M Y, g:i A');
            $confirmation .= "\n- Fecha: {$dateFormatted}";
        }
        if ($selection['description']) {
            $confirmation .= "\n- Contenido: {$selection['description']}";
        }
        $confirmation .= "\n\nResponde 'sí' para confirmar o 'no' para cancelar.";

        return $confirmation;
    }

    private function handleConfirmation(User $user, string $message, array $action): string
    {
        Cache::forget("bot_pending_{$user->id}");

        $message = strtolower(trim($message));

        if (in_array($message, ['sí', 'si', 'yes', 'confirmar', 'ok', 'dale', 'claro'])) {
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
                $typeLabels = ['call' => 'Llamada', 'email' => 'Email', 'meeting' => 'Reunión', 'note' => 'Nota'];

                $typeName = $typeLabels[$action['type']] ?? 'Actividad';
                $msg = "Listo. {$typeName} creada para {$lead->first_name} {$lead->last_name}.";
                if ($action['scheduled_at']) {
                    $msg .= " Programada para el " . \Carbon\Carbon::parse($action['scheduled_at'], 'America/Lima')->format('d M Y, g:i A') . ".";
                }
                return $msg;
            } catch (\Exception $e) {
                \Log::warning('R7 Bot create activity error: ' . $e->getMessage());
                return 'Error al crear la actividad. Intenta de nuevo.';
            }
        }

        return 'Entendido, no se creó la actividad.';
    }

    private function buildLeadsList(User $user): string
    {
        $isLeader = $user->role === 'admin' || $user->sales_type === 'lider';
        $query = DesignerLead::query();
        if (!$isLeader) {
            $query->where('assigned_to', $user->id);
        }

        $leads = $query->select('id', 'first_name', 'last_name', 'company_name', 'status')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        if ($leads->isEmpty()) return '';

        $list = $leads->map(fn($l) => "- {$l->first_name} {$l->last_name} ({$l->company_name}) - Estado: {$l->status}")->join("\n");

        return "LEADS DEL ASESOR:\n{$list}\n";
    }

    private function buildContext(User $user): string
    {
        $isLeader = $user->role === 'admin' || $user->sales_type === 'lider';
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

        $context = "Fecha y hora actual: {$now->format('d/m/Y g:i A')} (Lima, Peru)\n\n";
        $context .= "RESUMEN DE LEADS:\n";
        $context .= "- Total: {$totalLeads}, Nuevos: {$newLeads}, Calificados: {$qualifiedLeads}, Clientes: {$clientLeads}, Perdidos: {$lostLeads}\n";
        $context .= "- Oportunidades en negociacion: {$negotiatingCount}\n\n";
        $context .= "ACTIVIDADES HOY:\n";
        $context .= "- Pendientes: {$pendingToday}, Completadas: {$completedToday}, Vencidas: {$overdueCount}\n\n";

        if ($upcomingList) {
            $context .= "PROXIMAS ACTIVIDADES HOY:\n{$upcomingList}\n\n";
        }

        return $context;
    }
}
