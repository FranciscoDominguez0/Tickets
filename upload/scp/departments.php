<?php
require_once '../../config.php';
require_once '../../includes/helpers.php';
require_once '../../includes/Auth.php';

if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

requireLogin('agente');
requireRolePermission('admin.access');
$staff = getCurrentUser();
$currentRoute = 'departments';

$eid = empresaId();

$flashError = (string)($_SESSION['flash_error'] ?? '');
$flashMsg = (string)($_SESSION['flash_msg'] ?? '');
unset($_SESSION['flash_error'], $_SESSION['flash_msg']);

$deptHasEmpresa = false;
$emailAccHasEmpresa = false;
$staffHasEmpresa = false;
$ticketsHasEmpresa = false;
$helpTopicsHasEmpresa = false;
if (isset($mysqli) && $mysqli) {
    $colD = $mysqli->query("SHOW COLUMNS FROM departments LIKE 'empresa_id'");
    $deptHasEmpresa = ($colD && $colD->num_rows > 0);

    $colE = $mysqli->query("SHOW COLUMNS FROM email_accounts LIKE 'empresa_id'");
    $emailAccHasEmpresa = ($colE && $colE->num_rows > 0);

    $colS = $mysqli->query("SHOW COLUMNS FROM staff LIKE 'empresa_id'");
    $staffHasEmpresa = ($colS && $colS->num_rows > 0);

    $colT = $mysqli->query("SHOW COLUMNS FROM tickets LIKE 'empresa_id'");
    $ticketsHasEmpresa = ($colT && $colT->num_rows > 0);

    $chkHT = $mysqli->query("SHOW TABLES LIKE 'help_topics'");
    if ($chkHT && $chkHT->num_rows > 0) {
        $colHT = $mysqli->query("SHOW COLUMNS FROM help_topics LIKE 'empresa_id'");
        $helpTopicsHasEmpresa = ($colHT && $colHT->num_rows > 0);
    }
}

