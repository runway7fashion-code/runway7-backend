# Roadmap — Estado del Proyecto

**Fecha de actualización:** 27 Febrero 2026

---

## Resumen de Progreso

| Área | Estado | % Completado |
|------|--------|-------------|
| Infraestructura / DB | Completo | 100% |
| Panel Admin — Core | Completo | 100% |
| Módulo Modelos | Completo | 95% |
| Módulo Diseñadores | Completo | 95% |
| Módulo Eventos | Completo | 90% |
| Módulo Contabilidad | Completo | 100% |
| Módulo Tickets (público) | Tablas creadas, sin UI | 20% |
| Módulo Tickets (interno/passes) | No iniciado | 0% |
| API REST | Parcial | 30% |
| App Flutter | No iniciado (externo) | — |
| Integración WooCommerce | No iniciado | 0% |

**Estimado global:** ~60% del proyecto total

---

## LO QUE ESTÁ COMPLETADO

### Infraestructura
- [x] Laravel 12 + PostgreSQL 16 configurado
- [x] Inertia.js v2 + Vue 3 + Tailwind CSS 4
- [x] Laravel Reverb (WebSockets) configurado
- [x] Laravel Sanctum (API auth) configurado
- [x] 47 migraciones ejecutadas
- [x] Stripe SDK instalado
- [x] Sistema de roles y permisos personalizado
- [x] Middleware de secciones (`CheckSectionAccess`)
- [x] Datos compartidos globales con Inertia

### Panel Admin
- [x] Layout principal con sidebar colapsable (w-64 ↔ w-16)
- [x] Scrollbar dorado personalizado
- [x] Sidebar dinámico por rol
- [x] Flash messages (éxito/error)
- [x] Login con rate limiting
- [x] Dashboard adaptado por rol

### Módulo Eventos
- [x] CRUD completo de eventos
- [x] Duplicar evento
- [x] Días del evento (show_day, casting, setup, other)
- [x] Shows dentro de días
- [x] Asignación de diseñadores a shows
- [x] SoftDelete en eventos

### Módulo Modelos
- [x] CRUD completo
- [x] Comp card (4 posiciones de foto)
- [x] Foto de perfil
- [x] Medidas y datos del perfil
- [x] Asignación a eventos
- [x] Código de login para kiosko (`login_code`)

### Módulo Diseñadores
- [x] CRUD completo
- [x] Perfil de marca completo
- [x] Asignación a eventos (con paquete, looks, precio)
- [x] Asistentes del diseñador
- [x] Materiales de producción
- [x] Displays (video/audio)
- [x] Shows del diseñador

### Módulo Contabilidad (COMPLETO)
- [x] **Dashboard** con métricas financieras y gráficas
- [x] **Lista de Diseñadores** con info de plan + export CSV
- [x] **Deudas** — cuotas vencidas con filtros + export CSV (solo diseñadores activos)
- [x] **Planes de pago** — crear/editar planes con downpayment + cuotas
- [x] **Cuotas** con soporte de pagos parciales (`paid_amount`, status `partial`)
- [x] **Waterfall allocation** — distribución automática de pagos en cuotas por orden de vencimiento
- [x] **Registro de Pagos** — CRUD de pagos recibidos con asignación automática
- [x] **Historial/Bitácora (Support Cases)** — sistema mini-CRM completo:
  - [x] Lista con 5 filtros
  - [x] Crear caso con canal dinámico (email/whatsapp/sms/dm cambia el campo de contacto)
  - [x] Timeline de mensajes
  - [x] Adjuntos de archivos (drag & drop)
  - [x] Correos alternativos del diseñador (designer_contact_emails)
  - [x] Gestión de estado del caso
- [x] **Reporte de Liquidez** — cuotas agrupadas por fecha:
  - [x] Filtros: rango de fechas, evento, estado (Todos/Vencido/Pendiente)
  - [x] Tarjetas de resumen
  - [x] Modal de detalle con pagos parciales
  - [x] Export CSV

### Módulo Chats
- [x] Lista de conversaciones
- [x] Vista de mensajes
- [x] Tiempo real con Laravel Reverb

### Módulo Banners
- [x] CRUD completo
- [x] Upload de imagen
- [x] Reordering por drag & drop

### API REST
- [x] Auth: login con email/password
- [x] Auth: login con código kiosko
- [x] Auth: logout
- [x] Auth: me (perfil del usuario)
- [x] Chat: conversaciones, mensajes, enviar, marcar leído
- [x] Banners: listar activos

---

## PENDIENTE POR IMPLEMENTAR

### Módulo de Tickets — PRIORIDAD SIGUIENTE

#### Flujo 1: Tickets Internos (Passes)
Passes para participantes del evento generados automáticamente por el admin.

