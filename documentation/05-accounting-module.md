# Módulo de Contabilidad

El módulo más completo del sistema. Gestiona el ciclo de vida financiero completo de los diseñadores: desde el plan de pagos hasta la comunicación de soporte y el reporte de liquidez.

**Rutas base:** `/admin/accounting`
**Controlador:** `App\Http\Controllers\Admin\AccountingController` (~1400 líneas)
**Service:** `App\Services\AccountingService`
**Acceso:** `section:accounting_dashboard` (dashboard) | `section:accounting_payments` (todo lo demás)

---

## Páginas del Módulo

| Página | URL | Archivo Vue |
|--------|-----|-------------|
| Dashboard | `/admin/accounting/dashboard` | `Accounting/Dashboard.vue` |
| Lista de Diseñadores | `/admin/accounting/designers-list` | `Accounting/DesignersList.vue` |
| Deudas (vencidas) | `/admin/accounting/overdue` | `Accounting/OverdueList.vue` |
| Historial / Bitácora | `/admin/accounting/cases` | `Accounting/CaseHistory.vue` |
| Nuevo Caso | `/admin/accounting/cases/create` | `Accounting/CaseCreate.vue` |
| Detalle del Caso | `/admin/accounting/cases/{case}` | `Accounting/CaseShow.vue` |
| Reporte de Liquidez | `/admin/accounting/liquidity` | `Accounting/LiquidityReport.vue` |
| Pagos Diseñadores | `/admin/accounting/payments` | `Accounting/Payments.vue` |
| Detalle de Pagos | `/admin/accounting/payments/designer/{id}/event/{id}` | `Accounting/DesignerPayment.vue` |
| Registro de Pagos | `/admin/accounting/payment-records` | `Accounting/PaymentRecords.vue` |

---

## 1. Dashboard de Contabilidad

**Ruta:** `GET /admin/accounting/dashboard`

Muestra métricas financieras generales:
- Total facturado vs cobrado vs pendiente
- Diseñadores con planes activos
- Próximos vencimientos
- Gráfica de cobros por mes (Chart.js)

---

## 2. Lista de Diseñadores

**Ruta:** `GET /admin/accounting/designers-list`
**Export:** `GET /admin/accounting/designers-list/export` (CSV)

Lista todos los diseñadores con su información financiera:
- Nombre, marca, evento, paquete
- Monto total, pagado, pendiente
- Estado del downpayment
- Progreso porcentual (barra visual)
- Estado del plan

**Filtros disponibles:** evento, búsqueda por nombre/marca, estado del plan

**API interna:**
- `GET /admin/accounting/api/designer-detail/{designer}` — JSON con detalle completo del diseñador para modal

---

## 3. Deudas (Cuotas Vencidas)

**Ruta:** `GET /admin/accounting/overdue`
**Export:** `GET /admin/accounting/overdue/export` (CSV)

Lista diseñadores con cuotas vencidas o parcialmente vencidas.

**Filtros:** evento, búsqueda por nombre/marca

**Importante:** Solo muestra diseñadores con `status = 'active'`. Los diseñadores desactivados quedan excluidos automáticamente.

**Datos por fila:**
- Marca, diseñador, evento, rep. de ventas, paquete
- Número de cuotas vencidas
- Monto total vencido
- Días desde la cuota más antigua vencida

**Stats del header:**
- Total monto vencido
- Diseñadores con deuda
- Total cuotas vencidas
- Cuota más antigua

---

## 4. Historial / Bitácora de Soporte (Support Cases)

Sistema de mini-CRM para registrar y dar seguimiento a comunicaciones con diseñadores.

### 4.1 Lista de Casos

**Ruta:** `GET /admin/accounting/cases`

**Filtros disponibles:**
- Búsqueda por texto (número de caso, nombre del diseñador, marca)
- Evento
- Tipo de caso (Reclamo, Queja, Pago, Devolución)
- Canal (Email, SMS, Llamada, WhatsApp, DM)
- Estado (Abierto, En Proceso, Resuelto, Cerrado)

**Datos por fila:**
- Número del caso (CASO-0001, CASO-0002...)
- Diseñador + marca
- Canal + tipo (con badges de color)
- Fecha de reclamo
- Estado
- Último mensaje

### 4.2 Crear Caso

**Ruta:** `GET /admin/accounting/cases/create` | `POST /admin/accounting/cases`

**Secciones del formulario:**

**Sección 1 — Datos del Caso:**
- Diseñador (búsqueda AJAX con deduplicación por ID)
- Evento (opcional)
- ID de caso (auto-generado: CASO-XXXX)
- Canal (Email, SMS, Llamada, WhatsApp, DM)
- Tipo de caso

**Sección 2 — Contacto del Diseñador (dinámico por canal):**
- `email` → Campo de correo (con dropdown de correos guardados del diseñador)
- `whatsapp` → Campo tel para número de WhatsApp
- `sms`/`phone` → Campo tel para teléfono
- `dm` → Campo text para usuario/URL de Instagram
- Fecha de reclamo

