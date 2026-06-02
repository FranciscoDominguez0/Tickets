<?php
// Módulo: Directorio de usuarios (end users / clientes)
// Lista con búsqueda, ordenación, selección y paginación

if (!isset($_SESSION['staff_id'])) {
    http_response_code(401);
    $_SESSION['flash_error'] = 'No autorizado.';
    $to = function_exists('toAppAbsoluteUrl')
        ? toAppAbsoluteUrl('upload/scp/index.php')
        : 'index.php';
    header('Location: ' . $to);
    exit;
}

$roleName = getCurrentStaffRoleName();
$canViewUsers = roleHasPermission('user.view');
$canManageUsers = roleHasPermission('user.manage');

if (!$canViewUsers) {
    http_response_code(403);
    $_SESSION['flash_error'] = 'No tienes permiso para ver usuarios.';
    $to = function_exists('toAppAbsoluteUrl')
        ? toAppAbsoluteUrl('upload/scp/index.php')
        : 'index.php';
    header('Location: ' . $to);
    exit;
}

$eid = empresaId();

// AJAX: buscar organizaciones (Debe estar antes de cualquier salida HTML)
if (isset($_GET['ajax']) && $_GET['ajax'] === 'search_orgs' && isset($_GET['q'])) {
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');

    ensureUserOrganizationsTable($mysqli);

    $query = trim($_GET['q']);
    $excludeUserId = isset($_GET['user_id']) && is_numeric($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    $results = [];
    if (strlen($query) >= 2 && dbTableExists('organizations')) {
        $sql = "SELECT o.id, o.name FROM organizations o WHERE o.empresa_id = ? AND o.name LIKE ?";
        if ($excludeUserId > 0 && dbTableExists('user_organizations')) {
            $sql .= " AND NOT EXISTS (
                SELECT 1 FROM user_organizations uo
                WHERE uo.user_id = ? AND uo.organization_id = o.id AND uo.empresa_id = o.empresa_id
            )";
        }
        $sql .= ' ORDER BY o.name LIMIT 10';
        $stmt = $mysqli->prepare($sql);
        if ($stmt) {
            $like = '%' . $query . '%';
            if ($excludeUserId > 0 && dbTableExists('user_organizations')) {
                $stmt->bind_param('isi', $eid, $like, $excludeUserId);
            } else {
                $stmt->bind_param('is', $eid, $like);
            }
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res && ($row = $res->fetch_assoc())) {
                $results[] = [
                    'id' => (int)($row['id'] ?? 0),
                    'name' => (string)($row['name'] ?? ''),
                ];
            }
        }
    }
    echo json_encode($results, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$canManageUsers) {
    http_response_code(403);
    $_SESSION['flash_error'] = 'No tienes permiso para gestionar usuarios.';
    $to = function_exists('toAppAbsoluteUrl')
        ? toAppAbsoluteUrl('upload/scp/index.php?page=users')
        : 'index.php?page=users';
    header('Location: ' . $to);
    exit;
}

$usersHasPhone = false;
$chkPhone = $mysqli->query("SHOW COLUMNS FROM users LIKE 'phone'");
if ($chkPhone && $chkPhone->num_rows > 0) {
    $usersHasPhone = true;
}

$importFlash = null;
if (isset($_SESSION['users_import_flash']) && is_array($_SESSION['users_import_flash'])) {
    $importFlash = $_SESSION['users_import_flash'];
    unset($_SESSION['users_import_flash']);
}

// ==========================================
// EXPORTACIÓN A CSV (Antes de cualquier salida HTML/CSS)
// ==========================================

// 1. Exportar todos/filtrados
if (isset($_GET['a']) && $_GET['a'] === 'export') {
    if (ob_get_length()) {
        ob_clean();
    }

    $search = trim($_GET['q'] ?? '');
    $sort   = strtolower($_GET['sort'] ?? 'name');
    $order  = strtoupper($_GET['order'] ?? 'ASC');

    $validSorts = ['name', 'status', 'created', 'updated'];
    if (!in_array($sort, $validSorts)) $sort = 'name';
    $validOrders = ['ASC', 'DESC'];
    if (!in_array($order, $validOrders)) $order = 'ASC';

    $sortColumnsExport = [
        'name'    => 'CONCAT(u.firstname, \' \', u.lastname)',
        'status'  => 'u.status',
        'created' => 'u.created',
        'updated' => 'u.updated'
    ];
    $orderByExport = $sortColumnsExport[$sort] ?? $sortColumnsExport['name'];

    $exportSql = "
        SELECT
            u.id,
            u.email,
            u.firstname,
            u.lastname,
            u.company,
            u.status,
            u.created,
            u.updated,
            COUNT(t.id) AS ticket_count
        FROM users u
        LEFT JOIN tickets t ON t.user_id = u.id AND t.empresa_id = ?
        WHERE u.empresa_id = ?
    ";

    $exportParams = [];
    $exportTypes = 'ii';
    $exportParams[] = $eid;
    $exportParams[] = $eid;
    if ($search !== '') {
        $term = '%' . $search . '%';
        $exportSql .= " AND (u.firstname LIKE ? OR u.lastname LIKE ? OR u.email LIKE ? OR u.company LIKE ?)";
        array_push($exportParams, $term, $term, $term, $term);
        $exportTypes .= 'ssss';
    }

    $exportSql .= " GROUP BY u.id, u.email, u.firstname, u.lastname, u.company, u.status, u.created, u.updated";
    if ($usersHasPhone) {
        $exportSql .= ", u.phone";
    }
    $exportSql .= " ORDER BY $orderByExport $order";

    $stmtX = $mysqli->prepare($exportSql);
    if ($stmtX) {
        $types = $exportTypes;
        $stmtX->bind_param($types, ...$exportParams);
        $stmtX->execute();
        $resX = $stmtX->get_result();
    } else {
        header('Location: users.php');
        exit;
    }

    $ts = date('Ymd');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="users-' . $ts . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF");

    $headers = ['ID', 'Email', 'Nombre', 'Apellido'];
    if ($usersHasPhone) {
        $headers[] = 'Telefono';
    }
    $headers = array_merge($headers, ['Organizacion', 'Estado', 'Creado', 'Actualizado', 'Tickets']);
    fputcsv($out, $headers, ',', '"', '\\');

    while ($row = $resX->fetch_assoc()) {
        $line = [
            (int)($row['id'] ?? 0),
            (string)($row['email'] ?? ''),
            (string)($row['firstname'] ?? ''),
            (string)($row['lastname'] ?? '')
        ];
        if ($usersHasPhone) {
            $line[] = (string)($row['phone'] ?? '');
        }
        $line[] = (string)($row['company'] ?? '');
        $line[] = (string)($row['status'] ?? '');
        $line[] = (string)($row['created'] ?? '');
        $line[] = (string)($row['updated'] ?? '');
        $line[] = (int)($row['ticket_count'] ?? 0);
        fputcsv($out, $line, ',', '"', '\\');
    }
    fclose($out);
    exit;
}

