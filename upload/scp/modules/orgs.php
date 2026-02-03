<?php
// Módulo: Organizaciones (panel estilo osTicket, usando company de users)

// Vista detalle de una organización concreta (por nombre)
if (!empty($_GET['org'])) {
    $orgName = trim($_GET['org']);

    // Datos básicos de la organización
    $stmt = $mysqli->prepare("
        SELECT 
            u.company,
            COUNT(DISTINCT u.id)         AS user_count,
            COUNT(DISTINCT t.id)         AS ticket_count,
            SUM(CASE WHEN ts.name IN ('Abierto','En Progreso','Esperando Usuario') THEN 1 ELSE 0 END) AS open_tickets,
            MIN(u.created)               AS since
        FROM users u
        LEFT JOIN tickets t      ON t.user_id = u.id
        LEFT JOIN ticket_status ts ON ts.id = t.status_id
        WHERE u.company = ?
        GROUP BY u.company
    ");
    $stmt->bind_param('s', $orgName);
    $stmt->execute();
    $orgInfo = $stmt->get_result()->fetch_assoc();

    if (!$orgInfo) {
        echo '<div class="alert alert-warning m-4">Organización no encontrada.</div>';
        return;
    }

    // Usuarios de la organización
    $stmt = $mysqli->prepare("
        SELECT id, firstname, lastname, email, phone, status, created
        FROM users
        WHERE company = ?
        ORDER BY firstname, lastname
    ");
    $stmt->bind_param('s', $orgName);
    $stmt->execute();
    $orgUsers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Tickets de la organización (vía vista v_tickets_full si existe, si no, join directo)
    $tickets = [];
    $hasView = $mysqli->query("SHOW FULL TABLES LIKE 'v_tickets_full'")->num_rows > 0;
    if ($hasView) {
        $stmt = $mysqli->prepare("
            SELECT id, ticket_number, subject, status_name, priority_name, dept_name, created
            FROM v_tickets_full
            WHERE user_email IN (
                SELECT email FROM users WHERE company = ?
            )
            ORDER BY created DESC
            LIMIT 100
        ");
        $stmt->bind_param('s', $orgName);
        $stmt->execute();
        $tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        $stmt = $mysqli->prepare("
            SELECT 
              t.id,
              t.ticket_number,
              t.subject,
              ts.name AS status_name,
              p.name  AS priority_name,
              d.name  AS dept_name,
              t.created
            FROM tickets t
            JOIN users u        ON t.user_id = u.id
            JOIN departments d  ON t.dept_id = d.id
            JOIN ticket_status ts ON t.status_id = ts.id
            JOIN priorities p   ON t.priority_id = p.id
            WHERE u.company = ?
            ORDER BY t.created DESC
            LIMIT 100
        ");
        $stmt->bind_param('s', $orgName);
        $stmt->execute();
        $tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    ?>

    <div class="container-main" style="max-width: 1100px; margin: 0 auto;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <a href="orgs.php" class="text-decoration-none" style="color:#2563eb;"><i class="bi bi-arrow-left"></i> Volver al listado</a>
                <h1 class="h3 mb-0 mt-2">
                    <i class="bi bi-building"></i>
                    <?php echo html($orgInfo['company']); ?>
                </h1>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card h-100 shadow-sm border-0" style="border-radius: 14px;">
                    <div class="card-body">
                        <h6 class="text-uppercase text-muted mb-2" style="letter-spacing: .08em;">Resumen</h6>
                        <p class="mb-1"><strong><?php echo (int)$orgInfo['user_count']; ?></strong> usuarios</p>
                        <p class="mb-1"><strong><?php echo (int)$orgInfo['ticket_count']; ?></strong> tickets totales</p>
                        <p class="mb-1"><strong><?php echo (int)$orgInfo['open_tickets']; ?></strong> abiertos</p>
                        <p class="mb-0 text-muted small">
                            Desde: <?php echo $orgInfo['since'] ? date('d/m/Y', strtotime($orgInfo['since'])) : '—'; ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card h-100 shadow-sm border-0" style="border-radius: 14px;">
                    <div class="card-body">
                        <h6 class="text-uppercase text-muted mb-2" style="letter-spacing: .08em;">Notas</h6>
                        <p class="mb-0 text-muted small">Aquí podrías guardar notas internas sobre la organización, acuerdos de nivel de servicio, contactos clave, etc.</p>
                    </div>
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#org-users">Usuarios</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#org-tickets">Tickets</button>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="org-users">
                <?php if (empty($orgUsers)): ?>
                    <p class="text-muted">No hay usuarios asociados a esta organización.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
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
                                        <td>
                                            <a href="users.php?id=<?php echo (int)$u['id']; ?>">
                                                <?php echo html(trim($u['firstname'].' '.$u['lastname'])); ?>
                                            </a>
                                        </td>
                                        <td><?php echo html($u['email']); ?></td>
                                        <td><?php echo html($u['phone'] ?? ''); ?></td>
                                        <td><?php echo html($u['status']); ?></td>
                                        <td><?php echo $u['created'] ? date('d/m/y', strtotime($u['created'])) : '—'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="tab-pane fade" id="org-tickets">
                <?php if (empty($tickets)): ?>
                    <p class="text-muted">No hay tickets para esta organización.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
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
                                        <td><?php echo html($tkt['dept_name'] ?? ''); ?></td>
                                        <td><?php echo html($tkt['status_name'] ?? ''); ?></td>
                                        <td><?php echo html($tkt['priority_name'] ?? ''); ?></td>
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
    <?php
    return;
}

// Listado de organizaciones (agrupadas por company de users)
$search = trim($_GET['q'] ?? '');
$pageNum = max(1, (int)($_GET['p'] ?? 1));
$perPage = 20;

// Total de organizaciones (distinct company)
$where = "WHERE company IS NOT NULL AND company <> ''";
$countParams = [];
$countTypes  = '';
if ($search !== '') {
    $where .= " AND company LIKE ?";
    $like = '%' . $search . '%';
    $countParams[] = $like;
    $countTypes .= 's';
}

$countSql = "SELECT COUNT(DISTINCT company) AS total FROM users $where";
$stmt = $mysqli->prepare($countSql);
if (!empty($countParams)) {
    $stmt->bind_param($countTypes, ...$countParams);
}
$stmt->execute();
$totalRows = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$totalPages = $totalRows ? (int) ceil($totalRows / $perPage) : 1;
$pageNum = min($pageNum, max(1, $totalPages));
$offset = ($pageNum - 1) * $perPage;

// Listado paginado
$sql = "
    SELECT 
      u.company,
      COUNT(DISTINCT u.id) AS user_count,
      COUNT(DISTINCT t.id) AS ticket_count,
      SUM(CASE WHEN ts.name IN ('Abierto','En Progreso','Esperando Usuario') THEN 1 ELSE 0 END) AS open_tickets,
      MIN(u.created) AS since
    FROM users u
    LEFT JOIN tickets t      ON t.user_id = u.id
    LEFT JOIN ticket_status ts ON ts.id = t.status_id
    WHERE u.company IS NOT NULL AND u.company <> ''
";
$params = [];
$types  = '';
if ($search !== '') {
    $sql   .= " AND u.company LIKE ?";
    $params[] = $like;
    $types  .= 's';
}
$sql .= "
    GROUP BY u.company
    ORDER BY u.company ASC
    LIMIT ? OFFSET ?
";
$params[] = $perPage;
$params[] = $offset;
$types   .= 'ii';

$stmt = $mysqli->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$orgs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

?>

<div class="container-main" style="max-width: 1100px; margin: 0 auto;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">
            <i class="bi bi-building"></i>
            Organizaciones
        </h1>
    </div>

    <div class="card mb-3 shadow-sm border-0" style="border-radius: 14px;">
        <div class="card-body">
            <form method="get" action="orgs.php" class="row g-2 align-items-center">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" class="form-control" placeholder="Buscar por nombre de organización" value="<?php echo html($search); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">Buscar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0" style="border-radius: 14px;">
        <?php if (empty($orgs)): ?>
            <div class="card-body text-center text-muted py-5">
                <i class="bi bi-building" style="font-size: 2.5rem; opacity: .4;"></i>
                <p class="mt-2 mb-0">
                    No se encontraron organizaciones<?php echo $search ? ' con ese criterio.' : '.'; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Organización</th>
                            <th class="text-center">Usuarios</th>
                            <th class="text-center">Tickets abiertos</th>
                            <th class="text-center">Tickets totales</th>
                            <th>Desde</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orgs as $o): ?>
                            <tr>
                                <td>
                                    <a href="orgs.php?org=<?php echo urlencode($o['company']); ?>">
                                        <?php echo html($o['company']); ?>
                                    </a>
                                </td>
                                <td class="text-center"><?php echo (int)$o['user_count']; ?></td>
                                <td class="text-center"><?php echo (int)$o['open_tickets']; ?></td>
                                <td class="text-center"><?php echo (int)$o['ticket_count']; ?></td>
                                <td><?php echo $o['since'] ? date('d/m/Y', strtotime($o['since'])) : '—'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top small text-muted">
                <div>
                    Mostrando
                    <?php echo $totalRows ? ($offset + 1) : 0; ?>
                    –
                    <?php echo min($offset + $perPage, $totalRows); ?>
                    de <?php echo $totalRows; ?>
                </div>
                <div>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php if ($i === $pageNum): ?>
                            <strong>[<?php echo $i; ?>]</strong>
                        <?php else: ?>
                            <a href="orgs.php?<?php echo http_build_query(['q' => $search, 'p' => $i]); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