$ensureEmailAccountsTable = function () use ($mysqli) {
    if (!isset($mysqli) || !$mysqli) return false;
    $sql = "CREATE TABLE IF NOT EXISTS email_accounts (\n"
        . "  id INT PRIMARY KEY AUTO_INCREMENT,\n"
        . "  email VARCHAR(255) NOT NULL,\n"
        . "  name VARCHAR(255) NULL,\n"
        . "  priority VARCHAR(32) NULL,\n"
        . "  dept_id INT NULL,\n"
        . "  is_default TINYINT(1) NOT NULL DEFAULT 0,\n"
        . "  smtp_host VARCHAR(255) NULL,\n"
        . "  smtp_port INT NULL,\n"
        . "  smtp_secure VARCHAR(10) NULL,\n"
        . "  smtp_user VARCHAR(255) NULL,\n"
        . "  smtp_pass VARCHAR(255) NULL,\n"
        . "  created DATETIME DEFAULT CURRENT_TIMESTAMP,\n"
        . "  updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
        . "  KEY idx_email (email),\n"
        . "  KEY idx_default (is_default),\n"
        . "  KEY idx_dept (dept_id)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    return (bool)$mysqli->query($sql);
};
$ensureEmailAccountsTable();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        $_SESSION['flash_error'] = 'Token CSRF inválido.';
        header('Location: departments.php');
        exit;
    }

    $do = (string)($_POST['do'] ?? '');

    if ($do === 'create') {
        $name = trim((string)($_POST['name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $requiresReport = isset($_POST['requires_report']) ? 1 : 0;

        if ($name === '') {
            $_SESSION['flash_error'] = 'El nombre del departamento es requerido.';
            header('Location: departments.php');
            exit;
        }

        if ($deptHasEmpresa) {
            $stmt = $mysqli->prepare('INSERT INTO departments (empresa_id, name, description, is_active, requires_report, created) VALUES (?, ?, ?, ?, ?, NOW())');
        } else {
            $stmt = $mysqli->prepare('INSERT INTO departments (name, description, is_active, requires_report, created) VALUES (?, ?, ?, ?, NOW())');
        }
        if (!$stmt) {
            $_SESSION['flash_error'] = 'No se pudo crear el departamento.';
            header('Location: departments.php');
            exit;
        }
        $descParam = $description !== '' ? $description : null;
        if ($deptHasEmpresa) {
            $stmt->bind_param('issii', $eid, $name, $descParam, $isActive, $requiresReport);
        } else {
            $stmt->bind_param('ssii', $name, $descParam, $isActive, $requiresReport);
        }
        try {
            if ($stmt->execute()) {
                $_SESSION['flash_msg'] = 'Departamento creado correctamente.';
            } else {
                $_SESSION['flash_error'] = 'No se pudo crear el departamento.';
            }
        } catch (mysqli_sql_exception $e) {
            if ((int)$e->getCode() === 1062) {
                $_SESSION['flash_error'] = 'Ya existe un departamento con ese nombre. Por favor usa otro nombre.';
            } else {
                $_SESSION['flash_error'] = 'No se pudo crear el departamento.';
            }
        }
        header('Location: departments.php');
        exit;
    }

    if ($do === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $requiresReport = isset($_POST['requires_report']) ? 1 : 0;

        if ($id <= 0) {
            $_SESSION['flash_error'] = 'ID inválido.';
            header('Location: departments.php');
            exit;
        }
        if ($name === '') {
            $_SESSION['flash_error'] = 'El nombre del departamento es requerido.';
            header('Location: departments.php');
            exit;
        }

        if ($deptHasEmpresa) {
            $stmt = $mysqli->prepare('UPDATE departments SET name = ?, description = ?, is_active = ?, requires_report = ? WHERE id = ? AND empresa_id = ?');
        } else {
            $stmt = $mysqli->prepare('UPDATE departments SET name = ?, description = ?, is_active = ?, requires_report = ? WHERE id = ?');
        }
        if (!$stmt) {
            $_SESSION['flash_error'] = 'No se pudo actualizar el departamento.';
            header('Location: departments.php');
            exit;
        }
        $descParam = $description !== '' ? $description : null;
        if ($deptHasEmpresa) {
            $stmt->bind_param('ssiiii', $name, $descParam, $isActive, $requiresReport, $id, $eid);
        } else {
            $stmt->bind_param('ssiii', $name, $descParam, $isActive, $requiresReport, $id);
        }
        try {
            if ($stmt->execute()) {
                $_SESSION['flash_msg'] = 'Departamento actualizado correctamente.';
            } else {
                $_SESSION['flash_error'] = 'No se pudo actualizar el departamento.';
            }
        } catch (mysqli_sql_exception $e) {
            if ((int)$e->getCode() === 1062) {
                $_SESSION['flash_error'] = 'Ya existe un departamento con ese nombre. Por favor usa otro nombre.';
            } else {
                $_SESSION['flash_error'] = 'No se pudo actualizar el departamento.';
            }
        }
        header('Location: departments.php');
        exit;
    }

    if ($do === 'mass_process') {
        $ids = $_POST['ids'] ?? [];
        $action = (string)($_POST['a'] ?? '');

        if (empty($ids) || !is_array($ids)) {
            $_SESSION['flash_error'] = 'Debe seleccionar al menos un departamento.';
            header('Location: departments.php');
            exit;
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));
        if (empty($ids)) {
            $_SESSION['flash_error'] = 'Debe seleccionar al menos un departamento.';
            header('Location: departments.php');
            exit;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));

        if ($action === 'enable' || $action === 'disable') {
            $enabled = $action === 'enable' ? 1 : 0;
            if ($deptHasEmpresa) {
                $stmt = $mysqli->prepare("UPDATE departments SET is_active = ? WHERE empresa_id = ? AND id IN ($placeholders)");
            } else {
                $stmt = $mysqli->prepare("UPDATE departments SET is_active = ? WHERE id IN ($placeholders)");
            }
            if ($stmt) {
                if ($deptHasEmpresa) {
                    $stmt->bind_param('ii' . $types, $enabled, $eid, ...$ids);
                } else {
                    $stmt->bind_param('i' . $types, $enabled, ...$ids);
                }
                $stmt->execute();
                $_SESSION['flash_msg'] = $enabled ? 'Departamentos habilitados correctamente.' : 'Departamentos deshabilitados correctamente.';
            } else {
                $_SESSION['flash_error'] = 'No se pudo procesar la acción.';
            }
            header('Location: departments.php');
            exit;
        }

        if ($action === 'delete') {
            // Check if staff_departments table exists
            $hasStaffDepartmentsTableDel = false;
            if (isset($mysqli) && $mysqli) {
                try {
                    $rt = $mysqli->query("SHOW TABLES LIKE 'staff_departments'");
                    $hasStaffDepartmentsTableDel = ($rt && $rt->num_rows > 0);
                } catch (Throwable $e) {
                    $hasStaffDepartmentsTableDel = false;
                }
            }
            
            if ($hasStaffDepartmentsTableDel) {
                // New model: check staff_departments
                $stmtCntSql = "SELECT COUNT(DISTINCT sd.staff_id) c FROM staff_departments sd WHERE sd.dept_id IN ($placeholders)";
            } else {
                // Legacy model
                $stmtCntSql = "SELECT COUNT(*) c FROM staff WHERE dept_id IN ($placeholders)";
            }
            if ($staffHasEmpresa) $stmtCntSql .= " AND empresa_id = ?";
            $stmtCnt = $mysqli->prepare($stmtCntSql);
            if ($stmtCnt) {
                if ($staffHasEmpresa) {
                    $bind = array_merge($ids, [(int)$eid]);
                    $stmtCnt->bind_param($types . 'i', ...$bind);
                } else {
                    $stmtCnt->bind_param($types, ...$ids);
                }
                $stmtCnt->execute();
                $row = $stmtCnt->get_result()->fetch_assoc();
                if ((int)($row['c'] ?? 0) > 0) {
                    $_SESSION['flash_error'] = 'No se pueden eliminar departamentos que tienen agentes asignados.';
                    header('Location: departments.php');
                    exit;
                }
            }

            $stmtCntTSql = "SELECT COUNT(*) c FROM tickets WHERE dept_id IN ($placeholders)";
            if ($ticketsHasEmpresa) $stmtCntTSql .= " AND empresa_id = ?";
            $stmtCntT = $mysqli->prepare($stmtCntTSql);
            if ($stmtCntT) {
                if ($ticketsHasEmpresa) {
                    $bind = array_merge($ids, [(int)$eid]);
                    $stmtCntT->bind_param($types . 'i', ...$bind);
                } else {
                    $stmtCntT->bind_param($types, ...$ids);
                }
                $stmtCntT->execute();
                $row = $stmtCntT->get_result()->fetch_assoc();
                if ((int)($row['c'] ?? 0) > 0) {
                    $_SESSION['flash_error'] = 'No se pueden eliminar departamentos que tienen tickets asignados.';
                    header('Location: departments.php');
                    exit;
                }
            }

            // Si existen temas de ayuda asociados a este departamento, bloquear con mensaje claro.
            $hasTopics = false;
            $rt = @$mysqli->query("SHOW TABLES LIKE 'help_topics'");
            if ($rt && $rt->num_rows > 0) $hasTopics = true;
            if ($hasTopics) {
                $stmtCntHSql = "SELECT COUNT(*) c FROM help_topics WHERE dept_id IN ($placeholders)";
                if ($helpTopicsHasEmpresa) $stmtCntHSql .= " AND empresa_id = ?";
                $stmtCntH = $mysqli->prepare($stmtCntHSql);
                if ($stmtCntH) {
                    if ($helpTopicsHasEmpresa) {
                        $bind = array_merge($ids, [(int)$eid]);
                        $stmtCntH->bind_param($types . 'i', ...$bind);
                    } else {
                        $stmtCntH->bind_param($types, ...$ids);
                    }
                    $stmtCntH->execute();
                    $row = $stmtCntH->get_result()->fetch_assoc();
                    if ((int)($row['c'] ?? 0) > 0) {
                        $_SESSION['flash_error'] = 'No se puede eliminar este departamento porque tiene temas (help topics) asociados. Reasigna esos temas a otro departamento antes de eliminar.';
                        header('Location: departments.php');
                        exit;
                    }
                }
            }

            if ($deptHasEmpresa) {
                $stmt = $mysqli->prepare("DELETE FROM departments WHERE empresa_id = ? AND id IN ($placeholders)");
            } else {
                $stmt = $mysqli->prepare("DELETE FROM departments WHERE id IN ($placeholders)");
            }
            if ($stmt) {
                if ($deptHasEmpresa) {
                    $stmt->bind_param('i' . $types, $eid, ...$ids);
                } else {
                    $stmt->bind_param($types, ...$ids);
                }
                try {
                    if ($stmt->execute()) {
                        $_SESSION['flash_msg'] = 'Departamentos eliminados correctamente.';
                    } else {
                        $_SESSION['flash_error'] = 'No se pudieron eliminar los departamentos.';
                    }
                } catch (mysqli_sql_exception $e) {
                    // 1451: Cannot delete or update a parent row (FK)
                    if ((int)$e->getCode() === 1451) {
                        $_SESSION['flash_error'] = 'No se puede eliminar el departamento porque está siendo usado por otros registros (por ejemplo: temas, correos, etc.). Reasigna o elimina esas referencias antes de eliminar.';
                    } else {
                        $_SESSION['flash_error'] = 'No se pudieron eliminar los departamentos.';
                    }
                }
            } else {
                $_SESSION['flash_error'] = 'No se pudieron eliminar los departamentos.';
            }
            header('Location: departments.php');
            exit;
        }

        $_SESSION['flash_error'] = 'Acción no reconocida.';
        header('Location: departments.php');
        exit;
    }
}

