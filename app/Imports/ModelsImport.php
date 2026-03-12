<?php

namespace App\Imports;

use App\Models\Event;
use App\Models\EventDay;
use App\Models\ModelProfile;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ModelsImport implements ToCollection, WithHeadingRow
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
     * Columnas aceptadas en el Excel (case-insensitive, sin tildes):
     *
     * Obligatorias: email
     * Opcionales:   first_name / nombre, last_name / apellido,
     *               phone / telefono, event_id, casting_time,
     *               casting_date, looks, notes
     */
    public function collection(Collection $rows): void
    {
        set_time_limit(300); // 5 minutos para imports grandes

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

        // Si viene "nombre_completo" o "nombre completo" en una sola columna
        if (empty($firstName) && !empty($row['nombre_completo'] ?? '')) {
            $parts     = explode(' ', trim($row['nombre_completo']), 2);
            $firstName = $parts[0] ?? '';
            $lastName  = $parts[1] ?? '';
        }

        DB::transaction(function () use ($email, $firstName, $lastName, $phone, $row, &$rowNum) {
            $isNew = false;
            $user  = User::where('email', $email)->first();

            if (!$user) {
                $user = User::create([
                    'first_name' => $firstName ?: 'Modelo',
                    'last_name'  => $lastName  ?: '',
                    'email'      => $email,
                    'phone'      => $phone ?: null,
                    'password'   => Hash::make(Str::random(16), ['rounds' => 4]),
                    'role'       => 'model',
                    'status'     => 'pending',
                ]);

                $profile = ModelProfile::firstOrCreate(['user_id' => $user->id]);
                $this->updateProfile($profile, $row);
                $this->summary['created']++;
                $isNew = true;
            } else {
                // Actualizar datos básicos si se proporcionaron
                $updates = [];
                if ($firstName) $updates['first_name'] = $firstName;
                if ($lastName)  $updates['last_name']  = $lastName;
                if ($phone)     $updates['phone']      = $phone;
                if ($updates)   $user->update($updates);

                // Update profile fields if provided
                $profile = ModelProfile::firstOrCreate(['user_id' => $user->id]);
                $this->updateProfile($profile, $row);
                $this->summary['updated']++;
            }

            // Asignar a evento si se indicó (el evento global del form tiene prioridad sobre la columna del Excel)
            $eventId     = $this->globalEventId ?? intval($row['event_id'] ?? 0);
            $castingTime = $this->normalizeTime($row['casting_time'] ?? $row['hora_casting'] ?? null);
            $castingDate = trim($row['casting_date'] ?? $row['fecha_casting'] ?? '');
            $looks       = intval($row['looks'] ?? 0);

            if ($eventId) {
                $event = Event::find($eventId);
                if ($event) {
                    // Buscar casting day del evento
                    $castingDayId = null;
                    if ($castingDate) {
                        $dayRecord = EventDay::where('event_id', $eventId)
                            ->whereDate('date', $castingDate)
                            ->first();
                        $castingDayId = $dayRecord?->id;
                    }

                    // Pivot event_model
                    if (!$event->models()->where('model_id', $user->id)->exists()) {
                        $event->models()->attach($user->id, [
                            'status'       => 'invited',
                            'casting_time' => $castingTime ?: null,
                        ]);
                        $this->summary['assigned']++;
                    } elseif ($castingTime) {
                        $event->models()->updateExistingPivot($user->id, [
                            'casting_time' => $castingTime,
                        ]);
                    }
                }
            }
        });
    }

    private function updateProfile(ModelProfile $profile, array $row): void
    {
        $updates = [];

        // Campos de texto directo
        foreach (['age' => 'age', 'city' => 'location', 'location' => 'location', 'shoe_size' => 'shoe_size', 'dress_size' => 'dress_size', 'instagram' => 'instagram', 'agency' => 'agency'] as $excelCol => $profileField) {
            $value = trim((string) ($row[$excelCol] ?? ''));
            if ($value !== '') $updates[$profileField] = $value;
        }

        // Campos numéricos (extraer solo el número)
        foreach (['height', 'bust', 'waist', 'hips'] as $field) {
            $raw = trim((string) ($row[$field] ?? ''));
            if ($raw !== '') {
                $numeric = $this->extractNumeric($raw);
                if ($numeric !== null) $updates[$field] = $numeric;
            }
        }

        // Gender: normalizar a lowercase
        $gender = strtolower(trim((string) ($row['gender'] ?? '')));
        if ($gender !== '') {
            $updates['gender'] = match(true) {
                str_contains($gender, 'female') || $gender === 'f' => 'female',
                str_contains($gender, 'male')   || $gender === 'm' => 'male',
                str_contains($gender, 'non')                       => 'non_binary',
                default => null,
            };
            if ($updates['gender'] === null) unset($updates['gender']);
        }

        // Ethnicity: mapear valores del formulario externo a los permitidos
        $ethnicity = strtolower(trim((string) ($row['ethnicity'] ?? '')));
        if ($ethnicity !== '') {
            $updates['ethnicity'] = match(true) {
                str_contains($ethnicity, 'asian')                           => 'asian',
                str_contains($ethnicity, 'black') || str_contains($ethnicity, 'african') => 'black',
                str_contains($ethnicity, 'caucasian') || str_contains($ethnicity, 'white') => 'caucasian',
                str_contains($ethnicity, 'hispanic') || str_contains($ethnicity, 'latino') => 'hispanic',
                str_contains($ethnicity, 'middle')                          => 'middle_eastern',
                str_contains($ethnicity, 'mixed') || str_contains($ethnicity, 'multiracial') => 'mixed',
                default => 'other',
            };
        }

        // Hair: normalizar
        $hair = strtolower(trim((string) ($row['hair'] ?? '')));
        if ($hair !== '') {
            $updates['hair'] = match(true) {
                str_contains($hair, 'black') || str_contains($hair, 'negro') => 'black',
                str_contains($hair, 'brown') || str_contains($hair, 'casta') => 'brown',
                str_contains($hair, 'blond') || str_contains($hair, 'rubio') => 'blonde',
                str_contains($hair, 'red')   || str_contains($hair, 'rojo')  => 'red',
                str_contains($hair, 'gray')  || str_contains($hair, 'grey')  => 'gray',
                default => 'other',
            };
        }

        // Body type: normalizar
        $bodyType = strtolower(trim((string) ($row['body_type'] ?? '')));
        if ($bodyType !== '') {
            $updates['body_type'] = match(true) {
                str_contains($bodyType, 'slim') || str_contains($bodyType, 'delgad') => 'slim',
                str_contains($bodyType, 'athletic') || str_contains($bodyType, 'atlet') => 'athletic',
                str_contains($bodyType, 'average') || str_contains($bodyType, 'promedio') => 'average',
                str_contains($bodyType, 'curvy') || str_contains($bodyType, 'curvi') => 'curvy',
                str_contains($bodyType, 'plus') => 'plus_size',
                default => null,
            };
            if ($updates['body_type'] === null) unset($updates['body_type']);
        }

        if (!empty($updates)) {
            $profile->update($updates);
        }
    }

    /**
     * Extrae el valor numérico de un string como "5'1'' / 154.9 cm" → 154.9
     * o convierte pies/pulgadas a cm si no hay valor métrico.
     */
    private function extractNumeric(string $value): ?float
    {
        // Formato "X'Y'' / ZZZ.Z cm" → extraer ZZZ.Z
        if (preg_match('/(\d+(?:\.\d+))\s*cm/i', $value, $m)) {
            return (float) $m[1];
        }

        // Formato pies: "5'6''" → convertir a cm
        if (preg_match("/(\d+)'(\d+)''/", $value, $m)) {
            return round(((int)$m[1] * 30.48) + ((int)$m[2] * 2.54), 2);
        }

        // Ya es número puro
        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    /**
     * Normaliza el valor de hora que viene de Excel.
     * Excel guarda las horas como fracción decimal del día (ej: 0.375 = 09:00).
     * También acepta strings "09:00" o "9:00".
     */
    private function normalizeTime(mixed $value): string
    {
        if (empty($value) && $value !== 0) return '';

        // Si es numérico (fracción decimal de Excel), convertir a HH:MM
        if (is_numeric($value)) {
            $totalSeconds = round((float) $value * 86400); // 86400 = segundos en un día
            $hours        = intdiv($totalSeconds, 3600) % 24;
            $minutes      = intdiv($totalSeconds % 3600, 60);
            return sprintf('%02d:%02d', $hours, $minutes);
        }

        // Si ya es string, devolverlo limpio
        return trim((string) $value);
    }

}