**Sección 3 — Mensaje del Diseñador:**
- Textarea del mensaje recibido
- Fecha del mensaje
- Adjuntos (drag & drop, múltiples archivos: jpg, png, gif, pdf, doc, docx)

**Sección 4 — Respuesta del Equipo (opcional):**
- Checkbox para agregar respuesta inmediata
- Quién respondió (dropdown de team members)
- Fecha de respuesta
- Mensaje de respuesta
- Adjuntos

**API usadas:**
- `GET /admin/accounting/api/designers-all-events?search=...` — Búsqueda de diseñadores
- `GET /admin/accounting/api/designer-emails/{designer}` — Correos guardados del diseñador

### 4.3 Detalle del Caso

**Ruta:** `GET /admin/accounting/cases/{case}`

Vista tipo timeline:
- Hilo de mensajes ordenados cronológicamente
- Mensajes del diseñador (borde izquierdo rojo)
- Mensajes del equipo (borde izquierdo verde)
- Adjuntos en grid con thumbnails/iconos
- Dropdown para cambiar estado del caso
- Formulario para agregar nuevo mensaje (con selector designer/equipo)

**Rutas de acción:**
- `POST /admin/accounting/cases/{case}/messages` — Agregar mensaje
- `PUT /admin/accounting/cases/{case}/status` — Cambiar estado
- `DELETE /admin/accounting/cases/{case}` — Eliminar caso (con confirmación)

### Auto-generación del número de caso

```php
public static function generateCaseNumber(): string
{
    $lastCase = static::orderByDesc('id')->first();
    $nextNumber = $lastCase ? intval(substr($lastCase->case_number, 5)) + 1 : 1;
    return 'CASO-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
}
// Resultado: CASO-0001, CASO-0002, ..., CASO-0099, CASO-0100
```

---

## 5. Reporte de Liquidez

**Ruta:** `GET /admin/accounting/liquidity`
**Export:** `GET /admin/accounting/liquidity/export` (CSV)

Reporte de cuotas pendientes/vencidas agrupadas por fecha de vencimiento.

### Filtros

| Filtro | Tipo | Descripción |
|--------|------|-------------|
| Fecha desde | date | Default: primer día del mes siguiente |
| Fecha hasta | date | Default: último día del mes siguiente |
| Evento | select | Filtrar por evento |
| Estado | select | Todos / Solo Vencidas / Solo Pendientes |

**Prioridad del filtro:** El rango de fechas es el filtro principal. Todos los registros mostrados deben tener `due_date` dentro del rango. El filtro de estado aplica dentro de ese rango.

### Tarjetas de resumen
- **Total pendiente** (todos los estados en el rango) — fondo dorado
- **Vencido** (due_date < hoy, dentro del rango) — fondo rojo
- **Por vencer** (due_date >= hoy, dentro del rango) — fondo verde

### Tabla agrupada
Agrupa cuotas por `due_date`. Para cada fecha muestra:
- Fecha
- Cantidad de cuotas
- Monto total pendiente
- Diseñadores involucrados
- Estado dominante (badge)

Al hacer click en una fila, abre un modal con el detalle completo de cada cuota de esa fecha:
- Diseñador, marca, evento
- Cuota #N de #total
- Monto (formato `$300 / $1,000` si pago parcial)
- Días vencido
- Estado (badge)

### Export CSV
Mismo filtro que la vista, genera dos secciones:
1. Resumen estadístico
2. Detalle de cada cuota (Fecha Vencimiento, Marca, Diseñador, Evento, Cuota, Monto Total, Monto Pagado, Monto Pendiente, Días Vencido, Estado)

---

## 6. Pagos de Diseñadores

### 6.1 Lista de diseñadores con planes

**Ruta:** `GET /admin/accounting/payments`

Lista todos los diseñadores que tienen planes de pago activos.

**APIs internas:**
- `GET /admin/accounting/api/designers-by-event/{event}` — Diseñadores de un evento específico
- `GET /admin/accounting/api/designers-all-events?search=...` — Búsqueda global

### 6.2 Detalle de pagos de un diseñador

**Ruta:** `GET /admin/accounting/payments/designer/{designer}/event/{event}`

Vista completa del plan de pago de un diseñador para un evento:

**Header del diseñador:**
- Nombre, marca, evento, paquete, rep. de ventas
- Barra de progreso (% pagado)
- Stats: total, pagado, pendiente

**Sección Downpayment:**
- Monto del downpayment
- Estado (Pendiente / Pagado)
- Si pagado: fecha, comprobante (link)
- Botón "Marcar como pagado" si pendiente

