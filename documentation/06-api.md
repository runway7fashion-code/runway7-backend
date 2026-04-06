# API REST — Documentación

**Base URL:** `http://localhost:8000/api/v1`
**Autenticación:** Laravel Sanctum (Bearer Token)
**Content-Type:** `application/json`

---

## Índice

1. [Autenticación](#autenticación)
2. [Eventos](#eventos)
3. [Shows](#shows)
4. [Casting](#casting)
5. [Fittings](#fittings)
6. [Pagos (Diseñadores)](#pagos-diseñadores)
7. [Tickets y Pases](#tickets-y-pases)
8. [Check-in / Kiosko](#check-in--kiosko)
9. [Chat](#chat)
10. [Banners](#banners)
11. [Perfil](#perfil)
12. [Notificaciones (Device Tokens)](#notificaciones-device-tokens)
13. [Certificados (Voluntarios)](#certificados-voluntarios)
14. [Registro Público](#registro-público)
15. [Webhooks](#webhooks)
16. [WebSockets (Laravel Reverb)](#websockets-laravel-reverb)
17. [Códigos de Error](#códigos-de-error)

---

## Autenticación

### POST `/api/v1/auth/login`
Login con email y password. Retorna usuario con perfiles + token Sanctum.

**Rate limit:** 10 intentos/minuto por IP

**Request:**
```json
{
    "email": "admin@runway7.com",
    "password": "password123"
}
```

**Response 200:**
```json
{
    "user": {
        "id": 1,
        "first_name": "Admin",
        "last_name": "Runway7",
        "email": "admin@runway7.com",
        "role": "admin",
        "status": "active",
        "model_profile": null,
        "designer_profile": null
    },
    "token": "1|abcdef123456..."
}
```

**Response 403 (cuenta inactiva/rechazada/applicant):**
```json
{ "message": "Tu cuenta ha sido desactivada. Contacta al administrador." }
```

**Response 422 (credenciales incorrectas):**
```json
{
    "message": "The given data was invalid.",
    "errors": { "email": ["Las credenciales son incorrectas."] }
}
```

**Notas:**
- Si el usuario tiene `status: pending`, se auto-activa al primer login.
- Si es diseñador con `status: pending`, también se confirma su `sales_registration`.

---

### GET `/api/v1/me`
Retorna el usuario autenticado con sus perfiles.

**Headers:** `Authorization: Bearer {token}`

**Response 200:**
```json
{
    "user": {
        "id": 5,
        "first_name": "Sofia",
        "last_name": "Rivera",
        "email": "sofia.rivera@models.com",
        "phone": "+1-305-555-0101",
        "role": "model",
        "status": "active",
        "profile_picture": null,
        "model_profile": {
            "height": "175",
            "bust": "86",
            "waist": "61",
            "hips": "89",
            "shoe_size": "8",
            "dress_size": "S",
            "instagram": "sofia.rivera",
            "body_type": "slim",
            "ethnicity": "hispanic",
            "hair": "brown",
            "compcard_completed": true,
            "comp_card_progress": 100
        },
        "designer_profile": null
    }
}
```

---

### POST `/api/v1/auth/logout`
Revoca el token actual.

**Headers:** `Authorization: Bearer {token}`

**Response 200:**
```json
{ "message": "Sesión cerrada exitosamente." }
```

---

## Eventos

Requieren `Authorization: Bearer {token}`.

### GET `/api/v1/events`
Lista eventos del usuario según su rol:
- **model** → eventos donde está asignado
- **designer** → eventos donde está asignado
- **staff/admin** → eventos donde es staff
- **otros roles** → todos los eventos publicados/activos

**Response 200:**
```json
{
    "events": [
        {
            "id": 1,
            "name": "NYFW September 2026",
            "slug": "nyfw-september-2026",
            "city": "New York",
            "venue": "Spring Studios",
            "start_date": "2026-09-10",
            "end_date": "2026-09-15",
            "status": "active",
            "description": "..."
        }
    ]
}
```

---

### GET `/api/v1/events/{event}`
Detalle del evento con días, shows y diseñadores.

**Response 200:**
```json
{
    "event": {
        "id": 1,
        "name": "NYFW September 2026",
        "slug": "nyfw-september-2026",
        "city": "New York",
        "venue": "Spring Studios",
        "start_date": "2026-09-10",
        "end_date": "2026-09-15",
        "status": "active",
        "description": "...",
        "days": [
            {
                "id": 1,
                "date": "2026-09-10",
                "label": "Day 1 - Setup",
                "type": "setup",
                "start_time": "08:00",
                "end_time": "18:00",
                "description": null,
                "shows": [
                    {
                        "id": 1,
                        "name": "Opening Show",
                        "scheduled_time": "7:00 PM",
                        "status": "scheduled",
                        "model_slots": 15,
                        "designers": [
                            {
                                "id": 10,
                                "name": "Carolina Herrera",
                                "collection_name": "Spring 2027"
                            }
                        ]
                    }
                ]
            }
        ]
    }
}
```

---

## Shows

Requieren `Authorization: Bearer {token}`.

### GET `/api/v1/my-shows`
Shows asignados al modelo autenticado.

**Response 200:**
```json
{
    "shows": [
        {
            "id": 1,
            "name": "Opening Show",
            "scheduled_time": "7:00 PM",
            "status": "scheduled",
            "event": { "id": 1, "name": "NYFW September 2026" },
            "day": { "date": "2026-09-12", "label": "Day 3 - Shows" },
            "assignment": {
                "status": "requested",
                "walk_order": 5,
                "confirmed_at": null,
                "designer": {
                    "id": 10,
                    "name": "Carolina Herrera",
                    "collection_name": "Spring 2027"
                }
            }
        }
    ]
}
```

---

### POST `/api/v1/shows/{show}/confirm`
Confirmar participación en un show. Solo funciona si el status es `requested` o `reserved`.

**Response 200:**
```json
{ "message": "Show confirmado exitosamente." }
```

**Response 404:**
```json
{ "message": "No tienes una asignación pendiente en este show." }
```

---

### POST `/api/v1/shows/{show}/reject`
Rechazar participación en un show.

**Request (opcional):**
```json
{ "reason": "Schedule conflict with another show" }
```

**Response 200:**
```json
{ "message": "Show rechazado." }
```

---

## Casting

Requieren `Authorization: Bearer {token}`.

### GET `/api/v1/my-casting`
Horarios de casting asignados al modelo.

**Response 200:**
```json
{
    "castings": [
        {
            "event_id": 1,
            "event_name": "NYFW September 2026",
            "casting_time": "10:30",
            "casting_status": "scheduled",
            "status": "confirmed",
            "casting_date": "2026-09-11"
        }
    ]
}
```

---

### POST `/api/v1/events/{event}/casting/confirm`
Confirmar asistencia al slot de casting.

**Response 200:**
```json
{ "message": "Horario de casting confirmado." }
```

---

### POST `/api/v1/events/{event}/casting/reject`
Rechazar slot de casting (se reasigna automáticamente un nuevo horario).

**Response 200:**
```json
{ "message": "Horario de casting rechazado. Se te asignará un nuevo horario." }
```

---

## Fittings

### GET `/api/v1/my-fittings`
Schedule de fittings de la modelo. La modelo hereda el fitting de su diseñador asignado (via `show_model`).

**Headers:** `Authorization: Bearer {token}`

**Response 200:**
```json
{
    "fittings": [
        {
            "event_name": "NYFW September 2026",
            "day_label": "Day 2 - Fittings",
            "day_date": "2026-09-11",
            "time": "14:00",
            "designer_name": "Carolina Herrera",
            "brand_name": "CH"
        }
    ]
}
```

---

## Pagos (Diseñadores)

Requieren `Authorization: Bearer {token}`. Solo disponible para usuarios con rol `designer`.

### GET `/api/v1/my-payments`
Lista los planes de pago del diseñador autenticado.

**Response 200:**
```json
{
    "payment_plans": [
        {
            "id": 1,
            "event": { "id": 1, "name": "NYFW September 2026" },
            "package": "Premium",
            "total_amount": "5000.00",
            "downpayment": "1500.00",
            "downpayment_status": "paid",
            "remaining_amount": "3500.00",
            "installments_count": 4,
            "total_paid": 2750.00,
            "total_pending": 2250.00,
            "progress": 55,
            "status": "active"
        }
    ]
}
```

---

### GET `/api/v1/my-payments/{plan}`
Detalle de un plan de pago con todas sus cuotas.

**Response 200:**
```json
{
    "payment_plan": {
        "id": 1,
        "event": { "id": 1, "name": "NYFW September 2026" },
        "package": "Premium",
        "total_amount": "5000.00",
        "downpayment": "1500.00",
        "downpayment_status": "paid",
        "downpayment_paid_at": "2026-06-01T10:00:00+00:00",
        "remaining_amount": "3500.00",
        "installments_count": 4,
        "total_paid": 2750.00,
        "total_pending": 2250.00,
        "progress": 55,
        "status": "active",
        "notes": null,
        "installments": [
            {
                "id": 1,
                "number": 1,
                "amount": "875.00",
                "paid_amount": "875.00",
                "remaining": 0.0,
                "due_date": "2026-07-01",
                "status": "paid",
                "is_overdue": false,
                "payment_method": "zelle",
                "paid_at": "2026-06-28T15:30:00+00:00"
            },
            {
                "id": 2,
                "number": 2,
                "amount": "875.00",
                "paid_amount": "375.00",
                "remaining": 500.0,
                "due_date": "2026-08-01",
                "status": "pending",
                "is_overdue": false,
                "payment_method": null,
                "paid_at": null
            }
        ]
    }
}
```

**Response 403 (plan de otro diseñador):**
```json
{ "message": "No autorizado." }
```

---

## Tickets y Pases

Requieren `Authorization: Bearer {token}`.

### GET `/api/v1/my-passes`
Pases del evento del usuario (modelos, diseñadores, staff, VIP, etc.)

**Response 200:**
```json
{
    "passes": [
        {
            "id": 1,
            "qr_code": "PASS-ABC123",
            "pass_type": "model",
            "pass_type_label": "Modelo",
            "holder_name": "Sofia Rivera",
            "valid_days": ["2026-09-12", "2026-09-13"],
            "status": "active",
            "checked_in_at": null,
            "event": {
                "id": 1,
                "name": "NYFW September 2026",
                "city": "New York",
                "venue": "Spring Studios"
            }
        }
    ]
}
```

---

### GET `/api/v1/my-tickets`
Tickets comprados por el usuario (búsqueda por email).

**Response 200:**
```json
{
    "tickets": [
        {
            "id": 1,
            "qr_code": "TKT-XYZ789",
            "buyer_name": "John Doe",
            "status": "confirmed",
            "ticket_type": "VIP Front Row",
            "zone": "A",
            "event": { "id": 1, "name": "NYFW September 2026" },
            "day": { "date": "2026-09-12", "label": "Day 3 - Shows" },
            "first_check_in_at": null
        }
    ]
}
```

---

### POST `/api/v1/check-in/scan`
Escanear QR para check-in (staff/admin). Acepta pases (`PASS-XXXXXX`) y tickets.

**Request:**
```json
{ "qr_code": "PASS-ABC123" }
```

**Response 200 (pase válido):**
```json
{
    "valid": true,
    "type": "pass",
    "message": "Check-in exitoso.",
    "data": {
        "holder_name": "Sofia Rivera",
        "pass_type": "Modelo",
        "event": "NYFW September 2026",
        "previous_check_ins": 0
    }
}
```

**Response 200 (ticket válido):**
```json
{
    "valid": true,
    "type": "ticket",
    "message": "Check-in exitoso.",
    "data": {
        "buyer_name": "John Doe",
        "ticket_type": "VIP Front Row",
        "zone": "A",
        "event": "NYFW September 2026",
        "previous_check_ins": 0
    }
}
```

**Response 404:**
```json
{ "message": "Pase no encontrado.", "valid": false }
```

**Response 422 (cancelado):**
```json
{ "message": "Pase cancelado.", "valid": false }
```

---

## Check-in / Kiosko

### POST `/api/v1/kiosk/checkin`
Check-in de asistencia vía kiosko. Maneja entrada/salida para staff/voluntarios, y marcación simple para otros roles.

**Headers:** `Authorization: Bearer {token}`

**Request:**
```json
{ "qr_code": "PASS-ABC123" }
```

**Response 200:**
```json
{
    "success": true,
    "type": "entry",
    "type_label": "Entrada",
    "name": "Maria Garcia",
    "role": "volunteer",
    "area": "Backstage",
    "event": "NYFW September 2026",
    "day": "Day 3 - Shows",
    "checked_at": "14:30"
}
```

**Response 422 (ya marcado):**
```json
{
    "success": false,
    "message": "Maria ya completó su entrada y salida de hoy."
}
```

---

## Chat

Requieren `Authorization: Bearer {token}`. Las conversaciones son entre modelo y diseñador dentro de un show.

### GET `/api/v1/chat/conversations`
Lista conversaciones del usuario autenticado.

**Response 200:**
```json
{
    "data": [
        {
            "id": 1,
            "status": "active",
            "other_participant": {
                "id": 10,
                "name": "Carolina Herrera",
                "profile_picture": null,
                "role": "designer"
            },
            "show": { "id": 1, "name": "Opening Show" },
            "last_message": {
                "body": "See you at the fitting!",
                "type": "text",
                "sender_id": 10,
                "created_at": "2026-09-10T14:30:00.000Z"
            },
            "unread_count": 2,
            "last_message_at": "2026-09-10T14:30:00.000Z"
        }
    ]
}
```

---

### GET `/api/v1/chat/conversations/{conversation}`
Mensajes de una conversación (paginados, 50 por página). Solo accesible por los participantes.

**Query params:** `page` (int, default: 1)

**Response 200:**
```json
{
    "data": [
        {
            "id": 1,
            "body": "Hi, do you have any outfit preferences?",
            "sender_id": 10,
            "sender": {
                "id": 10,
                "first_name": "Carolina",
                "last_name": "Herrera",
                "profile_picture": null
            },
            "created_at": "2026-09-09T09:00:00.000Z",
            "read_at": null
        }
    ],
    "current_page": 1,
    "last_page": 3,
    "per_page": 50,
    "total": 142
}
```

---

### POST `/api/v1/chat/conversations/{conversation}/messages`
Enviar mensaje (texto o imagen).

**Request (texto):**
```json
{
    "body": "Sure, I prefer dark colors.",
    "type": "text"
}
```

**Request (imagen):**
```json
{
    "body": "Here's the reference",
    "type": "image",
    "image_url": "https://example.com/reference.jpg"
}
```

**Response 201:**
```json
{
    "data": {
        "id": 45,
        "conversation_id": 1,
        "sender_id": 5,
        "body": "Sure, I prefer dark colors.",
        "type": "text",
        "image_url": null,
        "created_at": "2026-09-09T10:00:00.000Z"
    }
}
```

---

### POST `/api/v1/chat/conversations/{conversation}/read`
Marca como leídos todos los mensajes de la conversación.

**Response 200:**
```json
{ "read_count": 5 }
```

---

## Banners

### GET `/api/v1/banners`
Banners activos filtrados por el rol del usuario.

**Headers:** `Authorization: Bearer {token}`

**Response 200:**
```json
{
    "data": [
        {
            "id": 1,
            "title": "NYFW September 2026",
            "image_url": "http://localhost:8000/storage/banners/banner1.jpg",
            "link_url": "https://runway7.com/nyfw2026",
            "order": 1
        }
    ]
}
```

---

## Perfil

Requieren `Authorization: Bearer {token}`.

### PUT `/api/v1/profile`
Actualizar perfil del usuario según su rol.

**Request (modelo):**
```json
{
    "first_name": "Sofia",
    "last_name": "Rivera",
    "phone": "+1-305-555-0101",
    "instagram": "sofia.rivera",
    "height": "175",
    "bust": "86",
    "waist": "61",
    "hips": "89",
    "shoe_size": "8",
    "dress_size": "S",
    "body_type": "slim",
    "ethnicity": "hispanic",
    "hair": "brown",
    "location": "Miami, FL"
}
```

**Request (diseñador):**
```json
{
    "first_name": "Carolina",
    "last_name": "Herrera",
    "phone": "+1-212-555-0200",
    "brand_name": "CH",
    "collection_name": "Spring 2027",
    "website": "https://carolinaherrera.com",
    "instagram": "carolinaherrera",
    "bio": "Venezuelan-American fashion designer",
    "country": "US"
}
```

**Request (otros roles):**
```json
{
    "first_name": "John",
    "last_name": "Doe",
    "phone": "+1-555-0000"
}
```

**Response 200:**
```json
{
    "message": "Perfil actualizado.",
    "user": { "..." }
}
```

**Notas:**
- Todos los campos son opcionales (usar `sometimes` para enviar solo lo que cambió).
- El campo `instagram` se sanitiza automáticamente (remueve URLs y @).
- Los valores válidos de `body_type`: slim, athletic, average, curvy, plus_size
- Los valores válidos de `ethnicity`: asian, black, caucasian, hispanic, middle_eastern, mixed, other
- Los valores válidos de `hair`: black, brown, blonde, red, gray, other

---

### POST `/api/v1/profile/photo`
Subir foto del comp card (solo modelos). Máximo 1.5MB.

**Request:** `multipart/form-data`
- `position` (int, required): 1-4 (1=Headshot, 2=Full Body Front, 3=Full Body Side, 4=Creative/Editorial)
- `photo` (file, required): imagen, max 1536KB

**Response 200:**
```json
{
    "message": "Foto actualizada.",
    "position": 1,
    "url": "http://localhost:8000/storage/models/5/compcard/abc123.jpg",
    "comp_card_progress": 75
}
```

---

### POST `/api/v1/profile/picture`
Subir foto de perfil (cualquier rol). Máximo 1.5MB.

**Request:** `multipart/form-data`
- `photo` (file, required): imagen, max 1536KB

**Response 200:**
```json
{
    "message": "Foto de perfil actualizada.",
    "url": "http://localhost:8000/storage/models/5/abc123.jpg"
}
```

---

## Notificaciones (Device Tokens)

Requieren `Authorization: Bearer {token}`.

### POST `/api/v1/device-tokens`
Registrar o actualizar un FCM device token para push notifications.

**Request:**
```json
{
    "token": "fMz3xK9...",
    "platform": "ios"
}
```

**Valores de `platform`:** `ios`, `android`, `web`

**Response 200:**
```json
{ "message": "Token registrado." }
```

---

### DELETE `/api/v1/device-tokens`
Eliminar un device token (al logout o desinstalar).

**Request:**
```json
{ "token": "fMz3xK9..." }
```

**Response 200:**
```json
{ "message": "Token eliminado." }
```

---

## Certificados (Voluntarios)

Requieren `Authorization: Bearer {token}`. Solo para roles `volunteer` y `staff`.

### GET `/api/v1/my-certificates`
Lista eventos donde el voluntario tiene certificado disponible.

**Response 200:**
```json
[
    {
        "event_id": 1,
        "event_name": "NYFW September 2026",
        "eligible": true
    }
]
```

**Reglas de elegibilidad:**
- 1-2 días asignados: asistencia 100% requerida
- 3+ días asignados: mínimo 2 días de asistencia

---

### GET `/api/v1/my-certificates/{event}`
Descargar certificado en PDF.

**Response:** archivo PDF (descarga directa)

**Response 403:**
```json
{ "error": "You have not completed all assigned days for this event." }
```

---

## Registro Público

Endpoints públicos con **rate limit de 10 intentos/minuto** por IP. No requieren autenticación.

### POST `/api/v1/models/register`
Registro público de modelos (desde WordPress/web).

### GET `/api/v1/models/events`
Lista eventos activos para el formulario de registro de modelos.

### POST `/api/v1/volunteers/register`
Registro público de voluntarios.

### GET `/api/v1/volunteers/events`
Lista eventos activos para registro de voluntarios.

### POST `/api/v1/media/register`
Registro público de media/prensa.

### GET `/api/v1/media/events`
Lista eventos activos para registro de media.

### POST `/api/v1/leads/register`
Registro de leads de diseñadores.

### GET `/api/v1/leads/events`
Lista eventos activos para leads.

### POST `/api/v1/check-email`
Verificar disponibilidad de email antes de registrarse.

**Request:**
```json
{ "email": "sofia@test.com", "role": "model" }
```

**Response 200 (disponible):**
```json
{ "available": true }
```

**Response 200 (ya existe con mismo rol):**
```json
{ "available": true, "existing": true }
```

**Response 200 (existe con otro rol):**
```json
{
    "available": false,
    "message": "This email is already registered as designer. Please use a different email or contact us at operations@runway7fashion.com"
}
```

---

## Webhooks

### POST `/api/webhooks/shopify/order-paid`
Webhook de Shopify para órdenes pagadas. Verificado por HMAC signature. Sin autenticación Sanctum.

**Comportamiento:**
- **Modelo rechazado compra merch** → se reactiva con tag `runway_merch`, se asigna slot de casting, se notifica a operaciones.
- **Modelo aceptado compra merch** → su pase se marca como preferencial.

---

## WebSockets (Laravel Reverb)

El sistema de chat usa WebSockets en tiempo real vía Laravel Reverb (protocolo compatible con Pusher).

**Configuración backend (.env):**
```env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=runway7
REVERB_APP_KEY=runway7key
REVERB_APP_SECRET=runway7secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
```

**Configuración Flutter (.env):**
```env
WS_URL=ws://192.168.1.100:8080
WS_KEY=runway7key
```

**Iniciar servidor WebSocket:**
```bash
php artisan reverb:start
```

**Canales privados:**
- `private-conversation.{id}` — Mensajes en tiempo real de una conversación

**Funcionalidades implementadas en Flutter:**
- Reconexión automática con backoff exponencial
- Heartbeat cada 25 segundos
- Gestión de ciclo de vida (pausa/resume)
- Autenticación por token

---

## Códigos de Error

| Código | Descripción |
|--------|-------------|
| 401 | No autenticado — token inválido o expirado |
| 403 | Sin permisos para esta acción |
| 404 | Recurso no encontrado |
| 422 | Error de validación |
| 429 | Too Many Requests (rate limit) |
| 500 | Error interno del servidor |

---

## Notas de Implementación

### Sanctum Token Lifecycle
- Los tokens no tienen expiración automática configurada (duración indefinida)
- Se revocan manualmente con `/auth/logout`
- Para producción: configurar `token_expiration` en `config/sanctum.php`

### Firebase Push Notifications
- Paquete: `kreait/firebase-php` v7
- Service account key: `storage/app/firebase/service-account.json`
- Tabla `device_tokens` almacena FCM tokens por usuario/plataforma
- Servicio `FirebaseNotificationService` soporta envío a usuario individual o por rol

### Roles de Usuario
admin, model, designer, media, volunteer, staff, assistant, accounting, operation, tickets_manager, marketing, public_relations, sales, attendee, vip, influencer, press, sponsor, complementary
