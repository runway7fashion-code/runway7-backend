# Guía de Desarrollo

---

## Requisitos del Entorno

| Software | Versión | Notas |
|----------|---------|-------|
| PHP | 8.2 | XAMPP: `/Applications/XAMPP/xamppfiles/bin/php-8.2.4` |
| PostgreSQL | 16 | Homebrew: `/usr/local/opt/postgresql@16/bin/` |
| Node.js | 24+ | |
| npm | Incluido con Node | |
| Composer | Última estable | |

---

## Setup Inicial (Primera Vez)

```bash
# 1. Clonar el repositorio
git clone ... && cd runway7-backend

# 2. Instalar dependencias PHP
composer install

# 3. Instalar dependencias JS
npm install

# 4. Copiar variables de entorno
cp .env.example .env

# 5. Generar app key
php artisan key:generate

# 6. Configurar .env (DB, Reverb, Stripe)
# Ver sección de variables de entorno

# 7. Asegurarse de que PostgreSQL 16 está en PATH
export PATH="/usr/local/opt/postgresql@16/bin:$PATH"

# 8. Iniciar PostgreSQL si no está corriendo
brew services start postgresql@16

# 9. Crear la base de datos y usuario (solo primera vez)
psql -U postgres
> CREATE DATABASE runway7;
> CREATE USER runway7_user WITH ENCRYPTED PASSWORD 'runway7_secret';
> GRANT ALL PRIVILEGES ON DATABASE runway7 TO runway7_user;
> \c runway7
> GRANT ALL ON SCHEMA public TO runway7_user;
> \q

# 10. Ejecutar migraciones y seeders
php artisan migrate:fresh --seed

# 11. Crear symlink de storage
php artisan storage:link

# 12. Compilar assets
npm run build
```

---

## Flujo de Trabajo Diario

```bash
# Terminal 1: Servidor Laravel
export PATH="/usr/local/opt/postgresql@16/bin:$PATH"
php artisan serve

# Terminal 2: Vite (HMR)
npm run dev
```

Acceder a: `http://localhost:8000/admin/login`

---

## Comandos PHP Artisan Útiles

```bash
# Migraciones
php artisan migrate                     # Ejecutar migraciones pendientes
php artisan migrate:fresh               # Borrar todo y re-migrar
php artisan migrate:fresh --seed        # Borrar, re-migrar y sembrar
php artisan migrate:status              # Ver estado de migraciones
php artisan migrate:rollback            # Revertir última migración

# Seeders
php artisan db:seed                     # Ejecutar todos los seeders
php artisan db:seed --class=UserSeeder  # Ejecutar un seeder específico

# Cache
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Crear archivos
php artisan make:model NombreModelo -m          # Modelo + migración
php artisan make:controller Admin/NombreController
php artisan make:migration create_tabla_table
php artisan make:seeder NombreSeeder

# Tinker (REPL interactivo)
php artisan tinker
>>> User::where('email', 'admin@runway7.com')->first()
>>> App\Models\DesignerPaymentPlan::with('installments')->first()

# Ver rutas
php artisan route:list
php artisan route:list --path=accounting

# Reverb (WebSockets)
php artisan reverb:start
```

---

## Convenciones de Código

### PHP / Laravel

**Controladores:**
- Un controlador por módulo/recurso
- Métodos nombrados claramente: `index`, `show`, `create`, `store`, `edit`, `update`, `destroy`
- Métodos API/AJAX: prefix `api` en la ruta, retornan JSON

**Servicios:**
- Lógica de negocio compleja en `app/Services/`
- Inyección de dependencias en el constructor del controlador
- Métodos públicos con nombres descriptivos

**Modelos:**
- `$fillable` siempre definido (nunca `$guarded = []`)
- `casts()` como método (sintaxis Laravel 11+/12)
- Relaciones como métodos con nombres en camelCase
- Scopes locales: `scopeActive()`, `scopeDesigners()`, etc.
- Accessors: usar `getAtributoAttribute()` o `Attribute::make()` (Laravel 9+)

**Migraciones:**
- Nomenclatura: `YYYY_MM_DD_HHMMSS_descripcion_tabla.php`
- Siempre incluir `down()` para rollback
- SoftDeletes cuando el registro puede necesitar recuperarse
- Índices en columnas que se usan en WHERE con frecuencia

### Vue 3 / Inertia

