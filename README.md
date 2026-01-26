# 📋 SISTEMA DE TICKETS - GUÍA RÁPIDA

## 🚀 INICIO RÁPIDO

### 1. CONFIGURACIÓN DE BASE DE DATOS

**Credenciales:**
- Host: `localhost`
- Puerto: `33065`
- Usuario: `root`
- Contraseña: `12345678`
- Base de datos: `tickets_db`

**Importar SQL:**
```bash
mysql -h localhost -P 33065 -u root -p12345678 tickets_db < SISTEMA_TICKETS_COMPLETO.sql
```

### 2. ESTRUCTURA DE CARPETAS

```
C:\Users\domin\sistema-tickets\
├── config.php              ← Configuración principal
├── SISTEMA_TICKETS_COMPLETO.sql  ← Base de datos SQL
├── LOGIN_SQL.sql           ← Queries de login
│
├── cliente/
│   ├── login.php           ← Login de cliente
│   ├── registrar.php       ← Registro de cliente
│   ├── index.php           ← Dashboard cliente
│   └── logout.php          ← Cerrar sesión
│
├── agente/
│   ├── login.php           ← Login de agente
│   ├── index.php           ← Dashboard agente
│   └── logout.php          ← Cerrar sesión
│
├── includes/
│   ├── Auth.php            ← Clase de autenticación
│   ├── Database.php        ← Clase de conexión BD
│   └── helpers.php         ← Funciones auxiliares
│
└── publico/
    └── css/
        └── login.css       ← Estilos login
```

---

## 👥 USUARIOS DE PRUEBA

### CLIENTES

| Email | Contraseña | Nombre |
|-------|-----------|--------|
| cliente@example.com | cliente123 | Juan Cliente |
| soporte@example.com | cliente123 | María Usuario |
| gerente@example.com | cliente123 | Carlos Manager |

**Para registrar un nuevo cliente:** `cliente/registrar.php`

### AGENTES

| Usuario | Contraseña | Nombre | Rol |
|---------|-----------|--------|-----|
| admin | admin123 | Admin System | admin |
| soporte1 | admin123 | Juan Soporte | agent |
| soporte2 | admin123 | María Ventas | agent |
| supervisor | admin123 | Luis Supervisor | supervisor |

---

## 🔐 FLUJO DE LOGIN Y REGISTRO

### CLIENTE

1. **Registrarse:** `/cliente/registrar.php`
   - Nombre, Apellido, Email, Empresa (opcional), Teléfono (opcional)
   - Contraseña (mín. 6 caracteres)
   - Validación CSRF + Email único

2. **Login:** `/cliente/login.php`
   - Email + Contraseña
   - Validación bcrypt
   - Redirige a `/cliente/index.php`

3. **Dashboard:** `/cliente/index.php`
   - Requiere estar logueado como cliente
   - Opciones: Crear Ticket, Mis Tickets, Mi Perfil
   - Botón Cerrar Sesión

### AGENTE

1. **Login:** `/agente/login.php`
   - Usuario + Contraseña
   - Validación bcrypt
   - Redirige a `/agente/index.php`

2. **Dashboard:** `/agente/index.php`
   - Requiere estar logueado como agente
   - Estadísticas: Total, Abiertos, Asignados
   - Opciones: Mis Tickets, Todos los Tickets, Mi Perfil
   - Botón Cerrar Sesión

---

## 🔑 CONFIGURACIÓN

### config.php

```php
// Puerto MySQL
define('DB_PORT', '33065');

// Usuario y contraseña
define('DB_USER', 'root');
define('DB_PASS', '12345678');

// Seguridad
define('CSRF_TIMEOUT', 3600);      // 1 hora
define('SESSION_LIFETIME', 86400);  // 24 horas
```

### Cambiar valores en PRODUCCIÓN

```php
define('SECRET_KEY', 'algo-muy-largo-y-aleatorio');
define('APP_URL', 'https://tudominio.com');
```

---

## 🛡️ SEGURIDAD

### CSRF Token
- Generado automáticamente en sesión
- Validado en cada POST
- Rotado después de cada acción

### Contraseñas
- Hash bcrypt con cost 10
- Validación: `password_verify()`
- Nunca en plaintext en BD

