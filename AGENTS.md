# AGENTS.md — Sistema de Tickets

## Visión general

Sistema de tickets de soporte técnico multi-tenant (multi-empresa) inspirado en osTicket.
Cada empresa tiene su propio panel de agentes (SCP) y portal de clientes.
El código es PHP puro **procedural** con algunas clases en `includes/`.

## Stack y dependencias

- **Backend**: PHP 8.x (procedural), MySQLi
- **Frontend**: Bootstrap 5, jQuery, Summernote (WYSIWYG), Bootstrap Icons
- **PDF**: Dompdf (vía Composer en `vendor/`)
- **Email**: PHPMailer o clase propia `Mailer` (SMTP configurable por empresa)
- **No hay framework**: todo es código propio

## Estructura de carpetas clave

```
sistema-tickets/
├── config.php                    # DB, APP_URL, SMTP, CSRF, sesiones, autoload
├── includes/
│   ├── helpers.php               # Funciones globales: requireLogin, html(), empresaId(), etc.
│   ├── Auth.php                  # CSRF, autenticación básica
│   ├── Mailer.php                # Envío de correos
│   ├── Database.php              # Clase DB (si se usa)
│   └── TicketPdfGenerator.php    # Generación de PDFs de tickets
├── upload/
│   ├── scp/                      # Panel de Agentes (Staff Control Panel)
│   │   ├── index.php             # Router único del SCP (carga modules/)
│   │   ├── layout/layout.php     # Layout con sidebar + header
│   │   ├── modules/              # Módulos cargados por index.php
│   │   │   ├── tickets.php       # Dispatcher principal de tickets
│   │   │   ├── tickets/          # NUEVO: refactorización de tickets.php
│   │   │   │   ├── tickets-bootstrap.inc.php
│   │   │   │   ├── tickets-list-controller.inc.php
│   │   │   │   └── tickets-list-view.inc.php
│   │   │   ├── ticket-view.inc.php   # Vista detallada de ticket (HTML)
│   │   │   ├── ticket-open.inc.php   # Formulario de nuevo ticket (HTML)
│   │   │   └── ...
│   │   └── tickets.php           # Proxy: setea $_GET['page']='tickets' y requiere index.php
│   ├── tickets.php               # Portal del cliente (vista de sus tickets)
│   ├── open.php                  # Cliente abre ticket
│   └── login.php                 # Login de clientes
├── agente/                       # Endpoints para agentes móviles/externos
│   └── close-ticket.php          # Cierre con firma del cliente
└── cliente/                      # Portal antiguo o alternativo del cliente
```

## Convenciones obligatorias

### Multi-tenancy: `empresa_id`

TODAS las queries a tablas de negocio **deben** filtrar por `empresa_id`.
La función `empresaId()` retorna `$_SESSION['empresa_id']` o `1`.

```php
$eid = empresaId(); // siempre usar esto
```

### Seguridad

- **CSRF**: Todos los formularios POST deben incluir `<input type="hidden" name="csrf_token" ...>` y validar con `Auth::validateCSRF($_POST['csrf_token'])`.
- **XSS**: Usar `html($text)` (alias de `htmlspecialchars`) en todo output dinámico.
- **Permisos**: El sistema usa roles y permisos. Verificar con `roleHasPermission('ticket.edit')` antes de acciones. Para bloquear completamente una acción POST usar `requireRolePermission('ticket.edit', $redirectUrl)`.
- **Sesiones**: Fingerprint de sesión (user agent + IP parcial), timeout configurable.

### Compatibilidad de esquema (muy importante)

El código usa constantemente `SHOW TABLES LIKE '...'` y `SHOW COLUMNS FROM ... LIKE '...'` para soportar migraciones parciales sin romper funcionalidad existente. **Nunca** asumas que una tabla o columna existe sin verificar.

```php
$hasTable = dbTableExists('staff_departments');
$hasColumn = dbColumnExists('tickets', 'topic_id');
```

### Routing del SCP

Las URLs del panel de agentes apuntan a `upload/scp/index.php?page=MODULO`.
`upload/scp/tickets.php` es un proxy que hace `$_GET['page'] = 'tickets'` y requiere `index.php`.

`index.php` hace `require __DIR__ . '/modules/' . $routes[$page]`. Los módulos se ejecutan en el scope global y luego se capturan en un buffer `ob_start()` que se inyecta en `layout.php`.

### Variables globales disponibles en módulos

Cuando un módulo carga, estas variables están disponibles en scope global:
- `$mysqli`: conexión MySQLi (desde `config.php`)
- `$staff`: array del agente logueado (desde `index.php`)
- `$_SESSION['staff_id']`, `$_SESSION['empresa_id']`, `$_SESSION['csrf_token']`
- Constantes: `APP_NAME`, `APP_URL`, `SECRET_KEY`, `ATTACHMENTS_DIR`

### Generación de números de ticket

Puede ser por formato (`######`) o por secuencia de BD (tabla `sequences`). Ver función `$generateTicketNumberFromFormat` y `$generateTicketNumberFromSequence`.

### Notificaciones por email

- Para agentes: usa `Mailer::send()` o `Mailer::sendWithOptions()`
- Para colas async: usa `enqueueEmailJob()` y `triggerEmailQueueWorkerAsync()` si existen
- Siempre verificar `filter_var($email, FILTER_VALIDATE_EMAIL)`

### Adjuntos

- Directorio: `ATTACHMENTS_DIR` (por defecto `upload/uploads/attachments/`)
- Tabla `attachments` vinculada a `thread_entries`
- Descarga vía `tickets.php?id=X&download=Y`

## Patrones de archivos nuevos

Cuando se refactoriza un módulo grande:
1. Crear subcarpeta en `modules/NOMBRE/`
2. Extraer bootstrap compartido a `NOMBRE-bootstrap.inc.php`
3. Extraer controllers a `NOMBRE-ACCION-controller.inc.php`
4. Extraer views a `NOMBRE-ACCION-view.inc.php` (o reutilizar `.inc.php` existentes)
5. El archivo `modules/NOMBRE.php` debe ser un **dispatcher** limpio que hace `require` del bootstrap y luego enruta a la acción correspondiente.

## Qué NO cambiar sin consultar

- `config.php`: contiene credenciales de BD y SMTP.
- Convenciones de nombres de tablas existentes (`tickets`, `thread_entries`, `threads`, `staff`, `users`, etc.).
- El mecanismo de `empresa_id` en queries.
- Las funciones helper de `includes/helpers.php` usadas por otros módulos.

## Tests / Verificación rápida

- Después de cambios en `modules/tickets.php` o sus includes, verificar que estas URLs funcionen:
  - `tickets.php` → listado
  - `tickets.php?filter=open`, `?filter=closed`, `?filter=mine`, `?filter=unassigned`
  - `tickets.php?id=X` → vista detallada
  - `tickets.php?a=open` → formulario nuevo ticket
  - `tickets.php?action=user_search&q=test` → AJAX de búsqueda de usuarios
  - `tickets.php?action=ticket_preview&id=X` → AJAX de preview
  - Acciones masivas (bulk assign, bulk status, bulk delete)
- Si hay errores de sintaxis, PHP los mostrará directamente (no hay logging estructurado).