**Estructura de página:**
```vue
<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ref, computed } from 'vue';
import { useForm, Link, router } from '@inertiajs/vue3';

// Props de Inertia
const props = defineProps({ ... });

// Estado reactivo
const someRef = ref(false);

// Forms con useForm para validación automática
const form = useForm({ ... });

// Funciones
function submit() {
    form.post('/admin/ruta', { ... });
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2>Título de la página</h2>
        </template>

        <!-- Contenido -->
    </AdminLayout>
</template>
```

**Formularios con Inertia:**
- Usar `useForm()` para forms con manejo de errores automático
- `form.errors.campo` para mostrar errores de validación
- `form.processing` para deshabilitar botón durante submit
- `forceFormData: true` cuando el form incluye archivos

**Navegación:**
- `<Link>` para links SPA sin recarga
- `router.post()` para acciones sin formulario (ej: logout, confirmar)
- `router.visit()` para redirecciones programáticas

---

## Creando una Nueva Página Admin

### Paso 1: Migración (si necesitas tabla nueva)
```bash
php artisan make:migration create_mi_tabla_table
```

### Paso 2: Modelo
```bash
php artisan make:model MiModelo
```

### Paso 3: Controlador
```bash
php artisan make:controller Admin/MiModuloController
```

### Paso 4: Rutas en `routes/web.php`
```php
Route::middleware('section:mi_seccion')->group(function () {
    Route::get('mi-ruta', [MiModuloController::class, 'index'])->name('mi-ruta.index');
    // ...
});
```

### Paso 5: Agregar sección al rol en `config/role_permissions.php`
```php
'admin' => [
    'sections' => [..., 'mi_seccion'],
    ...
],
```

### Paso 6: Agregar ítem al sidebar en `AdminLayout.vue`
```js
// En allNavItems o en la sección correspondiente
{ name: 'Mi Módulo', href: '/admin/mi-ruta', section: 'mi_seccion', icon: 'M...' }
```

### Paso 7: Crear la página Vue
```bash
mkdir -p resources/js/Pages/Admin/MiModulo
touch resources/js/Pages/Admin/MiModulo/Index.vue
```

### Paso 8: En el controlador, retornar con Inertia
```php
use Inertia\Inertia;
use Inertia\Response;

public function index(): Response
{
    return Inertia::render('Admin/MiModulo/Index', [
        'data' => MiModelo::paginate(20),
    ]);
}
```

---

## Storage / Archivos Subidos

**Configuración:** `FILESYSTEM_DISK=public` en `.env`

**Symlink:** `php artisan storage:link`
→ `public/storage` → `storage/app/public`

**Estructura de carpetas:**
```
storage/app/public/
├── models/              # Fotos de modelos (comp card, profile picture)
├── banners/             # Imágenes de banners
├── accounting/
│   └── cases/
│       └── {case_id}/  # Adjuntos de soporte
└── designers/           # Videos/audios de displays
```

**Acceder a archivos:**
```php
// Guardar
$path = $file->store('mi-carpeta', 'public');
// $path = "mi-carpeta/filename.jpg"

// URL pública
asset('storage/' . $path)
// = "http://localhost:8000/storage/mi-carpeta/filename.jpg"
```

---

## Variables de Entorno

```env
APP_NAME=Runway7
APP_ENV=local
APP_KEY=base64:...
APP_DEBUG=true
APP_URL=http://localhost:8000

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=runway7
DB_USERNAME=runway7_user
DB_PASSWORD=runway7_secret

CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync

BROADCAST_DRIVER=reverb
REVERB_APP_ID=runway7-app
REVERB_APP_KEY=runway7-key
REVERB_APP_SECRET=runway7-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=no-reply@runway7.com
MAIL_FROM_NAME="Runway7"
```

---

## Troubleshooting Común

### Error: "SQLSTATE: permission denied for schema public"
```bash
psql -U postgres -c "\c runway7" -c "GRANT ALL ON SCHEMA public TO runway7_user;"
```

### PostgreSQL no encontrado
```bash
export PATH="/usr/local/opt/postgresql@16/bin:$PATH"
```

### Vite no compila
```bash
npm install
npm run dev
```

### Errores 500 en Inertia
```bash
php artisan config:clear
php artisan cache:clear
# Revisar storage/logs/laravel.log
```

### Assets no cargan (CSS/JS)
```bash
npm run build
php artisan view:clear
```

### Storage files not found
```bash
php artisan storage:link
```
