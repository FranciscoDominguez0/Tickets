# Migración a Multi-Departamento para Staff

## Resumen de Cambios

Se ha actualizado el sistema para soportar que un miembro de staff (admin/agente) pertenezca a **múltiples departamentos** simultáneamente.

## Nueva Estructura

### Tabla: `staff_departments`
```sql
CREATE TABLE IF NOT EXISTS staff_departments (
  staff_id INT NOT NULL,
  dept_id  INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (staff_id, dept_id),

  KEY idx_sd_dept_id (dept_id),
  KEY idx_sd_staff_id (staff_id),

  CONSTRAINT fk_sd_staff
    FOREIGN KEY (staff_id) REFERENCES staff(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,

  CONSTRAINT fk_sd_department
    FOREIGN KEY (dept_id) REFERENCES departments(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);
```

### Relación
- **Antes:** Un staff → Un departamento (`staff.dept_id`)
- **Ahora:** Un staff → Múltiples departamentos (`staff_departments`)

## Archivos Actualizados

### 1. **upload/scp/modules/tickets.php**
**Cambios:**
- ✅ Función `$staffBelongsToDept`: Ahora usa `staff_departments` cuando está disponible
- ✅ Lista de staff para nuevo ticket: Usa JOIN con `staff_departments`
- ✅ Opciones de asignación de staff: Filtra por departamento usando `staff_departments`
- ✅ Mantiene compatibilidad legacy con fallback a `staff.dept_id`

**Ejemplo de cambio:**
```php
// ANTES (legacy)
SELECT id, firstname, lastname, COALESCE(NULLIF(dept_id, 0), ?) AS dept_id 
FROM staff WHERE empresa_id = ? AND is_active = 1

// AHORA (nuevo modelo)
SELECT DISTINCT s.id, s.firstname, s.lastname 
FROM staff s 
LEFT JOIN staff_departments sd ON sd.staff_id = s.id 
WHERE s.empresa_id = ? AND s.is_active = 1
```

### 2. **upload/scp/modules/ticket-view.inc.php**
**Cambios:**
- ✅ Dropdown de asignación: Usa `staff_departments` para filtrar agentes
- ✅ Verificación de existencia de tabla antes de usar nuevo modelo
- ✅ Fallback a modelo legacy si `staff_departments` no existe

### 3. **upload/scp/staff.php**
**Cambios:**
- ✅ Ya tenía la lógica correcta implementada
- ✅ Usa `EXISTS` con `staff_departments` para filtros
- ✅ Muestra múltiples departamentos por staff

### 4. **upload/scp/modules/directory.php**
**Cambios:**
- ✅ Query principal: Usa LEFT JOIN con `staff_departments`
- ✅ Filtro por departamento: Usa `EXISTS` con `staff_departments`
- ✅ Query de conteo: Usa `EXISTS` con `staff_departments`
- ✅ Agrupa departamentos con `GROUP_CONCAT`

### 5. **upload/scp/modules/tasks.php**
**Cambios:**
- ✅ Lista de agentes por departamento: Usa JOIN con `staff_departments`
- ✅ Construye array `$agentsByDept` desde tabla multi-departamento

### 6. **upload/scp/departments.php**
**Cambios:**
- ✅ Listado de departamentos: JOIN con `staff_departments` para contar staff
- ✅ Validación de eliminación: Verifica `staff_departments` antes de permitir delete
- ✅ Cuenta staff usando `COUNT(DISTINCT sd.staff_id)`

### 7. **upload/scp/inc/settings_tickets.inc.php**
**Cambios:**
- ✅ Lista de agentes: Removido `dept_id` del SELECT (ya no necesario)
- ✅ Validación de staff por defecto: Usa `staff_departments` para verificar pertenencia
- ✅ Filtrado en UI: Verifica `staff_departments` antes de mostrar agente

### 8. **upload/open.php**
**Cambios:**
- ✅ Validación de staff por defecto: Usa `staff_departments` para verificar pertenencia al depto
- ✅ Mantiene fallback legacy para compatibilidad

### 9. **agente/perfil.php**
**Cambios:**
- ✅ Query de perfil: Usa LEFT JOIN con `staff_departments` y `GROUP_CONCAT`
- ✅ Muestra todos los departamentos del staff (no solo uno)

## Patrón de Migración Aplicado

En cada archivo se siguió este patrón:

```php
// 1. Verificar si existe la tabla staff_departments
$hasStaffDepartmentsTable = false;
if (isset($mysqli) && $mysqli) {
    try {
        $rt = $mysqli->query("SHOW TABLES LIKE 'staff_departments'");
        $hasStaffDepartmentsTable = ($rt && $rt->num_rows > 0);
    } catch (Throwable $e) {
        $hasStaffDepartmentsTable = false;
    }
}

// 2. Usar nuevo modelo si está disponible
if ($hasStaffDepartmentsTable) {
    // NUEVO: Usar staff_departments
    $stmt = $mysqli->prepare(
        'SELECT ... FROM staff s 
         JOIN staff_departments sd ON sd.staff_id = s.id 
         WHERE sd.dept_id = ?'
    );
} else {
    // LEGACY: Usar staff.dept_id
    $stmt = $mysqli->prepare(
        'SELECT ... FROM staff 
         WHERE dept_id = ?'
    );
}
```

## Queries SQL - Antes vs Después

### Validar si staff pertenece a departamento

**ANTES:**
```sql
SELECT COALESCE(NULLIF(dept_id, 0), ?) AS dept_id 
FROM staff 
WHERE id = ? AND empresa_id = ? AND is_active = 1
```

**DESPUÉS:**
```sql
SELECT 1 
FROM staff s 
JOIN staff_departments sd ON sd.staff_id = s.id 
WHERE s.id = ? AND s.empresa_id = ? AND s.is_active = 1 AND sd.dept_id = ?
```

### Listar staff por departamento

**ANTES:**
```sql
SELECT id, firstname, lastname 
FROM staff 
WHERE empresa_id = ? AND is_active = 1 AND dept_id = ?
```

**DESPUÉS:**
```sql
SELECT DISTINCT s.id, s.firstname, s.lastname 
FROM staff s 
JOIN staff_departments sd ON sd.staff_id = s.id 
WHERE s.empresa_id = ? AND s.is_active = 1 AND sd.dept_id = ?
```

### Contar staff por departamento

**ANTES:**
```sql
SELECT COUNT(*) c FROM staff WHERE dept_id = ?
```

**DESPUÉS:**
```sql
SELECT COUNT(DISTINCT sd.staff_id) c 
FROM staff_departments sd 
WHERE sd.dept_id = ?
```

## Compatibilidad

✅ **Compatibilidad Legacy Mantentida:**
- Todos los archivos verifican la existencia de `staff_departments`
- Si la tabla no existe, usan el modelo antiguo con `staff.dept_id`
- Esto permite migración gradual sin romper funcionalidad

## Migración de Datos

Los datos existentes ya fueron migrados con:

```sql
INSERT IGNORE INTO staff_departments (staff_id, dept_id)
SELECT s.id, s.dept_id
FROM staff s
WHERE s.dept_id IS NOT NULL AND s.dept_id > 0;
```

## Pruebas Recomendadas

1. **Asignación de Tickets:**
   - Crear ticket en departamento X
   - Verificar que aparecen solo staff del depto X
   - Asignar a staff con múltiples departamentos
   - Verificar que la asignación funciona correctamente

2. **Filtros de Listado:**
   - Ir a Directorio de Staff
   - Filtrar por departamento
   - Verificar que muestra staff correcto

3. **Permisos:**
   - Login como staff con múltiples departamentos
   - Verificar que puede ver tickets de todos sus deptos
   - Verificar que puede asignar tickets de todos sus deptos

4. **Configuración:**
   - Ir a Configuración → Tickets
   - Verificar que puede asignar staff por defecto por departamento
   - Verificar que solo muestra staff del depto correspondiente

## Notas Importantes

⚠️ **NO eliminar `staff.dept_id` todavía:**
- Mantener como fallback durante período de transición
- Eliminar solo cuando todos los archivos estén verificados
- Crear script de limpieza cuando se decida remover legacy

## Archivos que NO Requieren Cambios

Los siguientes archivos usan `dept_id` en contextos **NO relacionados con staff**:

- `tickets.dept_id` - Departamento del ticket (NO cambiar)
- `help_topics.dept_id` - Departamento del tema de ayuda (NO cambiar)
- `email_accounts.dept_id` - Departamento de cuenta de email (NO cambiar)
- `tasks.dept_id` - Departamento de tarea (NO cambiar)
- `departments.id` - ID del departamento (NO cambiar)

## Fecha de Migración
15 de abril de 2026

## Estado
✅ **COMPLETADO** - Todos los archivos críticos actualizados
