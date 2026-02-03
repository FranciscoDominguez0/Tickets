<?php
// Módulo: Organizaciones (estilo osTicket)
// Listado desde tabla organizations + usuarios con company (compatibilidad)

$action_msg = null;
$action_type = null;

// Crear tabla de organizaciones si no existe
$mysqli->query("
    CREATE TABLE IF NOT EXISTS organizations (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(255) UNIQUE NOT NULL,
        address TEXT,
        phone VARCHAR(50),
        phone_ext VARCHAR(20),
        website VARCHAR(255),
        notes TEXT,
        created DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_name (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Agregar organización
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do']) && $_POST['do'] === 'add') {
    if (isset($_POST['csrf_token']) && Auth::validateCSRF($_POST['csrf_token'])) {
        $org_name = trim($_POST['org_name'] ?? '');
        $org_address = trim($_POST['org_address'] ?? '');
        $org_phone = trim($_POST['org_phone'] ?? '');
        $org_phone_ext = trim($_POST['org_phone_ext'] ?? '');
        $org_website = trim($_POST['org_website'] ?? '');
        $org_notes = trim($_POST['org_notes'] ?? '');

        $errors = [];
        if (!$org_name) {
            $errors[] = 'El nombre de la organización es obligatorio.';
        }
        if (empty($errors)) {
            $stmt = $mysqli->prepare("SELECT id FROM organizations WHERE name = ? LIMIT 1");
            $stmt->bind_param('s', $org_name);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()) {
                $errors[] = 'Ya existe una organización con ese nombre.';
            }
        }
        if (empty($errors)) {
            $stmt = $mysqli->prepare("INSERT INTO organizations (name, address, phone, phone_ext, website, notes) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('ssssss', $org_name, $org_address, $org_phone, $org_phone_ext, $org_website, $org_notes);
            if ($stmt->execute()) {
                header('Location: orgs.php?org=' . urlencode($org_name) . '&msg=org_added');
                exit;
            }
            $errors[] = 'Error al guardar. Inténtalo de nuevo.';
        }
        if (!empty($errors)) {
            $action_msg = implode('<br>', $errors);
            $action_type = 'danger';
        }
    }
}

// Eliminar organización
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do']) && $_POST['do'] === 'delete') {
    if (isset($_POST['csrf_token']) && Auth::validateCSRF($_POST['csrf_token'])) {
        $org_name = trim($_POST['org_name'] ?? '');
        if ($org_name) {
            $stmt = $mysqli->prepare("DELETE FROM organizations WHERE name = ?");
            $stmt->bind_param('s', $org_name);
            if ($stmt->execute()) {
                $stmt2 = $mysqli->prepare("UPDATE users SET company = NULL WHERE company = ?");
                $stmt2->bind_param('s', $org_name);
                $stmt2->execute();
                header('Location: orgs.php?msg=org_deleted');
                exit;
            }
        }
    }
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'org_added') {
        $action_msg = 'Organización creada exitosamente.';
        $action_type = 'success';
    } elseif ($_GET['msg'] === 'org_deleted') {
        $action_msg = 'Organización eliminada exitosamente.';
        $action_type = 'success';
    }
}

