<?php

namespace App\Imports;

use App\Models\DesignerLead;
use App\Models\Event;
use App\Models\LeadActivity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class LeadsImport implements ToCollection, WithHeadingRow
{
    public array $summary = [
        'created'  => 0,
        'updated'  => 0,
        'assigned' => 0,
        'skipped'  => 0,
        'errors'   => [],
    ];

    public function __construct(
        public readonly ?int $globalEventId = null,
        public readonly ?int $assignedTo = null,
        public readonly ?string $source = null,
    ) {}

    public function collection(Collection $rows): void
    {
        set_time_limit(300);

        foreach ($rows as $index => $row) {
            try {
                $this->processRow($row->toArray(), $index + 2);
            } catch (\Exception $e) {
                $this->summary['errors'][] = "Row " . ($index + 2) . ": " . $e->getMessage();
                $this->summary['skipped']++;
            }
        }
    }

    private function processRow(array $row, int $rowNum): void
    {
        $email = trim(strtolower($row['email'] ?? ''));

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->summary['errors'][] = "Row {$rowNum}: Invalid or empty email (\"{$email}\")";
            $this->summary['skipped']++;
            return;
        }

        $firstName   = trim($row['first_name'] ?? $row['nombre'] ?? '');
        $lastName    = trim($row['last_name']  ?? $row['apellido'] ?? '');
        $phone       = trim($row['phone']      ?? $row['telefono'] ?? '');
        $country     = trim($row['country']    ?? $row['pais'] ?? '');
        $companyName = trim($row['company_name'] ?? $row['empresa'] ?? '');
        $category    = trim($row['retail_category'] ?? $row['categoria'] ?? '');
        $website     = trim($row['website_url'] ?? $row['website'] ?? '');
        $instagram   = trim($row['instagram']  ?? '');
        $budget      = trim($row['budget']     ?? $row['presupuesto'] ?? '');
        $pastShows   = trim($row['past_shows'] ?? '');
        $notes       = trim($row['notes']      ?? $row['notas'] ?? '');

        // Full name fallback
        if (empty($firstName) && !empty($row['nombre_completo'] ?? $row['full_name'] ?? '')) {
            $parts     = explode(' ', trim($row['nombre_completo'] ?? $row['full_name'] ?? ''), 2);
            $firstName = $parts[0] ?? '';
            $lastName  = $parts[1] ?? $lastName;
        }

        // Clean instagram
        if ($instagram) {
            $instagram = strtok($instagram, '?');
            $instagram = preg_replace('#^https?://(www\.)?instagram\.com/#i', '', $instagram);
            $instagram = rtrim($instagram, '/');
            $instagram = ltrim($instagram, '@');
        }

        DB::transaction(function () use ($email, $firstName, $lastName, $phone, $country, $companyName, $category, $website, $instagram, $budget, $pastShows, $notes, $rowNum) {
            $existing = DesignerLead::where('email', $email)->first();

            if ($existing) {
                // Update fields if provided
                $updates = [];
                if ($firstName)   $updates['first_name']      = $firstName;
                if ($lastName)    $updates['last_name']       = $lastName;
                if ($phone)       $updates['phone']           = $phone;
                if ($country)     $updates['country']         = $country;
                if ($companyName) $updates['company_name']    = $companyName;
                if ($category)    $updates['retail_category'] = $category;
                if ($website)     $updates['website_url']     = $website;
                if ($instagram)   $updates['instagram']       = $instagram;
                if ($budget)      $updates['budget']          = $budget;
                if ($pastShows)   $updates['past_shows']      = $pastShows;
                if ($updates)     $existing->update($updates);

                $this->summary['updated']++;

                // Assign to event if not already
                $this->assignToEvent($existing, $rowNum);
            } else {
                $lead = DesignerLead::create([
                    'first_name'      => $firstName ?: 'Lead',
                    'last_name'       => $lastName ?: '',
                    'email'           => $email,
                    'phone'           => $phone ?: null,
                    'country'         => $country ?: null,
                    'company_name'    => $companyName ?: null,
                    'retail_category' => $category ?: null,
                    'website_url'     => $website ?: null,
                    'instagram'       => $instagram ?: null,
                    'budget'          => $budget ?: null,
                    'past_shows'      => $pastShows ?: null,
                    'notes'           => $notes ?: null,
                    'status'          => 'new',
                    'source'          => $this->source ?? 'manual',
                    'assigned_to'     => $this->assignedTo,
                ]);

                LeadActivity::create([
                    'lead_id'      => $lead->id,
                    'user_id'      => auth()->id(),
                    'type'         => 'system',
                    'title'        => 'Lead imported from file',
                    'status'       => 'completed',
                    'completed_at' => now(),
                ]);

                $this->summary['created']++;

                $this->assignToEvent($lead, $rowNum);
            }
        });
    }

    private function assignToEvent(DesignerLead $lead, int $rowNum): void
    {
        $eventId = $this->globalEventId;
        if (!$eventId) return;

        $event = Event::find($eventId);
        if (!$event) return;

        if (!$lead->events()->where('events.id', $eventId)->exists()) {
            $lead->events()->attach($eventId);
            $this->summary['assigned']++;

            LeadActivity::create([
                'lead_id'      => $lead->id,
                'user_id'      => auth()->id(),
                'type'         => 'system',
                'title'        => 'Event assigned: ' . $event->name,
                'status'       => 'completed',
                'completed_at' => now(),
            ]);
        }
    }
}
