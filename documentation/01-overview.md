# Runway7 Backend — Visión General

## Descripción del Proyecto

Runway7 es una plataforma de gestión integral para eventos de moda (fashion weeks). El backend administra modelos, diseñadores, eventos, contabilidad, tickets, chats y comunicaciones internas. Está compuesto por un **panel administrativo web** (Inertia + Vue 3) y una **API REST** consumida por aplicaciones móviles (Flutter) y kioscos.

---

## Stack Tecnológico

| Capa | Tecnología | Versión |
|------|-----------|---------|
| Backend framework | Laravel | 12.x |
| Lenguaje | PHP | 8.2 (XAMPP) |
| Base de datos | PostgreSQL | 16 |
| Frontend (Admin) | Inertia.js + Vue 3 | Inertia 2.x / Vue 3.5 |
| Estilos | Tailwind CSS | 4.x |
| Gráficas | Chart.js + vue-chartjs | 4.x |
| Auth API | Laravel Sanctum | 4.x |
| WebSockets | Laravel Reverb | 1.8 |
| Pagos | Stripe SDK | 19.x |
| Build tool | Vite | 7.x |
| HTTP client | Axios | 1.x |

### Dependencias PHP (composer)
```
laravel/framework          ^12.0
laravel/sanctum            ^4.3
laravel/reverb             ^1.8
inertiajs/inertia-laravel  ^2.0
spatie/laravel-permission  ^6.24
stripe/stripe-php          ^19.3
laravel/tinker             ^2.10.1
```

### Dependencias JS (npm)
```
vue                   ^3.5.28
@inertiajs/vue3       ^2.3.15
@vitejs/plugin-vue    ^6.0.4
tailwindcss           ^4.2.1
chart.js              ^4.5.1
vue-chartjs           ^5.3.3
axios                 ^1.11.0
```

---

## Arquitectura General

```
┌─────────────────────────────────────────────────────┐
│                   CLIENTE WEB                       │
│              (Admin Panel: Inertia + Vue 3)         │
└───────────────────────┬─────────────────────────────┘
                        │ HTTP (Inertia)
┌───────────────────────▼─────────────────────────────┐
│                  LARAVEL 12                         │
│                                                     │
│  ┌─────────────┐  ┌──────────────┐  ┌───────────┐  │
│  │  Web Routes │  │  API Routes  │  │ WebSocket │  │
│  │  /admin/*   │  │  /api/v1/*   │  │ (Reverb)  │  │
│  └──────┬──────┘  └──────┬───────┘  └─────┬─────┘  │
│         │                │                │         │
│  ┌──────▼────────────────▼────────────────▼──────┐  │
│  │              Controllers                       │  │
│  │  Admin/* | Api/V1/*                           │  │
│  └──────────────────────┬────────────────────────┘  │
│                         │                            │
│  ┌──────────────────────▼────────────────────────┐  │
│  │              Services                          │  │
│  │  AccountingService | DesignerService |         │  │
│  │  EventService | ModelService | ChatService     │  │
│  └──────────────────────┬────────────────────────┘  │
│                         │                            │
│  ┌──────────────────────▼────────────────────────┐  │
│  │              Eloquent Models (35+)             │  │
│  └──────────────────────┬────────────────────────┘  │
└─────────────────────────┼───────────────────────────┘
                          │
┌─────────────────────────▼───────────────────────────┐
│              PostgreSQL 16                          │
│              47 migraciones / ~38 tablas            │
└─────────────────────────────────────────────────────┘

Clientes externos:
┌──────────────────┐  ┌──────────────────┐
│  App Flutter     │  │  Kiosco Web      │
│  (API Sanctum)   │  │  (API Sanctum)   │
└──────────────────┘  └──────────────────┘
```

---

## Estructura de Directorios

```
runway7-backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/          # Controladores del panel admin
│   │   │   └── Api/V1/         # Controladores de la API REST
│   │   └── Middleware/
│   │       ├── HandleInertiaRequests.php
│   │       ├── CheckSectionAccess.php
│   │       └── EnsureUserIsInternal.php
│   ├── Models/                 # 35 modelos Eloquent
│   └── Services/               # Lógica de negocio
│       ├── AccountingService.php
│       ├── DesignerService.php
│       ├── EventService.php
│       ├── ModelService.php
│       ├── ChatService.php
│       └── CastingService.php
├── config/
│   └── role_permissions.php    # Permisos por rol
├── database/
│   ├── migrations/             # 47 migraciones
│   └── seeders/                # 7 seeders
├── resources/
│   ├── js/
│   │   ├── Layouts/
│   │   │   └── AdminLayout.vue # Layout principal del panel admin
│   │   └── Pages/Admin/        # Páginas Inertia (Vue 3)
│   │       ├── Accounting/     # 9 páginas
│   │       ├── Auth/
│   │       ├── Banners/
│   │       ├── Chats/
│   │       ├── Dashboard.vue
│   │       ├── Designers/
│   │       ├── Events/
│   │       ├── Models/
│   │       ├── Settings/
│   │       └── Users/
│   └── views/
│       └── app.blade.php       # Raíz Inertia
├── routes/
│   ├── web.php                 # Rutas admin + panel
│   └── api.php                 # API REST v1
└── storage/app/public/         # Archivos subidos
    ├── models/                 # Fotos de modelos
    ├── banners/                # Imágenes de banners
    └── accounting/cases/       # Adjuntos de soporte
```

---

## URLs Principales

| URL | Descripción |
|-----|-------------|
| `http://localhost:8000/admin/login` | Login del panel admin |
| `http://localhost:8000/admin/` | Dashboard admin |
| `http://localhost:8000/api/v1/` | Base URL de la API REST |

---

## Configuración de Entorno Local

### Requisitos previos
- PHP 8.2 (XAMPP: `/Applications/XAMPP/xamppfiles/bin/php-8.2.4`)
- PostgreSQL 16 (Homebrew: `/usr/local/opt/postgresql@16/bin/`)
- Node.js v24+
- Composer

### Variables de entorno clave (.env)
```env
APP_NAME=Runway7
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=runway7
DB_USERNAME=runway7_user
DB_PASSWORD=runway7_secret

BROADCAST_DRIVER=reverb
REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...

STRIPE_KEY=pk_...
STRIPE_SECRET=sk_...
```

### Comando para iniciar desarrollo
```bash
export PATH="/usr/local/opt/postgresql@16/bin:$PATH"
brew services start postgresql@16   # Si no está corriendo
php artisan serve                    # Puerto 8000
npm run dev                         # Vite HMR
```

### Setup inicial de base de datos
```bash
# Permisos PostgreSQL (solo primera vez)
psql -U postgres -c "GRANT ALL ON SCHEMA public TO runway7_user;"

# Migrar y sembrar
php artisan migrate:fresh --seed
```

---

## Diseño Visual del Admin Panel

| Elemento | Valor |
|----------|-------|
| Color principal | Negro `#000000` |
| Color de fondo | Gris claro `#F9FAFB` |
| Color acento/dorado | `#D4AF37` |
| Fuente | Sistema (Tailwind default) |
| Sidebar | Negro, colapsable, scrollbar dorado 8px |
| Íconos activos | `bg-yellow-900/30 text-yellow-400` |
