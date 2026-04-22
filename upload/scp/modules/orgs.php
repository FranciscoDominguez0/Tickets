<?php
// Módulo: Organizaciones (estilo osTicket)
// Listado desde tabla organizations + usuarios con company (compatibilidad)

$action_msg = null;
$action_type = null;

$eid = empresaId();

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
            $stmt = $mysqli->prepare("SELECT id FROM organizations WHERE empresa_id = ? AND name = ? LIMIT 1");
            $stmt->bind_param('is', $eid, $org_name);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()) {
                $errors[] = 'Ya existe una organización con ese nombre.';
            }
        }
        if (empty($errors)) {
            $stmt = $mysqli->prepare("INSERT INTO organizations (empresa_id, name, address, phone, phone_ext, website, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('issssss', $eid, $org_name, $org_address, $org_phone, $org_phone_ext, $org_website, $org_notes);
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
            $stmt = $mysqli->prepare("DELETE FROM organizations WHERE empresa_id = ? AND name = ?");
            $stmt->bind_param('is', $eid, $org_name);
            if ($stmt->execute()) {
                $stmt2 = $mysqli->prepare("UPDATE users SET company = NULL WHERE empresa_id = ? AND company = ?");
                $stmt2->bind_param('is', $eid, $org_name);
                $stmt2->execute();
                header('Location: orgs.php?msg=org_deleted');
                exit;
            }
        }
    }
}

// Editar organización
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do']) && $_POST['do'] === 'update') {
    if (isset($_POST['csrf_token']) && Auth::validateCSRF($_POST['csrf_token'])) {
        $old_name = trim((string)($_POST['old_name'] ?? ''));
        $org_name = trim((string)($_POST['org_name'] ?? ''));
        $org_address = trim((string)($_POST['org_address'] ?? ''));
        $org_phone = trim((string)($_POST['org_phone'] ?? ''));
        $org_phone_ext = trim((string)($_POST['org_phone_ext'] ?? ''));
        $org_website = trim((string)($_POST['org_website'] ?? ''));
        $org_notes = trim((string)($_POST['org_notes'] ?? ''));

        $errors = [];
        if ($old_name === '') $errors[] = 'Organización inválida.';
        if ($org_name === '') $errors[] = 'El nombre de la organización es obligatorio.';

        if (empty($errors) && strcasecmp($old_name, $org_name) !== 0) {
            $stmtC = $mysqli->prepare('SELECT id FROM organizations WHERE empresa_id = ? AND name = ? LIMIT 1');
            if ($stmtC) {
                $stmtC->bind_param('is', $eid, $org_name);
                $stmtC->execute();
                if ($stmtC->get_result()->fetch_assoc()) {
                    $errors[] = 'Ya existe una organización con ese nombre.';
                }
            }
        }

        if (empty($errors)) {
            $stmtE = $mysqli->prepare('SELECT id FROM organizations WHERE empresa_id = ? AND name = ? LIMIT 1');
            $existingId = 0;
            if ($stmtE) {
                $stmtE->bind_param('is', $eid, $old_name);
                $stmtE->execute();
                $row = $stmtE->get_result()->fetch_assoc();
                $existingId = (int)($row['id'] ?? 0);
            }

            if ($existingId > 0) {
                $stmtU = $mysqli->prepare('UPDATE organizations SET name = ?, address = ?, phone = ?, phone_ext = ?, website = ?, notes = ? WHERE id = ? AND empresa_id = ?');
                if ($stmtU) {
                    $stmtU->bind_param('ssssssii', $org_name, $org_address, $org_phone, $org_phone_ext, $org_website, $org_notes, $existingId, $eid);
                    $stmtU->execute();
                }
            } else {
                $stmtI = $mysqli->prepare('INSERT INTO organizations (empresa_id, name, address, phone, phone_ext, website, notes) VALUES (?, ?, ?, ?, ?, ?, ?)');
                if ($stmtI) {
                    $stmtI->bind_param('issssss', $eid, $org_name, $org_address, $org_phone, $org_phone_ext, $org_website, $org_notes);
                    $stmtI->execute();
                }
            }

            if (strcasecmp($old_name, $org_name) !== 0) {
                $stmtUc = $mysqli->prepare('UPDATE users SET company = ? WHERE empresa_id = ? AND company = ?');
                if ($stmtUc) {
                    $stmtUc->bind_param('sis', $org_name, $eid, $old_name);
                    $stmtUc->execute();
                }
            }

            header('Location: orgs.php?org=' . urlencode($org_name) . '&msg=org_updated');
            exit;
        }

        if (!empty($errors)) {
            $action_msg = implode('<br>', $errors);
            $action_type = 'danger';
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
    } elseif ($_GET['msg'] === 'org_updated') {
        $action_msg = 'Organización actualizada exitosamente.';
        $action_type = 'success';
    }
}

