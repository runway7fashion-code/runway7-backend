<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendLeadEmailJob;
use App\Models\DesignerLead;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LeadEmailController extends Controller
{
    public function send(Request $request, DesignerLead $lead)
    {
        $request->validate([
            'subject'      => 'required|string|max:255',
            'body'         => 'required|string|max:50000',
            'attachments'  => 'nullable|array',
            'attachments.*'=> 'file|max:10240',
            'scheduled_at' => 'nullable|date',
        ]);

        $user = auth()->user();
        $storedFiles = $this->storeAttachments($request);
        $delay = $this->calculateDelay($request->scheduled_at);

        $job = new SendLeadEmailJob(
            leadId: $lead->id,
            subject: $request->subject,
            body: $request->body,
            senderId: $user->id,
            senderName: "{$user->first_name} {$user->last_name}",
            senderEmail: $user->email,
            attachmentPaths: $storedFiles,
        );

        if ($delay) {
            dispatch($job)->delay($delay);
            return back()->with('success', "Email scheduled for {$lead->first_name} {$lead->last_name}.");
        }

        dispatch($job);
        return back()->with('success', "Email queued to {$lead->first_name} {$lead->last_name}.");
    }

    public function sendBulk(Request $request)
    {
        $request->validate([
            'lead_ids'     => 'required|array|min:1',
            'lead_ids.*'   => 'exists:designer_leads,id',
            'subject'      => 'required|string|max:255',
            'body'         => 'required|string|max:50000',
            'attachments'  => 'nullable|array',
            'attachments.*'=> 'file|max:10240',
            'scheduled_at' => 'nullable|date',
        ]);

        $user = auth()->user();
        $senderName = "{$user->first_name} {$user->last_name}";
        $storedFiles = $this->storeAttachments($request);
        $delay = $this->calculateDelay($request->scheduled_at);
        $count = 0;

        foreach ($request->lead_ids as $leadId) {
            $lead = DesignerLead::find($leadId);
            if (!$lead || !$lead->email) continue;

            $job = new SendLeadEmailJob(
                leadId: $lead->id,
                subject: $request->subject,
                body: $request->body,
                senderId: $user->id,
                senderName: $senderName,
                senderEmail: $user->email,
                attachmentPaths: $storedFiles,
            );

            $delay ? dispatch($job)->delay($delay) : dispatch($job);
            $count++;
        }

        $msg = $delay ? "{$count} email(s) scheduled." : "{$count} email(s) queued for delivery.";
        return back()->with('success', $msg);
    }

    private function storeAttachments(Request $request): array
    {
        $paths = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $paths[] = $file->store('lead-email-attachments', 'local');
            }
        }
        return $paths;
    }

    private function calculateDelay(?string $scheduledAt): ?Carbon
    {
        if (!$scheduledAt) return null;
        $date = Carbon::parse($scheduledAt);
        return $date->isFuture() ? $date : null;
    }
}
