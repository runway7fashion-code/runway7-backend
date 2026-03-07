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

class DesignersExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(
        private readonly ?string $search    = null,
        private readonly ?string $event     = null,
        private readonly ?string $category  = null,
        private readonly ?string $package   = null,
        private readonly ?string $salesRep  = null,
        private readonly ?string $materials = null,
        private readonly ?string $country   = null,
    ) {}

    public function collection(): Collection
    {
        $query = User::designers()->with([
            'designerProfile.category',
            'designerProfile.salesRep',
            'eventsAsDesigner',
        ]);

        if ($this->event) {
            $query->whereHas('eventsAsDesigner', fn($q) => $q->where('events.id', $this->event));
        }

        if ($this->category) {
            $query->whereHas('designerProfile', fn($q) => $q->where('category_id', $this->category));
        }

        if ($this->package) {
            $query->whereHas('eventsAsDesigner', fn($q) => $q->where('event_designer.package_id', $this->package));
        }

        if ($this->salesRep) {
            $query->whereHas('designerProfile', fn($q) => $q->where('sales_rep_id', $this->salesRep));
        }

        if ($this->country) {
            $query->whereHas('designerProfile', fn($q) => $q->where('country', $this->country));
        }

        if ($this->materials) {
            if ($this->materials === 'complete') {
                $query->whereHas('designerMaterials')
                      ->whereDoesntHave('designerMaterials', fn($q) => $q->whereIn('status', ['pending', 'rejected']));
            } elseif ($this->materials === 'incomplete') {
                $query->whereHas('designerMaterials', fn($q) => $q->whereIn('status', ['pending', 'rejected']));
            }
        }

        if ($this->search) {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'ilike', "%{$search}%")
                  ->orWhere('last_name',  'ilike', "%{$search}%")
                  ->orWhere('email',      'ilike', "%{$search}%")
                  ->orWhereHas('designerProfile', fn($pq) =>
                      $pq->where('brand_name', 'ilike', "%{$search}%")
                  );
            });
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function headings(): array
    {
        return [
            'Nombre',
            'Apellido',
            'Email',
            'Teléfono',
            'Estado',
            'Marca',
            'Categoría',
            'País',
            'Website',
            'Instagram',
            'Skype',
            'Vendedor',
            'Fecha Registro',
            'Último Login',
            'Correo Enviado',
            'SMS Enviado',
            'Eventos',
        ];
    }

    public function map($designer): array
    {
        $profile = $designer->designerProfile;
        $events  = $designer->eventsAsDesigner ?? collect();

        $statusLabel = [
            'active'   => 'Activo',
            'inactive' => 'Inactivo',
            'pending'  => 'Pendiente',
        ][$designer->status] ?? $designer->status;

        $salesRep = $profile?->salesRep;
        $salesRepName = $salesRep ? "{$salesRep->first_name} {$salesRep->last_name}" : '';

        return [
            $designer->first_name,
            $designer->last_name,
            $designer->email,
            $designer->phone ?? '',
            $statusLabel,
            $profile?->brand_name ?? '',
            $profile?->category?->name ?? '',
            $profile?->country ?? '',
            $profile?->website ?? '',
            $profile?->instagram ?? '',
            $profile?->skype ?? '',
            $salesRepName,
            $designer->created_at?->format('d/m/Y'),
            $designer->last_login_at?->format('d/m/Y H:i') ?? '',
            $designer->welcome_email_sent_at?->format('d/m/Y') ?? '',
            $designer->sms_sent_at?->format('d/m/Y') ?? '',
            $events->pluck('name')->join(', '),
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