// 2. Exportar seleccionados
if (isset($_GET['a']) && $_GET['a'] === 'export_selected') {
    if (ob_get_length()) {
        ob_clean();
    }

    $ids = [];
    if (isset($_GET['ids']) && is_array($_GET['ids'])) {
        foreach ($_GET['ids'] as $v) {
            $v = trim((string)$v);
            if (ctype_digit($v) && (int)$v > 0) $ids[] = (int)$v;
        }
    } else {
        $idsRaw = (string)($_GET['ids'] ?? '');
        foreach (explode(',', $idsRaw) as $v) {
            $v = trim((string)$v);
            if (ctype_digit($v) && (int)$v > 0) $ids[] = (int)$v;
        }
    }

    $ids = array_values(array_unique($ids));
    if (empty($ids)) {
        header('Location: users.php?msg=export_no_selection');
        exit;
    }
    if (count($ids) > 5000) {
        $ids = array_slice($ids, 0, 5000);
    }

    $sort   = strtolower($_GET['sort'] ?? 'name');
    $order  = strtoupper($_GET['order'] ?? 'ASC');
    $validSorts = ['name', 'status', 'created', 'updated'];
    if (!in_array($sort, $validSorts)) $sort = 'name';
    $validOrders = ['ASC', 'DESC'];
    if (!in_array($order, $validOrders)) $order = 'ASC';

    $sortColumnsExport = [
        'name'    => 'CONCAT(u.firstname, \' \', u.lastname)',
        'status'  => 'u.status',
        'created' => 'u.created',
        'updated' => 'u.updated'
    ];
    $orderByExport = $sortColumnsExport[$sort] ?? $sortColumnsExport['name'];

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $exportSql = "
        SELECT
            u.id,
            u.email,
            u.firstname,
            u.lastname,";
    if ($usersHasPhone) {
        $exportSql .= "\n            u.phone,";
    }
    $exportSql .= "
            u.company,
            u.status,
            u.created,
            u.updated,
            COUNT(t.id) AS ticket_count
        FROM users u
        LEFT JOIN tickets t ON t.user_id = u.id
        WHERE u.id IN ($placeholders)
        GROUP BY u.id, u.email, u.firstname, u.lastname, u.company, u.status, u.created, u.updated";
    if ($usersHasPhone) {
        $exportSql .= ", u.phone";
    }
    $exportSql .= " ORDER BY $orderByExport $order";

    $stmtX = $mysqli->prepare($exportSql);
    if ($stmtX) {
        $types = str_repeat('i', count($ids));
        $stmtX->bind_param($types, ...$ids);
        $stmtX->execute();
        $resX = $stmtX->get_result();
    } else {
        header('Location: users.php');
        exit;
    }

    $ts = date('Ymd');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="users-selected-' . $ts . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF");

    $headers = ['ID', 'Email', 'Nombre', 'Apellido'];
    if ($usersHasPhone) {
        $headers[] = 'Telefono';
    }
    $headers = array_merge($headers, ['Organizacion', 'Estado', 'Creado', 'Actualizado', 'Tickets']);
    fputcsv($out, $headers, ',', '"', '\\');

    while ($row = $resX->fetch_assoc()) {
        $line = [
            (int)($row['id'] ?? 0),
            (string)($row['email'] ?? ''),
            (string)($row['firstname'] ?? ''),
            (string)($row['lastname'] ?? '')
        ];
        if ($usersHasPhone) {
            $line[] = (string)($row['phone'] ?? '');
        }
        $line[] = (string)($row['company'] ?? '');
        $line[] = (string)($row['status'] ?? '');
        $line[] = (string)($row['created'] ?? '');
        $line[] = (string)($row['updated'] ?? '');
        $line[] = (int)($row['ticket_count'] ?? 0);
        fputcsv($out, $line, ',', '"', '\\');
    }
    fclose($out);
    exit;
}

?>
<style>
/* Estilos Premium para Modales de Usuarios (Inspirado en Organizaciones) */
.user-premium-modal .modal-content {
    border-radius: 20px !important;
    border: none !important;
    box-shadow: 0 20px 50px rgba(0,0,0,0.15) !important;
    overflow: hidden;
}
.user-premium-modal .modal-header {
    background: #f8fafc !important;
    border-bottom: 1px solid #e2e8f0 !important;
    padding: 20px 24px !important;
}
.user-premium-modal .modal-title {
    font-weight: 800 !important;
    color: #0f172a !important;
    font-size: 1.25rem;
}
.user-premium-modal .modal-body {
    padding: 24px !important;
}
.user-premium-modal .form-label {
    font-weight: 700 !important;
    font-size: 0.75rem !important;
    color: #64748b !important;
    text-transform: uppercase !important;
    letter-spacing: 0.05em !important;
    margin-bottom: 8px !important;
}
.user-premium-modal .form-control {
    border-radius: 12px !important;
    border: 1px solid #e2e8f0 !important;
    padding: 12px 16px !important;
    font-size: 0.95rem !important;
    transition: all 0.2s ease !important;
    background-color: #f8fafc !important;
}
.user-premium-modal .form-control:focus {
    background-color: #fff !important;
    border-color: #ef4444 !important;
    box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1) !important;
    outline: none;
}
.user-premium-modal .modal-footer {
    background: #f8fafc !important;
    border-top: 1px solid #e2e8f0 !important;
    padding: 20px 24px !important;
}
.user-premium-modal .btn-primary {
    background: linear-gradient(135deg, #dc2626, #ef4444) !important;
    border: none !important;
    border-radius: 12px !important;
    padding: 12px 28px !important;
    font-weight: 700 !important;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2) !important;
    transition: all 0.2s ease !important;
}
.user-premium-modal .btn-primary:hover {
    opacity: 0.9 !important;
    transform: translateY(-1px);
    box-shadow: 0 6px 15px rgba(239, 68, 68, 0.3) !important;
}

.user-premium-modal .btn-secondary {
    border-radius: 12px !important;
    padding: 12px 24px !important;
    font-weight: 600 !important;
    background: #fff !important;
    color: #64748b !important;
    border: 1px solid #e2e8f0 !important;
}
</style>
<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do']) && $_POST['do'] === 'import_users') {
    if (isset($_POST['csrf_token']) && Auth::validateCSRF($_POST['csrf_token'])) {
        $imported = 0;
        $skipped = 0;
        $failed = 0;

        $makePassword = function () {
            try {
                return bin2hex(random_bytes(6));
            } catch (Throwable $e) {
                return (string)mt_rand(100000, 999999) . (string)mt_rand(100000, 999999);
            }
        };

        $parseName = function ($nameStr) {
            $nameStr = trim((string)$nameStr);
            if ($nameStr === '') return ['', ''];
            $parts = preg_split('/\s+/', $nameStr);
            $firstname = (string)array_shift($parts);
            $lastname = trim(implode(' ', $parts));
            return [$firstname, $lastname];
        };

        $createUser = function ($email, $name, $phone, $address = '') use ($mysqli, $usersHasPhone, $makePassword, $parseName, $eid, &$imported, &$skipped, &$failed) {
            $email = trim((string)$email);
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $failed++;
                return;
            }

            [$firstname, $lastname] = $parseName($name);
            if ($firstname === '') {
                $prefix = explode('@', $email)[0] ?? '';
                $firstname = $prefix !== '' ? $prefix : 'Usuario';
            }
            if ($lastname === '') {
                $lastname = '';
            }

            $stmtC = $mysqli->prepare('SELECT id FROM users WHERE empresa_id = ? AND email = ? LIMIT 1');
            if (!$stmtC) {
                $failed++;
                return;
            }
            $stmtC->bind_param('is', $eid, $email);
            $stmtC->execute();
            if ($stmtC->get_result()->fetch_assoc()) {
                $skipped++;
                return;
            }

            $passwordPlain = $makePassword();
            $hash = password_hash($passwordPlain, PASSWORD_BCRYPT);
            $status = 'active';

            if ($usersHasPhone) {
                $phoneVal = trim((string)$phone);
                $phoneVal = $phoneVal !== '' ? $phoneVal : null;
                $stmtI = $mysqli->prepare('INSERT INTO users (empresa_id, email, address, password, firstname, lastname, phone, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                if (!$stmtI) {
                    $failed++;
                    return;
                }
                $stmtI->bind_param('isssssss', $eid, $email, $address, $hash, $firstname, $lastname, $phoneVal, $status);
            } else {
                $stmtI = $mysqli->prepare('INSERT INTO users (empresa_id, email, address, password, firstname, lastname, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
                if (!$stmtI) {
                    $failed++;
                    return;
                }
                $stmtI->bind_param('issssss', $eid, $email, $address, $hash, $firstname, $lastname, $status);
            }

            if ($stmtI->execute()) {
                $imported++;
            } else {
                $failed++;
            }
        };

        $hasFile = isset($_FILES['import_file']) && is_array($_FILES['import_file']) && (int)($_FILES['import_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
        $pasted = trim((string)($_POST['pasted'] ?? ''));

        if ($hasFile) {
            $tmp = (string)($_FILES['import_file']['tmp_name'] ?? '');
            if ($tmp && is_readable($tmp)) {
                $fh = fopen($tmp, 'r');
                if ($fh) {
                    $header = fgetcsv($fh);
                    $map = [];
                    if (is_array($header)) {
                        foreach ($header as $idx => $h) {
                            $key = strtolower(trim((string)$h));
                            $map[$key] = (int)$idx;
                        }
                    }

                    while (($row = fgetcsv($fh)) !== false) {
                        if (!is_array($row)) continue;
                        $email = '';
                        $name = '';
                        $phone = '';

                        if (isset($map['email'])) $email = (string)($row[$map['email']] ?? '');
                        if (isset($map['name'])) $name = (string)($row[$map['name']] ?? '');
                        if (isset($map['phone'])) $phone = (string)($row[$map['phone']] ?? '');

                        // fallback por posición si no hay header esperado
                        if ($email === '' && isset($row[0])) $email = (string)$row[0];
                        if ($name === '' && isset($row[1])) $name = (string)$row[1];

                        $address = '';
                        if (isset($map['address'])) $address = (string)($row[$map['address']] ?? '');

                        $createUser($email, $name, $phone, $address);
                    }
                    fclose($fh);
                }
            }
        } elseif ($pasted !== '') {
            $lines = preg_split('/\r\n|\r|\n/', $pasted);
            foreach ($lines as $line) {
                $line = trim((string)$line);
                if ($line === '') continue;
                // Formato: "Nombre Apellido, email" o "email, Nombre"
                $parts = array_map('trim', explode(',', $line));
                if (count($parts) >= 2) {
                    $a = (string)$parts[0];
                    $b = (string)$parts[1];
                    if (filter_var($b, FILTER_VALIDATE_EMAIL)) {
                        $createUser($b, $a, '', '');
                    } elseif (filter_var($a, FILTER_VALIDATE_EMAIL)) {
                        $createUser($a, $b, '', '');
                    } else {
                        $failed++;
                    }
                } else {
                    // si viene solo email
                    if (filter_var($line, FILTER_VALIDATE_EMAIL)) {
                        $createUser($line, '', '', '');
                    } else {
                        $failed++;
                    }
                }
            }
        } else {
            $failed++;
        }

        $_SESSION['users_import_flash'] = [
            'imported' => $imported,
            'skipped' => $skipped,
            'failed' => $failed,
        ];
        header('Location: users.php?msg=import_done');
        exit;
    }
}

// Añadir usuario (registro directo, sin confirmación por correo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do']) && $_POST['do'] === 'add') {
    $add_errors = [];
    $email      = trim($_POST['email'] ?? '');
    $firstname  = trim($_POST['firstname'] ?? '');
    $lastname   = trim($_POST['lastname'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $company    = '';
    $password   = $_POST['password'] ?? '';
    $password2  = $_POST['password2'] ?? '';
    $status     = 'active';

    if (!$email) $add_errors[] = 'El email es obligatorio.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $add_errors[] = 'Email no válido.';
    if (!$firstname) $add_errors[] = 'El nombre es obligatorio.';
    if (!$lastname) $add_errors[] = 'El apellido es obligatorio.';
    if (!$address) $add_errors[] = 'La dirección es obligatoria.';
    if (strlen($password) < 6) $add_errors[] = 'La contraseña debe tener al menos 6 caracteres.';
    elseif ($password !== $password2) $add_errors[] = 'Las contraseñas no coinciden.';

    if (empty($add_errors)) {
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE empresa_id = ? AND email = ?");
        $stmt->bind_param('is', $eid, $email);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            $add_errors[] = 'Ya existe un usuario con ese email.';
        }
    }

    if (empty($add_errors)) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        if ($usersHasPhone) {
            $phoneVal = $phone !== '' ? $phone : null;
            $stmt = $mysqli->prepare("INSERT INTO users (empresa_id, email, address, password, firstname, lastname, phone, company, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('issssssss', $eid, $email, $address, $hash, $firstname, $lastname, $phoneVal, $company, $status);
        } else {
            $stmt = $mysqli->prepare("INSERT INTO users (empresa_id, email, address, password, firstname, lastname, company, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('isssssss', $eid, $email, $address, $hash, $firstname, $lastname, $company, $status);
        }
        if ($stmt->execute()) {
            header('Location: users.php?added=1');
            exit;
        }
        $add_errors[] = 'Error al guardar. Inténtalo de nuevo.';
    }
}

$add_errors = $add_errors ?? [];

// Eliminar usuario (POST con confirmación desde modal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do']) && $_POST['do'] === 'delete' && isset($_POST['id']) && is_numeric($_POST['id'])) {
    if (isset($_POST['csrf_token']) && Auth::validateCSRF($_POST['csrf_token'])) {
        $del_id = (int) $_POST['id'];
        $stmt = $mysqli->prepare("DELETE FROM users WHERE id = ? AND empresa_id = ?");
        $stmt->bind_param('ii', $del_id, $eid);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            header('Location: users.php?msg=user_deleted');
            exit;
        }
    }
}