// ---------- Vista detalle (orgs.php?org=Nombre) ----------
if (!empty($_GET['org'])) {
    $orgName = trim($_GET['org']);

    $orgData = null;
    $stmt = $mysqli->prepare("SELECT * FROM organizations WHERE name = ? LIMIT 1");
    $stmt->bind_param('s', $orgName);
    $stmt->execute();
    $orgData = $stmt->get_result()->fetch_assoc();

    if (!$orgData) {
        $stmt = $mysqli->prepare("
            SELECT u.company AS name, COUNT(DISTINCT u.id) AS user_count, COUNT(DISTINCT t.id) AS ticket_count,
                   SUM(CASE WHEN ts.name IN ('Abierto','En Progreso','Esperando Usuario') THEN 1 ELSE 0 END) AS open_tickets,
                   MIN(u.created) AS since
            FROM users u
            LEFT JOIN tickets t ON t.user_id = u.id
            LEFT JOIN ticket_status ts ON ts.id = t.status_id
            WHERE u.company = ?
            GROUP BY u.company
        ");
        $stmt->bind_param('s', $orgName);
        $stmt->execute();
        $orgData = $stmt->get_result()->fetch_assoc();
    }

    if (!$orgData) {
        echo '<div class="alert alert-warning m-4">Organización no encontrada.</div>';
        return;
    }

    $orgInfo = $orgData;
    if (!isset($orgInfo['user_count'])) {
        $stmt = $mysqli->prepare("
            SELECT COUNT(DISTINCT u.id) AS user_count, COUNT(DISTINCT t.id) AS ticket_count,
                   SUM(CASE WHEN ts.name IN ('Abierto','En Progreso','Esperando Usuario') THEN 1 ELSE 0 END) AS open_tickets,
                   MIN(u.created) AS since
            FROM users u
            LEFT JOIN tickets t ON t.user_id = u.id
            LEFT JOIN ticket_status ts ON ts.id = t.status_id
            WHERE u.company = ?
        ");
        $stmt->bind_param('s', $orgName);
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc();
        $orgInfo = array_merge($orgData, $stats ?: ['user_count' => 0, 'ticket_count' => 0, 'open_tickets' => 0, 'since' => null]);
    }
    $orgInfo['address'] = $orgInfo['address'] ?? '';
    $orgInfo['phone'] = $orgInfo['phone'] ?? '';
    $orgInfo['phone_ext'] = $orgInfo['phone_ext'] ?? '';
    $orgInfo['website'] = $orgInfo['website'] ?? '';
    $orgInfo['notes'] = $orgInfo['notes'] ?? '';

    $stmt = $mysqli->prepare("SELECT id, firstname, lastname, email, phone, status, created FROM users WHERE company = ? ORDER BY firstname, lastname");
    $stmt->bind_param('s', $orgName);
    $stmt->execute();
    $orgUsers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $tickets = [];
    $stmt = $mysqli->prepare("
        SELECT t.id, t.ticket_number, t.subject, ts.name AS status_name, p.name AS priority_name, d.name AS dept_name, t.created
        FROM tickets t
        JOIN users u ON t.user_id = u.id
        JOIN departments d ON t.dept_id = d.id
        JOIN ticket_status ts ON t.status_id = ts.id
        JOIN priorities p ON t.priority_id = p.id
        WHERE u.company = ?
        ORDER BY t.created DESC
        LIMIT 100
    ");
    $stmt->bind_param('s', $orgName);
    $stmt->execute();
    $tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    ?>
    <div class="org-detail-container">
        <?php if ($action_msg): ?>
            <div class="alert alert-<?php echo $action_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $action_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <div class="user-view-header">
            <div>
                <a href="orgs.php" class="org-back-link"><i class="bi bi-arrow-left"></i> Volver al listado</a>
                <h1 class="user-view-title">
                    <i class="bi bi-building"></i>
                    <?php echo html($orgInfo['name']); ?>
                </h1>
            </div>
            <div class="user-view-actions">
                <button type="button" class="btn btn-danger btn-delete-org" data-org-name="<?php echo html($orgInfo['name']); ?>">
                    <i class="bi bi-trash"></i> Eliminar organización
                </button>
            </div>
        </div>

        <div class="user-view-card mb-4">
            <div class="user-view-profile">
                <div class="user-view-avatar">
                    <i class="bi bi-building"></i>
                </div>
                <div class="user-view-details">
                    <div class="user-view-detail">
                        <label>Name</label>
                        <div class="value"><?php echo html($orgInfo['name']); ?></div>
                    </div>
                    <?php if (!empty($orgInfo['address'])): ?>
                    <div class="user-view-detail">
                        <label>Address</label>
                        <div class="value"><?php echo nl2br(html($orgInfo['address'])); ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="user-view-detail">
                        <label>Phone</label>
                        <div class="value">
                            <?php
                            $phone = html($orgInfo['phone'] ?? '');
                            $ext = html($orgInfo['phone_ext'] ?? '');
                            echo $phone ? $phone . ($ext ? ' ext. ' . $ext : '') : '—';
                            ?>
                        </div>
                    </div>
                    <?php if (!empty($orgInfo['website'])): ?>
                    <div class="user-view-detail">
                        <label>Website</label>
                        <div class="value">
                            <a href="<?php echo html($orgInfo['website']); ?>" target="_blank" rel="noopener"><?php echo html($orgInfo['website']); ?></a>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($orgInfo['notes'])): ?>
                    <div class="user-view-detail" style="grid-column: 1 / -1;">
                        <label>Internal Notes</label>
                        <div class="value" style="white-space: pre-wrap;"><?php echo html($orgInfo['notes']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="org-stat-card org-stat-users">
                    <div class="org-stat-icon"><i class="bi bi-people"></i></div>
                    <div class="org-stat-content">
                        <div class="org-stat-value"><?php echo (int)($orgInfo['user_count'] ?? 0); ?></div>
                        <div class="org-stat-label">Usuarios</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="org-stat-card org-stat-tickets">
                    <div class="org-stat-icon"><i class="bi bi-ticket"></i></div>
                    <div class="org-stat-content">
                        <div class="org-stat-value"><?php echo (int)($orgInfo['ticket_count'] ?? 0); ?></div>
                        <div class="org-stat-label">Tickets totales</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="org-stat-card org-stat-open">
                    <div class="org-stat-icon"><i class="bi bi-clock-history"></i></div>
                    <div class="org-stat-content">
                        <div class="org-stat-value"><?php echo (int)($orgInfo['open_tickets'] ?? 0); ?></div>
                        <div class="org-stat-label">Tickets abiertos</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="org-stat-card org-stat-date">
                    <div class="org-stat-icon"><i class="bi bi-calendar-event"></i></div>
                    <div class="org-stat-content">
                        <div class="org-stat-value"><?php echo !empty($orgInfo['since']) ? date('d/m/Y', strtotime($orgInfo['since'])) : '—'; ?></div>
                        <div class="org-stat-label">Desde</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="user-view-tabs">
            <a href="#org-users" class="tab active" data-bs-toggle="tab" data-bs-target="#org-users"><i class="bi bi-people"></i> Usuarios</a>
            <a href="#org-tickets" class="tab" data-bs-toggle="tab" data-bs-target="#org-tickets"><i class="bi bi-ticket"></i> Tickets</a>
        </div>
        <div class="user-view-card">
            <div class="tab-content">
                <div class="tab-pane fade show active user-view-tab-content" id="org-users">
                    <?php if (empty($orgUsers)): ?>
                        <div class="empty-state">
                            <i class="bi bi-people icon"></i>
                            <p>No hay usuarios asociados a esta organización.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="user-view-tickets-table">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Email</th>
                                        <th>Teléfono</th>
                                        <th>Estado</th>
                                        <th>Creado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orgUsers as $u): ?>
                                        <tr>
                                            <td><a href="users.php?id=<?php echo (int)$u['id']; ?>"><?php echo html(trim($u['firstname'].' '.$u['lastname'])); ?></a></td>
                                            <td><?php echo html($u['email']); ?></td>
                                            <td><?php echo html($u['phone'] ?? '—'); ?></td>
                                            <td><span class="user-view-status-badge <?php echo html($u['status']); ?>"><?php echo $u['status'] === 'active' ? 'Activo' : ($u['status'] === 'inactive' ? 'Inactivo' : 'Bloqueado'); ?></span></td>
                                            <td><?php echo $u['created'] ? date('d/m/y', strtotime($u['created'])) : '—'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="tab-pane fade user-view-tab-content" id="org-tickets">
                    <?php if (empty($tickets)): ?>
                        <div class="empty-state">
                            <i class="bi bi-ticket icon"></i>
                            <p>No hay tickets para esta organización.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="user-view-tickets-table">
                                <thead>
                                    <tr>
                                        <th>Número</th>
                                        <th>Asunto</th>
                                        <th>Departamento</th>
                                        <th>Estado</th>
                                        <th>Prioridad</th>
                                        <th>Creado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tickets as $tkt): ?>
                                        <tr>
                                            <td><a href="tickets.php?id=<?php echo (int)$tkt['id']; ?>"><?php echo html($tkt['ticket_number']); ?></a></td>
                                            <td><?php echo html($tkt['subject']); ?></td>
                                            <td><?php echo html($tkt['dept_name'] ?? '—'); ?></td>
                                            <td><?php echo html($tkt['status_name'] ?? '—'); ?></td>
                                            <td><?php echo html($tkt['priority_name'] ?? '—'); ?></td>
                                            <td><?php echo $tkt['created'] ? date('d/m/y H:i', strtotime($tkt['created'])) : '—'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteOrgModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content org-modal-content org-modal-danger">
                <div class="modal-header org-modal-header">
                    <h5 class="modal-title org-modal-title"><i class="bi bi-exclamation-triangle"></i> Eliminar organización</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" action="orgs.php" id="deleteOrgForm">
                    <?php csrfField(); ?>
                    <input type="hidden" name="do" value="delete">
                    <input type="hidden" name="org_name" id="delete_org_name" value="<?php echo html($orgInfo['name']); ?>">
                    <div class="modal-body org-modal-body">
                        <div class="org-delete-warning">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <p class="mb-2"><strong>¿Estás seguro de eliminar esta organización?</strong></p>
                            <p class="mb-0 text-muted">Se eliminará la organización y la asociación de usuarios. Los usuarios y tickets no se borran.</p>
                        </div>
                        <div class="org-delete-org-name"><strong id="delete_org_display"><?php echo html($orgInfo['name']); ?></strong></div>
                    </div>
                    <div class="modal-footer org-modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-danger" onclick="if(confirm('¿Confirmas que deseas eliminar permanentemente esta organización?')) document.getElementById('deleteOrgForm').submit();"><i class="bi bi-trash"></i> Eliminar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
    return;
}

// ---------- Listado de organizaciones ----------
// Mostrar solo de tabla organizations
$search = trim($_GET['q'] ?? '');
$pageNum = max(1, (int)($_GET['p'] ?? 1));
$perPage = 20;

$like = $search !== '' ? '%' . $search . '%' : '';

// Organizaciones de la tabla organizations
$sql1 = "
    SELECT o.*,
           COUNT(DISTINCT u.id) AS user_count,
           COUNT(DISTINCT t.id) AS ticket_count,
           SUM(CASE WHEN ts.name IN ('Abierto','En Progreso','Esperando Usuario') THEN 1 ELSE 0 END) AS open_tickets,
           MIN(u.created) AS since
    FROM organizations o
    LEFT JOIN users u ON u.company = o.name
    LEFT JOIN tickets t ON t.user_id = u.id
    LEFT JOIN ticket_status ts ON ts.id = t.status_id
    WHERE 1=1
";
$params1 = [];
$types1 = '';
if ($search !== '') {
    $sql1 .= " AND o.name LIKE ?";
    $params1[] = $like;
    $types1 .= 's';
}
$sql1 .= " GROUP BY o.id ORDER BY o.name ASC";

$stmt = $mysqli->prepare($sql1);
if (!empty($params1)) {
    $stmt->bind_param($types1, ...$params1);
}
$stmt->execute();
$allOrgs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$totalRows = count($allOrgs);
$totalPages = $totalRows ? (int)ceil($totalRows / $perPage) : 1;
$pageNum = min($pageNum, max(1, $totalPages));
$offset = ($pageNum - 1) * $perPage;
$orgs = array_slice($allOrgs, $offset, $perPage);
?>

<div class="org-list-container">
    <?php if ($action_msg): ?>
        <div class="alert alert-<?php echo $action_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $action_msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="org-search-card">
        <form method="get" action="orgs.php" class="org-search-form">
            <div class="org-search-input-wrapper">
                <i class="bi bi-search org-search-icon"></i>
                <input type="text" name="q" class="org-search-input" placeholder="Buscar por nombre de organización..." value="<?php echo html($search); ?>">
                <?php if ($search): ?>
                    <a href="orgs.php" class="org-search-clear"><i class="bi bi-x-circle"></i></a>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn org-search-btn"><i class="bi bi-search"></i> Buscar</button>
        </form>
    </div>

    <div class="org-header-section">
        <h1 class="org-page-title">Organizaciones</h1>
        <div class="header-actions">
            <button type="button" class="btn btn-add-org" data-bs-toggle="modal" data-bs-target="#addOrgModal">
                <i class="bi bi-plus-lg"></i> Añadir organización
            </button>
        </div>
    </div>

    <?php if (empty($orgs)): ?>
        <div class="table-card" style="padding: 48px 24px; text-align: center;">
            <p class="text-muted mb-0">
                <i class="bi bi-building" style="font-size: 2.5rem; opacity: 0.5;"></i><br>
                No se encontraron organizaciones<?php echo $search ? ' con ese criterio.' : '.'; ?>
            </p>
            <?php if (!$search): ?>
                <button type="button" class="btn btn-add-org mt-3" data-bs-toggle="modal" data-bs-target="#addOrgModal">
                    <i class="bi bi-plus-lg"></i> Añadir organización
                </button>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-card">
            <div class="org-grid">
                <?php foreach ($orgs as $o): ?>
                    <div class="org-card">
                        <div class="org-card-header">
                            <div class="org-card-icon"><i class="bi bi-building"></i></div>
                            <h3 class="org-card-title">
                                <a href="orgs.php?org=<?php echo urlencode($o['name']); ?>"><?php echo html($o['name']); ?></a>
                            </h3>
                        </div>
                        <div class="org-card-body">
                            <div class="org-card-stats">
                                <div class="org-card-stat">
                                    <i class="bi bi-people"></i>
                                    <span class="org-card-stat-value"><?php echo (int)($o['user_count'] ?? 0); ?></span>
                                    <span class="org-card-stat-label">Usuarios</span>
                                </div>
                                <div class="org-card-stat">
                                    <i class="bi bi-clock-history"></i>
                                    <span class="org-card-stat-value"><?php echo (int)($o['open_tickets'] ?? 0); ?></span>
                                    <span class="org-card-stat-label">Abiertos</span>
                                </div>
                                <div class="org-card-stat">
                                    <i class="bi bi-ticket"></i>
                                    <span class="org-card-stat-value"><?php echo (int)($o['ticket_count'] ?? 0); ?></span>
                                    <span class="org-card-stat-label">Total</span>
                                </div>
                            </div>
                            <div class="org-card-footer">
                                <span class="org-card-date">
                                    <i class="bi bi-calendar-event"></i>
                                    Desde <?php echo !empty($o['since']) ? date('d/m/Y', strtotime($o['since'])) : ($o['created'] ?? '—'); ?>
                                </span>
                                <a href="orgs.php?org=<?php echo urlencode($o['name']); ?>" class="org-card-link">Ver detalles <i class="bi bi-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="table-footer-bar">
                    <div class="showing-text">
                        Mostrando <?php echo $totalRows ? ($offset + 1) : 0; ?> – <?php echo min($offset + $perPage, $totalRows); ?> de <?php echo $totalRows; ?>
                    </div>
                    <div class="pagination-wrap">
                        <?php if ($pageNum > 1): ?>
                            <a href="orgs.php?<?php echo http_build_query(['q' => $search, 'p' => $pageNum - 1]); ?>"><i class="bi bi-chevron-left"></i></a>
                        <?php endif; ?>
                        <?php for ($i = max(1, $pageNum - 2); $i <= min($totalPages, $pageNum + 2); $i++): ?>
                            <?php if ($i === $pageNum): ?>
                                <strong style="margin: 0 4px;"><?php echo $i; ?></strong>
                            <?php else: ?>
                                <a href="orgs.php?<?php echo http_build_query(['q' => $search, 'p' => $i]); ?>" style="margin: 0 4px;"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <?php if ($pageNum < $totalPages): ?>
                            <a href="orgs.php?<?php echo http_build_query(['q' => $search, 'p' => $pageNum + 1]); ?>"><i class="bi bi-chevron-right"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Añadir Organización -->
<div class="modal fade" id="addOrgModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content org-modal-content">
            <div class="modal-header org-modal-header">
                <h5 class="modal-title org-modal-title"><i class="bi bi-building"></i> Añadir nueva organización</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="orgs.php" id="addOrgForm">
                <?php csrfField(); ?>
                <input type="hidden" name="do" value="add">
                <div class="modal-body org-modal-body">
                    <?php if ($action_msg && $action_type === 'danger'): ?>
                        <div class="alert alert-danger"><?php echo $action_msg; ?></div>
                    <?php endif; ?>
                    <div class="org-form-group">
                        <label for="org_name" class="org-form-label"><i class="bi bi-building"></i> Name <span class="text-danger">*</span></label>
                        <input type="text" class="org-form-control" id="org_name" name="org_name" required placeholder="Nombre de la organización" value="<?php echo html($_POST['org_name'] ?? ''); ?>">
                    </div>
                    <div class="org-form-group">
                        <label for="org_address" class="org-form-label"><i class="bi bi-geo-alt"></i> Address</label>
                        <textarea class="org-form-control" id="org_address" name="org_address" rows="2" placeholder="Dirección"><?php echo html($_POST['org_address'] ?? ''); ?></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-8">
                            <div class="org-form-group">
                                <label for="org_phone" class="org-form-label"><i class="bi bi-telephone"></i> Phone</label>
                                <input type="text" class="org-form-control" id="org_phone" name="org_phone" placeholder="Teléfono" value="<?php echo html($_POST['org_phone'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="org-form-group">
                                <label for="org_phone_ext" class="org-form-label"><i class="bi bi-telephone-forward"></i> EXT</label>
                                <input type="text" class="org-form-control" id="org_phone_ext" name="org_phone_ext" placeholder="Ext." value="<?php echo html($_POST['org_phone_ext'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="org-form-group">
                        <label for="org_website" class="org-form-label"><i class="bi bi-globe"></i> Website</label>
                        <input type="text" class="org-form-control" id="org_website" name="org_website" placeholder="https://www.ejemplo.com" value="<?php echo html($_POST['org_website'] ?? ''); ?>">
                    </div>
                    <div class="org-form-group">
                        <label for="org_notes" class="org-form-label"><i class="bi bi-file-text"></i> Internal Notes</label>
                        <textarea class="org-form-control" id="org_notes" name="org_notes" rows="4" placeholder="Notas internas"><?php echo html($_POST['org_notes'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="modal-footer org-modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Crear organización</button>
                </div>
            </form>
        </div>
    </div>
</div>
