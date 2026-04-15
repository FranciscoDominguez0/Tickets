<?php
require_once '../../config.php';
require_once '../../includes/helpers.php';
require_once '../../includes/Auth.php';

if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

requireLogin('agente');
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

        if ($name === '') {
            $_SESSION['flash_error'] = 'El nombre del departamento es requerido.';
            header('Location: departments.php');
            exit;
        }

        if ($deptHasEmpresa) {
            $stmt = $mysqli->prepare('INSERT INTO departments (empresa_id, name, description, is_active, created) VALUES (?, ?, ?, ?, NOW())');
        } else {
            $stmt = $mysqli->prepare('INSERT INTO departments (name, description, is_active, created) VALUES (?, ?, ?, NOW())');
        }
        if (!$stmt) {
            $_SESSION['flash_error'] = 'No se pudo crear el departamento.';
            header('Location: departments.php');
            exit;
        }
        $descParam = $description !== '' ? $description : null;
        if ($deptHasEmpresa) {
            $stmt->bind_param('issi', $eid, $name, $descParam, $isActive);
        } else {
            $stmt->bind_param('ssi', $name, $descParam, $isActive);
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
            $stmt = $mysqli->prepare('UPDATE departments SET name = ?, description = ?, is_active = ? WHERE id = ? AND empresa_id = ?');
        } else {
            $stmt = $mysqli->prepare('UPDATE departments SET name = ?, description = ?, is_active = ? WHERE id = ?');
        }
        if (!$stmt) {
            $_SESSION['flash_error'] = 'No se pudo actualizar el departamento.';
            header('Location: departments.php');
            exit;
        }
        $descParam = $description !== '' ? $description : null;
        if ($deptHasEmpresa) {
            $stmt->bind_param('ssiii', $name, $descParam, $isActive, $id, $eid);
        } else {
            $stmt->bind_param('ssii', $name, $descParam, $isActive, $id);
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
$sql .= "    GROUP BY d.id, d.name, d.description, d.is_active, ea.id, ea.email, ea.name
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
            <span class="badge bg-success"><?php echo (int)$activeCount; ?> Activos</span>
            <span class="badge bg-secondary"><?php echo (int)$inactiveCount; ?> Inactivos</span>
            <span class="badge bg-info"><?php echo (int)count($departments); ?> Total</span>
        </div>
    </div>
</div>

<?php if ($flashError): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?php echo html($flashError); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($flashMsg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i><?php echo html($flashMsg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="alert alert-danger alert-dismissible fade show d-none" role="alert" id="deptsClientError" aria-live="polite" data-alert-static="1">
    <i class="bi bi-exclamation-triangle me-2"></i><span id="deptsClientErrorText"></span>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>

<div class="row">
    <div class="col-12">
        <div class="card settings-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-diagram-3"></i> Lista de Departamentos</strong>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addDeptModal">
                        <i class="bi bi-plus-circle"></i> Añadir nuevo Departamento
                    </button>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" id="deptsMoreDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-gear"></i> Más
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="deptsMoreDropdown">
                            <li><button class="dropdown-item" type="button" data-dept-action="enable"><i class="bi bi-check-circle me-2"></i>Habilitar</button></li>
                            <li><button class="dropdown-item" type="button" data-dept-action="disable"><i class="bi bi-slash-circle me-2"></i>Deshabilitar</button></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><button class="dropdown-item text-danger" type="button" data-dept-action="delete"><i class="bi bi-trash me-2"></i>Eliminar</button></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <form method="post" action="departments.php" id="deptsMassForm">
                    <input type="hidden" name="do" value="mass_process">
                    <?php csrfField(); ?>
                    <input type="hidden" name="a" value="" id="deptsMassAction">

                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="30"><input type="checkbox" id="selectAllDepts" class="form-check-input"></th>
                                    <th>Departamento</th>
                                    <th class="text-center">Estado</th>
                                    <th class="text-center">Agentes</th>
                                    <th>Correo Electrónico</th>
                                    <th class="text-center">Tickets</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($departments)): ?>
                                    <tr><td colspan="7" class="text-center text-muted py-4">No hay departamentos para mostrar.</td></tr>
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
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="ids[]" value="<?php echo $id; ?>" class="form-check-input dept-checkbox">
                                            </td>
                                            <td>
                                                <div class="fw-semibold">
                                                    <?php echo html($name); ?>
                                                </div>
                                                <div class="text-muted small"><?php if ($description !== ''): ?><?php echo html($description); ?><?php endif; ?></div>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($active): ?>
                                                    <span class="badge bg-success">Activo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactivo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center"><strong><?php echo (int)$staffTotal; ?></strong></td>
                                            <td>
                                                <?php if ($deptEmail !== '' && $emailId > 0): ?>
                                                    <a class="text-decoration-none" href="email.php?id=<?php echo (int)$emailId; ?>">
                                                        <?php if ($deptEmailName !== ''): ?>
                                                            <?php echo html($deptEmailName); ?> &lt;<?php echo html($deptEmail); ?>&gt;
                                                        <?php else: ?>
                                                            <?php echo html($deptEmail); ?>
                                                        <?php endif; ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center"><strong><?php echo (int)$ticketTotal; ?></strong></td>
                                            <td class="text-end">
                                                <button type="button" class="btn btn-sm btn-outline-primary dept-edit-btn"
                                                    data-id="<?php echo $id; ?>"
                                                    data-name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-description="<?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-is-active="<?php echo $active ? '1' : '0'; ?>"
                                                    data-bs-toggle="modal" data-bs-target="#editDeptModal">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addDeptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="departments.php">
                <input type="hidden" name="do" value="create">
                <?php csrfField(); ?>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle text-primary"></i> Añadir nuevo Departamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="createDeptIsActive" checked>
                        <label class="form-check-label" for="createDeptIsActive">Activo</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editDeptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="departments.php">
                <input type="hidden" name="do" value="update">
                <?php csrfField(); ?>
                <input type="hidden" name="id" id="dept_edit_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil text-primary"></i> Editar Departamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="name" id="dept_edit_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea name="description" id="dept_edit_description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="dept_edit_is_active" checked>
                        <label class="form-check-label" for="dept_edit_is_active">Activo</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteDeptsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-trash text-danger"></i> Eliminar departamentos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                ¿Deseas eliminar los departamentos seleccionados?
                <div class="text-muted small mt-2">
                    Seleccionados: <strong><span id="deleteDeptsCount">0</span></strong>
                </div>
                <div class="text-muted small mt-2">Solo se eliminarán departamentos sin agentes ni tickets asignados.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteDeptsBtn"><i class="bi bi-trash"></i> Eliminar</button>
            </div>
        </div>
    </div>
</div>

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
                    + '<div class="alert alert-danger alert-dismissible fade show" role="alert" id="deptsClientError" aria-live="polite" data-alert-static="1">'
                    + '  <i class="bi bi-exclamation-triangle me-2"></i><span id="deptsClientErrorText"></span>'
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

    var selectAll = document.getElementById('selectAllDepts');
    if (selectAll) {
        selectAll.addEventListener('change', function(){
            var boxes = document.querySelectorAll('.dept-checkbox');
            boxes.forEach(function(b){ b.checked = selectAll.checked; });
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
            var idEl = document.getElementById('dept_edit_id');
            var nameEl = document.getElementById('dept_edit_name');
            var descEl = document.getElementById('dept_edit_description');
            var actEl = document.getElementById('dept_edit_is_active');
            if (idEl) idEl.value = id;
            if (nameEl) nameEl.value = name;
            if (descEl) descEl.value = desc;
            if (actEl) actEl.checked = active;
        });
    });
});
</script>

<?php
$content = ob_get_clean();

require_once 'layout_admin.php';
?>
