# API REST — Documentación

**Base URL:** `http://localhost:8000/api/v1`
**Autenticación:** Laravel Sanctum (Bearer Token)
**Content-Type:** `application/json`

---

## Autenticación

### POST `/api/v1/auth/login`
Login con email y password.

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
    "token": "1|abcdef123456...",
    "user": {
        "id": 1,
        "first_name": "Admin",
        "last_name": "Runway7",
        "email": "admin@runway7.com",
        "role": "admin",
        "status": "active"
    }
}
```

**Response 422:**
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "email": ["These credentials do not match our records."]
    }
}
```

---

### GET `/api/v1/me`
Retorna el usuario autenticado.

**Headers:** `Authorization: Bearer {token}`

**Response 200:**
```json
{
    "id": 1,
    "first_name": "Sofia",
    "last_name": "Rivera",
    "email": "sofia.rivera@models.com",
    "phone": "+1-305-555-0101",
    "role": "model",
    "status": "active",
    "profile_picture": null,
    "model_profile": {
        "height": 175.00,
        "bust": 86.00,
        "waist": 61.00,
        "hips": 89.00,
        "shoe_size": "8",
        "dress_size": "S",
        "instagram": "@sofia.rivera",
        "compcard_completed": true,
        ...
    }
}
```

---

### POST `/api/v1/auth/logout`
Revoca el token actual.

**Headers:** `Authorization: Bearer {token}`

**Response 200:**
```json
{
    "message": "Sesión cerrada exitosamente."
}
```

---

## Chat

Todas las rutas de chat requieren `Authorization: Bearer {token}`.

### GET `/api/v1/chat/conversations`
Lista las conversaciones del usuario autenticado.

**Response 200:**
```json
[
    {
        "id": 1,
        "type": "direct",
        "name": null,
        "last_message": {
            "body": "Hola, ¿cómo estás?",
            "created_at": "2026-02-25T10:30:00Z"
        },
        "unread_count": 2,
        "participants": [
            { "id": 2, "full_name": "Sofia Rivera", "role": "model" }
        ]
    }
]
```

---

### GET `/api/v1/chat/conversations/{conversation}`
Retorna los mensajes de una conversación con paginación.

**Query params:**
- `page` (int, default: 1)
- `per_page` (int, default: 50)

**Response 200:**
```json
{
    "data": [
        {
            "id": 1,
            "body": "Hola, tengo una pregunta sobre el desfile.",
            "user_id": 5,
            "user": {
                "id": 5,
                "full_name": "Alejandro Vasquez",
                "role": "designer"
            },
            "created_at": "2026-02-25T09:00:00Z",
            "read_at": null
        }
    ],
    "meta": { "current_page": 1, "last_page": 3, "total": 142 }
}
```

---

### POST `/api/v1/chat/conversations/{conversation}/messages`
Envía un mensaje a una conversación.

**Request:**
```json
{
    "body": "El mensaje de texto aquí."
}
```

**Response 201:**
```json
{
    "id": 45,
    "body": "El mensaje de texto aquí.",
    "user_id": 5,
    "created_at": "2026-02-27T14:00:00Z"
}
```

---

### POST `/api/v1/chat/conversations/{conversation}/read`
Marca como leídos todos los mensajes de la conversación.

**Response 200:**
```json
{
    "message": "Mensajes marcados como leídos."
}
```

---

## Banners

### GET `/api/v1/banners`
Retorna los banners activos, ordenados por `order`.

**Headers:** `Authorization: Bearer {token}`

**Response 200:**
```json
[
    {
        "id": 1,
        "title": "NYFW September 2026",
        "subtitle": "El evento más importante de la temporada",
        "image_url": "http://localhost:8000/storage/banners/banner1.jpg",
        "link_url": "https://runway7.com/nyfw2026",
        "is_active": true,
        "order": 1,
        "target_role": null
    }
]
```

---

## Códigos de Error Comunes

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

### WebSockets (Laravel Reverb)
El sistema de chat usa WebSockets en tiempo real vía Laravel Reverb.

**Configuración:**
```env
BROADCAST_DRIVER=reverb
REVERB_APP_ID=runway7-app
REVERB_APP_KEY=runway7-key
REVERB_APP_SECRET=runway7-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
```

**Canales privados:**
- `private-conversation.{id}` — Mensajes en tiempo real de una conversación

---

## Endpoints Pendientes de Implementar

Los siguientes endpoints están planeados pero aún no existen:

```
# Eventos
GET  /api/v1/events                    # Lista de eventos del usuario
GET  /api/v1/events/{event}            # Detalle del evento

# Modelo
GET  /api/v1/model/profile             # Mi perfil como modelo
GET  /api/v1/model/shows               # Mis shows asignados
POST /api/v1/model/show/{show}/confirm # Confirmar participación en show

# Diseñador
GET  /api/v1/designer/profile          # Mi perfil como diseñador
GET  /api/v1/designer/payment-plan     # Mi plan de pagos
GET  /api/v1/designer/installments     # Mis cuotas

# Check-in (Kiosko/Flutter)
POST /api/v1/checkin/scan              # Escanear QR
GET  /api/v1/checkin/stats/{event}     # Stats de check-in en tiempo real

# Tickets
GET  /api/v1/tickets/{qr_code}         # Validar ticket por QR
POST /api/v1/tickets/{qr_code}/checkin # Hacer check-in del ticket
```
