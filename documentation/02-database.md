# Base de Datos — Esquema Completo

**Motor:** PostgreSQL 16
**Total migraciones:** 47
**Total tablas de aplicación:** ~38 (+ tablas Laravel: cache, jobs, sessions, migrations, etc.)

---

## Diagrama de Grupos

```
┌─────────────────────────────────────────────────────────────┐
│  CORE                                                       │
│  users · events · event_days                                │
└──────────────────────────────┬──────────────────────────────┘
                               │
         ┌─────────────────────┼──────────────────────┐
         │                     │                      │
┌────────▼────────┐  ┌─────────▼────────┐  ┌─────────▼──────┐
│  MODELOS        │  │  DISEÑADORES      │  │  SHOWS         │
│  model_profiles │  │  designer_*       │  │  shows         │
│  event_model    │  │  event_designer   │  │  show_model    │
│  casting_slots  │  │  designer_payment_│  │  show_designer │
│                 │  │  plans + install. │  │                │
└─────────────────┘  └──────────────────┘  └────────────────┘

┌────────────────────────────────────────────────────────────┐
│  CONTABILIDAD                                              │
│  designer_payment_plans · designer_installments            │
│  payment_records · support_cases · support_case_messages  │
│  support_case_attachments · designer_contact_emails        │
└────────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────────┐
│  TICKETS (parcialmente implementado)                       │
│  ticket_types · tickets                                    │
└────────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────────┐
│  COMUNICACIÓN                                              │
│  conversations · messages · banners                        │
└────────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────────┐
│  COMERCIO (definido, no implementado en admin aún)        │
│  orders · order_items · designer_products                  │
│  merch_products · promo_codes · promo_code_usage          │
└────────────────────────────────────────────────────────────┘
```

---

## Tablas — Detalle Completo

### `users`
Tabla central. Unifica todos los tipos de usuario.

| Columna | Tipo | Descripción |
|---------|------|-------------|
| id | bigint PK | |
| first_name | string | |
| last_name | string | |
| email | string unique | |
| phone | string nullable | |
| password | string hashed | |
| role | string | Ver roles en documento 03 |
| status | string | `active`, `inactive`, `pending` |
| profile_picture | string nullable | Ruta al archivo |
| email_verified_at | timestamp nullable | |
| remember_token | string nullable | |
| deleted_at | timestamp nullable | SoftDeletes |
| created_at / updated_at | timestamps | |

**Scopes disponibles:** `active()`, `role()`, `models()`, `designers()`, `admins()`, `internalTeam()`, `participants()`, `attendees()`, `vips()`, `press()`, `sponsors()`, `byCategory()`

---

### `events`
| Columna | Tipo | Descripción |
|---------|------|-------------|
| id | bigint PK | |
| name | string | Nombre del evento |
| slug | string unique | URL-friendly (ej: `nyfw-september-2026`) |
| city | string | Ciudad |
| venue | string | Sede/venue |
| timezone | string | Ej: `America/New_York` |
| start_date | date | |
| end_date | date | |
| status | string | `draft`, `active`, `completed`, `cancelled` |
| description | text nullable | |
| settings | jsonb nullable | Configuraciones adicionales |
| model_number_start | integer | Número inicial para modelos |
| deleted_at | timestamp nullable | SoftDeletes |

---

### `event_days`
| Columna | Tipo | Descripción |
|---------|------|-------------|
| id | bigint PK | |
| event_id | FK → events | |
| date | date | |
| type | string | `show_day`, `casting`, `setup`, `other` |
| label | string nullable | Etiqueta descriptiva |
| order | integer | Orden de presentación |

---

### `model_profiles`
| Columna | Tipo | Descripción |
|---------|------|-------------|
| id | bigint PK | |
| user_id | FK → users | |
| birth_date | date nullable | |
| age | integer nullable | |
| gender | string | `female`, `male`, `non-binary` |
| location | string nullable | Ciudad, País |
| agency | string nullable | Nombre de la agencia |
| is_agency | boolean | ¿Representada por agencia? |
| instagram | string nullable | Handle |
| participation_number | integer nullable | Número de participación en evento |
| height | decimal(5,2) nullable | cm |
| bust | decimal(5,2) nullable | cm |
| waist | decimal(5,2) nullable | cm |
| hips | decimal(5,2) nullable | cm |
| shoe_size | string nullable | |
| dress_size | string nullable | `XS`,`S`,`M`,`L`,`XL` |
| ethnicity | string nullable | |
| hair | string nullable | |
| body_type | string nullable | |
| photo_1..photo_4 | string nullable | Rutas a fotos del comp card |
| compcard_completed | boolean | ¿Comp card completo? |
| notes | text nullable | |