- [ ] Migración: tabla `event_passes` (vinculada a users, event_days, con QR)
- [ ] Modelo `EventPass` con QR único
- [ ] Generación automática de pass al asignar modelo a evento
- [ ] Generación automática de pass al asignar diseñador a evento
- [ ] Generación para staff, prensa, sponsors
- [ ] Vista admin: gestión de passes (lista, re-emitir, revocar)
- [ ] QR generado en Laravel (`simplesoftwareio/simple-qrcode`)
- [ ] Vista del pass (HTML/PDF imprimible)
- [ ] Envío del pass por email

#### Flujo 2: Tickets Públicos (WooCommerce)
Tickets vendidos al público general via WooCommerce/FooEvents.

- [ ] Webhook endpoint: `POST /api/v1/webhooks/woocommerce`
- [ ] Recibir orden completada → crear `Ticket` con QR propio
- [ ] Manejo de errores y reintentos del webhook
- [ ] Admin: vista de tickets vendidos con stats (vendidos/capacidad/disponibles)
- [ ] Admin: gestión de `ticket_types` (crear, editar tipos para venta pública)

#### Check-in System
- [ ] Endpoint: `POST /api/v1/checkin/scan` — validar QR y registrar check-in
- [ ] Endpoint: `GET /api/v1/checkin/stats/{event}` — stats en tiempo real
- [ ] Soporte para scanner físico (via API) o app Flutter
- [ ] Check-in duplicado: mostrar advertencia sin bloquear
- [ ] Dashboard check-in en tiempo real (WebSockets/Reverb)
- [ ] App kiosko web: scanner QR para el día del evento

---

### Módulo Usuarios
- [ ] Crear/editar usuarios de todos los roles desde el panel
- [ ] Cambio de contraseña
- [ ] Activar/desactivar usuarios (status toggle)
- [ ] Filtros por rol y status

### Módulo Settings (expandir)
- [ ] Ajustes generales del evento (logo, colores, info de contacto)
- [ ] Gestión de zonas del venue
- [ ] Plantillas de email

### API REST (expandir)
- [ ] Endpoints de evento para app Flutter
- [ ] Perfil y shows del modelo
- [ ] Perfil y plan de pagos del diseñador
- [ ] Notificaciones push (Firebase/APNs via `device_tokens`)
- [ ] Endpoints de check-in

### Módulos Futuros
- [ ] **Marketing Dashboard** — métricas de campañas, banners, engagement
- [ ] **Ventas Dashboard** — pipeline de diseñadores, conversión de paquetes
- [ ] **PR Dashboard** — gestión de medios, credenciales de prensa
- [ ] **Casting Module** — scheduling de casting, confirmaciones de modelos
- [ ] **Marketplace** — productos de diseñadores + merchandise
- [ ] **Promo Codes** — descuentos con `promo_codes` y `promo_code_usage`
- [ ] **Orders** — gestión de órdenes del marketplace

---

## Decisiones Técnicas Clave

### Por qué tabla `users` unificada
Todos los tipos de usuario comparten la misma tabla. Esto simplifica:
- Autenticación (Sanctum funciona con un solo guard)
- Chat (conversaciones entre cualquier tipo de usuario)
- Historial de actividad centralizado

Los datos específicos por tipo van en tablas de perfil separadas (`model_profiles`, `designer_profiles`, `press_profiles`, `sponsor_profiles`).

### Por qué Inertia.js y no API + SPA separada
El panel admin es exclusivamente interno. Inertia ofrece:
- Una sola aplicación Laravel (sin gestionar dos servidores)
- Autenticación de sesión estándar (más simple y segura que tokens para el admin)
- Server-side rendering implícito (mejor SEO y carga inicial)
- Validación de formularios automática (`useForm`)

La API REST existe separadamente solo para los clientes externos (Flutter, kioscos).

### Por qué campo `users.role` y no Spatie Permissions
Aunque `spatie/laravel-permission` está instalado, se optó por un sistema de permisos propio vía `config/role_permissions.php` porque:
- Los roles son fijos y bien definidos (no dinámicos)
- La configuración es más transparente y fácil de auditar
- El middleware `CheckSectionAccess` es suficientemente flexible
- Evita complejidad adicional con tablas de pivot de roles/permisos

Spatie está disponible si en el futuro se necesitan permisos más granulares.

### Por qué paid_amount en installments (vs tabla separada)
El campo `paid_amount` en `designer_installments` permite:
- Pagos parciales sin tabla adicional de transacciones
- Consultas más simples para calcular progreso
- La lógica waterfall más eficiente

Los detalles de cada pago individual van en `payment_records` (registro histórico inmutable).