// Asignar organización a usuario (puede tener varias)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do']) && $_POST['do'] === 'assign_org' && isset($_POST['user_id'])) {
    if (isset($_POST['csrf_token']) && Auth::validateCSRF($_POST['csrf_token'])) {
        $user_id = (int)$_POST['user_id'];
        $org_id = (int)($_POST['organization_id'] ?? 0);
        $org_name = trim((string)($_POST['org_name'] ?? ''));

        if ($org_id <= 0 && $org_name !== '' && dbTableExists('organizations')) {
            $stmt = $mysqli->prepare('SELECT id FROM organizations WHERE empresa_id = ? AND name = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('is', $eid, $org_name);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $org_id = (int)($row['id'] ?? 0);
            }
        }

        if ($user_id > 0 && $org_id > 0) {
            $before = getUserOrganizations($mysqli, $user_id, $eid);
            $had = false;
            foreach ($before as $o) {
                if ((int)($o['organization_id'] ?? 0) === $org_id) {
                    $had = true;
                    break;
                }
            }
            if (addUserToOrganization($mysqli, $user_id, $org_id, $eid)) {
                $msg = $had ? 'org_already' : 'org_assigned';
                header('Location: users.php?id=' . $user_id . '&msg=' . $msg);
                exit;
            }
        }
        header('Location: users.php?id=' . $user_id . '&msg=org_error');
        exit;
    }
}

// Activar / desactivar vista de tickets por organización (portal cliente)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do']) && $_POST['do'] === 'toggle_org_tickets_view' && isset($_POST['user_id'])) {
    if (isset($_POST['csrf_token']) && Auth::validateCSRF($_POST['csrf_token'])) {
        $user_id = (int)$_POST['user_id'];
        $enable = isset($_POST['enable']) && (string)$_POST['enable'] === '1';
        if ($user_id > 0 && setUserOrgTicketsView($mysqli, $user_id, $eid, $enable)) {
            header('Location: users.php?id=' . $user_id . '&msg=' . ($enable ? 'org_view_on' : 'org_view_off'));
            exit;
        }
        header('Location: users.php?id=' . $user_id . '&msg=org_view_error');
        exit;
    }
}

// Remover una organización del usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do']) && $_POST['do'] === 'remove_org' && isset($_POST['user_id'])) {
    if (isset($_POST['csrf_token']) && Auth::validateCSRF($_POST['csrf_token'])) {
        $user_id = (int)$_POST['user_id'];
        $org_id = (int)($_POST['organization_id'] ?? 0);
        if ($user_id > 0 && $org_id > 0 && removeUserFromOrganization($mysqli, $user_id, $org_id, $eid)) {
            header('Location: users.php?id=' . $user_id . '&msg=org_removed');
            exit;
        }
        header('Location: users.php?id=' . $user_id . '&msg=org_error');
        exit;
    }
}