---

### `designer_profiles`
| Columna | Tipo | Descripción |
|---------|------|-------------|
| id | bigint PK | |
| user_id | FK → users | |
| brand_name | string | Nombre de la marca |
| collection_name | string nullable | Nombre de la colección |
| website | string nullable | |
| instagram | string nullable | |
| bio | text nullable | |
| country | string nullable | País de origen |
| category_id | FK → designer_categories nullable | |
| sales_rep_id | FK → users nullable | Rep. de ventas asignado |
| tracking_link | string nullable | Link de seguimiento |
| skype | string nullable | |
| social_media | jsonb nullable | `{instagram, facebook, tiktok, website, other}` |

---

### `designer_categories`
| Columna | Tipo | Descripción |
|---------|------|-------------|
| id | bigint PK | |
| name | string | Ej: "Streetwear", "Evening Wear & Gowns" |
| slug | string unique | Ej: `streetwear` |
| description | text nullable | |

---

### `designer_packages`
| Columna | Tipo | Descripción |
|---------|------|-------------|
| id | bigint PK | |
| name | string | Ej: "Standard", "Premium" |
| price | decimal(10,2) | |
| description | text nullable | |
| features | jsonb nullable | Lista de características incluidas |
| is_active | boolean | |

---

### `designer_assistants`
| Columna | Tipo | Descripción |
|---------|------|-------------|
| id | bigint PK | |
| designer_id | FK → users | |
| name | string | |
| email | string nullable | |
| phone | string nullable | |
| role | string nullable | Ej: "Coordinador", "Estilista" |

---

### `designer_materials`
Materiales/telas que trae el diseñador al evento.

| Columna | Tipo | Descripción |
|---------|------|-------------|
| id | bigint PK | |
| designer_id | FK → users | |
| name | string | |
| quantity | integer nullable | |
| notes | text nullable | |
| checked | boolean | ¿Recibido/confirmado? |

---

### `designer_displays`
Elementos de exhibición (stands, pantallas, etc.).

| Columna | Tipo | Descripción |
|---------|------|-------------|
| id | bigint PK | |
| designer_id | FK → users | |
| type | string | Tipo de display |
| description | text nullable | |
| video_url | string nullable | Video subido |
| audio_url | string nullable | Audio subido |
| notes | text nullable | |

---

### `event_designer` (pivot)
| Columna | Tipo | Descripción |
|---------|------|-------------|
| designer_id | FK → users | |
| event_id | FK → events | |
| status | string | `pending`, `confirmed`, `cancelled` |
| package_id | FK → designer_packages nullable | |
| looks | integer | Número de looks/outfits |
| model_casting_enabled | boolean | ¿Casting habilitado? |
| package_price | decimal(10,2) nullable | Precio acordado |
| notes | text nullable | |

---

### `event_model` (pivot)
| Columna | Tipo | Descripción |
|---------|------|-------------|
| model_id | FK → users | |
| event_id | FK → events | |
| participation_number | integer nullable | |
| casting_time | time nullable | |
| casting_checked_in_at | timestamp nullable | |
| casting_status | string nullable | |
| status | string | `pending`, `confirmed`, `rejected` |
| checked_in_at | timestamp nullable | |

---

### `event_staff` (pivot)
| Columna | Tipo | Descripción |
|---------|------|-------------|
| user_id | FK → users | |
| event_id | FK → events | |
| assigned_role | string | Rol específico en el evento |
| status | string | |
| checked_in_at | timestamp nullable | |
| notes | text nullable | |

---

### `shows`
| Columna | Tipo | Descripción |
|---------|------|-------------|
| id | bigint PK | |
| event_day_id | FK → event_days | |
| name | string | |
| start_time | time nullable | |
| end_time | time nullable | |
| runway_type | string nullable | |
| capacity | integer nullable | |
| notes | text nullable | |

---

### `show_model` (pivot)
| Columna | Tipo | Descripción |
|---------|------|-------------|
| show_id | FK → shows | |
| model_id | FK → users | |
| status | string | `pending`, `confirmed`, `rejected` |
| walk_order | integer nullable | |
| confirmed_at | timestamp nullable | |
| rejection_reason | text nullable | |
| requested_at | timestamp nullable | |
| responded_at | timestamp nullable | |
| notes | text nullable | |

---

### `show_designer` (pivot)
| Columna | Tipo | Descripción |
|---------|------|-------------|
| show_id | FK → shows | |
| designer_id | FK → users | |
| order | integer nullable | Orden en el show |
| collection_name | string nullable | |
| status | string | |
| notes | text nullable | |

---

