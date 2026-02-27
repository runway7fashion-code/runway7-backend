# Panel Admin — Módulos y Páginas

**URL base:** `http://localhost:8000/admin`
**Layout:** `resources/js/Layouts/AdminLayout.vue`
**Stack:** Inertia.js v2 + Vue 3 (Composition API con `<script setup>`)

---

## Layout Principal (AdminLayout.vue)

El layout envuelve todas las páginas del admin. Características:

- **Sidebar colapsable:** Botón `«`/`»` al lado del logo. Cuando colapsado (`w-16`), muestra solo íconos con tooltip nativo al hover. Cuando expandido (`w-64`), muestra íconos + texto.
- **Scrollbar personalizado:** 8px de ancho, color dorado `#D4AF37`, track transparente.
- **Animación suave:** `transition-all duration-300 ease-in-out`
- **Usuario bottom:** Muestra avatar (iniciales en dorado), nombre, email y rol. En modo colapsado muestra solo el avatar.
- **Flash messages:** Mensajes de éxito/error de sesión mostrados debajo del header.
- **Header:** Nombre de la página (slot) + fecha actual en español.

---

## Módulo: Autenticación

**Ruta:** `/admin/login`
**Controlador:** `Admin\AuthController`
**Páginas:** `Auth/Login.vue`

- Login con email y password
- Rate limiting: 5 intentos/minuto
- Redirige al dashboard tras login exitoso
- `POST /admin/logout` para cerrar sesión

---

## Módulo: Dashboard

**Ruta:** `/admin/`
**Controlador:** `Admin\DashboardController`
**Página:** `Dashboard.vue`
**Acceso:** `section:dashboard`

Muestra métricas generales adaptadas al rol del usuario.

---

## Módulo: Eventos

**Rutas base:** `/admin/events`
**Controlador:** `Admin\EventController`, `Admin\EventDayController`, `Admin\ShowController`
**Páginas:** `Events/Index.vue`, `Events/Create.vue`, `Events/Edit.vue`, `Events/Show.vue`
**Acceso:** `section:events`

### Funcionalidades

**Gestión de eventos:**
- CRUD completo (Index, Create, Edit, Show, Delete)
- Duplicar evento: `POST /admin/events/{event}/duplicate`
- Campos: name, slug, city, venue, timezone, start_date, end_date, status, description, settings, model_number_start
- SoftDelete: los eventos eliminados no desaparecen de la BD

**Días del evento (`event_days`):**
- Agregar días: `POST /admin/events/{event}/days`
- Editar días: `PUT /admin/events/{event}/days/{day}`
- Eliminar días: `DELETE /admin/events/{event}/days/{day}`
- Tipos: `show_day`, `casting`, `setup`, `other`
- Generación automática de shows: `POST /admin/events/{event}/generate-shows`

**Shows:**
- Crear show en un día: `POST /admin/events/{event}/days/{day}/shows`
- Editar: `PUT /admin/shows/{show}`
- Eliminar: `DELETE /admin/shows/{show}`
- Asignar diseñador: `POST /admin/shows/{show}/assign-designer`
- Remover diseñador: `POST /admin/shows/{show}/remove-designer`

---

## Módulo: Modelos

**Rutas base:** `/admin/models`
**Controlador:** `Admin\ModelController`
**Páginas:** `Models/Index.vue`, `Models/Create.vue`, `Models/Edit.vue`, `Models/Show.vue`
**Acceso:** `section:models`
**Service:** `ModelService`

### Funcionalidades

**CRUD de modelos:**
- Crear modelo con `model_profile` en una sola vista
- Campos del profile: medidas, fotos comp card (4 posiciones), agencia, redes sociales

**Gestión del comp card (fotos):**
- Subir foto por posición: `POST /admin/models/{model}/upload-photo/{position}`
- Eliminar foto: `DELETE /admin/models/{model}/delete-photo/{position}`
- Foto de perfil: `POST /admin/models/{model}/upload-profile-picture`
- Eliminar foto de perfil: `DELETE /admin/models/{model}/delete-profile-picture`

**Asignación a eventos:**
- Asignar: `POST /admin/models/{model}/assign-event`
- Remover: `DELETE /admin/models/{model}/remove-event/{event}`

**Comunicación:**
- Enviar email de bienvenida: `POST /admin/models/{model}/send-welcome-email`

---

## Módulo: Diseñadores

**Rutas base:** `/admin/designers`
**Controlador:** `Admin\DesignerController`
**Páginas:** `Designers/Index.vue`, `Designers/Create.vue`, `Designers/Edit.vue`, `Designers/Show.vue`
**Acceso:** `section:designers`
**Service:** `DesignerService`

### Funcionalidades

**CRUD de diseñadores:**
- Crear diseñador con `designer_profile` en una sola vista
- Campos del profile: brand_name, collection_name, bio, website, instagram, country, category, sales_rep, tracking_link, social_media (json)

