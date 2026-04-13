<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendBulkUserEmailJob;
use App\Jobs\SendBulkUserNotificationJob;
use App\Jobs\SendBulkUserSmsJob;
use App\Models\CommunicationLog;
use App\Models\DeviceToken;
use App\Models\Event;
use App\Models\User;
use App\Services\SmsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CommunicationController extends Controller
{
    /**
     * Statuses available per role (dynamic filter).
     */
    public static function statusesByRole(): array
    {
        return [
            'model'       => ['active', 'applicant', 'pending', 'inactive'],
            'designer'    => ['active', 'registered', 'pending', 'inactive'],
            'media'       => ['active', 'applicant', 'pending', 'inactive'],
            'volunteer'   => ['active', 'applicant', 'pending', 'inactive'],
            'staff'       => ['active', 'inactive'],
            'assistant'   => ['active', 'inactive'],
            'attendee'    => ['active', 'inactive'],
            'vip'         => ['active', 'inactive'],
            'influencer'  => ['active', 'inactive'],
            'press'       => ['active', 'inactive'],
            'sponsor'     => ['active', 'inactive'],
        ];
    }

    /**
     * Roles that each internal role is allowed to communicate with.
     */
    private function allowedTargetRoles(): array
    {
        $user = auth()->user();
        $role = $user->role;

        return match ($role) {
            'admin'            => ['model', 'designer', 'media', 'volunteer', 'staff', 'assistant', 'attendee', 'vip', 'influencer', 'press', 'sponsor'],
            'operation'        => ['model', 'designer', 'media', 'volunteer', 'staff', 'assistant'],
            'sales'            => ['designer'],
            'marketing'        => ['model', 'designer', 'media', 'volunteer', 'attendee', 'vip', 'influencer', 'press', 'sponsor'],
            'public_relations' => ['media', 'press', 'influencer'],
            'tickets_manager'  => ['model', 'designer', 'attendee', 'vip', 'sponsor'],
            'accounting'       => ['designer'],
            default            => [],
        };
    }

    public function email(Request $request)
    {
        return $this->renderChannel($request, 'email');
    }

    public function sms(Request $request)
    {
        return $this->renderChannel($request, 'sms');
    }

    public function notifications(Request $request)
    {
        return $this->renderChannel($request, 'notifications');
    }

    private function renderChannel(Request $request, string $channel)
    {
        $allowedRoles = $this->allowedTargetRoles();

        if (empty($allowedRoles)) {
            abort(403, 'You do not have permission to send communications.');
        }

        $query = User::query()
            ->whereNull('deleted_at')
            ->whereIn('role', $allowedRoles)
            ->select('id', 'first_name', 'last_name', 'email', 'phone', 'role', 'status', 'profile_picture', 'created_at');

        // Filter by role
        if ($request->filled('role') && in_array($request->role, $allowedRoles)) {
            $query->where('role', $request->role);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by event
        if ($request->filled('event_id')) {
            $eventId = $request->event_id;
            $query->where(function ($q) use ($eventId) {
                $q->whereHas('eventsAsModel', fn($e) => $e->where('events.id', $eventId))
                  ->orWhereHas('eventsAsDesigner', fn($e) => $e->where('events.id', $eventId))
                  ->orWhereHas('eventsAsVolunteer', fn($e) => $e->where('events.id', $eventId))
                  ->orWhereHas('eventsAsMedia', fn($e) => $e->where('events.id', $eventId));
            });
        }

        // Search
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('first_name', 'ilike', "%{$s}%")
                  ->orWhere('last_name', 'ilike', "%{$s}%")
                  ->orWhere('email', 'ilike', "%{$s}%")
                  ->orWhere('phone', 'ilike', "%{$s}%");
            });
        }

        // Channel-specific filters
        if ($channel === 'email') {
            $query->whereNotNull('email');
        } elseif ($channel === 'sms') {
            $query->whereNotNull('phone');

            // Phone validity filter (E.164: +[1-9][6-14 digits])
            $phoneValid = $request->phone_valid;
            if ($phoneValid === 'valid') {
                $query->where('phone', '~', '^\+[1-9][0-9]{6,14}$');
            } elseif ($phoneValid === 'invalid') {
                $query->where('phone', '!~', '^\+[1-9][0-9]{6,14}$');
            }
        } elseif ($channel === 'notifications') {
            // Add device count subquery
            $query->withCount(['deviceTokens as device_count' => fn($q) => $q->where('is_active', true)]);

            $hasDevice = $request->has_device;
            if ($hasDevice === 'with') {
                $query->whereHas('deviceTokens', fn($q) => $q->where('is_active', true));
            } elseif ($hasDevice === 'without') {
                $query->whereDoesntHave('deviceTokens', fn($q) => $q->where('is_active', true));
            }
        }

        $users = $query->orderBy('first_name')->paginate(50)->withQueryString();

        $statusesByRole = self::statusesByRole();
        $filteredStatuses = array_intersect_key($statusesByRole, array_flip($allowedRoles));

        $extraProps = [
            'users'          => $users,
            'channel'        => $channel,
            'allowedRoles'   => $allowedRoles,
            'statusesByRole' => $filteredStatuses,
            'events'         => Event::whereNull('deleted_at')->where('status', '!=', 'cancelled')->select('id', 'name')->orderBy('start_date', 'desc')->get(),
            'filters'        => $request->only(['search', 'role', 'status', 'event_id', 'phone_valid', 'has_device']),
        ];

        if ($channel === 'sms' || $channel === 'notifications') {
            $extraProps['variables'] = SmsService::availableVariables();
        }

        if ($channel === 'notifications') {
            $extraProps['deepLinks'] = [
                ['key' => '',          'label' => 'None (no deep link)'],
                ['key' => 'home',      'label' => 'Home'],
                ['key' => 'profile',   'label' => 'Profile'],
                ['key' => 'events',    'label' => 'Events'],
                ['key' => 'tickets',   'label' => 'Tickets'],
                ['key' => 'shows',     'label' => 'Shows'],
                ['key' => 'payments',  'label' => 'Payments'],
                ['key' => 'chat',      'label' => 'Chat'],
            ];
        }

        return Inertia::render('Admin/Communications/' . ucfirst($channel), $extraProps);
    }

    public function previewSms(Request $request, SmsService $smsService)
    {
        $request->validate([
            'user_ids'   => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
            'message'    => 'required|string|max:1600',
        ]);

        $allowedRoles = $this->allowedTargetRoles();
        if (empty($allowedRoles)) abort(403);

        $users = User::whereIn('id', $request->user_ids)
            ->whereIn('role', $allowedRoles)
            ->whereNotNull('phone')
            ->get(['id', 'first_name', 'last_name', 'phone']);

        $validCount = 0;
        $invalidCount = 0;
        $invalidSamples = [];

        foreach ($users as $user) {
            if ($smsService->isValidE164($user->phone)) {
                $validCount++;
            } else {
                $invalidCount++;
                if (count($invalidSamples) < 5) {
                    $invalidSamples[] = [
                        'name'  => trim("{$user->first_name} {$user->last_name}"),
                        'phone' => $user->phone,
                    ];
                }
            }
        }

        // Build a sample message with signature (using first user's data if possible)
        $sampleUser = $users->first();
        $sampleMessage = $sampleUser
            ? $smsService->replaceVariables($request->message, $sampleUser)
            : $request->message;
        $sampleMessage = $smsService->appendSignature($sampleMessage);

        $estimation = $smsService->estimateCost($sampleMessage, $validCount);
        $balance = $smsService->getBalance();

        return response()->json([
            'total_selected'   => count($request->user_ids),
            'valid_count'      => $validCount,
            'invalid_count'    => $invalidCount,
            'invalid_samples'  => $invalidSamples,
            'sample_message'   => $sampleMessage,
            'estimation'       => $estimation,
            'balance'          => $balance,
        ]);
    }

    public function sendSms(Request $request, SmsService $smsService)
    {
        $request->validate([
            'user_ids'     => 'required|array|min:1',
            'user_ids.*'   => 'exists:users,id',
            'message'      => 'required|string|max:1600',
            'scheduled_at' => 'nullable|date',
        ]);

        $allowedRoles = $this->allowedTargetRoles();
        if (empty($allowedRoles)) abort(403);

        $sender = auth()->user();

        $delay = null;
        if ($request->scheduled_at) {
            $date = Carbon::parse($request->scheduled_at);
            $delay = $date->isFuture() ? $date : null;
        }

        $count = 0;
        foreach ($request->user_ids as $userId) {
            $targetUser = User::where('id', $userId)
                ->whereIn('role', $allowedRoles)
                ->whereNotNull('phone')
                ->first();
            if (!$targetUser) continue;
            if (!$smsService->isValidE164($smsService->normalizePhone($targetUser->phone))) continue;

            $job = new SendBulkUserSmsJob(
                userId: $targetUser->id,
                messageTemplate: $request->message,
                senderId: $sender->id,
            );

            $delay ? dispatch($job)->delay($delay) : dispatch($job);
            $count++;
        }

        $msg = $delay ? "{$count} SMS scheduled." : "{$count} SMS queued for delivery.";
        return back()->with('success', $msg);
    }

    public function previewNotifications(Request $request, SmsService $smsService)
    {
        $request->validate([
            'user_ids'   => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
            'title'      => 'required|string|max:100',
            'body'       => 'required|string|max:500',
        ]);

        $allowedRoles = $this->allowedTargetRoles();
        if (empty($allowedRoles)) abort(403);

        $users = User::whereIn('id', $request->user_ids)
            ->whereIn('role', $allowedRoles)
            ->withCount(['deviceTokens as device_count' => fn($q) => $q->where('is_active', true)])
            ->get(['id', 'first_name', 'last_name']);

        $withDevices = 0;
        $withoutDevices = 0;
        $totalDevices = 0;
        $withoutSamples = [];

        foreach ($users as $user) {
            if ($user->device_count > 0) {
                $withDevices++;
                $totalDevices += $user->device_count;
            } else {
                $withoutDevices++;
                if (count($withoutSamples) < 5) {
                    $withoutSamples[] = trim("{$user->first_name} {$user->last_name}");
                }
            }
        }

        $sampleUser = $users->first();
        $sampleTitle = $sampleUser ? $smsService->replaceVariables($request->title, $sampleUser) : $request->title;
        $sampleBody = $sampleUser ? $smsService->replaceVariables($request->body, $sampleUser) : $request->body;

        return response()->json([
            'total_selected'  => count($request->user_ids),
            'with_devices'    => $withDevices,
            'without_devices' => $withoutDevices,
            'total_devices'   => $totalDevices,
            'without_samples' => $withoutSamples,
            'sample_title'    => $sampleTitle,
            'sample_body'     => $sampleBody,
        ]);
    }

    public function sendNotifications(Request $request)
    {
        $request->validate([
            'user_ids'     => 'required|array|min:1',
            'user_ids.*'   => 'exists:users,id',
            'title'        => 'required|string|max:100',
            'body'         => 'required|string|max:500',
            'deep_link'    => 'nullable|string|max:100',
            'scheduled_at' => 'nullable|date',
        ]);

        $allowedRoles = $this->allowedTargetRoles();
        if (empty($allowedRoles)) abort(403);

        $sender = auth()->user();

        $delay = null;
        if ($request->scheduled_at) {
            $date = Carbon::parse($request->scheduled_at);
            $delay = $date->isFuture() ? $date : null;
        }

        $data = $request->deep_link ? ['screen' => $request->deep_link] : [];

        $count = 0;
        foreach ($request->user_ids as $userId) {
            $targetUser = User::where('id', $userId)
                ->whereIn('role', $allowedRoles)
                ->whereHas('deviceTokens', fn($q) => $q->where('is_active', true))
                ->first();
            if (!$targetUser) continue;

            $job = new SendBulkUserNotificationJob(
                userId: $targetUser->id,
                titleTemplate: $request->title,
                bodyTemplate: $request->body,
                senderId: $sender->id,
                data: $data,
            );

            $delay ? dispatch($job)->delay($delay) : dispatch($job);
            $count++;
        }

        $msg = $delay ? "{$count} notification(s) scheduled." : "{$count} notification(s) queued for delivery.";
        return back()->with('success', $msg);
    }

    public function sendEmail(Request $request)
    {
        $request->validate([
            'user_ids'      => 'required|array|min:1',
            'user_ids.*'    => 'exists:users,id',
            'subject'       => 'required|string|max:255',
            'body'          => 'required|string|max:50000',
            'attachments'   => 'nullable|array',
            'attachments.*' => 'file|max:10240',
            'scheduled_at'  => 'nullable|date',
        ]);

        $allowedRoles = $this->allowedTargetRoles();
        if (empty($allowedRoles)) {
            abort(403);
        }

        $sender = auth()->user();
        $senderName = "{$sender->first_name} {$sender->last_name}";

        // Store attachments
        $storedFiles = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $storedFiles[] = $file->store('user-email-attachments', 'local');
            }
        }

        $delay = null;
        if ($request->scheduled_at) {
            $date = Carbon::parse($request->scheduled_at);
            $delay = $date->isFuture() ? $date : null;
        }

        $count = 0;
        foreach ($request->user_ids as $userId) {
            $targetUser = User::where('id', $userId)
                ->whereIn('role', $allowedRoles)
                ->whereNotNull('email')
                ->first();
            if (!$targetUser) continue;

            $job = new SendBulkUserEmailJob(
                userId: $targetUser->id,
                subject: $request->subject,
                body: $request->body,
                senderId: $sender->id,
                senderName: $senderName,
                senderEmail: $sender->email,
                attachmentPaths: $storedFiles,
            );

            $delay ? dispatch($job)->delay($delay) : dispatch($job);
            $count++;
        }

        $msg = $delay
            ? "{$count} email(s) scheduled."
            : "{$count} email(s) queued for delivery.";

        return back()->with('success', $msg);
    }
}