### `casting_slots`
| Columna | Tipo | Descripción |
|---------|------|-------------|
| id | bigint PK | |
| event_day_id | FK → event_days | |
| designer_id | FK → users | |
| time_slot | time | |
| duration_minutes | integer | |
| notes | text nullable | |

---

## Tablas de Contabilidad

### `designer_payment_plans`
Un plan de pago por diseñador por evento.

| Columna | Tipo | Descripción |
|---------|------|-------------|
| id | bigint PK | |
| designer_id | FK → users | |
| event_id | FK → events | |
| package_id | FK → designer_packages nullable | |
| created_by | FK → users | Usuario que creó el plan |
| total_amount | decimal(10,2) | Monto total del contrato |
| downpayment | decimal(10,2) | Monto del depósito inicial |
| remaining_amount | decimal(10,2) | Total - Downpayment |
| installments_count | integer | Número de cuotas |
| downpayment_status | string | `pending`, `paid` |
| downpayment_receipt | string nullable | Ruta al comprobante |
| downpayment_paid_at | timestamp nullable | |
| status | string | `active`, `completed`, `cancelled` |
| notes | text nullable | |

**Métodos del modelo:**
- `totalPaid()` — Suma downpayment pagado + paid_amount de cuotas
- `totalPending()` — total_amount - totalPaid()
- `progressPercentage()` — 0-100%
- `isFullyPaid()` — boolean
- `overdueInstallments()` — cuotas vencidas

---

### `designer_installments`
Cuotas individuales de un plan de pago.

| Columna | Tipo | Descripción |
|---------|------|-------------|
| id | bigint PK | |
| payment_plan_id | FK → designer_payment_plans | |
| installment_number | integer | Número de cuota (1, 2, 3...) |
| amount | decimal(10,2) | Monto total de la cuota |
| paid_amount | decimal(10,2) | Monto pagado (soporta parciales) |
| due_date | date | Fecha de vencimiento |
| status | string | `pending`, `partial`, `paid`, `overdue` |
| receipt_url | string nullable | Comprobante de pago |
| payment_method | string nullable | `zelle`, `wire`, `cash`, `card`, etc. |
| payment_reference | string nullable | Número de referencia |
| paid_at | timestamp nullable | Fecha de pago |
| marked_by | FK → users nullable | Quién marcó el pago |
| notes | text nullable | |

**Métodos del modelo:**
- `remainingAmount()` — amount - paid_amount
- `isOverdue()` — bool (pending/partial y fecha pasada)

**Lógica Waterfall (AccountingService):**
Al registrar un pago tipo `installment`, el sistema distribuye automáticamente el monto en las cuotas pendientes/vencidas en orden de vencimiento (más antigua primero), con soporte para pagos parciales.

---

### `payment_records`
Registro de todos los pagos recibidos (independiente de la asignación a cuotas).

| Columna | Tipo | Descripción |
|---------|------|-------------|
| id | bigint PK | |
| designer_id | FK → users | |
| event_id | FK → events nullable | |
| recorded_by | FK → users | Quién registró el pago |
| amount | decimal(10,2) | |
| payment_method | string | `zelle`, `wire`, `cash`, `card`, `check`, `other` |
| payment_type | string | `downpayment`, `installment`, `other` |
| reference | string nullable | Número de referencia |
| notes | text nullable | |
| payment_date | date | |

---

### `support_cases`
Bitácora de comunicaciones con diseñadores (mini-CRM).

| Columna | Tipo | Descripción |
|---------|------|-------------|
| id | bigint PK | |
| case_number | string unique | Auto-generado: `CASO-0001` |
| designer_id | FK → users | |
| event_id | FK → events nullable | |
| channel | string | `email`, `sms`, `phone`, `whatsapp`, `dm` |
| case_type | string | `claim`, `complaint`, `payment`, `refund` |
| contact_email | string nullable | Correo/teléfono/usuario de contacto |
| claim_date | date | Fecha del reclamo |
| status | string | `open`, `in_progress`, `resolved`, `closed` |
| created_by | FK → users | |

**Nota:** El campo `contact_email` almacena el dato de contacto que varía según el canal: correo (email), número (whatsapp/sms), usuario Instagram (dm).

---

### `support_case_messages`
Mensajes del hilo de un caso de soporte.

| Columna | Tipo | Descripción |
|---------|------|-------------|
| id | bigint PK | |
| support_case_id | FK → support_cases | |
| sender_type | string | `designer`, `team` |
| team_member_id | FK → users nullable | Si sender_type = team |
| message | text | |
| message_date | date | |

---

### `support_case_attachments`
Archivos adjuntos a mensajes de soporte.

