<?php

namespace App\Imports;

use App\Models\User;
use App\Support\InstagramSanitizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class VolunteersImport implements ToCollection, WithHeadingRow
{
    public array $summary = [
        'created' => 0,
        'updated' => 0,
        'errors'  => [],
    ];

    public function __construct(public readonly ?int $globalEventId = null) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            try {
                $this->processRow($row->toArray(), $index + 2);
            } catch (\Exception $e) {
                $this->summary['errors'][] = "Fila " . ($index + 2) . ": " . $e->getMessage();
            }
        }
    }

    private function processRow(array $row, int $rowNumber): void
    {
        $email = trim($row['email'] ?? '');
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception("Email inválido o vacío.");
        }

        $firstName = trim($row['first_name'] ?? $row['nombre'] ?? '');
        $lastName  = trim($row['last_name'] ?? $row['apellido'] ?? '');
        $phone     = trim($row['phone'] ?? $row['telefono'] ?? '');

        $instagram = InstagramSanitizer::sanitize($row['instagram'] ?? null);

        $user = User::where('email', $email)->first();

        if ($user) {
            if ($firstName) $user->first_name = $firstName;
            if ($lastName) $user->last_name = $lastName;
            if ($phone) $user->phone = $phone;
            $user->save();
            $this->summary['updated']++;
        } else {
            $user = User::create([
                'first_name' => $firstName ?: 'Volunteer',
                'last_name'  => $lastName ?: '',
                'email'      => $email,
                'phone'      => $phone,
                'role'       => 'volunteer',
                'status'     => 'applicant',
                'password'   => Hash::make(Str::random(16)),
            ]);
            $this->summary['created']++;
        }

        // Actualizar o crear perfil
        $profileData = array_filter([
            'location'               => trim($row['location'] ?? $row['ubicacion'] ?? '') ?: null,
            'tshirt_size'            => trim($row['tshirt_size'] ?? $row['talla'] ?? '') ?: null,
            'experience'             => trim($row['experience'] ?? $row['experiencia'] ?? '') ?: null,
            'comfortable_fast_paced' => trim($row['work_style'] ?? $row['comfortable_fast_paced'] ?? '') ?: null,
            'full_availability'      => trim($row['availability'] ?? $row['full_availability'] ?? $row['disponibilidad'] ?? '') ?: null,
            'contribution'           => trim($row['contribution'] ?? $row['contribucion'] ?? '') ?: null,
            'resume_link'            => trim($row['resume_link'] ?? $row['resume'] ?? '') ?: null,
            'instagram'              => $instagram ?: null,
        ], fn ($v) => $v !== null);

        if (!empty($profileData)) {
            $user->volunteerProfile()->updateOrCreate(
                ['user_id' => $user->id],
                $profileData,
            );
        }

        // Asignar al evento
        $eventId = $this->globalEventId ?? (int) ($row['event_id'] ?? 0);
        if ($eventId && !$user->eventsAsStaff()->where('events.id', $eventId)->exists()) {
            $user->eventsAsStaff()->attach($eventId, [
                'assigned_role' => 'volunteer',
                'status'        => 'assigned',
            ]);
        }
    }
}
