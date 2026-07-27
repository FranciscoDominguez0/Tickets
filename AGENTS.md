# AGENTS.md — Sistema de Tickets

Multi-tenant (multi-empresa) helpdesk. PHP 8 procedural + MySQLi, Bootstrap 5, jQuery,
Summernote, Dompdf (composer). No framework.

## Rutas principales (coexisten ambas)

| Portal | Cliente | Agente |
|--------|---------|--------|
| Principal | `upload/tickets.php` | `upload/scp/index.php?page=MODULO` |
| Legacy | `cliente/` | `agente/` |

Siempre trabajar en `upload/` (nuevo portal). `cliente/` y `agente/` son legacy.

## SCP routing (agent panel)

`upload/scp/index.php` → `$routes[page]` → `require modules/FILE`.
Contenido capturado con `ob_start()` e inyectado en `layout/layout.php`.

`upload/scp/tickets.php` es proxy: setea `$_GET['page']='tickets'` e incluye `index.php`.

Rutas disponibles: `dashboard`, `tickets`, `statistics`* , `users`, `tasks`, `canned`,
`directory`, `profile`, `orgs`, `notifications`, `mapa`* , `credits`, `cotizaciones`.
`*` redirigen a standalone pages (ej: `statics.php`, `mapa.php`).

## Tickets module internals

`modules/tickets.php` es dispatcher → bootstrap + subfiles en `modules/tickets/`:
- `tickets-bootstrap.inc.php`: status ID mapping (ticket_status table), departamentos,
  seen-IDs tracking, auto-close (deshabilitado/comentado)
- `tickets-ajax.inc.php`
- `tickets-list-controller.inc.php` + `tickets-list-view.inc.php`
- `tickets-view-controller.inc.php`
- `tickets-open.inc.php`

## Variables globales disponibles en módulos SCP

- `$mysqli` (MySQLi), `$staff` (array del agente logueado)
- `$_SESSION['staff_id']`, `$_SESSION['empresa_id']`, `$_SESSION['csrf_token']`
- Constantes: `APP_URL`, `APP_NAME`, `SECRET_KEY`, `ATTACHMENTS_DIR`

## Helpers clave (includes/helpers.php)

| Función | Uso |
|---------|-----|
| `empresaId()` | Retorna `$_SESSION['empresa_id']`  fallback 1 |
| `html($text)` | `htmlspecialchars($text, ENT_QUOTES, 'UTF-8')` — **siempre** en output |
| `csrfField()` / `validateCSRF()` | CSRF en forms y validación POST |
| `dbTableExists($t)` / `dbColumnExists($t,$c)` | Usar en vez de asumir schema |
| `getAppSetting($key, $default)` | Config app_settings por empresa |
| `addLog($action, ...)` | Auditoría en tabla `logs` |
| `roleHasPermission($key)` / `requireRolePermission($key, $url)` | Permisos por rol |
| `generateTicketNumber()` | Número random `XXX-YYYYMMDD-######` |

## Multi-tenancy: `empresa_id`

TODAS las queries a tablas de negocio **deben** filtrar por `empresa_id`:
```php
$eid = empresaId();
```
La empresa 1 es "always active" (billing bypass).

## Schema compatibilidad (importante)

Nunca asumas que una tabla/columna existe. Usar `dbTableExists()` y `dbColumnExists()`.
Tienen cache en sesión (300s TTL) y listas internas de tablas/columnas conocidas
(ver `helpers.php` ~línea 719 y ~797). No necesitas verificar tablas/columnas que
estén en esas listas.

## Login / Auth (includes/Auth.php)

Dos flujos separados:
- **Cliente**: `Auth::loginUser($email, $password)` → sesión con `user_id`, `user_type='cliente'`
- **Agente**: `Auth::loginStaff($username, $password)` → primero busca en `super_admins`
  (superadmin global), luego en `staff` → sesión con `staff_id`, `user_type='agente'`

Ambos usan: bcrypt cost 10, session fingerprint (UA + IP parcial), rate-limit
(tablas `user_login_attempts` / `staff_login_attempts`).

## Read-only por billing

Cuando `empresas.estado_pago` está vencido o `empresas.bloqueada=1`, los agentes entran
en modo read-only (todos los POST en SCP son bloqueados con 403). `helpers.php:321-329`.

El script `scripts/billing_daily.php` sincroniza estados vía cron:
```php
php scripts/billing_daily.php
```

## Config (config.php — NO CAMBIAR sin consultar)

- DB: port `3306`, user `root`, pass `12345678`, db `tickets_db`
- Autoload PSR-4-like: `includes/ClassName.php`
- DB port en config.php (3306) — el README.md está desactualizado (dice 33065)
- `ATTACHMENTS_DIR` = `upload/uploads/attachments/`

## Adjuntos

- Directorio: `ATTACHMENTS_DIR` (config.php)
- Tabla `attachments` vinculada a `thread_entries`
- Descarga vía `tickets.php?id=X&download=Y`

## PWA (Progressive Web App)

Instalable como app nativa. Archivos clave:
- `upload/manifest.json` — metadatos, iconos, theme_color (#b91c1c), display standalone
- `upload/sw.js` — service worker: cache-first para JS/CSS/fonts, network-first para páginas
- `upload/publico/pwa-offline.html` — fallback offline
- `publico/img/pwa/` — iconos 192×192, 512×512, maskable, apple-touch-icon

Inyectado en: `layout/layout.php`, `layout_admin.php`, `superadmin/layout.php`, `login.php`
(agente y cliente), `tickets.php`. Requiere HTTPS para instalarse.

## Testing / verificación rápida

Sin test runner configurado. Verificar URLs manualmente:
- SCP: `upload/scp/tickets.php`, `?filter=open/closed/mine/unassigned`, `?id=X`, `?a=open`
- AJAX: `?action=user_search&q=test`, `?action=ticket_preview&id=X`
- Bulk: assign, status, delete
- Errores PHP se muestran directamente (sin logging estructurado)