| Columna | Tipo | Descripción |
|---------|------|-------------|
| id | bigint PK | |
| support_case_message_id | FK → support_case_messages | |
| file_url | string | Ruta en storage |
| file_name | string | Nombre original |
| file_type | string | MIME type |
| file_size | integer | Bytes |

**Storage path:** `storage/app/public/accounting/cases/{case_id}/`

---

### `designer_contact_emails`
Correos/contactos alternativos de diseñadores (además del email de login).

| Columna | Tipo | Descripción |
|---------|------|-------------|
| id | bigint PK | |
| designer_id | FK → users | |
| email | string | Correo/contacto guardado |
| label | string nullable | Ej: "Asistente", "Personal" |

**Constraint único:** `[designer_id, email]`

---

## Tablas de Tickets

### `ticket_types`
Tipos de tickets disponibles para venta pública.

| Columna | Tipo | Descripción |
|---------|------|-------------|
| id | bigint PK | |
| event_day_id | FK → event_days | Día del evento al que aplica |
| name | string | Ej: "General Admission", "VIP" |
| zone | string | Zona/área del venue |
| price | decimal(10,2) | |
| capacity | integer | Total de asientos/entradas |
| sold | integer default 0 | Cantidad vendida |
| status | enum | `available`, `sold_out`, `closed` |

---

### `tickets`
Tickets individuales emitidos.

| Columna | Tipo | Descripción |
|---------|------|-------------|
| id | bigint PK | |
| ticket_type_id | FK → ticket_types | |
| buyer_first_name | string | |
| buyer_last_name | string | |
| buyer_email | string | |
| buyer_phone | string nullable | |
| qr_code | string unique | Código QR único |
| status | enum | `confirmed`, `checked_in`, `cancelled`, `refunded` |
| source | enum | `web`, `woocommerce`, `kiosk`, `manual` |
| external_order_id | string nullable | ID en WooCommerce/FooEvents |
| check_times | jsonb nullable | Historial de check-ins |
| first_check_in_at | timestamp nullable | |
| deleted_at | timestamp nullable | SoftDeletes |

**Nota:** Esta tabla es para tickets del público general (Flujo 2: WooCommerce). Los passes internos para modelos/diseñadores/staff aún no están implementados.

---

## Tablas de Comunicación

### `conversations`
| Columna | Tipo | Descripción |
|---------|------|-------------|
| id | bigint PK | |
| type | string | `direct`, `group`, `broadcast` |
| name | string nullable | Para grupos |
| created_by | FK → users nullable | |

### `messages`
| Columna | Tipo | Descripción |
|---------|------|-------------|
| id | bigint PK | |
| conversation_id | FK → conversations | |
| user_id | FK → users | |
| body | text | Contenido del mensaje |
| read_at | timestamp nullable | |
| deleted_at | timestamp nullable | |

### `banners`
| Columna | Tipo | Descripción |
|---------|------|-------------|
| id | bigint PK | |
| title | string | |
| subtitle | string nullable | |
| image_url | string nullable | |
| link_url | string nullable | |
| is_active | boolean | |
| order | integer | Para drag-and-drop reordering |
| target_role | string nullable | Rol destino (null = todos) |

---

## Tablas de Comercio (Definidas, no activas en admin)

### `orders`
| Columna | Tipo | Descripción |
|---------|------|-------------|
| id | bigint PK | |
| user_id | FK → users | |
| status | string | `pending`, `completed`, `cancelled`, `refunded` |
| total | decimal(10,2) | |
| payment_method | string nullable | |
| stripe_payment_intent | string nullable | |
| notes | text nullable | |

### `order_items`
| Columna | Tipo | Descripción |
|---------|------|-------------|
| id | bigint PK | |
| order_id | FK → orders | |
| product_type | string | `designer_product`, `merch` |
| product_id | bigint | Polymorphic |
| quantity | integer | |
| unit_price | decimal(10,2) | |

### `designer_products`, `merch_products`
Productos del marketplace (diseñadores) y merchandise oficial.

### `promo_codes`, `promo_code_usage`
Sistema de códigos promocionales con seguimiento de uso.

### `push_notifications`, `device_tokens`
Notificaciones push para la app móvil.

---

## Tablas de Perfiles Adicionales

### `press_profiles`
| Columna | Tipo | Descripción |
|---------|------|-------------|
| user_id | FK → users | |
| media_outlet | string | Medio de comunicación |
| position | string | Cargo |
| website | string nullable | |
| instagram | string nullable | |

### `sponsor_profiles`
| Columna | Tipo | Descripción |
|---------|------|-------------|
| user_id | FK → users | |
| company_name | string | |
| sponsorship_level | string | `bronze`, `silver`, `gold`, `platinum` |
| website | string nullable | |
| notes | text nullable | |
