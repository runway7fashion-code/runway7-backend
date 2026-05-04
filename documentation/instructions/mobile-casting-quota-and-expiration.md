# Casting: Quota de Looks + Expiración de Invitaciones

> **Para**: Mobile dev
> **Backend deployed**: 2026-05-04
> **Status**: ✅ Activo en producción (cron corriendo cada minuto)

## Contexto del cambio

Producción confirmó que **un designer tiene 1 solo show por evento**. Aprovechando eso:

1. **Looks como cupo**: si el designer tiene 15 looks en su paquete, solo puede tener 15 invitaciones activas (`requested` + `confirmed`) a la vez. Si invita una 16ª → backend bloquea con 422.
2. **Expiración configurable por evento**: cada evento tiene un campo `casting_invitation_expiration_hours`. Cuando un designer invita a una modelo, el backend setea `expires_at = now() + N horas`. Si la modelo no responde antes → status pasa a `expired` automáticamente y el cupo se libera.
3. **Notificaciones de expiración**: la modelo recibe pushes 1h, 30min y 5min antes. Al expirar, modelo y designer reciben notificación.
4. **Re-invitación**: si la invitación previa fue `expired`, el designer puede volver a invitar a la misma modelo. Si fue `rejected`, no.
5. **Chat groups por show**: ya no se necesita `show_id` para crear grupos — backend lo auto-resuelve.

---

## 1. Endpoint `POST /api/v1/shows/{show}/request-model`

Sin cambios en el body:

```json
{ "model_id": 123, "message": "optional" }
```

### Nuevos errores `422`

El backend valida la quota de looks. El mobile debería mostrar el `message` tal cual.

| Mensaje | Cuándo |
|---|---|
| `You reached your invitation limit (15). Resolve pending invites or wait for them to expire before sending more.` | Designer ya tiene N invitaciones activas (requested + confirmed) y N >= designer.looks |
| `This model already rejected this request. You cannot send it again.` | Existe pivot con status `rejected` |
| `A request for this model already exists for this show.` | Existe pivot con status `requested` o `confirmed` |

### Quota — qué cuenta

| Status | Cuenta contra el cupo? |
|---|---|
| `requested` | ✅ Sí |
| `confirmed` | ✅ Sí |
| `rejected` | ❌ No (libera slot) |
| `expired` | ❌ No (libera slot) |

**Re-invitar a misma modelo**:

| Status previo | ¿Se puede re-invitar? |
|---|---|
| `rejected` | ❌ Bloqueado (rechazó explícitamente) |
| `expired` | ✅ Permitido (no respondió a tiempo, no rechazó) |

---

## 2. Nuevo status `expired`

`show_model.status` ahora puede ser:

| Status | Significado |
|---|---|
| `requested` | Pendiente, esperando respuesta de la modelo |
| `confirmed` | Modelo aceptó |
| `rejected` | Modelo rechazó (estado terminal) |
| **`expired`** | **NUEVO**. Modelo no respondió a tiempo, slot liberado |
| `reserved` | Legacy (sin cambios) |

### UX sugerida en mobile

- Badge con color distinto del `rejected` (sugerencia: gris en `expired`, rojo en `rejected`)
- Texto: "Expired — model didn't respond in time"
- Permitir botón "Invite again" en items `expired`

---

## 3. Nuevo campo `expires_at`

Timestamp ISO o `null`. Está en estos endpoints:

### `GET /api/v1/events/{event}/my-requests` (designer)

```json
{
  "data": [
    {
      "request_id": 42,
      "status": "requested",
      "requested_at": "2026-05-04T15:00:00Z",
      "expires_at": "2026-05-04T17:00:00Z",   // ← NUEVO
      "responded_at": null,
      "confirmed_at": null,
      "rejection_reason": null,
      "show": { "id": 88, "name": "...", "scheduled_time": "13:00", "date": "...", "day_label": "..." },
      "model": { ... }
    }
  ],
  "counts": {
    "requested": 3,
    "confirmed": 8,
    "rejected": 1,
    "expired": 2                              // ← NUEVO
  }
}
```

### `GET /api/v1/my-shows` (modelo)

```json
{
  "shows": [
    {
      "id": 88,
      "name": "Day 3 NYFW – 1:00 PM",
      "scheduled_time": "13:00",
      "status": "confirmed",
      "event": { "id": 1, "name": "..." },
      "day": { "date": "2026-09-08", "label": "..." },
      "assignment": {
        "status": "requested",
        "walk_order": null,
        "confirmed_at": null,
        "message": "...",
        "requested_at": "2026-05-04T15:00:00Z",
        "expires_at": "2026-05-04T17:00:00Z",   // ← NUEVO
        "designer": { ... }
      }
    }
  ]
}
```

### Cuando `expires_at` es `null`

Significa que el evento no tiene expiración configurada (`casting_invitation_expiration_hours = NULL`). En ese caso la invitación nunca expira automáticamente. El mobile debería simplemente no mostrar countdown.

---

## 4. Looks por show en `GET /api/v1/events/{event}` (detalle de evento)

Cada show del array `event.days[].shows[]` ahora incluye `looks`:

```json
"days": [
  {
    "id": 5,
    "date": "2026-09-08",
    "label": "Day 3",
    "shows": [
      {
        "id": 88,
        "name": "Day 3 NYFW – 1:00 PM",
        "scheduled_time": "13:00",
        "status": "confirmed",
        "model_slots": 30,
        "looks": 15,                  // ← NUEVO: cupo del designer en este show
        "designers": [...]
      }
    ]
  }
]
```

**Reglas de `looks`**:

| Caso | Valor |
|---|---|
| User es designer asignado a este show | `event_designer.looks` (su total del evento) |
| User es designer pero NO asignado a este show | `null` |
| User no es designer (modelo, staff, etc.) | `null` |

