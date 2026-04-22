<?php

namespace App\Jobs\Sponsorship;

use App\Mail\Sponsorship\SponsorOnboardingMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendSponsorOnboardingEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public int $sponsorUserId,
        public ?int $registrationId = null,
    ) {}

    public function handle(): void
    {
        $sponsor = User::where('role', 'sponsor')->find($this->sponsorUserId);
        if (!$sponsor) {
            Log::warning("Sponsor onboarding: user {$this->sponsorUserId} not found or not a sponsor.");
            return;
        }

        try {
            Mail::to($sponsor->email, "{$sponsor->first_name} {$sponsor->last_name}")
                ->send(new SponsorOnboardingMail($sponsor));

            $sponsor->update(['welcome_email_sent_at' => now()]);

            if ($this->registrationId) {
                \App\Models\Sponsorship\Registration::where('id', $this->registrationId)
                    ->update(['onboarding_email_sent_at' => now()]);
            }
        } catch (\Throwable $e) {
            Log::warning("Sponsor onboarding email failed for user {$sponsor->id}: " . $e->getMessage());
            throw $e;
        }
    }
}
