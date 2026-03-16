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

class VolunteersExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(
        private readonly ?string $search  = null,
        private readonly ?string $status  = null,
        private readonly ?string $eventId = null,
    ) {}

    public function collection(): Collection
    {
        $query = User::where('role', 'volunteer')->with([
            'volunteerProfile',
            'eventsAsStaff',
        ]);

        if ($this->search) {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'ilike', "%{$search}%")
                  ->orWhere('last_name', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%")
                  ->orWhere('phone', 'ilike', "%{$search}%");
            });
        }

        if ($this->status) {
            $query->where('status', $this->status);
        }

        if ($this->eventId) {
            $eventId = $this->eventId;
            $query->whereHas('eventsAsStaff', fn ($q) => $q->where('events.id', $eventId));
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nombre',
            'Apellido',
            'Email',
            'Teléfono',
            'Edad',
            'Género',
            'Ubicación',
            'Instagram',
            'Talla Camiseta',
            'Experiencia',
            'Estilo de Trabajo',
            'Disponibilidad',
            'Contribución',
            'Resume Link',
            'Eventos',
            'Estado',
            'Fecha Registro',
        ];
    }

    public function map($user): array
    {
        $profile = $user->volunteerProfile;
        $events = $user->eventsAsStaff ?? collect();

        $statusLabel = [
            'active' => 'Activo',
            'inactive' => 'Inactivo',
            'pending' => 'Pendiente',
            'applicant' => 'Aplicante',
        ][$user->status] ?? $user->status;

        $genderLabel = [
            'female' => 'Femenino',
            'male' => 'Masculino',
            'non_binary' => 'No binario',
        ][$profile?->gender ?? ''] ?? '';

        $experienceLabel = [
            'none' => 'Sin experiencia',
            'some' => 'Algo de experiencia',
            'experienced' => 'Con experiencia',
        ][$profile?->experience] ?? '';

        $workStyleLabel = [
            'multitask' => 'Multitarea / Dinámico',
            'structured' => 'Estructurado',
        ][$profile?->comfortable_fast_paced] ?? '';

        $availabilityLabel = [
            'yes' => 'Completa',
            'no' => 'No disponible',
            'partially' => 'Parcial',
        ][$profile?->full_availability] ?? '';

        return [
            $user->id,
            $user->first_name,
            $user->last_name,
            $user->email,
            $user->phone ?? '',
            $profile?->age ?? '',
            $genderLabel,
            $profile?->location ?? '',
            $profile?->instagram ?? '',
            $profile?->tshirt_size ?? '',
            $experienceLabel,
            $workStyleLabel,
            $availabilityLabel,
            $profile?->contribution ?? '',
            $profile?->resume_link ?? '',
            $events->pluck('name')->join(', '),
            $statusLabel,
            $user->created_at?->format('d/m/Y'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '000000']],
            ],
        ];
    }
}
