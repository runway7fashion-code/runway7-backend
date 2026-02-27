# Runway7 Backend — Documentación

Documentación técnica completa del proyecto al 27 de Febrero de 2026.

## Índice

| Archivo | Contenido |
|---------|-----------|
| [01-overview.md](./01-overview.md) | Visión general, stack, arquitectura, estructura de directorios |
| [02-database.md](./02-database.md) | Esquema completo de BD: todas las tablas con columnas y descripciones |
| [03-roles-permissions.md](./03-roles-permissions.md) | Sistema de roles, permisos por sección, middleware, sidebar dinámico |
| [04-admin-panel.md](./04-admin-panel.md) | Módulos del panel admin: rutas, páginas Vue, funcionalidades |
| [05-accounting-module.md](./05-accounting-module.md) | Módulo de contabilidad detallado: planes, cuotas, waterfall, soporte, liquidez |
| [06-api.md](./06-api.md) | API REST v1: endpoints, request/response, autenticación |
| [07-test-data.md](./07-test-data.md) | Cuentas de prueba, evento demo, datos del seeder |
| [08-development.md](./08-development.md) | Setup, comandos artisan, convenciones de código, troubleshooting |
| [09-roadmap.md](./09-roadmap.md) | Qué está hecho vs pendiente, decisiones técnicas |

## Acceso Rápido

- **Panel Admin:** `http://localhost:8000/admin/login`
- **Admin:** `admin@runway7.com` / `password123`
- **Contabilidad:** `accounting@runway7.com` / `password123`
- **API Base:** `http://localhost:8000/api/v1`

## Iniciar desarrollo

```bash
export PATH="/usr/local/opt/postgresql@16/bin:$PATH"
php artisan serve    # Puerto 8000
npm run dev          # Vite HMR
```