**Asignación a eventos:**
- Asignar: `POST /admin/designers/{designer}/assign-event`
  - Parámetros: event_id, status, package_id, looks, model_casting_enabled, package_price, notes
- Remover: `DELETE /admin/designers/{designer}/remove-event/{event}`

**Asistentes:**
- Agregar: `POST /admin/designers/{designer}/assistants`
- Remover: `DELETE /admin/designers/assistants/{assistant}`

**Shows:**
- Asignar diseñador a show: `POST /admin/designers/{designer}/shows`
- Remover de show: `DELETE /admin/designers/{designer}/shows/{show}`

**Materiales y Displays:**
- Actualizar material: `PUT /admin/designer-materials/{material}`
- Actualizar display: `PUT /admin/designer-displays/{display}`
- Subir video al display: `POST /admin/designer-displays/{display}/upload-video`
- Subir audio al display: `POST /admin/designer-displays/{display}/upload-audio`

---

## Módulo: Usuarios

**Rutas base:** `/admin/users`
**Controlador:** `Admin\UserController`
**Páginas:** `Users/Index.vue` (+ Create, Edit, Show inferidos del resource)
**Acceso:** `section:users` (solo admin)

CRUD completo de usuarios internos del equipo y participantes.

---

## Módulo: Chats

**Rutas base:** `/admin/chats`
**Controlador:** `Admin\ChatController`
**Páginas:** `Chats/Index.vue`, `Chats/Show.vue`
**Acceso:** `section:chats`
**Service:** `ChatService`
**Tiempo real:** Laravel Reverb (WebSockets)

| Ruta | Acción |
|------|--------|
| `GET /admin/chats` | Lista de conversaciones |
| `GET /admin/chats/{conversation}` | Mensajes de una conversación |

---

## Módulo: Banners

**Rutas base:** `/admin/banners`
**Controlador:** `Admin\BannerController`
**Páginas:** `Banners/Index.vue`, `Banners/Create.vue`, `Banners/Edit.vue`
**Acceso:** `section:banners`

| Ruta | Acción |
|------|--------|
| Resource CRUD | Index, Create, Store, Edit, Update, Destroy |
| `POST /admin/banners/{banner}/upload-image` | Subir imagen del banner |
| `POST /admin/banners/reorder` | Reordenar banners por drag & drop |

**Campos:** title, subtitle, image_url, link_url, is_active, order, target_role

---

## Módulo: Contabilidad

Ver [05-accounting-module.md](./05-accounting-module.md) para documentación detallada.

**Rutas base:** `/admin/accounting`
**Acceso:** `section:accounting_dashboard` o `section:accounting_payments`

---

## Módulo: Ajustes

**Rutas base:** `/admin/settings`
**Controlador:** `Admin\DesignerSettingsController`
**Páginas:** `Settings/Designers.vue`
**Acceso:** `section:settings` (solo admin)

### Categorías de Diseñadores
| Ruta | Acción |
|------|--------|
| `POST /admin/settings/designer-categories` | Crear categoría |
| `PUT /admin/settings/designer-categories/{category}` | Actualizar |
| `DELETE /admin/settings/designer-categories/{category}` | Eliminar |

### Paquetes de Diseñadores
| Ruta | Acción |
|------|--------|
| `POST /admin/settings/designer-packages` | Crear paquete |
| `PUT /admin/settings/designer-packages/{package}` | Actualizar |
| `DELETE /admin/settings/designer-packages/{package}` | Eliminar |

---

## Componentes y Convenciones Frontend

### Tecnologías Vue
- `<script setup>` con Composition API
- `useForm()` de Inertia para formularios con manejo de errores
- `router.visit()`, `router.post()` para navegación programática
- `usePage()` para acceder a props compartidas (auth, flash)
- `Link` de Inertia para navegación SPA sin recarga

### Patrones comunes
- **Paginación:** Componente de paginación Inertia con `withQueryString()`
- **Filtros:** Preservados en URL query string
- **Flash messages:** Mostrados en `AdminLayout` desde `$page.props.flash`
- **Confirmación de eliminación:** Modal inline con `ref(false)` para mostrar/ocultar
- **Upload de archivos:** `forceFormData: true` en forms de Inertia
- **APIs internas:** Fetch nativo para endpoints AJAX (`/admin/accounting/api/*`)

### Colores y estilos
```css
/* Activo en sidebar */
.active-nav { background: rgb(113 63 18 / 0.3); color: rgb(250 204 21); }

/* Botón principal */
button.primary { background-color: #D4AF37; color: black; }

/* Badges de estado */
.badge-green  { background: #dcfce7; color: #166534; }
.badge-red    { background: #fee2e2; color: #991b1b; }
.badge-yellow { background: #fef9c3; color: #854d0e; }
.badge-blue   { background: #dbeafe; color: #1e40af; }
.badge-gray   { background: #f3f4f6; color: #374151; }
```
