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
                    'password'   => Hash::make(Str::random(16)),
                    'role'       => 'model',
                    'status'     => 'pending',
                    'login_code' => $this->generateLoginCode(),
                ]);

                ModelProfile::firstOrCreate(['user_id' => $user->id]);
                $this->summary['created']++;
                $isNew = true;
            } else {
                // Actualizar datos básicos si se proporcionaron
                $updates = [];
                if ($firstName) $updates['first_name'] = $firstName;
                if ($lastName)  $updates['last_name']  = $lastName;
                if ($phone)     $updates['phone']      = $phone;
                if ($updates)   $user->update($updates);
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
                            'status'       => 'confirmed',
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

    private function generateLoginCode(): string
    {
        do {
            $code = 'MOD' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (User::where('login_code', $code)->exists());

        return $code;
    }
}
