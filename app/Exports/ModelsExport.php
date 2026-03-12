<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ModelsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(
        private readonly ?string $search     = null,
        private readonly ?string $event      = null,
        private readonly ?string $compcard   = null,
        private readonly ?string $gender     = null,
        private readonly ?string $emailSent  = null,
        private readonly ?string $testModel  = null,
    ) {}

    public function collection(): Collection
    {
        $query = User::models()->with([
            'modelProfile',
            'eventsAsModelWithCasting',
        ]);

        if ($this->event) {
            $query->whereHas('eventsAsModelWithCasting', fn($q) => $q->where('events.id', $this->event));
        }

        if ($this->compcard) {
            $query->whereHas('modelProfile', function ($q) {
                if ($this->compcard === 'complete') {
                    $q->where('compcard_completed', true);
                } elseif ($this->compcard === 'incomplete') {
                    $q->where('compcard_completed', false);
                }
            });
        }

        if ($this->gender) {
            $query->whereHas('modelProfile', fn($q) => $q->where('gender', $this->gender));
        }

        if ($this->search) {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'ilike', "%{$search}%")
                  ->orWhere('last_name',  'ilike', "%{$search}%")
                  ->orWhere('email',      'ilike', "%{$search}%")
                  ->orWhere('phone',      'ilike', "%{$search}%")
                  ->orWhereHas('eventsAsModelWithCasting', fn($eq) =>
                      $eq->where('event_model.participation_number', 'like', "%{$search}%")
                  );
            });
        }

        if ($this->emailSent === 'sent') {
            $query->whereNotNull('welcome_email_sent_at');
        } elseif ($this->emailSent === 'not_sent') {
            $query->whereNull('welcome_email_sent_at');
        }

        if ($this->testModel === 'only_test') {
            $query->whereHas('modelProfile', fn($q) => $q->where('is_test_model', true));
        } elseif ($this->testModel === 'only_real') {
            $query->where(function ($q) {
                $q->whereHas('modelProfile', fn($pq) => $pq->where('is_test_model', false))
                  ->orWhereDoesntHave('modelProfile');
            });
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function headings(): array
    {
        return [
            // Datos personales
            'Nombre',
            'Apellido',
            'Email',
            'Teléfono',
            'Estado',
            'Fecha Registro',
            'Último Login',
            'Correo Enviado',
            // Perfil
            'Género',
            'Edad',
            'Ubicación',
            'Altura (in)',
            'Busto (in)',
            'Cintura (in)',
            'Cadera (in)',
            'Talla Zapato',
            'Talla Ropa',
            'Cabello',
            'Etnicidad',
            'Tipo de Cuerpo',
            'Instagram',
            'Agencia',
            'Comp Card %',
            'Modelo de Prueba',
            'Notas',
            // Eventos
            'Eventos',
            'Horario Casting',
            '# Participación',
        ];
    }

    public function map($model): array
    {
        $profile    = $model->modelProfile;
        $events     = $model->eventsAsModelWithCasting ?? collect();
        $firstEvent = $events->first();

        $statusLabel = [
            'active'   => 'Activa',
            'inactive' => 'Inactiva',
            'pending'  => 'Pendiente',
        ][$model->status] ?? $model->status;

        $genderLabel = [
            'female'     => 'Femenino',
            'male'       => 'Masculino',
            'non_binary' => 'No binario',
        ][$profile?->gender] ?? '';

        $hairLabel = [
            'black'  => 'Negro',
            'brown'  => 'Castaño',
            'blonde' => 'Rubio',
            'red'    => 'Rojo',
            'gray'   => 'Gris',
            'other'  => 'Otro',
        ][$profile?->hair] ?? '';

        $ethnicityLabel = [
            'asian'          => 'Asiática',
            'black'          => 'Negra',
            'caucasian'      => 'Caucásica',
            'hispanic'       => 'Hispana',
            'middle_eastern' => 'Medio Oriente',
            'mixed'          => 'Mixta',
            'other'          => 'Otra',
        ][$profile?->ethnicity] ?? '';

        $bodyTypeLabel = [
            'slim'      => 'Delgada',
            'athletic'  => 'Atlética',
            'average'   => 'Promedio',
            'curvy'     => 'Curvy',
            'plus_size' => 'Plus Size',
        ][$profile?->body_type] ?? '';

        return [
            // Datos personales
            $model->first_name,
            $model->last_name,
            $model->email,
            $model->phone ?? '',
            $statusLabel,
            $model->created_at?->format('d/m/Y'),
            $model->last_login_at?->format('d/m/Y H:i') ?? '',
            $model->welcome_email_sent_at?->format('d/m/Y') ?? '',
            // Perfil
            $genderLabel,
            $profile?->age ?? '',
            $profile?->location ?? '',
            $profile?->height ?? '',
            $profile?->bust ?? '',
            $profile?->waist ?? '',
            $profile?->hips ?? '',
            $profile?->shoe_size ?? '',
            $profile?->dress_size ?? '',
            $hairLabel,
            $ethnicityLabel,
            $bodyTypeLabel,
            $profile?->instagram ?? '',
            $profile?->agency ?? '',
            ($profile?->comp_card_progress ?? 0) . '%',
            ($profile?->is_test_model ? 'Sí' : 'No'),
            $profile?->notes ?? '',
            // Eventos
            $events->pluck('name')->join(', '),
            $firstEvent?->pivot?->casting_time ?? '',
            $firstEvent?->pivot?->participation_number ?? '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // Fila de encabezados: fondo negro, texto blanco, negrita
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '000000']],
            ],
        ];
    }
}