> **Nota técnica**: hoy `show_designer` no tiene columna `looks` propia. La distribución de looks por show no existe — cada show toma el total del evento (porque hay 1 show por designer).

---

## 5. Push notifications nuevas

El cron `casting:process-invitations` corre cada minuto en producción y dispara estos pushes.

### Para la modelo

| `data.type` | Cuándo | Mensaje aproximado |
|---|---|---|
| `invitation_expires_1h` | Faltan ≤ 1h para expirar | "Invitation expires in 1 hour" |
| `invitation_expires_30m` | Faltan ≤ 30m | "Invitation expires in 30 minutes" |
| `invitation_expires_5m` | Faltan ≤ 5m | "Invitation expires in 5 minutes" |
| `invitation_expired` | Ya expiró | "Your invitation from {Designer} for {Show} expired without a response." |

### Para el designer

| `data.type` | Cuándo | Mensaje aproximado |
|---|---|---|
| `invitation_expired` | Su invitación expiró sin respuesta | "Your invitation to {Model} expired. Your slot is free again." |

### Payload completo del push

```json
{
  "title": "Invitation expires in 30 minutes",
  "body": "Respond to NICO PAZ's invitation for Day 3 NYFW – 1:00 PM before it expires.",
  "data": {
    "screen": "shows",
    "show_id": "88",
    "type": "invitation_expires_30m"
  }
}
```

**Deep link**: todos abren la pantalla `shows`. El mobile puede usar `type` para distinguir notificaciones urgentes (5m) y dar feedback visual diferente.

### Idempotencia

Cada ventana (1h, 30m, 5m) se notifica una sola vez por invitación. El backend usa flags `notified_1h_at`, `notified_30m_at`, `notified_5m_at` en `show_model` para evitar duplicados. El mobile no tiene que hacer nada extra.

---

## 6. Crear grupos de chat: `show_id` ya no es required

Endpoint: `POST /api/v1/chat/groups`

### Body antes

```json
{
  "name": "Group name",
  "member_ids": [1, 2, 3],
  "show_id": 88        // requerido para designer
}
```

### Body ahora

```json
{
  "name": "Group name",
  "member_ids": [1, 2, 3],
  "event_id": 1,         // OPCIONAL, recomendado cuando designer está en múltiples eventos
  "show_id": 88          // OPCIONAL ahora (backwards-compat: si lo mandas, lo respetamos)
}
```

### Comportamiento del backend

| Caso | Resultado |
|---|---|
| Designer con 1 solo show asignado, sin `show_id` ni `event_id` | Auto-resuelve `show_id` y crea el grupo |
| Designer con múltiples shows asignados, sin `event_id` | **422**: "You are assigned to multiple shows; specify show_id or event_id." |
| Designer manda `event_id`, tiene 1 show en ese evento | Auto-resuelve dentro del evento, OK |
| Designer manda `show_id` directo | Funciona como antes |
| User es operation/admin | `show_id` siempre fue opcional, sin cambios |

### Recomendación al mobile

Quitar el step de "Select show" del flujo de creación de grupo del designer. En su lugar:
- Si el designer está en 1 solo evento activo → no mandar nada extra (auto-resuelve)
- Si está en múltiples eventos → mostrar selector de evento → mandar solo `event_id`

---

## 7. Configuración del admin (para que sepas qué pasa del otro lado)

En la página de Edit Event hay un campo nuevo:

- **"Casting invitation expiration (hours)"** debajo de "Hair & Makeup Address"
- Tipo: número entero entre 1 y 168 (1h a 1 semana)
- Vacío = sin expiración (las invitaciones nunca caducan automáticamente)
- Default sugerido: **2 horas**

Eventos existentes (NYFW Sept 2026, Feb 2027) están en `null` hasta que el admin los configure manualmente.

---

## 8. Checklist sugerido para el mobile

### Pantalla "Available Models" (designer)
- [ ] Mostrar contador "X / 15 invitations used" (sumando `requested` + `confirmed`)
- [ ] Deshabilitar botón "Invite" si llegó al límite
- [ ] Manejar el error 422 con el `message` del backend (toast/alert)

### Pantalla "My Requests" (designer)
- [ ] Agregar status `expired` con visual distinto (gris)
- [ ] Para items `requested`: countdown con `expires_at`
- [ ] Botón "Invite again" disponible en items `expired`
- [ ] Mostrar nuevo conteo `expired` en el resumen

### Pantalla "My Shows" (modelo)
- [ ] Para invitaciones `requested`: countdown grande con `expires_at`
- [ ] Visual urgente cuando quedan < 30min, super urgente < 5min
- [ ] Push `invitation_expires_5m` debe abrir directo a la invitación pendiente

### Creador de grupo de chat (designer)
- [ ] Quitar el step de "Select show"
- [ ] Si está en múltiples eventos, mostrar selector de evento → mandar `event_id`
- [ ] Si está en 1 solo evento → no mandar nada (backend auto-resuelve)

### Manejo de pushes
- [ ] Registrar handlers para `invitation_expires_1h`, `_30m`, `_5m`, `invitation_expired`
- [ ] Distinguir visualmente notificaciones urgentes (5m) vs. tempranas (1h)
- [ ] Refrescar la lista de invitaciones cuando llega un push

---

## Resumen ejecutivo

> Si el designer tiene 15 looks → solo puede tener 15 invitaciones activas. Si la modelo no responde dentro del tiempo configurado del evento (`casting_invitation_expiration_hours`), la invitación se cancela automáticamente y libera el slot. La modelo recibe pushes a 1h/30m/5m de la expiración. El designer puede re-invitar a la misma modelo si expiró (no si rechazó). En chat groups, ya no se necesita `show_id` — backend lo resuelve solo.
