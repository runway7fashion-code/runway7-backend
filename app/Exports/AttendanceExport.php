<?php

namespace App\Exports;

use App\Models\Checkin;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AttendanceExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(
        private readonly ?string $eventId    = null,
        private readonly ?string $eventDayId = null,
        private readonly ?string $role       = null,
        private readonly ?string $method     = null,
        private readonly ?string $search     = null,
    ) {}

    public function collection(): Collection
    {
        $query = Checkin::with(['user', 'event:id,name', 'eventDay:id,date,label']);

        if ($this->eventId)    $query->where('event_id', $this->eventId);
        if ($this->eventDayId) $query->where('event_day_id', $this->eventDayId);
        if ($this->method)     $query->where('method', $this->method);

        if ($this->role) {
            $query->whereHas('user', fn ($q) => $q->where('role', $this->role));
        }

        if ($this->search) {
            $s = $this->search;
            $query->whereHas('user', fn ($q) => $q
                ->where('first_name', 'ilike', "%{$s}%")
                ->orWhere('last_name', 'ilike', "%{$s}%")
                ->orWhere('email', 'ilike', "%{$s}%")
            );
        }

        $checkins = $query->orderBy('checked_at', 'desc')->get();

        // Attach area
        $pairs = $checkins
            ->filter(fn ($c) => in_array($c->user?->role, ['volunteer', 'staff']))
            ->map(fn ($c) => ['user_id' => $c->user_id, 'event_id' => $c->event_id])
            ->unique(fn ($p) => $p['user_id'] . '_' . $p['event_id']);

        $areaMap = [];
        foreach ($pairs as $pair) {
            $area = \DB::table('event_staff')
                ->where('user_id', $pair['user_id'])
                ->where('event_id', $pair['event_id'])
                ->value('area');
            $areaMap[$pair['user_id'] . '_' . $pair['event_id']] = $area;
        }

        foreach ($checkins as $checkin) {
            $checkin->area = $areaMap[$checkin->user_id . '_' . $checkin->event_id] ?? null;
        }

        return $checkins;
    }

    public function headings(): array
    {
        return ['Nombre', 'Apellido', 'Email', 'Rol', 'Área', 'Evento', 'Día', 'Tipo', 'Hora Marcación', 'Método'];
    }

    public function map($checkin): array
    {
        $typeLabel   = ['entry' => 'Entrada', 'exit' => 'Salida', 'single' => 'Asistencia'][$checkin->type] ?? $checkin->type;
        $methodLabel = ['kiosk' => 'Kiosco', 'manual' => 'Manual'][$checkin->method] ?? $checkin->method;

        return [
            $checkin->user?->first_name ?? '',
            $checkin->user?->last_name ?? '',
            $checkin->user?->email ?? '',
            $checkin->user?->role ?? '',
            $checkin->area ?? '',
            $checkin->event?->name ?? '',
            $checkin->eventDay?->label ?? '',
            $typeLabel,
            $checkin->checked_at?->format('d/m/Y H:i'),
            $methodLabel,
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
