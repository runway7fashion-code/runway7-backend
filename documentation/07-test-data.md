# Datos de Prueba (Seeders)

Para cargar los datos de prueba:
```bash
php artisan migrate:fresh --seed
```

---

## Seeders y su Orden

```php
// DatabaseSeeder.php
$this->call([
    DesignerSettingsSeeder::class,  // 1. Categorías y paquetes de diseñadores
    UserSeeder::class,              // 2. Usuarios (equipo, modelos, diseñadores, etc.)
    EventSeeder::class,             // 3. Evento NYFW + días + shows
    AccountingSeeder::class,        // 4. Planes de pago, cuotas, registros
    SupportCaseSeeder::class,       // 5. Casos de soporte (bitácora)
    ConversationSeeder::class,      // 6. Conversaciones y mensajes de chat
    BannerSeeder::class,            // 7. Banners del app
]);
```

---

## Cuentas de Usuario

### Equipo Interno
| Email | Password | Rol | Acceso |
|-------|----------|-----|--------|
| `admin@runway7.com` | `password123` | admin | Todo el panel |
| `accounting@runway7.com` | `password123` | accounting | Dashboard + Pagos contabilidad |
| `operation@runway7.com` | `password123` | operation | Eventos, Modelos, Diseñadores, Chats |
| `sales@runway7.com` | `password123` | sales | Diseñadores |

### Modelos
| Email / Login Code | Password | Nombre | Comp Card |
|-------------------|----------|--------|-----------|
| `sofia.rivera@models.com` / `MOD001` | `runway7` | Sofia Rivera | Completo (4 fotos) |
| `isabella.chen@models.com` / `MOD002` | `runway7` | Isabella Chen | Incompleto (2 fotos) |

### Diseñadores
| Email | Password | Marca | Categoría |
|-------|----------|-------|-----------|
| `ale@nocturnadesign.com` | `runway7` | Nocturna Design | Streetwear |
| `val@lunawhite.com` | `runway7` | Luna White | Evening Wear & Gowns |

### Otros
| Email | Password | Rol |
|-------|----------|-----|
| `james.walker@fashionweekly.com` | `password123` | press (Fashion Weekly) |
| `rachel@luxebrand.com` | `password123` | sponsor (Luxe Brand Co.) |
| `emily.j@gmail.com` | `password123` | attendee |
| `m.torres@gmail.com` | `password123` | attendee |
| `victoria.r@vip.runway7.com` | `password123` | vip |

---

## Evento de Prueba

**Nombre:** NYFW September 2026
**Slug:** `nyfw-september-2026`
**Ciudad:** New York City
**Venue:** Lincoln Center for the Performing Arts
**Fechas:** 5 - 12 septiembre 2026
**Timezone:** America/New_York

**Días del evento:**
| Día | Tipo | Fecha |
|-----|------|-------|
| Día de Casting | casting | 5 Sep |
| Show Day 1 | show_day | 7 Sep |
| Show Day 2 | show_day | 8 Sep |
| Show Day 3 | show_day | 9 Sep |

---

## Categorías de Diseñadores

Creadas por `DesignerSettingsSeeder`:
- Streetwear (`streetwear`)
- Evening Wear & Gowns (`eveningwear-gowns`)
- Bridal (`bridal`)
- Avant-Garde (`avant-garde`)
- Ready-to-Wear (`ready-to-wear`)
- Couture (`couture`)
- Swimwear (`swimwear`)
- Menswear (`menswear`)

---

## Paquetes de Diseñadores

Creados por `DesignerSettingsSeeder`:
| Nombre | Precio |
|--------|--------|
| Emerging Designer | $2,500 |
| Standard | $4,500 |
| Premium | $7,500 |
| Elite | $12,000 |
| Headline | $20,000 |

---

## Datos de Contabilidad (AccountingSeeder)

### Plan de Alejandro Vasquez (Nocturna Design)
- **Evento:** NYFW September 2026
- **Paquete:** Premium ($7,500)
- **Total:** $7,500
- **Downpayment:** $2,500 — **Pagado** ✓
- **Cuotas:** 4 cuotas de $1,250 cada una

| Cuota | Vencimiento | Estado | Pagado |
|-------|-------------|--------|--------|
| 1 | Ene 2026 | Pagado ✓ | $1,250 |
| 2 | Feb 2026 | Parcial | $750 de $1,250 |
| 3 | Mar 2026 | Pendiente | — |
| 4 | May 2026 | Pendiente | — |

**Registros de pago:**
- $2,500 (downpayment) - Zelle - Referencia: ZL20260115
- $1,250 (cuota 1) - Wire transfer - Referencia: WT20260201
- $750 (cuota 2 parcial) - Zelle - Referencia: ZL20260215

### Plan de Valentina Morales (Luna White)
- **Evento:** NYFW September 2026
- **Paquete:** Standard ($4,500)
- **Total:** $4,500
- **Downpayment:** $1,500 — **Pendiente** ⏳
- **Cuotas:** 3 cuotas de $1,000 cada una

| Cuota | Vencimiento | Estado | Pagado |
|-------|-------------|--------|--------|
| 1 | Ene 2026 | Vencida | — |
| 2 | Feb 2026 | Vencida | — |
| 3 | Mar 2026 | Pendiente | — |

---

## Casos de Soporte (SupportCaseSeeder)

### CASO-0001
- **Diseñador:** Alejandro Vasquez (Nocturna Design)
- **Canal:** Email
- **Tipo:** Pagos
- **Estado:** En Proceso
- **Contacto:** `alejandro.asistente@gmail.com` (guardado con label "Asistente")
- **Fecha reclamo:** 15 Feb 2026

**Mensajes:**
1. (Designer, 15 Feb) — Reclamo de pago de $500 por Zelle sin reflejar
2. (Equipo, 16 Feb) — Confirmación de recepción, plazo 24h
3. (Designer, 16 Feb) — "Gracias, quedo atento"

### CASO-0002
- **Diseñador:** Valentina Morales (Luna White)
- **Canal:** WhatsApp
- **Tipo:** Queja
- **Estado:** Abierto
- **Contacto:** `valentina@lunawhite.com` (guardado con label "Personal")
- **Fecha reclamo:** 20 Feb 2026

**Mensajes:**
1. (Designer, 20 Feb) — Solicitud de cambio de fecha de cuota del 1 al 15 de marzo

---

## Notas sobre Seeders

- Los seeders usan `firstOrCreate` donde es posible para ser idempotentes
- Si un seeder depende de datos de otro (ej: SupportCaseSeeder necesita usuarios y evento), retorna silenciosamente si los datos requeridos no existen
- Las contraseñas están hasheadas con bcrypt al momento de la siembra
- Los archivos de fotos del comp card son rutas placeholder (`models/placeholder-*.jpg`) — las imágenes reales deben subirse via admin
