<?php
require_once 'config.php';
require_once 'includes/helpers.php';

session_start();

echo "<h2>Diagnóstico de Permisos</h2>";

// Verificar sesión
echo "<h3>Sesión actual:</h3>";
echo "staff_id: " . ($_SESSION['staff_id'] ?? 'no set') . "<br>";
echo "empresa_id: " . ($_SESSION['empresa_id'] ?? 'no set') . "<br>";

if (!isset($_SESSION['staff_id'])) {
    echo "<p style='color:red'>No hay staff_id en sesión. Por favor inicia sesión en el SCP primero.</p>";
    exit;
}

$role = getCurrentStaffRoleName();
echo "Rol desde BD: $role<br>";

$eid = empresaId();
echo "empresa_id desde función: $eid<br><br>";

// Verificar permisos
echo "<h3>Verificación de permisos:</h3>";
echo "user.view: " . (roleHasPermission('user.view') ? '<span style="color:green">true</span>' : '<span style="color:red">false</span>') . "<br>";
echo "user.manage: " . (roleHasPermission('user.manage') ? '<span style="color:green">true</span>' : '<span style="color:red">false</span>') . "<br>";
echo "org.view: " . (roleHasPermission('org.view') ? '<span style="color:green">true</span>' : '<span style="color:red">false</span>') . "<br>";
echo "org.manage: " . (roleHasPermission('org.manage') ? '<span style="color:green">true</span>' : '<span style="color:red">false</span>') . "<br>";
echo "admin.access: " . (roleHasPermission('admin.access') ? '<span style="color:green">true</span>' : '<span style="color:red">false</span>') . "<br><br>";

// Verificar qué hay en la base de datos
echo "<h3>Permisos en BD para rol '$role' (empresa_id=$eid):</h3>";
$stmt = $mysqli->prepare('SELECT perm_key, is_enabled FROM role_permissions WHERE empresa_id = ? AND role_name = ? ORDER BY perm_key');
$stmt->bind_param('is', $eid, $role);
$stmt->execute();
$result = $stmt->get_result();

$userManageFound = false;
$orgManageFound = false;

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>perm_key</th><th>is_enabled</th></tr>";
while ($row = $result->fetch_assoc()) {
    $color = $row['is_enabled'] ? 'green' : 'red';
    echo "<tr><td>{$row['perm_key']}</td><td style='color:$color'>" . ($row['is_enabled'] ? 'enabled' : 'disabled') . "</td></tr>";
    
    if ($row['perm_key'] === 'user.manage') $userManageFound = true;
    if ($row['perm_key'] === 'org.manage') $orgManageFound = true;
}
echo "</table>";

if (!$userManageFound) {
    echo "<p style='color:orange'>⚠️ El permiso 'user.manage' NO está definido en la BD para este rol.</p>";
}
if (!$orgManageFound) {
    echo "<p style='color:orange'>⚠️ El permiso 'org.manage' NO está definido en la BD para este rol.</p>";
}

echo "<br><h3>Recomendación:</h3>";
echo "<p>Si los permisos 'user.manage' u 'org.manage' no están definidos en la BD, el sistema aplicará el override de admin.access.</p>";
echo "<p>Para bloquear explícitamente estos permisos, debes:</p>";
echo "<ol>";
echo "<li>Ir a la página de configuración de roles</li>";
echo "<li>Seleccionar el rol '$role'</li>";
echo "<li>Desmarcar los permisos 'user.manage' y 'org.manage'</li>";
echo "<li>Guardar cambios</li>";
echo "</ol>";