**Tabla de cuotas:**
| Cuota # | Fecha Venc. | Monto | Estado | Acciones |
|---------|-------------|-------|--------|----------|
| 1 | 01/03/2026 | $1,000 | Pagado | — |
| 2 | 01/04/2026 | $500 / $1,000 | Parcial | Marcar pagada |
| 3 | 01/05/2026 | $1,000 | Pendiente | Marcar pagada |
| 4 | 01/06/2026 | $1,000 | Vencida | Marcar pagada |

**Badges de estado:**
- `pending` → amarillo "Pendiente"
- `partial` → azul "Parcial"
- `paid` → verde "Pagado"
- `overdue` → rojo "Vencida"

**Acciones:**
- Crear/editar plan de pago
- Marcar downpayment como pagado: `POST /admin/accounting/payments/plans/{plan}/downpayment-paid`
- Marcar cuota como pagada: `POST /admin/accounting/payments/installments/{installment}/mark-paid`
- Subir comprobante: `POST /admin/accounting/payments/installments/{installment}/upload-receipt`
- Actualizar info del diseñador: `PUT /admin/accounting/payments/designer/{designer}/event/{event}`

### 6.3 Crear/Editar Plan de Pago

**Rutas:**
- `POST /admin/accounting/payments/create-plan` — Crear nuevo plan
- `PUT /admin/accounting/payments/plans/{plan}` — Actualizar plan

**Campos del plan:**
- Total del contrato
- Downpayment (monto)
- Número de cuotas
- Paquete (opcional)
- Notas

El sistema genera automáticamente las cuotas dividiendo `(total - downpayment) / installments_count`.

---

## 7. Registro de Pagos

**Ruta:** `GET /admin/accounting/payment-records`

Registro cronológico de todos los pagos recibidos, independiente del plan.

**CRUD:**
- `POST /admin/accounting/payment-records` — Registrar nuevo pago
- `PUT /admin/accounting/payment-records/{record}` — Editar
- `DELETE /admin/accounting/payment-records/{record}` — Eliminar

**Al registrar un pago, se aplica automáticamente la lógica waterfall:**

```
Si payment_type = 'installment':
  → allocatePaymentToInstallments(designer_id, event_id, amount, ...)
  → Distribuye el monto en cuotas pendientes/vencidas ordenadas por due_date
  → Soporta pagos parciales (paid_amount parcial, status='partial')

Si payment_type = 'downpayment':
  → allocateDownpayment(designer_id, event_id)
  → Si downpayment pendiente → lo marca como pagado
  → Si ya pagado → solo registra sin cambios
```

**Campos del registro:**
- Diseñador (búsqueda AJAX)
- Evento (opcional)
- Monto
- Método de pago (`zelle`, `wire`, `cash`, `card`, `check`, `other`)
- Tipo de pago (`downpayment`, `installment`, `other`)
- Referencia
- Fecha del pago
- Notas

---

## AccountingService — Métodos Principales

```php
// Actualiza automáticamente cuotas de pending a overdue cuando pasa la fecha
updateOverdueInstallments(): void

// Distribuye un monto en cuotas en orden waterfall
allocatePaymentToInstallments(
    int $designerId,
    int $eventId,
    float $amount,
    int $markedById,
    string $paymentMethod,
    ?string $reference
): void

// Maneja el pago del downpayment
allocateDownpayment(int $designerId, int $eventId): void

// Verifica si el plan está completamente pagado
checkIfCompleted(DesignerPaymentPlan $plan): void
```

### Lógica Waterfall Detallada

```
1. Buscar el plan del diseñador para ese evento
2. Si no existe plan → no hacer nada
3. Obtener cuotas con remaining > 0, ordenadas por:
   - Primero las 'overdue' (ya vencidas)
   - Luego 'partial' (pago parcial previo)
   - Luego 'pending'
   - Dentro de cada grupo: por due_date ASC (más antigua primero)
4. Para cada cuota:
   a. remaining = cuota.amount - cuota.paid_amount
   b. asignar = min(montoDisponible, remaining)
   c. cuota.paid_amount += asignar
   d. Si paid_amount == amount → status='paid', paid_at=now()
   e. Si paid_amount < amount → status='partial'
   f. montoDisponible -= asignar
   g. Si montoDisponible <= 0 → stop
5. checkIfCompleted() — si todas las cuotas y downpayment pagados → plan.status='completed'
```

---

## APIs Internas del Módulo

| Método | URL | Descripción |
|--------|-----|-------------|
| GET | `/admin/accounting/api/designer-detail/{designer}` | JSON detalle del diseñador + plan |
| GET | `/admin/accounting/api/designers-by-event/{event}` | Diseñadores de un evento |
| GET | `/admin/accounting/api/designers-all-events?search=` | Búsqueda global de diseñadores |
| GET | `/admin/accounting/api/search-designers?q=` | Búsqueda para registro de pagos |
| GET | `/admin/accounting/api/designer-emails/{designer}` | Correos guardados del diseñador |