// ---------- Vista detalle (orgs.php?org=Nombre) ----------
if (!empty($_GET['org'])) {
    $orgName = trim($_GET['org']);

    $orgData = null;
    $stmt = $mysqli->prepare("SELECT * FROM organizations WHERE empresa_id = ? AND name = ? LIMIT 1");
    $stmt->bind_param('is', $eid, $orgName);
    $stmt->execute();
    $orgData = $stmt->get_result()->fetch_assoc();

    if (!$orgData) {
        $stmt = $mysqli->prepare("
            SELECT u.company AS name, COUNT(DISTINCT u.id) AS user_count, COUNT(DISTINCT t.id) AS ticket_count,
                   SUM(CASE WHEN ts.name IN ('Abierto','En Progreso','Esperando Usuario') THEN 1 ELSE 0 END) AS open_tickets,
                   MIN(u.created) AS since
            FROM users u
            LEFT JOIN tickets t ON t.user_id = u.id AND t.empresa_id = ?
            LEFT JOIN ticket_status ts ON ts.id = t.status_id
            WHERE u.empresa_id = ? AND u.company = ?
            GROUP BY u.company
        ");
        $stmt->bind_param('iis', $eid, $eid, $orgName);
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
            LEFT JOIN tickets t ON t.user_id = u.id AND t.empresa_id = ?
            LEFT JOIN ticket_status ts ON ts.id = t.status_id
            WHERE u.empresa_id = ? AND u.company = ?
        ");
        $stmt->bind_param('iis', $eid, $eid, $orgName);
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc();
        $orgInfo = array_merge($orgData, $stats ?: ['user_count' => 0, 'ticket_count' => 0, 'open_tickets' => 0, 'since' => null]);
    }
    $orgInfo['address'] = $orgInfo['address'] ?? '';
    $orgInfo['phone'] = $orgInfo['phone'] ?? '';
    $orgInfo['phone_ext'] = $orgInfo['phone_ext'] ?? '';
    $orgInfo['website'] = $orgInfo['website'] ?? '';
    $orgInfo['notes'] = $orgInfo['notes'] ?? '';

    $perPageLimit = 10;

    // Paginación para Usuarios
    $up = max(1, (int)($_GET['up'] ?? 1));
    $userTotal = (int)($orgInfo['user_count'] ?? 0);
    $uTotalPages = $userTotal ? (int)ceil($userTotal / $perPageLimit) : 1;
    $up = min($up, max(1, $uTotalPages));
    $uOffset = ($up - 1) * $perPageLimit;

    $stmt = $mysqli->prepare("SELECT id, firstname, lastname, email, phone, status, created FROM users WHERE empresa_id = ? AND company = ? ORDER BY firstname, lastname LIMIT ? OFFSET ?");
    $stmt->bind_param('isii', $eid, $orgName, $perPageLimit, $uOffset);
    $stmt->execute();
    $orgUsers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Paginación para Tickets
    $tp = max(1, (int)($_GET['tp'] ?? 1));
    $ticketTotal = (int)($orgInfo['ticket_count'] ?? 0);
    $tTotalPages = $ticketTotal ? (int)ceil($ticketTotal / $perPageLimit) : 1;
    $tp = min($tp, max(1, $tTotalPages));
    $tOffset = ($tp - 1) * $perPageLimit;

    $tickets = [];
    $stmt = $mysqli->prepare("
        SELECT t.id, t.ticket_number, t.subject, ts.name AS status_name, p.name AS priority_name, d.name AS dept_name, t.created
        FROM tickets t
        JOIN users u ON t.user_id = u.id
        JOIN departments d ON t.dept_id = d.id
        JOIN ticket_status ts ON t.status_id = ts.id
        JOIN priorities p ON t.priority_id = p.id
        WHERE t.empresa_id = ? AND u.empresa_id = ? AND u.company = ?
        ORDER BY t.created DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param('iisii', $eid, $eid, $orgName, $perPageLimit, $tOffset);
    $stmt->execute();
    $tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $activeTab = $_GET['t'] ?? 'users';
    if ($activeTab !== 'users' && $activeTab !== 'tickets') $activeTab = 'users';

    $orgsBaseUrl = (string)toAppAbsoluteUrl('upload/scp/orgs.php');
    $backToOrgTickets = 'orgs.php?org=' . urlencode($orgName) . '&t=tickets';
    $ticketsBaseUrl = (string)toAppAbsoluteUrl('upload/scp/tickets.php');
    ?>
    <style>
    /* Estilos Premium para Desktop - Vista Organización */
    @media (min-width: 769px) {
        .org-detail-container .user-view-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 16px;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 24px;
        }
        .org-detail-container .user-view-title {
            font-size: 1.8rem;
            font-weight: 800;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0;
        }
        
        /* Stats Cards Desktop */
        .org-detail-container .org-stat-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 16px 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
            transition: all 0.3s ease;
            height: 100%;
        }
        .org-detail-container .org-stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.1);
            border-color: #bfdbfe;
        }
        .org-detail-container .org-stat-icon {
            font-size: 1.6rem;
            color: #2563eb;
            background: #eff6ff;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            flex-shrink: 0;
        }
        .org-detail-container .org-stat-content {
            display: flex;
            flex-direction: column;
            width: 100%;
        }
        .org-detail-container .org-stat-value {
            font-size: clamp(0.9rem, 1.25vw + 0.1rem, 1.3rem);
            font-weight: 800;
            color: #0f172a;
            line-height: 1.2;
            word-break: break-word; /* Permite salto si no cabe */
        }
        .org-detail-container .org-stat-label {
            font-size: clamp(0.65rem, 0.8vw, 0.75rem);
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 2px;
            line-height: 1.2;
        }

        /* Tabla Premium para Tickets y Usuarios */
        .org-detail-container .table-responsive {
            background: #fff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
            padding: 0;
            overflow: hidden;
        }
        .org-detail-container .user-view-tickets-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 0;
        }
        .org-detail-container .user-view-tickets-table thead th {
            background: #f8fafc;
            color: #475569;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 16px 20px;
            border-bottom: 2px solid #e2e8f0;
            text-align: left;
        }
        .org-detail-container .user-view-tickets-table tbody tr {
            transition: background 0.2s;
        }
        .org-detail-container .user-view-tickets-table tbody tr:hover {
            background: #f8fafc;
        }
        .org-detail-container .user-view-tickets-table tbody td {
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            font-size: 0.95rem;
            vertical-align: middle;
        }
        .org-detail-container .user-view-tickets-table tbody tr:last-child td {
            border-bottom: none;
        }
        .org-detail-container .user-view-tickets-table tbody td a {
            color: #2563eb;
            font-weight: 600;
            text-decoration: none;
        }
        .org-detail-container .user-view-tickets-table tbody td a:hover {
            text-decoration: underline;
        }
        
        /* Detalles de Perfil de la Empresa */
        .org-detail-container .user-view-card {
            background: #fff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
            margin-bottom: 30px;
            padding: 24px;
        }
        .org-detail-container .user-view-profile {
            display: flex;
            align-items: flex-start;
            gap: 30px;
        }
        .org-detail-container .user-view-avatar {
            font-size: 3.5rem;
            color: #3b82f6;
            background: #eff6ff;
            width: 90px;
            height: 90px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 20px;
            flex-shrink: 0;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.06);
        }
        .org-detail-container .user-view-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            width: 100%;
        }
        .org-detail-container .user-view-detail {
            display: flex;
            flex-direction: column;
            background: #f8fafc;
            padding: 16px;
            border-radius: 12px;
            border: 1px solid #f1f5f9;
            text-align: left;
        }
        .org-detail-container .user-view-detail label {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.05em;
            margin-bottom: 6px;
        }
        .org-detail-container .user-view-detail .value {
            font-size: 1rem;
            color: #0f172a;
            font-weight: 600;
        }

        /* Nav Tabs Desktop */
        .org-detail-container .user-view-tabs {
            display: flex;
            gap: 8px;
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 24px;
            padding-bottom: 0;
        }
        .org-detail-container .user-view-tabs .tab {
            padding: 12px 24px;
            color: #64748b;
            font-weight: 600;
            border-bottom: 3px solid transparent;
            text-decoration: none;
            transition: all 0.2s;
            margin-bottom: -2px;
            font-size: 0.95rem;
        }
        .org-detail-container .user-view-tabs .tab:hover {
            color: #2563eb;
            background: #f8fafc;
            border-radius: 8px 8px 0 0;
        }
        .org-detail-container .user-view-tabs .tab.active {
            color: #2563eb;
            border-bottom-color: #2563eb;
            background: transparent;
        }
    }

    /* Corrección y Estilo Profesional en Móviles */
    @media (max-width: 768px) {
        .org-detail-container .user-view-header,
        .org-detail-container .user-view-card,
        .org-detail-container .user-view-tabs {
            display: block !important;
        }
        
        /* Solo mostrar el contenido de los tabs si es la pestaña activa, 
           así evitamos el enorme espacio en blanco fantasma de la inactiva */
        .org-detail-container .user-view-tab-content.active {
            display: block !important;
        }
        .org-detail-container .user-view-tabs {
            display: flex !important;
        }

        /* Ocultar columnas secundarias para limpieza visual */
        .org-hide-mobile {
            display: none !important;
        }

        /* Cabecera, Título y Acciones */
        .org-detail-container .user-view-header {
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 16px;
            margin-bottom: 20px;
        }
        .org-detail-container .user-view-actions {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .org-detail-container .user-view-actions .btn {
            width: 100%;
            justify-content: center;
            padding: 10px;
            font-weight: 600;
        }
        
        /* Contenedor del Perfil de Empresa */
        .org-detail-container .user-view-card {
            background: transparent;
            box-shadow: none;
            border: none;
            padding: 0;
            margin-bottom: 20px;
        }
        .org-detail-container .user-view-profile {
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            gap: 15px !important;
        }
        .org-detail-container .user-view-avatar {
            margin: 0 auto !important;
            width: 60px !important;
            height: 60px !important;
            font-size: 2rem !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }
        
        /* Campos del Perfil (Name, Address, Phone...) */
        .org-detail-container .user-view-details {
            display: flex !important;
            flex-direction: column !important;
            gap: 12px !important;
            width: 100% !important;
            margin-left: 0 !important; /* Quitar cualquier margen desktop */
        }
        .org-detail-container .user-view-detail {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            background: #fff;
            padding: 16px 14px;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 10px rgba(0,0,0,0.02);
            width: 100%;
        }
        .org-detail-container .user-view-detail label {
            font-size: 0.7rem;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 6px;
            font-weight: 700;
        }
        .org-detail-container .user-view-detail .value {
            font-size: 1rem;
            font-weight: 700;
            color: #0f172a;
        }
        .org-detail-container .user-view-detail .value a {
            color: #2563eb;
            text-decoration: none;
        }

        /* Tarjetas de Estadísticas (Stats Cards) - Grid 2x2 */
        .org-detail-container .row.g-4 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin: 0 0 24px 0 !important;
            padding: 0;
        }
        .org-detail-container .row.g-4 > .col-md-3 {
            width: 100%;
            padding: 0;
            margin: 0;
        }
        .org-detail-container .org-stat-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            background: #fff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            padding: 18px 10px;
            height: 100%;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            transition: transform 0.2s;
        }
        .org-detail-container .org-stat-card:active {
            transform: scale(0.97);
        }
        .org-detail-container .org-stat-icon {
            font-size: 1.5rem;
            color: #2563eb;
            margin-bottom: 10px;
            background: #eff6ff;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
        }
        .org-detail-container .org-stat-value {
            font-size: 1.4rem;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.1;
        }
        .org-detail-container .org-stat-label {
            font-size: 0.75rem;
            color: #64748b;
            font-weight: 600;
            margin-top: 6px;
        }

        /* Tabs */
        .org-detail-container .user-view-tabs {
            flex-wrap: wrap;
            gap: 8px;
        }
        .org-detail-container .user-view-tabs .tab {
            flex: 1 1 calc(50% - 8px);
            margin: 0;
            justify-content: center;
            border-radius: 8px;
        }

        /* Tablas tipo Tarjetas Profesionales */
        .org-detail-container .user-view-tickets-table thead {
            display: none;
        }
        .org-detail-container .user-view-tickets-table tbody tr {
            display: flex;
            flex-direction: column;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-top: 4px solid #2563eb;
            border-radius: 12px;
            margin-bottom: 16px;
            padding: 16px 12px 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
            align-items: center; /* Centramos tarjetas globalmente */
        }
        
        .org-detail-container .user-view-tickets-table tbody td {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 8px 0;
            border-bottom: 1px dashed #f1f5f9;
            width: 100%;
            font-size: 0.95rem;
            text-align: center;
        }
        .org-detail-container .user-view-tickets-table tbody td:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        .org-detail-container .user-view-tickets-table tbody td::before {
            content: attr(data-label);
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
        }
        
        /* Destacar el campo principal (ej: Nombre o Número/Asunto) */
        .org-detail-container .user-view-tickets-table tbody td.primary-field {
            font-size: 1.1rem;
            font-weight: 700;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 12px;
            margin-bottom: 4px;
        }
        .org-detail-container .user-view-tickets-table tbody td.primary-field::before {
            display: none; /* Ocultar "label" del título principal para que sea limpio */
        }
        .org-detail-container .user-view-tickets-table tbody td.primary-field a {
            color: #0f172a;
            text-decoration: none;
        }
    }
    </style>
    <div class="org-detail-container">
        <?php if ($action_msg): ?>
            <div class="alert alert-<?php echo $action_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $action_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php if (isset($_GET['msg']) && $action_type === 'success'): ?>
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
        <?php endif; ?>
        <div class="user-view-header">
            <div>
                <a href="<?php echo html($orgsBaseUrl); ?>" class="org-back-link"><i class="bi bi-arrow-left"></i> Volver al listado</a>
                <h1 class="user-view-title">
                    <i class="bi bi-building"></i>
                    <?php echo html($orgInfo['name']); ?>
                </h1>
            </div>
            <div class="user-view-actions">
                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editOrgModal">
                    <i class="bi bi-pencil"></i> Editar
                </button>
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

        <div class="user-view-tabs" id="org-tabs">
            <a href="<?php echo html($orgsBaseUrl); ?>?org=<?php echo urlencode($orgName); ?>&t=users" class="tab <?php echo $activeTab === 'users' ? 'active' : ''; ?>"><i class="bi bi-people"></i> Usuarios</a>
            <a href="<?php echo html($orgsBaseUrl); ?>?org=<?php echo urlencode($orgName); ?>&t=tickets" class="tab <?php echo $activeTab === 'tickets' ? 'active' : ''; ?>"><i class="bi bi-ticket"></i> Tickets</a>
        </div>
        <div class="user-view-card">
            <div class="tab-content">
                <div class="tab-pane fade <?php echo $activeTab === 'users' ? 'show active' : ''; ?> user-view-tab-content" id="org-users">
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
                                        <th>Usuario</th>
                                        <th>Estado</th>
                                        <th class="org-hide-mobile">Fecha de Registro</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orgUsers as $u): ?>
                                        <tr>
                                            <td class="primary-field" data-label="Usuario">
                                                <a href="users.php?id=<?php echo (int)$u['id']; ?>" style="display:block; font-weight:700; color:#0f172a; text-decoration:none; margin-bottom:2px;">
                                                    <?php echo html(trim((string)($u['firstname'] ?? '') . ' ' . (string)($u['lastname'] ?? ''))); ?>
                                                </a>
                                                <span style="font-size: 0.85rem; color: #64748b;">
                                                    <i class="bi bi-envelope" style="margin-right:4px;"></i><?php echo html($u['email']); ?>
                                                    <?php if(!empty($u['phone'])): ?>
                                                        <span class="ms-2"><i class="bi bi-telephone ms-1" style="margin-right:2px;"></i><?php echo html($u['phone']); ?></span>
                                                    <?php endif; ?>
                                                </span>
                                            </td>
                                            <td data-label="Estado" style="vertical-align: middle;">
                                                <span class="user-view-status-badge <?php echo html($u['status']); ?>">
                                                    <?php echo $u['status'] === 'active' ? 'Activo' : ($u['status'] === 'inactive' ? 'Inactivo' : 'Bloqueado'); ?>
                                                </span>
                                            </td>
                                            <td class="org-hide-mobile" data-label="Fecha" style="vertical-align: middle; color: #475569;">
                                                <i class="bi bi-calendar3" style="margin-right: 5px; color:#94a3b8;"></i>
                                                <?php echo $u['created'] ? date('d/m/Y', strtotime($u['created'])) : '—'; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($uTotalPages > 1): ?>
                            <div class="table-footer-bar" style="border-top: none; padding: 15px;">
                                <div class="showing-text">
                                    Mostrando <?php echo $userTotal ? ($uOffset + 1) : 0; ?> – <?php echo min($uOffset + $perPageLimit, $userTotal); ?> de <?php echo $userTotal; ?>
                                </div>
                                <div class="pagination-wrap">
                                    <?php if ($up > 1): ?>
                                        <a href="<?php echo html($orgsBaseUrl); ?>?<?php echo http_build_query(['org' => $orgName, 't' => 'users', 'up' => $up - 1, 'tp' => $tp]); ?>"><i class="bi bi-chevron-left"></i></a>
                                    <?php endif; ?>
                                    <?php for ($i = max(1, $up - 2); $i <= min($uTotalPages, $up + 2); $i++): ?>
                                        <?php if ($i === $up): ?>
                                            <strong style="margin: 0 4px; color:var(--bs-primary);"><?php echo $i; ?></strong>
                                        <?php else: ?>
                                            <a href="<?php echo html($orgsBaseUrl); ?>?<?php echo http_build_query(['org' => $orgName, 't' => 'users', 'up' => $i, 'tp' => $tp]); ?>" style="margin: 0 4px;"><?php echo $i; ?></a>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                    <?php if ($up < $uTotalPages): ?>
                                        <a href="<?php echo html($orgsBaseUrl); ?>?<?php echo http_build_query(['org' => $orgName, 't' => 'users', 'up' => $up + 1, 'tp' => $tp]); ?>"><i class="bi bi-chevron-right"></i></a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="tab-pane fade <?php echo $activeTab === 'tickets' ? 'show active' : ''; ?> user-view-tab-content" id="org-tickets">
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
                                        <th>Detalle del Ticket</th>
                                        <th>Estado / Prioridad</th>
                                        <th class="org-hide-mobile">Departamento</th>
                                        <th class="org-hide-mobile">Apertura</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tickets as $tkt): ?>
                                        <tr>
                                            <td data-label="Detalle" class="primary-field">
                                                <a href="<?php echo html($ticketsBaseUrl); ?>?id=<?php echo (int)$tkt['id']; ?>&back=<?php echo urlencode($backToOrgTickets); ?>" style="display:block; font-weight:800; font-size: 1rem; color:#2563eb; text-decoration:none; margin-bottom:2px;">
                                                    <i class="bi bi-ticket-detailed" style="margin-right:4px; opacity:0.8;"></i>#<?php echo html($tkt['ticket_number']); ?>
                                                </a>
                                                <span style="color:#475569; font-size:0.95rem; font-weight:500;">
                                                    <?php echo html($tkt['subject']); ?>
                                                </span>
                                            </td>
                                            <td data-label="Estado/Prioridad" style="vertical-align: middle;">
                                                <span class="user-view-status-badge" style="background:#f1f5f9; color:#334155; font-weight:700; padding:6px 12px; border-radius:8px; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; border:1px solid #e2e8f0;">
                                                    <?php echo html($tkt['status_name'] ?? '—'); ?>
                                                </span>
                                                <div style="font-size:0.8rem; color:#64748b; margin-top:6px; font-weight:600;">
                                                    <i class="bi bi-flag-fill" style="margin-right:4px; color:#cbd5e1;"></i><?php echo html($tkt['priority_name'] ?? '—'); ?>
                                                </div>
                                            </td>
                                            <td class="org-hide-mobile" data-label="Departamento" style="vertical-align: middle; color:#334155; font-weight:500;">
                                                <i class="bi bi-diagram-3" style="color:#94a3b8; margin-right:5px;"></i>
                                                <?php echo html($tkt['dept_name'] ?? '—'); ?>
                                            </td>
                                            <td class="org-hide-mobile" data-label="Apertura" style="vertical-align: middle; color:#64748b; font-size:0.9rem;">
                                                <i class="bi bi-clock" style="color:#cbd5e1; margin-right:5px;"></i>
                                                <?php echo $tkt['created'] ? date('d/m/Y', strtotime($tkt['created'])) : '—'; ?> <br>
                                                <span style="font-size:0.75rem; margin-left:18px;"><?php echo $tkt['created'] ? date('H:i', strtotime($tkt['created'])) : ''; ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($tTotalPages > 1): ?>
                            <div class="table-footer-bar" style="border-top: none; padding: 15px;">
                                <div class="showing-text">
                                    Mostrando <?php echo $ticketTotal ? ($tOffset + 1) : 0; ?> – <?php echo min($tOffset + $perPageLimit, $ticketTotal); ?> de <?php echo $ticketTotal; ?>
                                </div>
                                <div class="pagination-wrap">
                                    <?php if ($tp > 1): ?>
                                        <a href="<?php echo html($orgsBaseUrl); ?>?<?php echo http_build_query(['org' => $orgName, 't' => 'tickets', 'up' => $up, 'tp' => $tp - 1]); ?>"><i class="bi bi-chevron-left"></i></a>
                                    <?php endif; ?>
                                    <?php for ($i = max(1, $tp - 2); $i <= min($tTotalPages, $tp + 2); $i++): ?>
                                        <?php if ($i === $tp): ?>
                                            <strong style="margin: 0 4px; color:var(--bs-primary);"><?php echo $i; ?></strong>
                                        <?php else: ?>
                                            <a href="<?php echo html($orgsBaseUrl); ?>?<?php echo http_build_query(['org' => $orgName, 't' => 'tickets', 'up' => $up, 'tp' => $i]); ?>" style="margin: 0 4px;"><?php echo $i; ?></a>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                    <?php if ($tp < $tTotalPages): ?>
                                        <a href="<?php echo html($orgsBaseUrl); ?>?<?php echo http_build_query(['org' => $orgName, 't' => 'tickets', 'up' => $up, 'tp' => $tp + 1]); ?>"><i class="bi bi-chevron-right"></i></a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="modal fade" id="editOrgModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content org-modal-content">
                    <div class="modal-header org-modal-header">
                        <h5 class="modal-title org-modal-title"><i class="bi bi-pencil-square"></i> Editar organización</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="post" action="<?php echo html($orgsBaseUrl); ?>?org=<?php echo urlencode($orgInfo['name']); ?>">
                        <?php csrfField(); ?>
                        <input type="hidden" name="do" value="update">
                        <input type="hidden" name="old_name" value="<?php echo html($orgInfo['name']); ?>">
                        <div class="modal-body org-modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Nombre <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="org_name" required value="<?php echo html($orgInfo['name']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Sitio Web</label>
                                    <input type="text" class="form-control" name="org_website" value="<?php echo html($orgInfo['website'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Teléfono</label>
                                    <input type="text" class="form-control" name="org_phone" value="<?php echo html($orgInfo['phone'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Ext</label>
                                    <input type="text" class="form-control" name="org_phone_ext" value="<?php echo html($orgInfo['phone_ext'] ?? ''); ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Dirección</label>
                                    <textarea class="form-control" name="org_address" rows="3"><?php echo html($orgInfo['address'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Notas internas</label>
                                    <textarea class="form-control" name="org_notes" rows="4"><?php echo html($orgInfo['notes'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer org-modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Guardar</button>
                        </div>
                    </form>
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
                <form method="post" action="<?php echo html($orgsBaseUrl); ?>" id="deleteOrgForm">
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
                        <button type="submit" class="btn btn-danger"><i class="bi bi-trash"></i> Eliminar</button>
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

$orgsBaseUrl = (string)toAppAbsoluteUrl('upload/scp/orgs.php');

$like = $search !== '' ? '%' . $search . '%' : '';

// Organizaciones de la tabla organizations
$sql1 = "
    SELECT o.*,
           COUNT(DISTINCT u.id) AS user_count,
           COUNT(DISTINCT t.id) AS ticket_count,
           SUM(CASE WHEN ts.name IN ('Abierto','En Progreso','Esperando Usuario') THEN 1 ELSE 0 END) AS open_tickets,
           MIN(u.created) AS since
    FROM organizations o
    LEFT JOIN users u ON u.company = o.name AND u.empresa_id = ?
    LEFT JOIN tickets t ON t.user_id = u.id AND t.empresa_id = ?
    LEFT JOIN ticket_status ts ON ts.id = t.status_id
    WHERE o.empresa_id = ?
";
$params1 = [];
$types1 = 'iii';
$params1[] = $eid;
$params1[] = $eid;
$params1[] = $eid;
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
        <?php if (isset($_GET['msg']) && $action_type === 'success'): ?>
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
    <?php endif; ?>

    <div class="org-search-card">
        <form method="get" action="<?php echo html($orgsBaseUrl); ?>" class="org-search-form">
            <div class="org-search-input-wrapper">
                <i class="bi bi-search org-search-icon"></i>
                <input type="text" name="q" class="org-search-input" placeholder="Buscar por nombre de organización..." value="<?php echo html($search); ?>">
                <?php if ($search): ?>
                    <a href="<?php echo html($orgsBaseUrl); ?>" class="org-search-clear"><i class="bi bi-x-circle"></i></a>
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
                                <a href="<?php echo html($orgsBaseUrl); ?>?org=<?php echo urlencode($o['name']); ?>"><?php echo html($o['name']); ?></a>
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
                                <a href="<?php echo html($orgsBaseUrl); ?>?org=<?php echo urlencode($o['name']); ?>" class="org-card-link">Ver detalles <i class="bi bi-arrow-right"></i></a>
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
                            <a href="<?php echo html($orgsBaseUrl); ?>?<?php echo http_build_query(['q' => $search, 'p' => $pageNum - 1]); ?>"><i class="bi bi-chevron-left"></i></a>
                        <?php endif; ?>
                        <?php for ($i = max(1, $pageNum - 2); $i <= min($totalPages, $pageNum + 2); $i++): ?>
                            <?php if ($i === $pageNum): ?>
                                <strong style="margin: 0 4px;"><?php echo $i; ?></strong>
                            <?php else: ?>
                                <a href="<?php echo html($orgsBaseUrl); ?>?<?php echo http_build_query(['q' => $search, 'p' => $i]); ?>" style="margin: 0 4px;"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <?php if ($pageNum < $totalPages): ?>
                            <a href="<?php echo html($orgsBaseUrl); ?>?<?php echo http_build_query(['q' => $search, 'p' => $pageNum + 1]); ?>"><i class="bi bi-chevron-right"></i></a>
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
            <form method="post" action="<?php echo html($orgsBaseUrl); ?>" id="addOrgForm">
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