// Cambiar estado de usuario (activo/inactivo/baneado)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do']) && $_POST['do'] === 'update_status' && isset($_POST['user_id']) && is_numeric($_POST['user_id'])) {
    if (isset($_POST['csrf_token']) && Auth::validateCSRF($_POST['csrf_token'])) {
        $user_id = (int) $_POST['user_id'];
        $new_status = $_POST['status'] ?? '';
        if (!in_array($new_status, ['active', 'inactive', 'banned'], true)) {
            $new_status = 'inactive';
        }
        $stmt = $mysqli->prepare("UPDATE users SET status = ?, updated = NOW() WHERE id = ? AND empresa_id = ?");
        $stmt->bind_param('sii', $new_status, $user_id, $eid);
        if ($stmt->execute()) {
            header('Location: users.php?id=' . $user_id . '&msg=status_updated');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do']) && $_POST['do'] === 'update_profile' && isset($_POST['user_id']) && is_numeric($_POST['user_id'])) {
    if (isset($_POST['csrf_token']) && Auth::validateCSRF($_POST['csrf_token'])) {
        $user_id = (int) $_POST['user_id'];
        $email = trim($_POST['email'] ?? '');
        $firstname = trim($_POST['firstname'] ?? '');
        $lastname = trim($_POST['lastname'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');

        $edit_errors = [];
        if ($email === '') {
            $edit_errors[] = 'El email es obligatorio.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $edit_errors[] = 'Email no válido.';
        }
        if ($firstname === '') $edit_errors[] = 'El nombre es obligatorio.';
        if ($lastname === '') $edit_errors[] = 'El apellido es obligatorio.';
        if ($address === '') $edit_errors[] = 'La dirección es obligatoria.';

        if (empty($edit_errors)) {
            $stmtE = $mysqli->prepare('SELECT id FROM users WHERE empresa_id = ? AND email = ? AND id <> ? LIMIT 1');
            if ($stmtE) {
                $stmtE->bind_param('isi', $eid, $email, $user_id);
                if ($stmtE->execute()) {
                    if ($stmtE->get_result()->fetch_assoc()) {
                        $edit_errors[] = 'Ya existe otro usuario con ese email.';
                    }
                }
            }
        }

        if (empty($edit_errors)) {
            if ($usersHasPhone) {
                $phoneVal = $phone !== '' ? $phone : null;
                $stmtU = $mysqli->prepare('UPDATE users SET email = ?, firstname = ?, lastname = ?, phone = ?, address = ?, updated = NOW() WHERE id = ? AND empresa_id = ?');
                if ($stmtU) {
                    $stmtU->bind_param('sssssii', $email, $firstname, $lastname, $phoneVal, $address, $user_id, $eid);
                    if ($stmtU->execute()) {
                        header('Location: users.php?id=' . $user_id . '&msg=user_updated');
                        exit;
                    }
                }
            } else {
                $stmtU = $mysqli->prepare('UPDATE users SET email = ?, firstname = ?, lastname = ?, address = ?, updated = NOW() WHERE id = ? AND empresa_id = ?');
                if ($stmtU) {
                    $stmtU->bind_param('ssssii', $email, $firstname, $lastname, $address, $user_id, $eid);
                    if ($stmtU->execute()) {
                        header('Location: users.php?id=' . $user_id . '&msg=user_updated');
                        exit;
                    }
                }
            }
            $edit_errors[] = 'Error al actualizar el usuario.';
        }
        $add_errors = array_merge($add_errors ?? [], $edit_errors);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do']) && $_POST['do'] === 'add_user_note' && isset($_POST['user_id']) && is_numeric($_POST['user_id'])) {
    if (isset($_POST['csrf_token']) && Auth::validateCSRF($_POST['csrf_token'])) {
        $user_id = (int)$_POST['user_id'];
        $note = trim((string)($_POST['note'] ?? ''));
        if ($note !== '') {
            $staff_id = isset($_SESSION['staff_id']) && is_numeric($_SESSION['staff_id']) ? (int)$_SESSION['staff_id'] : null;
            if ($staff_id === 0) $staff_id = null;
            $stmtN = $mysqli->prepare('INSERT INTO user_notes (empresa_id, user_id, staff_id, note, created) VALUES (?, ?, ?, ?, NOW())');
            if ($stmtN) {
                $stmtN->bind_param('iiis', $eid, $user_id, $staff_id, $note);
                $stmtN->execute();
            }
        }
        header('Location: users.php?id=' . $user_id . '&t=notes');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do']) && $_POST['do'] === 'update_user_note' && isset($_POST['user_id']) && is_numeric($_POST['user_id']) && isset($_POST['note_id']) && is_numeric($_POST['note_id'])) {
    if (isset($_POST['csrf_token']) && Auth::validateCSRF($_POST['csrf_token'])) {
        $user_id = (int)$_POST['user_id'];
        $note_id = (int)$_POST['note_id'];
        $note = trim((string)($_POST['note'] ?? ''));
        if ($note !== '') {
            $stmtU = $mysqli->prepare('UPDATE user_notes SET note = ?, updated = NOW() WHERE id = ? AND user_id = ? AND empresa_id = ?');
            if ($stmtU) {
                $stmtU->bind_param('siii', $note, $note_id, $user_id, $eid);
                $stmtU->execute();
            }
        }
        header('Location: users.php?id=' . $user_id . '&t=notes');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do']) && $_POST['do'] === 'delete_user_note' && isset($_POST['user_id']) && is_numeric($_POST['user_id']) && isset($_POST['note_id']) && is_numeric($_POST['note_id'])) {
    if (isset($_POST['csrf_token']) && Auth::validateCSRF($_POST['csrf_token'])) {
        $user_id = (int)$_POST['user_id'];
        $note_id = (int)$_POST['note_id'];
        $stmtD = $mysqli->prepare('DELETE FROM user_notes WHERE id = ? AND user_id = ? AND empresa_id = ?');
        if ($stmtD) {
            $stmtD->bind_param('iii', $note_id, $user_id, $eid);
            $stmtD->execute();
        }
        header('Location: users.php?id=' . $user_id . '&t=notes');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do']) && $_POST['do'] === 'send_user_reset' && isset($_POST['user_id']) && is_numeric($_POST['user_id'])) {
    if (isset($_POST['csrf_token']) && Auth::validateCSRF($_POST['csrf_token'])) {
        $user_id = (int)$_POST['user_id'];
        $tab = isset($_POST['tab']) ? trim((string)$_POST['tab']) : '';
        $tabParam = ($tab !== '') ? ('&t=' . urlencode($tab)) : '';

        $mysqli->query(
            "CREATE TABLE IF NOT EXISTS password_resets (\n"
            . "  id INT PRIMARY KEY AUTO_INCREMENT,\n"
            . "  user_id INT NOT NULL,\n"
            . "  token_hash CHAR(64) NOT NULL,\n"
            . "  expires_at DATETIME NOT NULL,\n"
            . "  used_at DATETIME NULL,\n"
            . "  created DATETIME DEFAULT CURRENT_TIMESTAMP,\n"
            . "  KEY idx_user_id (user_id),\n"
            . "  KEY idx_token_hash (token_hash),\n"
            . "  KEY idx_expires (expires_at),\n"
            . "  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $stmtU = $mysqli->prepare('SELECT id, email, firstname, lastname FROM users WHERE id = ? AND empresa_id = ? LIMIT 1');
        if ($stmtU) {
            $stmtU->bind_param('ii', $user_id, $eid);
            $stmtU->execute();
            $userRow = $stmtU->get_result()->fetch_assoc();
            if ($userRow) {
                $user_id = (int)($userRow['id'] ?? 0);
            }
        }
        // Invalidar tokens pendientes anteriores (dejar un solo enlace activo)
        $stmtInv = $mysqli->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL");
        if ($stmtInv) {
            $uid = (int)($userRow['id'] ?? 0);
            $stmtInv->bind_param('i', $uid);
            $stmtInv->execute();
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + 3600);

        $stmtIns = $mysqli->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)');
        if ($stmtIns) {
            $uid = (int)($userRow['id'] ?? 0);
            $stmtIns->bind_param('iss', $uid, $tokenHash, $expiresAt);
            $stmtIns->execute();
        }

        $resetUrl = rtrim(APP_URL, '/') . '/upload/reset.php?token=' . urlencode($token);
        $name = trim(((string)($userRow['firstname'] ?? '')) . ' ' . ((string)($userRow['lastname'] ?? '')));
        if ($name === '') $name = (string)($userRow['email'] ?? '');

        $subject = 'Restablecer contraseña - ' . APP_NAME;
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');

        $bodyHtml = ''
            . '<div style="font-family:Segoe UI, Tahoma, Arial, sans-serif; background:#f1f5f9; padding:24px;">'
            . '  <div style="max-width:640px; margin:0 auto;">'
            . '    <div style="background:linear-gradient(135deg,#0f172a,#1d4ed8); color:#ffffff; border-radius:16px; padding:18px 20px;">'
            . '      <div style="font-size:14px; font-weight:800; letter-spacing:.02em; opacity:.95;">' . htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') . '</div>'
            . '      <div style="font-size:22px; font-weight:900; margin-top:4px;">Restablecer contraseña</div>'
            . '    </div>'
            . '    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:16px; padding:18px 20px; margin-top:12px;">'
            . '      <p style="margin:0 0 10px 0; color:#0f172a; font-size:14px;">Hola <strong>' . $safeName . '</strong>,</p>'
            . '      <p style="margin:0 0 10px 0; color:#334155; font-size:14px; line-height:1.5;">Recibimos una solicitud para restablecer la contraseña de tu cuenta. Para continuar, haz clic en el siguiente botón:</p>'
            . '      <p style="margin:14px 0 12px 0;">'
            . '        <a href="' . $safeUrl . '" style="display:inline-block; background:#2563eb; color:#ffffff; text-decoration:none; padding:11px 16px; border-radius:12px; font-weight:800;">Restablecer contraseña</a>'
            . '      </p>'
            . '      <div style="margin:0 0 10px 0; color:#64748b; font-size:12px; line-height:1.5;">Este enlace vence en <strong>1 hora</strong> por seguridad.</div>'
            . '      <div style="margin:0; color:#64748b; font-size:12px; line-height:1.5;">Si no solicitaste este cambio, puedes ignorar este correo. Tu contraseña no se modificará.</div>'
            . '      <hr style="border:0; border-top:1px solid #e2e8f0; margin:14px 0;">'
            . '      <div style="color:#94a3b8; font-size:11px; line-height:1.5;">Si el botón no funciona, copia y pega este enlace en tu navegador:<br><span style="word-break:break-all;">' . $safeUrl . '</span></div>'
            . '    </div>'
            . '  </div>'
            . '</div>';

        $bodyText = "Hola $name,\n\n" .
            "Recibimos una solicitud para restablecer la contraseña de tu cuenta.\n\n" .
            "Enlace para restablecer contraseña (vence en 1 hora):\n$resetUrl\n\n" .
            "Si no solicitaste este cambio, puedes ignorar este correo.";

        Mailer::send((string)$userRow['email'], $subject, $bodyHtml, $bodyText);
    }

    header('Location: users.php?id=' . $user_id . $tabParam . '&msg=reset_sent');
    exit;
}


// Vista de un usuario concreto (users.php?id=X)
$viewUser = null;
$usersHasOrgTicketsView = ensureUserOrgTicketsViewColumn($mysqli);
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $uid = (int) $_GET['id'];
    $orgViewCol = $usersHasOrgTicketsView ? ', org_tickets_view' : '';
    if ($usersHasPhone) {
        $stmt = $mysqli->prepare("SELECT id, email, address, firstname, lastname, phone, company, status, created, updated{$orgViewCol} FROM users WHERE id = ? AND empresa_id = ?");
    } else {
        $stmt = $mysqli->prepare("SELECT id, email, address, firstname, lastname, company, status, created, updated{$orgViewCol} FROM users WHERE id = ? AND empresa_id = ?");
    }
    $stmt->bind_param('ii', $uid, $eid);
    $stmt->execute();
    $viewUser = $stmt->get_result()->fetch_assoc();
}

if ($viewUser) {
    $uid2 = (int)($viewUser['id'] ?? 0);

    // Contar tickets del usuario para paginación
    $stmtCountT = $mysqli->prepare("SELECT COUNT(id) AS total FROM tickets WHERE empresa_id = ? AND user_id = ?");
    $stmtCountT->bind_param('ii', $eid, $uid2);
    $stmtCountT->execute();
    $userTicketTotal = (int) $stmtCountT->get_result()->fetch_assoc()['total'];

    $perPageLimit = 10;
    $tp = max(1, (int)($_GET['tp'] ?? 1));
    $tTotalPages = $userTicketTotal ? (int)ceil($userTicketTotal / $perPageLimit) : 1;
    $tp = min($tp, max(1, $tTotalPages));
    $tOffset = ($tp - 1) * $perPageLimit;

    // Obtener los tickets paginados
    $stmt = $mysqli->prepare("SELECT t.id, t.ticket_number, t.subject, t.created, s.name as status_name, s.color as status_color FROM tickets t LEFT JOIN ticket_status s ON s.id = t.status_id WHERE t.empresa_id = ? AND t.user_id = ? ORDER BY t.created DESC LIMIT ? OFFSET ?");
    $stmt->bind_param('iiii', $eid, $uid2, $perPageLimit, $tOffset);
    $stmt->execute();
    $userTickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $userNotes = [];
    $stmtN = $mysqli->prepare(
        "SELECT n.id, n.note, n.created, n.updated, n.staff_id, "
        . "CONCAT(COALESCE(st.firstname,''), ' ', COALESCE(st.lastname,'')) AS staff_name "
        . "FROM user_notes n "
        . "LEFT JOIN staff st ON st.id = n.staff_id "
        . "WHERE n.user_id = ? AND n.empresa_id = ? "
        . "ORDER BY n.created DESC"
    );
    if ($stmtN) {
        $stmtN->bind_param('ii', $viewUser['id'], $eid);
        $stmtN->execute();
        $userNotes = $stmtN->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    $statusLabels = ['active' => 'Activo', 'inactive' => 'Inactivo', 'banned' => 'Bloqueado'];
    $viewUserName = trim($viewUser['firstname'] . ' ' . $viewUser['lastname']) ?: $viewUser['email'];
    ensureUserOrganizationsTable($mysqli);
    $viewUserOrgTicketsView = $usersHasOrgTicketsView && ((int)($viewUser['org_tickets_view'] ?? 0) === 1);
    $viewUserOrganizations = getUserOrganizations($mysqli, $uid2, $eid);
    if (empty($viewUserOrganizations) && trim((string)($viewUser['company'] ?? '')) !== '' && dbTableExists('organizations')) {
        $legacyName = trim((string)$viewUser['company']);
        $stmtLeg = $mysqli->prepare('SELECT id, name FROM organizations WHERE empresa_id = ? AND name = ? LIMIT 1');
        if ($stmtLeg) {
            $stmtLeg->bind_param('is', $eid, $legacyName);
            if ($stmtLeg->execute()) {
                $leg = $stmtLeg->get_result()->fetch_assoc();
                if ($leg && addUserToOrganization($mysqli, $uid2, (int)$leg['id'], $eid)) {
                    $viewUserOrganizations = getUserOrganizations($mysqli, $uid2, $eid);
                }
            }
        }
    }
    require __DIR__ . '/user-view.inc.php';
    return;
}

$search   = trim($_GET['q'] ?? '');
$sort     = strtolower($_GET['sort'] ?? 'name');
$order    = strtoupper($_GET['order'] ?? 'ASC');
$pageNum  = max(1, (int)($_GET['p'] ?? 1));
$perPage  = 10;

$validSorts = ['name', 'status', 'created', 'updated'];
if (!in_array($sort, $validSorts)) $sort = 'name';
$validOrders = ['ASC', 'DESC'];
if (!in_array($order, $validOrders)) $order = 'ASC';



// Cambiar organización (bulk): validar selección (sin depender de JS)
if (isset($_GET['a']) && $_GET['a'] === 'bulk_org') {
    $ids = [];
    if (isset($_GET['ids']) && is_array($_GET['ids'])) {
        foreach ($_GET['ids'] as $v) {
            $v = trim((string)$v);
            if (ctype_digit($v) && (int)$v > 0) $ids[] = (int)$v;
        }
    }
    $ids = array_values(array_unique($ids));
    if (empty($ids)) {
        header('Location: users.php?msg=org_bulk_no_selection');
        exit;
    }
    $_SESSION['bulk_org_ids'] = $ids;
    header('Location: users.php?msg=bulk_org_pick');
    exit;
}

// Asignar organización masivo (lista de usuarios)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do']) && $_POST['do'] === 'bulk_assign_org') {
    if (isset($_POST['csrf_token']) && Auth::validateCSRF($_POST['csrf_token'])) {
        $org_name = trim((string)($_POST['org_name'] ?? ''));
        $ids = [];

        if (isset($_POST['ids']) && is_array($_POST['ids'])) {
            foreach ($_POST['ids'] as $v) {
                $v = trim((string)$v);
                if (ctype_digit($v) && (int)$v > 0) $ids[] = (int)$v;
            }
        } elseif (isset($_SESSION['bulk_org_ids']) && is_array($_SESSION['bulk_org_ids'])) {
            foreach ($_SESSION['bulk_org_ids'] as $v) {
                $v = (int)$v;
                if ($v > 0) $ids[] = $v;
            }
        }

        $ids = array_values(array_unique($ids));
        if ($org_name === '' || empty($ids)) {
            header('Location: users.php?msg=org_bulk_error');
            exit;
        }

        $stmtO = $mysqli->prepare("SELECT id FROM organizations WHERE empresa_id = ? AND name = ? LIMIT 1");
        if (!$stmtO) {
            header('Location: users.php?msg=org_bulk_error');
            exit;
        }
        $stmtO->bind_param('is', $eid, $org_name);
        $stmtO->execute();
        $orgRow = $stmtO->get_result()->fetch_assoc();
        if (!$orgRow) {
            header('Location: users.php?msg=org_bulk_error');
            exit;
        }
        $orgIdBulk = (int)($orgRow['id'] ?? 0);
        ensureUserOrganizationsTable($mysqli);
        $affected = 0;
        foreach ($ids as $uidBulk) {
            if (addUserToOrganization($mysqli, (int)$uidBulk, $orgIdBulk, $eid)) {
                $affected++;
            }
        }
        unset($_SESSION['bulk_org_ids']);
        header('Location: users.php?msg=org_bulk_assigned&count=' . $affected);
        exit;
    }
}



// Contar total (con filtro de búsqueda)
$countSql = "SELECT COUNT(*) AS total FROM users WHERE empresa_id = ?";
$countParams = [];
$countTypes = 'i';
$countParams[] = $eid;
if ($search !== '') {
    $term = '%' . $search . '%';
    $countSql .= " AND (firstname LIKE ? OR lastname LIKE ? OR email LIKE ? OR company LIKE ?)";
    $countParams = [$term, $term, $term, $term];
    $countTypes .= 'ssss';
    array_unshift($countParams, $eid);
}
$countStmt = $mysqli->prepare($countSql);
if (!empty($countParams)) $countStmt->bind_param($countTypes, ...$countParams);
$countStmt->execute();
$totalRows = (int) $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = $totalRows ? (int) ceil($totalRows / $perPage) : 1;
$pageNum = min($pageNum, max(1, $totalPages));
$offset = ($pageNum - 1) * $perPage;

// Mapeo de ordenación
$sortColumns = [
    'name'    => 'CONCAT(u.firstname, \' \', u.lastname)',
    'status'  => 'u.status',
    'created' => 'u.created',
    'updated' => 'u.updated'
];
$orderBy = $sortColumns[$sort] ?? $sortColumns['name'];

$sql = "
    SELECT 
        u.id,
        u.email,
        u.firstname,
        u.lastname,
        u.company,
        u.status,
        u.created,
        u.updated,
        COUNT(t.id) AS ticket_count
    FROM users u
    LEFT JOIN tickets t ON t.user_id = u.id AND t.empresa_id = ?
    WHERE u.empresa_id = ?
";
$params = [];
$types = 'ii';
$params[] = $eid;
$params[] = $eid;
if ($search !== '') {
    $term = '%' . $search . '%';
    $sql .= " AND (u.firstname LIKE ? OR u.lastname LIKE ? OR u.email LIKE ? OR u.company LIKE ?)";
    array_push($params, $term, $term, $term, $term);
    $types .= 'ssss';
}
$sql .= " GROUP BY u.id, u.email, u.firstname, u.lastname, u.company, u.status, u.created, u.updated";
$sql .= " ORDER BY $orderBy $order LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$types .= 'ii';

$stmt = $mysqli->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

$baseUrl = 'users.php?';
$queryParams = [];
if ($search !== '') $queryParams['q'] = $search;
$currentSort = $sort;
$currentOrder = $order;
$nextOrder = $order === 'ASC' ? 'DESC' : 'ASC';

$statusLabels = [
    'active'   => 'Activo',
    'inactive' => 'Inactivo',
    'banned'   => 'Bloqueado'
];
$statusBadges = [
    'active'   => 'user-status-active',
    'inactive' => 'user-status-inactive',
    'banned'   => 'user-status-banned'
];
?>

<div class="users-directory">
    <?php /* Los errores de add_errors se muestran dentro del modal (ver #modalAddUser) */ ?>
    <?php if ($importFlash && isset($_GET['msg']) && $_GET['msg'] === 'import_done'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Importación completada. Importados: <?php echo (int)($importFlash['imported'] ?? 0); ?>.
            Omitidos: <?php echo (int)($importFlash['skipped'] ?? 0); ?>.
            Errores: <?php echo (int)($importFlash['failed'] ?? 0); ?>.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <script>
        (function(){
            try {
                var url = new URL(window.location.href);
                url.searchParams.delete('msg');
                history.replaceState(null, '', url.toString());
            } catch (e) {}
        })();
        </script>
    <?php endif; ?>
    <?php if (isset($_GET['added']) && $_GET['added'] === '1'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Usuario añadido correctamente. El usuario puede iniciar sesión de inmediato (no requiere confirmación por correo).
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'user_deleted'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Usuario eliminado correctamente.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'org_assigned'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Organización asignada correctamente.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'org_removed'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Organización removida correctamente.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'export_no_selection'): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            Debes seleccionar al menos un usuario para exportar.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'org_bulk_no_selection'): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            Debes seleccionar al menos un usuario para cambiar la organización.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'org_bulk_assigned'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Organización asignada correctamente.
            <?php if (isset($_GET['count']) && is_numeric($_GET['count'])): ?>
                Se actualizaron <?php echo (int)$_GET['count']; ?> usuarios.
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <script>
        (function(){
            try {
                var url = new URL(window.location.href);
                url.searchParams.delete('msg');
                url.searchParams.delete('count');
                history.replaceState(null, '', url.toString());
            } catch (e) {}
        })();
        </script>
    <?php endif; ?>
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'org_bulk_error'): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            No se pudo cambiar la organización.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <script>
        (function(){
            try {
                var url = new URL(window.location.href);
                url.searchParams.delete('msg');
                history.replaceState(null, '', url.toString());
            } catch (e) {}
        })();
        </script>
    <?php endif; ?>

    <!-- Búsqueda -->
    <div class="search-card">
        <form method="GET" action="users.php" class="search-form">
            <?php if ($currentSort !== 'name' || $currentOrder !== 'ASC'): ?>
                <input type="hidden" name="sort" value="<?php echo html($currentSort); ?>">
                <input type="hidden" name="order" value="<?php echo html($currentOrder); ?>">
            <?php endif; ?>
            <div class="search-wrap">
                <div class="search-icon-wrap">
                    <input type="text"
                           name="q"
                           class="form-control"
                           placeholder="Buscar por nombre, email o empresa..."
                           value="<?php echo html($search); ?>">
                </div>
                <button type="submit" class="btn btn-search">
                    <i class="bi bi-search"></i> Buscar
                </button>
            </div>
        </form>
    </div>

    <!-- Título y acciones -->
    <div class="page-header">
        <h1 class="page-title">Directorio de usuarios</h1>
        <div class="header-actions">
            <button type="button" class="btn btn-add" data-bs-toggle="modal" data-bs-target="#modalAddUser"><i class="bi bi-plus-lg"></i> Añadir usuario</button>
            <a href="javascript:void(0)" class="btn btn-import" data-bs-toggle="modal" data-bs-target="#modalImportUsers"><i class="bi bi-upload"></i><span class="d-none d-md-inline ms-1">Importar</span></a>
            <div class="dropdown">
                <button class="btn btn-more" type="button" data-bs-toggle="dropdown" style="padding-right: 12px;">
                    <i class="bi bi-gear"></i><span class="d-none d-md-inline ms-1">Más <i class="bi bi-chevron-down" style="font-size:0.7rem;"></i></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><button type="submit" form="usersListForm" name="a" value="export_selected" class="dropdown-item">Exportar seleccionados</button></li>
                    <li><button type="submit" form="usersListForm" name="a" value="bulk_org" class="dropdown-item">Cambiar organización</button></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="modal fade" id="bulkOrgModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title"><i class="bi bi-building me-2"></i>Asignar organización</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form method="post" action="users.php">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="bulkOrgSearch" class="form-label">Buscar organización</label>
                            <input type="text" class="form-control" id="bulkOrgSearch" name="org_name" placeholder="Escribe el nombre de la organización..." autocomplete="off">
                            <div id="bulkOrgSuggestions" class="list-group mt-2" style="max-height: 200px; overflow-y: auto;"></div>
                        </div>
                        <input type="hidden" name="do" value="bulk_assign_org">
                        <?php if (isset($_SESSION['bulk_org_ids']) && is_array($_SESSION['bulk_org_ids'])): ?>
                            <?php foreach ($_SESSION['bulk_org_ids'] as $sid): ?>
                                <input type="hidden" name="ids[]" value="<?php echo (int)$sid; ?>">
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                    </div>
                    <div class="modal-footer border-top">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check me-1"></i> Asignar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'bulk_org_pick'): ?>
        <script>
        (function(){
            function openModal(){
                try {
                    var el = document.getElementById('bulkOrgModal');
                    if (el && window.bootstrap && window.bootstrap.Modal) {
                        window.bootstrap.Modal.getOrCreateInstance(el).show();
                    }
                } catch (e) {}
            }

            function wireAutocomplete(){
                try {
                    var input = document.getElementById('bulkOrgSearch');
                    var suggestions = document.getElementById('bulkOrgSuggestions');
                    if (!input || !suggestions) return;
                    var lastController = null;
                    input.addEventListener('input', function(){
                        var query = (input.value || '').toString().trim();
                        if (query.length < 2) {
                            suggestions.innerHTML = '';
                            return;
                        }
                        if (lastController && typeof lastController.abort === 'function') lastController.abort();
                        lastController = (typeof AbortController !== 'undefined') ? new AbortController() : null;
                        var url = 'users.php?ajax=search_orgs&q=' + encodeURIComponent(query);
                        fetch(url, lastController ? { signal: lastController.signal } : undefined)
                          .then(function(r){ return r.json(); })
                          .then(function(data){
                              suggestions.innerHTML = '';
                              if (!Array.isArray(data)) return;
                              data.forEach(function(org){
                                  var item = document.createElement('a');
                                  item.href = '#';
                                  item.className = 'list-group-item list-group-item-action';
                                  item.textContent = (org && org.name ? org.name : '').toString();
                                  item.addEventListener('click', function(ev){
                                      ev.preventDefault();
                                      input.value = item.textContent;
                                      suggestions.innerHTML = '';
                                  });
                                  suggestions.appendChild(item);
                              });
                          })
                          .catch(function(){});
                    });
                } catch (e) {}
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function(){
                    openModal();
                    wireAutocomplete();
                });
            } else {
                openModal();
                wireAutocomplete();
            }

            try {
                var url = new URL(window.location.href);
                url.searchParams.delete('msg');
                history.replaceState(null, '', url.toString());
            } catch (e) {}
        })();
        </script>
    <?php endif; ?>

    <div class="modal fade" id="exportNeedSelectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Atención</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    Debes seleccionar al menos un usuario para exportar.
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Entendido</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <?php if (empty($users)): ?>
        <div class="table-card" style="padding: 48px 24px; text-align: center;">
            <p class="text-muted mb-0">
                <i class="bi bi-people" style="font-size: 2.5rem; opacity: 0.5;"></i><br>
                No se encontraron usuarios<?php echo $search ? ' con ese criterio de búsqueda.' : '.'; ?>
            </p>
        </div>
    <?php else: ?>
        <div class="users-mobile-list d-md-none">
            <?php foreach ($users as $u): ?>
                <?php
                $fullName = trim((string)($u['firstname'] ?? '') . ' ' . (string)($u['lastname'] ?? ''));
                if ($fullName === '') $fullName = (string)($u['email'] ?? 'Usuario');
                $email = (string)($u['email'] ?? '');
                $status = (string)($u['status'] ?? 'inactive');
                $badgeClass = $statusBadges[$status] ?? 'user-status-inactive';
                $label = $statusLabels[$status] ?? ucfirst($status);
                $ticketCount = (int)($u['ticket_count'] ?? 0);
                $company = trim((string)($u['company'] ?? ''));

                $parts = preg_split('/\s+/', trim($fullName));
                $i1 = strtoupper((string)($parts[0][0] ?? ''));
                $i2 = '';
                if (count($parts) > 1) {
                    $i2 = strtoupper((string)($parts[1][0] ?? ''));
                } elseif (strlen($fullName) > 1) {
                    $i2 = strtoupper(substr($fullName, 1, 1));
                }
                $initials = trim($i1 . $i2);
                if ($initials === '') $initials = 'U';
                ?>
                <div class="users-mobile-card umc-status-<?php echo html($status); ?>">
                    <div class="umc-header">
                        <div class="umc-avatar">
                            <?php echo html($initials); ?>
                        </div>
                        <div class="umc-info">
                            <h4 class="umc-name">
                                <a href="users.php?id=<?php echo (int)$u['id']; ?>"><?php echo html($fullName); ?></a>
                            </h4>
                            <div class="umc-contact">
                                <span><i class="bi bi-envelope"></i> <?php echo html($email !== '' ? $email : 'Sin correo'); ?></span>
                                <?php if ($company !== ''): ?>
                                    <span class="umc-company-badge"><i class="bi bi-building"></i> <?php echo html($company); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="umc-badge">
                            <span class="badge <?php echo html($badgeClass); ?>"><?php echo html($label); ?></span>
                        </div>
                    </div>

                    <div class="umc-body">
                        <div class="umc-stat">
                            <span class="umc-stat-label">TICKETS</span>
                            <span class="umc-stat-val <?php echo $ticketCount > 0 ? 'text-success' : 'text-muted'; ?>"><?php echo $ticketCount; ?></span>
                        </div>
                        <div class="umc-stat">
                            <span class="umc-stat-label">REGISTRADO</span>
                            <span class="umc-stat-val"><?php echo $u['created'] ? date('d/m/y', strtotime($u['created'])) : '—'; ?></span>
                        </div>
                        <div class="umc-stat">
                            <span class="umc-stat-label">ACTUALIZADO</span>
                            <span class="umc-stat-val"><?php echo $u['updated'] ? date('d/m/y', strtotime($u['updated'])) : '—'; ?></span>
                        </div>
                    </div>

                    <div class="umc-footer">
                        <a class="btn-umc-primary" href="users.php?id=<?php echo (int)$u['id']; ?>">
                            <i class="bi bi-person-badge"></i> Ver perfil
                        </a>
                        <?php if ($email !== ''): ?>
                            <a class="btn-umc-icon" href="mailto:<?php echo html($email); ?>">
                                <i class="bi bi-envelope-paper"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
        <?php endforeach; ?>
    </div>

        <!-- Paginación Móvil -->
        <div class="users-mobile-pagination d-md-none">
            <?php
            $prevQp = array_merge($queryParams, ['sort' => $currentSort, 'order' => $currentOrder, 'p' => max(1, $pageNum - 1)]);
            $nextQp = array_merge($queryParams, ['sort' => $currentSort, 'order' => $currentOrder, 'p' => min($totalPages, $pageNum + 1)]);
            ?>
            <div class="ump-info-row">
                <span class="ump-showing">Mostrando <?php echo $totalRows ? $offset + 1 : 0; ?>–<?php echo min($offset + $perPage, $totalRows); ?> de <?php echo $totalRows; ?></span>
                <a class="ump-export-btn" href="users.php?<?php echo http_build_query(array_merge($queryParams, ['sort' => $currentSort, 'order' => $currentOrder, 'p' => $pageNum, 'a' => 'export'])); ?>">
                    <i class="bi bi-download"></i> Exportar
                </a>
            </div>
            <div class="ump-nav-row">
                <?php if ($pageNum > 1): ?>
                    <a class="ump-nav-btn" href="users.php?<?php echo http_build_query($prevQp); ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="ump-nav-btn ump-disabled"><i class="bi bi-chevron-left"></i></span>
                <?php endif; ?>

                <div class="ump-pages">
                    <?php
                    $startPage = max(1, $pageNum - 2);
                    $endPage = min($totalPages, $pageNum + 2);
                    for ($pg = $startPage; $pg <= $endPage; $pg++):
                        $pgQp = array_merge($queryParams, ['sort' => $currentSort, 'order' => $currentOrder, 'p' => $pg]);
                    ?>
                        <?php if ($pg === $pageNum): ?>
                            <span class="ump-page-num ump-page-active"><?php echo $pg; ?></span>
                        <?php else: ?>
                            <a class="ump-page-num" href="users.php?<?php echo http_build_query($pgQp); ?>"><?php echo $pg; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>

                <?php if ($pageNum < $totalPages): ?>
                    <a class="ump-nav-btn" href="users.php?<?php echo http_build_query($nextQp); ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="ump-nav-btn ump-disabled"><i class="bi bi-chevron-right"></i></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-card d-none d-md-block">
            <form id="usersListForm" method="get" action="users.php">
                <?php if ($search !== ''): ?>
                    <input type="hidden" name="q" value="<?php echo html($search); ?>">
                <?php endif; ?>
                <?php if ($currentSort !== 'name' || $currentOrder !== 'ASC'): ?>
                    <input type="hidden" name="sort" value="<?php echo html($currentSort); ?>">
                    <input type="hidden" name="order" value="<?php echo html($currentOrder); ?>">
                <?php endif; ?>
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width: 44px;">
                            <input type="checkbox" class="form-check-input" id="selectAll" title="Seleccionar todos">
                        </th>
                        <th>
                            <a href="users.php?<?php echo http_build_query(array_merge($queryParams, ['sort' => 'name', 'order' => $currentSort === 'name' ? $nextOrder : 'ASC', 'p' => 1])); ?>">
                                Nombre
                                <?php if ($currentSort === 'name'): ?>
                                    <i class="bi bi-arrow-<?php echo $currentOrder === 'ASC' ? 'up' : 'down'; ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="users.php?<?php echo http_build_query(array_merge($queryParams, ['sort' => 'status', 'order' => $currentSort === 'status' ? $nextOrder : 'ASC', 'p' => 1])); ?>">
                                Estado
                                <?php if ($currentSort === 'status'): ?>
                                    <i class="bi bi-arrow-<?php echo $currentOrder === 'ASC' ? 'up' : 'down'; ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="users.php?<?php 
                                $ord = $currentSort === 'created' ? $nextOrder : 'DESC'; 
                                echo http_build_query(array_merge($queryParams, ['sort' => 'created', 'order' => $ord, 'p' => 1])); ?>">
                                Creado
                                <?php if ($currentSort === 'created'): ?>
                                    <i class="bi bi-arrow-<?php echo $currentOrder === 'ASC' ? 'up' : 'down'; ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="users.php?<?php 
                                $ord = $currentSort === 'updated' ? $nextOrder : 'DESC'; 
                                echo http_build_query(array_merge($queryParams, ['sort' => 'updated', 'order' => $ord, 'p' => 1])); ?>">
                                Actualizado
                                <?php if ($currentSort === 'updated'): ?>
                                    <i class="bi bi-arrow-<?php echo $currentOrder === 'ASC' ? 'up' : 'down'; ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="form-check-input user-row-cb" name="ids[]" value="<?php echo (int)$u['id']; ?>">
                            </td>
                            <td class="user-name-cell">
                                <a href="users.php?id=<?php echo (int)$u['id']; ?>">
                                    <?php echo html(trim($u['firstname'] . ' ' . $u['lastname']) ?: $u['email']); ?>
                                </a>
                                <?php if ((int)$u['ticket_count'] > 0): ?>
                                    <span class="ticket-count"><i class="bi bi-ticket-perforated"></i> (<?php echo (int)$u['ticket_count']; ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $status = $u['status'] ?? 'inactive';
                                $badgeClass = $statusBadges[$status] ?? 'user-status-inactive';
                                $label = $statusLabels[$status] ?? ucfirst($status);
                                ?>
                                <span class="badge <?php echo $badgeClass; ?>" style="padding: 6px 10px; border-radius: 8px; font-weight: 500;">
                                    <?php echo html($label); ?>
                                </span>
                            </td>
                            <td><?php echo $u['created'] ? date('d/m/y', strtotime($u['created'])) : '-'; ?></td>
                            <td><?php echo $u['updated'] ? date('d/m/y h:i A', strtotime($u['updated'])) : '-'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            </form>

            <!-- Pie de tabla: paginación -->
            <div class="table-footer-bar" style="padding: 0; background: transparent; border: none; box-shadow: none;">
                <div class="w-100 d-flex align-items-center justify-content-center" style="margin-top: -10px;">
                    <?php
                    $baseParams = $queryParams;
                    $baseParams['sort'] = $currentSort;
                    $baseParams['order'] = $currentOrder;
                    unset($baseParams['p']);
                    $urlParams = '';
                    foreach ($baseParams as $k => $v) {
                        $urlParams .= '&' . urlencode($k) . '=' . urlencode($v);
                    }
                    echo renderModernPagination($pageNum, $totalPages, $urlParams, 'p');
                    ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Modal Añadir usuario -->
    <div class="modal fade user-premium-modal" id="modalAddUser" tabindex="-1" aria-labelledby="modalAddUserLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAddUserLabel"><i class="bi bi-person-plus me-2" style="color: #ef4444;"></i> Añadir nuevo usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form method="post" action="users.php">
                    <input type="hidden" name="do" value="add">
                    <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                    <div class="modal-body">
                        <?php if (!empty($add_errors)): ?>
                        <div class="alert alert-danger d-flex align-items-start gap-2 mb-4" role="alert" style="border-radius:12px;">
                            <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
                            <div><?php echo implode('<br>', array_map('htmlspecialchars', $add_errors)); ?></div>
                        </div>
                        <?php endif; ?>
                        <div class="mb-4">
                            <label for="add-email" class="form-label"><i class="bi bi-envelope me-1" style="color: #ef4444;"></i> Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="add-email" name="email" required placeholder="usuario@ejemplo.com" value="<?php echo html($_POST['email'] ?? ''); ?>">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label for="add-firstname" class="form-label"><i class="bi bi-person me-1" style="color: #ef4444;"></i> Nombre <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="add-firstname" name="firstname" required placeholder="Nombre" value="<?php echo html($_POST['firstname'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-4">
                                <label for="add-lastname" class="form-label"><i class="bi bi-person me-1" style="color: #ef4444;"></i> Apellido <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="add-lastname" name="lastname" required placeholder="Apellido" value="<?php echo html($_POST['lastname'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label for="add-phone" class="form-label"><i class="bi bi-telephone me-1" style="color: #ef4444;"></i> Teléfono</label>
                                <input type="text" class="form-control" id="add-phone" name="phone" placeholder="Ej. 6621-8585" value="<?php echo html($_POST['phone'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-4">
                                <label for="add-address" class="form-label"><i class="bi bi-geo-alt me-1" style="color: #ef4444;"></i> Dirección <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="add-address" name="address" required placeholder="Dirección completa" value="<?php echo html($_POST['address'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label for="add-password" class="form-label"><i class="bi bi-shield-lock me-1" style="color: #ef4444;"></i> Contraseña <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="add-password" name="password" required minlength="6" placeholder="Mínimo 6 caracteres">
                            </div>
                            <div class="col-md-6 mb-4">
                                <label for="add-password2" class="form-label"><i class="bi bi-shield-check me-1" style="color: #ef4444;"></i> Repetir <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="add-password2" name="password2" required minlength="6" placeholder="Repetir">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-person-plus-fill me-2"></i> Crear usuario</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if (!empty($add_errors)): ?>
    <script>
    (function(){
        function reopenAddUserModal() {
            try {
                var el = document.getElementById('modalAddUser');
                if (el && window.bootstrap && window.bootstrap.Modal) {
                    window.bootstrap.Modal.getOrCreateInstance(el).show();
                }
            } catch(e) {}
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', reopenAddUserModal);
        } else {
            reopenAddUserModal();
        }
    })();
    </script>
    <?php endif; ?>

    <!-- Modal Importar usuarios -->
    <div class="modal fade" id="modalImportUsers" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Importar usuarios</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form method="post" action="users.php" enctype="multipart/form-data" onsubmit="return confirm('¿Desea importar los usuarios?');">
                    <input type="hidden" name="do" value="import_users">
                    <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                    <div class="modal-body">
                        <ul class="nav nav-tabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" type="button" data-bs-toggle="tab" data-bs-target="#importPaste" role="tab">Copiar pegar</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#importCsv" role="tab">Subir</button>
                            </li>
                        </ul>
                        <div class="tab-content pt-3">
                            <div class="tab-pane fade show active" id="importPaste" role="tabpanel">
                                <h6 class="mb-2">Nombre y Email</h6>
                                <div class="text-muted small mb-2">Ingrese un nombre y dirección de email por línea.</div>
                                <textarea class="form-control" name="pasted" rows="6" placeholder="Ej., Francisco Dominguez, fran03@gmail.com"></textarea>
                            </div>
                            <div class="tab-pane fade" id="importCsv" role="tabpanel">
                                <h6 class="mb-2">Importar archivo CSV</h6>
                                <div class="text-muted small mb-2">Columnas soportadas: Email, Name<?php echo $usersHasPhone ? ', Phone' : ''; ?>.</div>
                                <div class="table-responsive mb-3" style="max-width: 560px; font-size: 13px; line-height: 1.05;">
                                    <table class="table table-sm table-bordered mb-0" style="word-break: break-word; overflow-wrap: anywhere; white-space: nowrap;">
                                        <thead>
                                            <tr>
                                                <th style="padding: 2px 6px;">Email</th>
                                                <th style="padding: 2px 6px;">Name</th>
                                                <?php if ($usersHasPhone): ?>
                                                    <th style="padding: 2px 6px;">Phone</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td style="padding: 2px 6px;">fran03@gmail.com</td>
                                                <td style="padding: 2px 6px;">Francisco Dominguez</td>
                                                <?php if ($usersHasPhone): ?>
                                                    <td style="padding: 2px 6px;">6621-8585</td>
                                                <?php endif; ?>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <input type="file" class="form-control" name="import_file" accept=".csv,text/csv">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="reset" class="btn btn-outline-secondary">Restablecer</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Importar usuarios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