### SQL Injection
- Prepared statements en todas las queries
- Bind parameters automático
- Escape de caracteres especiales

### XSS Prevention
- `htmlspecialchars()` en todo output
- Helper `html()` para escapar
- CSRF tokens en formularios

### Session Management
- Sesiones en BD (tabla sessions)
- Timeout automático (24 horas)
- Validación de estado activo

---

## 📝 ARCHIVOS PRINCIPALES

### config.php
Centraliza toda la configuración (BD, app, seguridad)

### Auth.php
```php
Auth::hash($password)              // Hash bcrypt
Auth::verify($password, $hash)     // Verificar
Auth::loginUser($email, $password) // Login cliente
Auth::loginStaff($user, $pass)     // Login agente
Auth::validateCSRF($token)         // Validar CSRF
```

### Database.php
```php
Database::getInstance()    // Instancia única
$db->prepare($sql)         // Preparar
$db->query($sql, $params)  // Ejecutar query
$db->fetchOne()            // Un registro
$db->fetchAll()            // Múltiples
$db->lastInsertId()        // Último ID
```

### helpers.php
```php
requireLogin('cliente|agente')  // Proteger página
validateCSRF()                  // Validar CSRF
html($text)                     // Escapar output
getCurrentUser()                // Usuario actual
isValidEmail($email)            // Validar email
```

---

## 🧪 TESTING

### Test 1: Registro Cliente
```
1. Ir a: cliente/registrar.php
2. Llenar formulario
3. Verificar email no exista
4. Verificar contraseñas coincidan
5. Verificar hash en BD (bcrypt)
```

### Test 2: Login Cliente
```
1. Ir a: cliente/login.php
2. Ingresar cliente@example.com / cliente123
3. Verificar redirección a cliente/index.php
4. Verificar $_SESSION['user_id'] establecida
```

### Test 3: Login Agente
```
1. Ir a: agente/login.php
2. Ingresar admin / admin123
3. Verificar redirección a agente/index.php
4. Verificar $_SESSION['staff_id'] establecida
```

### Test 4: Protección de Páginas
```
1. Sin login, ir a cliente/index.php → redirect a login
2. Sin login, ir a agente/index.php → redirect a login
3. Cliente logueado, ir a agente/index.php → error
4. Agente logueado, ir a cliente/index.php → error
```

### Test 5: CSRF Protection
```
1. Modificar CSRF token en formulario
2. Intentar POST → debe fallar
3. Token debe ser único por sesión
```

### Test 6: Logout
```
1. Logueado, click en "Cerrar Sesión"
2. Session debe destruirse
3. Redirect a login.php
4. Al intentar acceder a dashboard → redirect a login
```

---

## 📧 TABLA DE LOGIN

### QUERY: Login Cliente
```sql
SELECT id, email, firstname, lastname, password 
FROM users 
WHERE email = ? AND status = 'active'
```

### QUERY: Login Agente
```sql
SELECT id, username, email, firstname, lastname, password 
FROM staff 
WHERE username = ? AND is_active = 1
```

### QUERY: Update Last Login
```sql
UPDATE users SET last_login = NOW() WHERE id = ?
UPDATE staff SET last_login = NOW() WHERE id = ?
```

---

## ⚙️ PRÓXIMAS PÁGINAS A DESARROLLAR

- [ ] cliente/crear-ticket.php
- [ ] cliente/mis-tickets.php
- [ ] cliente/ver-ticket.php
- [ ] agente/mis-tickets.php
- [ ] agente/todos-tickets.php
- [ ] agente/ver-ticket.php
- [ ] Ambos: perfil.php

---

## 📞 SOPORTE

**Problemas comunes:**

1. **Error de conexión a BD**
   → Verificar puerto 33065 en config.php

2. **CSRF token inválido**
   → Verificar que csrfField() esté en formulario

3. **Usuario no redirige a dashboard**
   → Verificar $SESSION se crea en Auth::login*()

4. **Página protected muestra error**
   → Usar requireLogin('cliente') o requireLogin('agente')

---

**Última actualización:** Enero 26, 2025
**Versión:** 1.0