$departments = [];
$sql = "
    SELECT
        d.id,
        d.name,
        d.description,
        d.is_active,
        d.requires_report,
        ea.id AS email_id,
        ea.email AS dept_email,
        ea.name AS dept_email_name,
        COUNT(DISTINCT s.id) AS staff_total,
        COUNT(DISTINCT t.id) AS ticket_total
    FROM departments d
    LEFT JOIN (
        SELECT dept_id, MIN(id) AS email_id
        FROM email_accounts
        WHERE dept_id IS NOT NULL
";
if ($emailAccHasEmpresa) {
    $sql .= "        AND empresa_id = " . (int)$eid . "\n";
}
$sql .= "        GROUP BY dept_id
    ) eam ON eam.dept_id = d.id
    LEFT JOIN email_accounts ea ON ea.id = eam.email_id
";

// Check if staff_departments table exists for proper JOIN
$hasStaffDepartmentsTableDept = false;
if (isset($mysqli) && $mysqli) {
    try {
        $rt = $mysqli->query("SHOW TABLES LIKE 'staff_departments'");
        $hasStaffDepartmentsTableDept = ($rt && $rt->num_rows > 0);
    } catch (Throwable $e) {
        $hasStaffDepartmentsTableDept = false;
    }
}

if ($hasStaffDepartmentsTableDept) {
    $sql .= "    LEFT JOIN staff_departments sd ON sd.dept_id = d.id\n";
    $sql .= "    LEFT JOIN staff s ON s.id = sd.staff_id";
    if ($staffHasEmpresa) {
        $sql .= " AND s.empresa_id = " . (int)$eid;
    }
} else {
    $sql .= "    LEFT JOIN staff s ON s.dept_id = d.id";
    if ($staffHasEmpresa) {
        $sql .= " AND s.empresa_id = " . (int)$eid;
    }
}
$sql .= "
    LEFT JOIN tickets t ON t.dept_id = d.id";
if ($ticketsHasEmpresa) {
    $sql .= " AND t.empresa_id = " . (int)$eid;
}
$sql .= "
";
if ($deptHasEmpresa) {
    $sql .= "    WHERE d.empresa_id = " . (int)$eid . "\n";
}
$sql .= "    GROUP BY d.id, d.name, d.description, d.is_active, d.requires_report, ea.id, ea.email, ea.name
    ORDER BY d.name
";
$res = $mysqli->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $departments[] = $row;
    }
}

$activeCount = 0;
$inactiveCount = 0;
foreach ($departments as $d) {
    if ((int)($d['is_active'] ?? 0) === 1) $activeCount++;
    else $inactiveCount++;
}

ob_start();
?>

<div class="settings-hero">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-3">
            <span class="settings-hero-icon"><i class="bi bi-diagram-3"></i></span>
            <div>
                <h1>Departamentos</h1>
                <p>Gestión de departamentos</p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge bg-success-subtle text-success px-3 py-2 rounded-pill fw-bold border border-success-subtle"><i class="bi bi-check-circle-fill me-1"></i><?php echo (int)$activeCount; ?> Activos</span>
            <span class="badge bg-secondary-subtle text-secondary px-3 py-2 rounded-pill fw-bold border border-secondary-subtle"><i class="bi bi-pause-circle-fill me-1"></i><?php echo (int)$inactiveCount; ?> Inactivos</span>
            <span class="badge bg-info-subtle text-info px-3 py-2 rounded-pill fw-bold border border-info-subtle"><i class="bi bi-diagram-3-fill me-1"></i><?php echo (int)count($departments); ?> Total</span>
        </div>
    </div>
