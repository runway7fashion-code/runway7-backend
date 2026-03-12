# Roles y Permisos

## Sistema de Roles

Todos los usuarios comparten la tabla `users` con un campo `role`. Los roles se dividen en tres categorías:

### Categoría: Internal (Equipo Interno)
| Rol | Label | Acceso al panel admin |
|-----|-------|----------------------|
| `admin` | Administrador | Acceso total a todo |
| `accounting` | Contabilidad | Dashboard y pagos contabilidad |
| `operation` | Operaciones | Eventos, modelos, diseñadores, chats |
| `tickets_manager` | Tickets | Dashboard y gestión de tickets |
| `marketing` | Marketing | Banners, dashboard marketing |
| `public_relations` | Relaciones Públicas | Dashboard PR |
| `sales` | Ventas | Diseñadores, dashboard ventas |

### Categoría: Participant (Participantes del Evento)
| Rol | Label | Notas |
|-----|-------|-------|
| `designer` | Diseñador | Tiene designer_profile |
| `model` | Modelo | Tiene model_profile |
| `media` | Media | |
| `volunteer` | Voluntario | |
| `staff` | Staff | |

### Categoría: Attendee (Asistentes)
| Rol | Label | Notas |
|-----|-------|-------|
| `attendee` | Asistente | Público general |
| `vip` | VIP | |
| `influencer` | Influencer | |
| `press` | Prensa | Tiene press_profile |
| `sponsor` | Sponsor | Tiene sponsor_profile |
| `complementary` | Complementario | Pase cortesía |

---

## Secciones del Panel Admin

Cada sección del panel requiere que el usuario tenga ese string en su array de secciones permitidas. El rol `admin` tiene acceso implícito a todo.

| Sección | Descripción |
|---------|-------------|
| `dashboard` | Dashboard principal |
| `events` | Gestión de eventos y días |
| `models` | Gestión de modelos |
| `designers` | Gestión de diseñadores |
| `chats` | Chat interno |
| `banners` | Gestión de banners |
| `users` | Gestión de usuarios |
| `settings` | Ajustes del sistema (categorías, paquetes) |
| `accounting_dashboard` | Dashboard de contabilidad |
| `accounting_payments` | Módulo completo de pagos y contabilidad |
| `tickets_dashboard` | Dashboard de tickets (pendiente) |
| `tickets_management` | Gestión de tickets (pendiente) |
| `marketing_dashboard` | Dashboard marketing (pendiente) |
| `pr_dashboard` | Dashboard PR (pendiente) |
| `sales_dashboard` | Dashboard ventas (pendiente) |

---

## Configuración por Rol (`config/role_permissions.php`)

```php
'admin' => [
    'sections' => ['dashboard', 'events', 'models', 'designers', 'chats',
                   'banners', 'users', 'settings', 'accounting',
                   'accounting_dashboard', 'accounting_payments'],
    'label' => 'Administrador',
],
'accounting' => [
    'sections' => ['accounting_dashboard', 'accounting_payments'],
    'label' => 'Contabilidad',
],
'operation' => [
    'sections' => ['events', 'models', 'designers', 'chats'],
    'label' => 'Operaciones',
],
'tickets_manager' => [
    'sections' => ['tickets_dashboard', 'tickets_management'],
    'label' => 'Tickets',
],
'marketing' => [
    'sections' => ['banners', 'marketing_dashboard'],
    'label' => 'Marketing',
],
'public_relations' => [
    'sections' => ['pr_dashboard'],
    'label' => 'Relaciones Públicas',
],
'sales' => [
    'sections' => ['sales_dashboard', 'designers'],
    'label' => 'Ventas',
],
```

---

## Middleware

### `EnsureUserIsInternal`
Alias: `internal`

Verifica que el usuario tenga un rol interno (no es modelo, diseñador, asistente, etc.). Aplicado a todas las rutas del panel admin.

### `CheckSectionAccess`
Alias: `section:{nombre}`

Verifica que el usuario tenga la sección en su configuración de permisos. El rol `admin` siempre pasa. Los demás roles consultan `config/role_permissions.{role}.sections`.

**Uso en rutas:**
```php
Route::middleware('section:accounting_payments')->group(function () {
    // Solo accounting y admin pueden acceder
});
```

---

## Datos Compartidos con Inertia (HandleInertiaRequests)

En cada request, el middleware comparte:

```js
{
    auth: {
        user: {
            // ...todos los campos del usuario
            allowed_sections: ['accounting_dashboard', 'accounting_payments'],
            role_label: 'Contabilidad'
        }
    },
    flash: {
        success: '...',  // Mensaje de éxito de sesión
        error: '...'     // Mensaje de error de sesión
    }
}
```

El frontend (`AdminLayout.vue`) usa `allowed_sections` para filtrar dinámicamente los ítems del sidebar mostrados a cada usuario.

---

## Sidebar Dinámico

El componente `AdminLayout.vue` filtra los ítems de navegación según el rol:

```js
function hasSection(section) {
    return isAdmin.value || allowedSections.value.includes(section);
}
```

**Ítems principales** (requieren su sección):
- Dashboard → `section:dashboard`
- Eventos → `section:events`
- Modelos → `section:models`
- Diseñadores → `section:designers`
- Chats → `section:chats`
- Banners → `section:banners`
- Usuarios → `section:users`

**Sub-sección Contabilidad** (requiere `accounting_dashboard` o `accounting_payments`):
- Dashboard contabilidad → `accounting_dashboard`
- Diseñadores, Deudas, Historial, Liquidez, Pagos, Registro → `accounting_payments`

**Ajustes** (solo admin):
- Diseñadores (categorías, paquetes) → `section:settings`

---

## Autenticación

### Panel Admin (Web)
- Sesión Laravel estándar con cookie
- Rate limiting: 5 intentos por minuto en login
- Login: `POST /admin/login`
- Logout: `POST /admin/logout`
- Middleware: `auth` + `internal`

### API REST (Sanctum)
- Tokens de API (Personal Access Tokens)
- Login con email/password: `POST /api/v1/auth/login`
- Rate limiting: 10 intentos por minuto
- Todas las rutas protegidas usan `auth:sanctum`
