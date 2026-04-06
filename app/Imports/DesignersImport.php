<?php

namespace App\Imports;

use App\Models\DesignerProfile;
use App\Models\Event;
use App\Models\User;
use App\Services\DesignerService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class DesignersImport implements ToCollection, WithHeadingRow
{
    public array $summary = [
        'created'  => 0,
        'updated'  => 0,
        'assigned' => 0,
        'skipped'  => 0,
        'errors'   => [],
    ];

    public function __construct(public readonly ?int $globalEventId = null) {}

    /**
     * Columnas aceptadas en el Excel (case-insensitive):
     *
     * Obligatorias: email
     * Opcionales:   first_name / nombre, last_name / apellido,
     *               phone / telefono, brand_name / marca,
     *               country / pais, website, instagram, skype,
     *               event_id, looks, notes
     */
    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            try {
                $this->processRow($row->toArray(), $index + 2);
            } catch (\Exception $e) {
                $this->summary['errors'][] = "Fila " . ($index + 2) . ": " . $e->getMessage();
                $this->summary['skipped']++;
            }
        }
    }

    private function processRow(array $row, int $rowNum): void
    {
        $email = trim(strtolower($row['email'] ?? ''));

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->summary['errors'][] = "Fila {$rowNum}: Email inválido o vacío (\"{$email}\")";
            $this->summary['skipped']++;
            return;
        }

        $firstName = trim($row['first_name'] ?? $row['nombre'] ?? '');
        $lastName  = trim($row['last_name']  ?? $row['apellido'] ?? '');
        $phone     = trim($row['phone']      ?? $row['telefono'] ?? '');
        $brandName = trim($row['brand_name'] ?? $row['marca'] ?? '');
        $country   = trim($row['country']    ?? $row['pais'] ?? '');
        $website   = trim($row['website']    ?? '');
        $instagram = trim($row['instagram']  ?? '');
        $skype     = trim($row['skype']      ?? '');

        if (empty($firstName) && !empty($row['nombre_completo'] ?? '')) {
            $parts     = explode(' ', trim($row['nombre_completo']), 2);
            $firstName = $parts[0] ?? '';
            $lastName  = $parts[1] ?? '';
        }

        DB::transaction(function () use ($email, $firstName, $lastName, $phone, $brandName, $country, $website, $instagram, $skype, $row, &$rowNum) {
            $user = User::where('email', $email)->first();

            if (!$user) {
                $user = User::create([
                    'first_name' => $firstName ?: 'Diseñador',
                    'last_name'  => $lastName  ?: '',
                    'email'      => $email,
                    'phone'      => $phone ?: null,
                    'password'   => bcrypt('runway7'),
                    'role'       => 'designer',
                    'status'     => 'pending',
                ]);

                $profileData = array_filter([
                    'brand_name' => $brandName ?: null,
                    'country'    => $country ?: null,
                    'website'    => $website ?: null,
                    'instagram'  => $instagram ?: null,
                    'skype'      => $skype ?: null,
                ]);

                $user->designerProfile()->create($profileData);
                $this->summary['created']++;
            } else {
                $updates = [];
                if ($firstName) $updates['first_name'] = $firstName;
                if ($lastName)  $updates['last_name']  = $lastName;
                if ($phone)     $updates['phone']      = $phone;
                if ($updates)   $user->update($updates);

                $profileUpdates = array_filter([
                    'brand_name' => $brandName ?: null,
                    'country'    => $country ?: null,
                    'website'    => $website ?: null,
                    'instagram'  => $instagram ?: null,
                    'skype'      => $skype ?: null,
                ]);

                if ($profileUpdates) {
                    $user->designerProfile()->updateOrCreate(
                        ['user_id' => $user->id],
                        $profileUpdates
                    );
                }

                $this->summary['updated']++;
            }

            // Asignar a evento
            $eventId = $this->globalEventId ?? intval($row['event_id'] ?? 0);
            $looks   = intval($row['looks'] ?? 0);

            if ($eventId) {
                $event = Event::find($eventId);
                if ($event && !$event->designers()->where('designer_id', $user->id)->exists()) {
                    try {
                        app(DesignerService::class)->assignToEvent($user, $eventId, [
                            'looks' => $looks ?: 10,
                            'notes' => trim($row['notes'] ?? '') ?: null,
                        ]);
                        $this->summary['assigned']++;
                    } catch (\Exception $e) {
                        $this->summary['errors'][] = "Fila {$rowNum}: Error al asignar al evento: " . $e->getMessage();
                    }
                }
            }
        });
    }
}