</div>

<?php if ($flashError): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo html($flashError); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($flashMsg): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?php echo html($flashMsg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="alert alert-danger alert-dismissible fade show d-none border-0 shadow-sm rounded-4 mb-4" role="alert" id="deptsClientError" aria-live="polite" data-alert-static="1">
    <i class="bi bi-exclamation-triangle-fill me-2"></i><span id="deptsClientErrorText"></span>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>

<form method="post" action="departments.php" id="deptsMassForm">
    <input type="hidden" name="do" value="mass_process">
    <?php csrfField(); ?>
    <input type="hidden" name="a" value="" id="deptsMassAction">

    <!-- Control Bar -->
    <div class="d-flex justify-content-between align-items-center mb-4 gap-3 flex-wrap p-3 rounded-4 shadow-sm" style="background: var(--dept-card-bg); border: 1px solid var(--dept-card-border); transition: all 0.3s;">
        <div class="d-flex align-items-center gap-3">
            <div class="form-check mb-0 d-flex align-items-center gap-2">
                <input type="checkbox" id="selectAllDepts" class="form-check-input ms-0 shadow-sm" style="width: 1.25rem; height: 1.25rem; cursor: pointer; border-radius: 4px;">
                <label class="form-check-label fw-bold text-muted" for="selectAllDepts" style="cursor: pointer; user-select: none; font-size: 0.9rem;">Seleccionar todos</label>
            </div>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <button type="button" class="btn btn-primary px-3 py-2 rounded-3 btn-add-custom shadow-sm d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addDeptModal" style="background: linear-gradient(135deg, #2563eb, #1d4ed8); border: 0; font-weight: 600; font-size: 0.88rem;">
                <i class="bi bi-plus-circle-fill"></i> Añadir nuevo Departamento
            </button>
            <div class="dropdown">
                <button class="btn dropdown-toggle px-3 py-2 rounded-3 fw-bold d-flex align-items-center gap-2 btn-actions-custom" type="button" id="deptsMoreDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 0.88rem; background: var(--dept-stat-bg); color: var(--dept-text-main); border: 1px solid var(--dept-card-border);">
                    <i class="bi bi-gear-fill"></i> Acciones
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-2" aria-labelledby="deptsMoreDropdown" style="background: var(--dept-card-bg); border: 1px solid var(--dept-card-border); border-radius: 12px;">
                    <li><button class="dropdown-item fw-semibold rounded-3 py-2" type="button" data-dept-action="enable" style="color: var(--dept-text-main);"><i class="bi bi-check-circle-fill text-success me-2"></i>Habilitar</button></li>
                    <li><button class="dropdown-item fw-semibold rounded-3 py-2" type="button" data-dept-action="disable" style="color: var(--dept-text-main);"><i class="bi bi-slash-circle-fill text-warning me-2"></i>Deshabilitar</button></li>
                    <li><hr class="dropdown-divider" style="border-top: 1px solid var(--dept-card-border);"></li>
                    <li><button class="dropdown-item fw-semibold rounded-3 py-2 text-danger" type="button" data-dept-action="delete"><i class="bi bi-trash-fill me-2"></i>Eliminar</button></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Premium Table Layout -->
    <div class="premium-table-wrapper">
        <div class="table-responsive">
            <table class="premium-table">
                <thead>
                    <tr>
                        <th width="50" class="text-center d-none d-md-table-cell">#</th>
                        <th class="d-none d-md-table-cell">Departamento</th>
                        <th class="text-center d-none d-md-table-cell">Estado</th>
                        <th class="text-center d-none d-md-table-cell">Agentes</th>
                        <th class="text-center d-none d-md-table-cell">Tickets</th>
                        <th width="140" class="text-end d-none d-md-table-cell">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($departments)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <span class="fs-2 mb-2 d-block"><i class="bi bi-diagram-3"></i></span>
                                No se encontraron departamentos registrados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($departments as $d): ?>
                            <?php
                            $id = (int)($d['id'] ?? 0);
                            $name = (string)($d['name'] ?? '');
                            $description = (string)($d['description'] ?? '');
                            $active = (int)($d['is_active'] ?? 0) === 1;
                            $emailId = (int)($d['email_id'] ?? 0);
                            $deptEmail = (string)($d['dept_email'] ?? '');
                            $deptEmailName = (string)($d['dept_email_name'] ?? '');
                            $staffTotal = (int)($d['staff_total'] ?? 0);
                            $ticketTotal = (int)($d['ticket_total'] ?? 0);
                            ?>
                            <tr data-dept-row-id="<?php echo $id; ?>" style="cursor: pointer;">
                                <!-- VISTA MÓVIL (Tarjeta Original Preservada) -->
                                <td class="d-md-none p-0">
                                    <div class="role-mobile-card">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div class="d-flex align-items-center gap-3">
                                                <input type="checkbox" name="ids[]" value="<?php echo $id; ?>" class="form-check-input dept-checkbox m-0 shadow-sm" style="width: 1.25rem; height: 1.25rem; cursor: pointer; border-radius: 4px;">
                                                <?php if ($active): ?>
                                                    <span class="role-badge active"><i class="bi bi-check-circle-fill"></i> Activo</span>
                                                <?php else: ?>
                                                    <span class="role-badge inactive"><i class="bi bi-pause-circle-fill"></i> Inactivo</span>
                                                <?php endif; ?>
                                            </div>
                                            <button type="button" class="role-mobile-action-btn dept-edit-btn"
                                                data-id="<?php echo $id; ?>"
                                                data-name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"
                                                data-description="<?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>"
                                                data-is-active="<?php echo $active ? '1' : '0'; ?>"
                                                data-requires-report="<?php echo (int)($d['requires_report'] ?? 0) === 1 ? '1' : '0'; ?>"
                                                data-bs-toggle="modal" data-bs-target="#editDeptModal">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        </div>

                                        <div class="role-mobile-card-title">
                                            <?php echo html($name); ?>
                                        </div>
                                        <?php if ($description !== ''): ?>
                                            <div class="role-mobile-card-meta mb-2" style="font-size: 0.8rem; line-height: 1.3;">
                                                <?php echo html($description); ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($deptEmail !== '' && $emailId > 0): ?>
                                            <div class="role-mobile-card-meta mb-3" style="font-size: 0.78rem;">
                                                <i class="bi bi-envelope text-muted me-1"></i>
                                                <a href="email.php?id=<?php echo (int)$emailId; ?>" style="color: var(--dept-link-color) !important; text-decoration: none;">
                                                    <?php if ($deptEmailName !== ''): ?>
                                                        <?php echo html($deptEmailName); ?> &lt;<?php echo html($deptEmail); ?>&gt;
                                                    <?php else: ?>
                                                        <?php echo html($deptEmail); ?>
                                                    <?php endif; ?>
                                                </a>
                                            </div>
                                        <?php endif; ?>

                                        <div class="row g-2 mt-2 pt-3 role-stats-row">
                                            <div class="col-6">
                                                <div class="role-stat-box">
                                                    <div class="stat-label">Agentes</div>
                                                    <div class="stat-value"><?php echo (int)$staffTotal; ?></div>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="role-stat-box">
                                                    <div class="stat-label">Tickets</div>
                                                    <div class="stat-value" style="color: #3b82f6;"><?php echo (int)$ticketTotal; ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- VISTA ESCRITORIO -->
                                <td class="text-center d-none d-md-table-cell" onclick="event.stopPropagation();">
                                    <input type="checkbox" name="ids[]" value="<?php echo $id; ?>" class="form-check-input dept-checkbox shadow-sm m-0" style="width: 1.25rem; height: 1.25rem; cursor: pointer; border-radius: 4px;">
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fs-5" style="color: <?php echo $active ? '#2563eb' : 'var(--dept-text-muted)'; ?>;"><i class="bi bi-diagram-3-fill"></i></span>
                                        <div>
                                            <div class="fw-bold" style="color: var(--dept-text-main); font-size: 0.95rem;">
                                                <?php echo html($name); ?>
                                            </div>
                                            <?php if ($description !== ''): ?>
                                                <div class="text-muted small" style="font-size: 0.78rem; line-height: 1.2;"><?php echo html($description); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center d-none d-md-table-cell">
                                    <?php if ($active): ?>
                                        <span class="role-badge active"><i class="bi bi-check-circle-fill"></i> Activo</span>
                                    <?php else: ?>
                                        <span class="role-badge inactive"><i class="bi bi-pause-circle-fill"></i> Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center d-none d-md-table-cell">
                                    <span class="agent-count-badge"><?php echo (int)$staffTotal; ?></span>
                                </td>
                                <td class="text-center d-none d-md-table-cell">
                                    <span class="agent-count-badge <?php echo $ticketTotal > 0 ? 'active-tickets' : ''; ?>"><?php echo (int)$ticketTotal; ?></span>
                                </td>
                                <td class="text-end d-none d-md-table-cell" onclick="event.stopPropagation();">
                                    <button type="button" class="btn-permissions dept-edit-btn"
                                        data-id="<?php echo $id; ?>"
                                        data-name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"
                                        data-description="<?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>"
                                        data-is-active="<?php echo $active ? '1' : '0'; ?>"
                                        data-requires-report="<?php echo (int)($d['requires_report'] ?? 0) === 1 ? '1' : '0'; ?>"
                                        data-bs-toggle="modal" data-bs-target="#editDeptModal">
                                        <i class="bi bi-pencil-square"></i> Editar
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>

<div class="modal fade" id="addDeptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <form method="post" action="departments.php">
                <input type="hidden" name="do" value="create">
                <?php csrfField(); ?>
                <div class="modal-header d-flex align-items-center px-4 py-3">
                    <h5 class="modal-title d-flex align-items-center gap-2 fs-5"><i class="bi bi-plus-circle text-primary"></i> Añadir nuevo Departamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body px-4 py-4">
                    <div class="mb-3">
                        <label class="form-label mb-2">Nombre</label>
                        <input type="text" name="name" class="form-control" placeholder="Ej. Ventas" required autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label class="form-label mb-2">Descripción</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Descripción breve de la oficina/área"></textarea>
                    </div>
                    <div class="form-check form-switch fs-6 mt-2">
                        <input class="form-check-input" type="checkbox" name="is_active" id="createDeptIsActive" checked>
                        <label class="form-check-label fw-semibold" for="createDeptIsActive">Activo</label>
                    </div>
                    <div class="form-check form-switch fs-6 mt-2">
                        <input class="form-check-input" type="checkbox" name="requires_report" id="createDeptRequiresReport" value="1">
                        <label class="form-check-label fw-semibold" for="createDeptRequiresReport">Requiere reporte de cierre</label>
                    </div>
                </div>
                <div class="modal-footer px-4 py-3 border-0">
                    <button type="button" class="btn btn-secondary px-4 py-2 fw-semibold rounded-3 btn-cancel-custom" data-bs-dismiss="modal" style="font-size: 0.9rem;">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold rounded-3 btn-save-custom" style="font-size: 0.9rem; background: linear-gradient(135deg, #2563eb, #1d4ed8); border: 0;"><i class="bi bi-check-circle me-1"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editDeptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <form method="post" action="departments.php">
                <input type="hidden" name="do" value="update">
                <?php csrfField(); ?>
                <input type="hidden" name="id" id="dept_edit_id" value="">
                <div class="modal-header d-flex align-items-center px-4 py-3">
                    <h5 class="modal-title d-flex align-items-center gap-2 fs-5"><i class="bi bi-pencil text-primary"></i> Editar Departamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body px-4 py-4">
                    <div class="mb-3">
                        <label class="form-label mb-2">Nombre</label>
                        <input type="text" name="name" id="dept_edit_name" class="form-control" required autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label class="form-label mb-2">Descripción</label>
                        <textarea name="description" id="dept_edit_description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-check form-switch fs-6 mt-2">
                        <input class="form-check-input" type="checkbox" name="is_active" id="dept_edit_is_active" checked>
                        <label class="form-check-label fw-semibold" for="dept_edit_is_active">Activo</label>
                    </div>
                    <div class="form-check form-switch fs-6 mt-2">
                        <input class="form-check-input" type="checkbox" name="requires_report" id="dept_edit_requires_report" value="1">
                        <label class="form-check-label fw-semibold" for="dept_edit_requires_report">Requiere reporte de cierre</label>
                    </div>
                </div>
                <div class="modal-footer px-4 py-3 border-0">
                    <button type="button" class="btn btn-secondary px-4 py-2 fw-semibold rounded-3 btn-cancel-custom" data-bs-dismiss="modal" style="font-size: 0.9rem;">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold rounded-3 btn-save-custom" style="font-size: 0.9rem; background: linear-gradient(135deg, #2563eb, #1d4ed8); border: 0;"><i class="bi bi-check-circle me-1"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteDeptsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header d-flex align-items-center px-4 py-3">
                <h5 class="modal-title d-flex align-items-center gap-2 fs-5"><i class="bi bi-trash text-danger"></i> Eliminar departamentos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-4">
                ¿Deseas eliminar los departamentos seleccionados?
                <div class="text-muted small mt-2">
                    Seleccionados: <strong><span id="deleteDeptsCount">0</span></strong>
                </div>
                <div class="alert alert-warning border-0 rounded-3 mt-3 d-flex gap-2 align-items-start" style="font-size: 0.85rem;">
                    <i class="bi bi-exclamation-triangle-fill fs-5 mt-0.5"></i>
                    <div>Solo se eliminarán aquellos departamentos que no tengan agentes ni tickets asignados actualmente en el sistema.</div>
                </div>
            </div>
            <div class="modal-footer px-4 py-3 border-0">
                <button type="button" class="btn btn-secondary px-4 py-2 fw-semibold rounded-3 btn-cancel-custom" data-bs-dismiss="modal" style="font-size: 0.9rem;">Cancelar</button>
                <button type="button" class="btn btn-danger px-4 py-2 fw-semibold rounded-3" id="confirmDeleteDeptsBtn" style="font-size: 0.9rem;"><i class="bi bi-trash"></i> Eliminar</button>
            </div>
        </div>
    </div>
</div>

<style>
/* ── Design System Variables ── */
:root {
    --dept-card-bg: #ffffff;
    --dept-card-border: #e2e8f0;
    --dept-stat-bg: #f8fafc;
    --dept-table-header-bg: #f8fafc;
    --dept-table-row-hover: #f8fafc;
    --dept-text-main: #0f172a;
    --dept-text-muted: #64748b;
    --dept-badge-active-bg: rgba(16, 185, 129, 0.1);
    --dept-badge-active-color: #16a34a;
    --dept-badge-inactive-bg: #f1f5f9;
    --dept-badge-inactive-color: #64748b;
    --dept-badge-count-bg: #e2e8f0;
    --dept-badge-count-color: #1e293b;
    --dept-btn-perm-hover-bg: #cbd5e1;
    --modal-close-filter: none;

    /* Mobile variables */
    --role-mobile-card-bg: #ffffff;
    --role-mobile-card-border: #e2e8f0;
    --role-mobile-stat-bg: #f8fafc;
    --role-mobile-stat-text: #0f172a;
    --role-mobile-card-title: #0f172a;
    --role-mobile-card-meta: #64748b;
    --role-mobile-dashed-border: #e2e8f0;
    --role-mobile-action-btn-bg: #f8fafc;
    --role-mobile-action-btn-color: #64748b;
    --dept-link-color: #475569;
}

body.dark-mode {
    --dept-card-bg: #111111;
    --dept-card-border: #2a2a2a;
    --dept-stat-bg: #1a1a1a;
    --dept-table-header-bg: #161616;
    --dept-table-row-hover: #1a1a1a;
    --dept-text-main: #e5e5e5;
    --dept-text-muted: #888888;
    --dept-badge-active-bg: rgba(16, 185, 129, 0.15);
    --dept-badge-active-color: #34d399;
    --dept-badge-inactive-bg: #222222;
    --dept-badge-inactive-color: #888888;
    --dept-badge-count-bg: #222222;
    --dept-badge-count-color: #e5e5e5;
    --dept-btn-perm-hover-bg: #2a2a2a;
    --modal-close-filter: invert(1);

    /* Mobile variables */
    --role-mobile-card-bg: #111111;
    --role-mobile-card-border: #2a2a2a;
    --role-mobile-stat-bg: #1a1a1a;
    --role-mobile-stat-text: #f1f5f9;
    --role-mobile-card-title: #f8fafc;
    --role-mobile-card-meta: #94a3b8;
    --role-mobile-dashed-border: #2a2a2a;
    --role-mobile-action-btn-bg: #1a1a1a;
    --role-mobile-action-btn-color: #94a3b8;
    --dept-link-color: #94a3b8;
}

/* Premium Table Container */
.premium-table-wrapper {
    background: var(--dept-card-bg);
    border: 1px solid var(--dept-card-border);
    border-radius: 16px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.premium-table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

.premium-table th {
    background: var(--dept-table-header-bg);
    color: var(--dept-text-muted);
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 16px 20px;
    border-bottom: 1px solid var(--dept-card-border);
}

.premium-table td {
    padding: 16px 20px;
    border-bottom: 1px solid var(--dept-card-border);
    color: var(--dept-text-main);
    vertical-align: middle;
    transition: all 0.2s ease;
}

.premium-table tr:last-child td {
    border-bottom: none;
}

/* Row Hover & Selected States */
.premium-table tr {
    transition: all 0.2s ease;
}

.premium-table tr.selected td {
    background: rgba(37, 99, 235, 0.02) !important;
}

body.dark-mode .premium-table tr.selected td {
    background: #18181b !important;
}

.premium-table tr:hover td {
    background: var(--dept-table-row-hover);
}

/* Status & Count Badges */
.role-badge {
    font-size: 0.72rem;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.role-badge.active {
    background: var(--dept-badge-active-bg);
    color: var(--dept-badge-active-color);
}

.role-badge.inactive {
    background: var(--dept-badge-inactive-bg);
    color: var(--dept-badge-inactive-color);
}

.agent-count-badge {
    font-size: 0.8rem;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 8px;
    background: var(--dept-badge-count-bg);
    color: var(--dept-badge-count-color);
    display: inline-block;
    min-width: 32px;
    text-align: center;
}

.agent-count-badge.active-tickets {
    background: rgba(37, 99, 235, 0.1);
    color: #2563eb;
}

body.dark-mode .agent-count-badge.active-tickets {
    background: rgba(37, 99, 235, 0.15);
    color: #3b82f6;
}

/* Buttons */
.btn-permissions {
    padding: 6px 14px;
    font-size: 0.8rem;
    font-weight: 600;
    border-radius: 8px;
    background: var(--dept-badge-count-bg);
    color: var(--dept-text-main);
    border: none;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-permissions:hover {
    background: var(--dept-btn-perm-hover-bg);
    color: var(--dept-text-main);
    transform: translateY(-1px);
}

.btn-add-custom:hover {
    background: linear-gradient(135deg, #1d4ed8, #1e40af) !important;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(37,99,235,0.3) !important;
}

.btn-actions-custom:hover {
    background: var(--dept-btn-perm-hover-bg) !important;
}

/* Modal Styling overrides */
.modal-content {
    background: var(--dept-card-bg) !important;
    border: 1px solid var(--dept-card-border) !important;
    color: var(--dept-text-main) !important;
}
.modal-header {
    border-bottom: 1px solid var(--dept-card-border) !important;
}
.modal-footer {
    border-top: 1px solid var(--dept-card-border) !important;
}
.modal-content .form-label {
    font-weight: 600;
    color: var(--dept-text-muted);
    font-size: 0.85rem;
}
.modal-content .form-control {
    background: var(--dept-stat-bg);
    border: 1px solid var(--dept-card-border);
    color: var(--dept-text-main);
    border-radius: 10px;
    padding: 10px 14px;
    transition: all 0.2s;
}
.modal-content .form-control:focus {
    background: var(--dept-card-bg);
    border-color: #3b82f6;
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
}
.modal-content .btn-close {
    filter: var(--modal-close-filter);
}
.btn-cancel-custom {
    background: var(--dept-stat-bg);
    border: 1px solid var(--dept-card-border);
    color: var(--dept-text-main);
}
.btn-cancel-custom:hover {
    background: var(--dept-btn-perm-hover-bg) !important;
    color: var(--dept-text-main);
}

body.dark-mode .dropdown-item:hover {
    background-color: #222222 !important;
    color: #ffffff !important;
}

/* ── Mobile Cards Styles ── */
.role-mobile-card {
    padding: 16px;
    background: var(--role-mobile-card-bg);
    position: relative;
    text-align: left;
}
.role-mobile-card .role-mobile-card-title {
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--role-mobile-card-title);
    margin-bottom: 4px;
    line-height: 1.2;
}
.role-mobile-card .role-mobile-card-meta {
    font-size: 0.75rem;
    color: var(--role-mobile-card-meta);
    font-weight: 600;
    margin-bottom: 12px;
}
.role-mobile-card .role-stats-row {
    border-top: 1px dashed var(--role-mobile-dashed-border);
}
.role-stat-box {
    background: var(--role-mobile-stat-bg);
    border-radius: 8px;
    padding: 8px 12px;
}
.role-stat-box .stat-label {
    font-size: 0.65rem;
    color: var(--role-mobile-card-meta);
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 2px;
}
.role-stat-box .stat-value {
    font-size: 1rem;
    color: var(--role-mobile-stat-text);
    font-weight: 800;
}
.role-mobile-action-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--role-mobile-action-btn-bg) !important;
    color: var(--role-mobile-action-btn-color) !important;
    border: none !important;
    text-decoration: none;
}

/* Responsive Table -> Cards for Mobile */
@media (max-width: 768px) {
    .premium-table-wrapper { border: none !important; overflow: visible !important; background: transparent !important; box-shadow: none !important; }
    .premium-table { display: block !important; width: 100% !important; }
    .premium-table thead { display: none !important; }
    .premium-table tbody { display: block !important; width: 100% !important; }
    .premium-table tbody tr {
        display: block !important;
        margin-bottom: 1rem !important;
        background: var(--role-mobile-card-bg) !important;
        border: 1px solid var(--role-mobile-card-border) !important;
        border-radius: 16px !important;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05) !important;
        overflow: hidden !important;
        transition: all 0.25s ease !important;
    }
    .premium-table tbody tr.selected {
        border-color: #2563eb !important;
    }
    body.dark-mode .premium-table tbody tr.selected {
        border-color: #404040 !important;
        background: #18181b !important;
    }
    .premium-table tbody td.d-md-none {
        display: block !important;
        width: 100% !important;
        padding: 0 !important;
        border: none !important;
    }
    .premium-table tbody td.d-none {
        display: none !important;
    }
}
</style>

<script>
window.addEventListener('DOMContentLoaded', function(){
    function getCheckedIds(){
        var boxes = document.querySelectorAll('.dept-checkbox:checked');
        var ids = [];
        boxes.forEach(function(b){ ids.push(b.value); });
        return ids;
    }

    function requireAtLeastOneDeptSelected(ids) {
        if (ids.length < 1) {
            var box = document.getElementById('deptsClientError');
            if (!box) {
                var wrapper = document.createElement('div');
                wrapper.innerHTML = ''
                    + '<div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert" id="deptsClientError" aria-live="polite" data-alert-static="1">'
                    + '  <i class="bi bi-exclamation-triangle-fill me-2"></i><span id="deptsClientErrorText"></span>'
                    + '  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
                    + '</div>';
                var newEl = wrapper.firstElementChild;
                var hero = document.querySelector('.settings-hero');
                if (hero && hero.parentNode) {
                    hero.parentNode.insertBefore(newEl, hero.nextSibling);
                } else {
                    document.body.insertBefore(newEl, document.body.firstChild);
                }
                box = newEl;
            }
            var txt = document.getElementById('deptsClientErrorText');
            if (txt) txt.textContent = 'Debe seleccionar al menos un departamento';
            box.classList.remove('d-none');
            box.scrollIntoView({ behavior: 'smooth', block: 'start' });
            try {
                if (box._autoHideTimer) window.clearTimeout(box._autoHideTimer);
                box._autoHideTimer = window.setTimeout(function(){
                    if (box) box.classList.add('d-none');
                }, 3500);
            } catch (e) {}
            return false;
        }
        return true;
    }

    // Toggle row styling when checked
    function syncRowSelection(checkbox) {
        var row = checkbox.closest('tr');
        if (row) {
            if (checkbox.checked) {
                row.classList.add('selected');
            } else {
                row.classList.remove('selected');
            }
        }
    }

    var checkboxes = document.querySelectorAll('.dept-checkbox');
    checkboxes.forEach(function(cb) {
        syncRowSelection(cb);
        cb.addEventListener('change', function() {
            syncRowSelection(cb);
        });
        
        // Also allow clicking anywhere on the row body except on checkbox container and action buttons/links to select it
        var row = cb.closest('tr');
        if (row) {
            row.addEventListener('click', function(e) {
                // If the user clicked on a link, button, or the checkbox itself, don't trigger
                if (e.target.closest('a') || e.target.closest('button') || e.target === cb || e.target.closest('.dept-checkbox')) {
                    return;
                }
                cb.checked = !cb.checked;
                cb.dispatchEvent(new Event('change'));
            });
        }
    });

    var selectAll = document.getElementById('selectAllDepts');
    if (selectAll) {
        selectAll.addEventListener('change', function(){
            var boxes = document.querySelectorAll('.dept-checkbox');
            boxes.forEach(function(b){ 
                b.checked = selectAll.checked; 
                syncRowSelection(b);
            });
        });
    }

    var actionButtons = document.querySelectorAll('[data-dept-action]');
    actionButtons.forEach(function(btn){
        btn.addEventListener('click', function(){
            var ids = getCheckedIds();
            var action = btn.getAttribute('data-dept-action') || '';
            if (action === 'delete') {
                if (!requireAtLeastOneDeptSelected(ids)) return;
                var countEl = document.getElementById('deleteDeptsCount');
                if (countEl) countEl.textContent = String(ids.length);
                var modalEl = document.getElementById('deleteDeptsModal');
                if (!modalEl || typeof bootstrap === 'undefined') return;
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
                return;
            }
            if (!requireAtLeastOneDeptSelected(ids)) return;
            var form = document.getElementById('deptsMassForm');
            var act = document.getElementById('deptsMassAction');
            if (!form || !act) return;
            act.value = action;
            form.submit();
        });
    });

    var confirmDeleteBtn = document.getElementById('confirmDeleteDeptsBtn');
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function(){
            var ids = getCheckedIds();
            if (!requireAtLeastOneDeptSelected(ids)) return;
            var form = document.getElementById('deptsMassForm');
            var act = document.getElementById('deptsMassAction');
            if (!form || !act) return;
            act.value = 'delete';
            form.submit();
        });
    }

    var editBtns = document.querySelectorAll('.dept-edit-btn');
    editBtns.forEach(function(btn){
        btn.addEventListener('click', function(){
            var id = btn.getAttribute('data-id') || '';
            var name = btn.getAttribute('data-name') || '';
            var desc = btn.getAttribute('data-description') || '';
            var active = (btn.getAttribute('data-is-active') || '0') === '1';
            var reqRep = (btn.getAttribute('data-requires-report') || '0') === '1';
            var idEl = document.getElementById('dept_edit_id');
            var nameEl = document.getElementById('dept_edit_name');
            var descEl = document.getElementById('dept_edit_description');
            var actEl = document.getElementById('dept_edit_is_active');
            var repEl = document.getElementById('dept_edit_requires_report');
            if (idEl) idEl.value = id;
            if (nameEl) nameEl.value = name;
            if (descEl) descEl.value = desc;
            if (actEl) actEl.checked = active;
            if (repEl) repEl.checked = reqRep;
        });
    });
});
</script>

<?php
$content = ob_get_clean();

require_once 'layout_admin.php';
?>
