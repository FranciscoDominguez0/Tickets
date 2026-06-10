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

if (!roleHasPermission('org.reports')) {
    $_SESSION['flash_error'] = 'No tienes permiso para enviar informes a jefes de organización.';
    header('Location: index.php');
    exit;
}

$currentRoute = 'informes_jefes';
$eid = empresaId();
$sid = (int) ($_SESSION['staff_id'] ?? 0);

ensureOrgBossReportAttachmentsTable();

// AJAX: usuarios de una organización
if (isset($_GET['action']) && $_GET['action'] === 'org_users') {
    header('Content-Type: application/json; charset=utf-8');
    $orgId = (int) ($_GET['org_id'] ?? 0);
    $users = [];
    if ($orgId > 0) {
        $stmtOrg = $mysqli->prepare('SELECT id, name FROM organizations WHERE id = ? AND empresa_id = ? LIMIT 1');
        if ($stmtOrg) {
            $stmtOrg->bind_param('ii', $orgId, $eid);
            $stmtOrg->execute();
            $orgRow = $stmtOrg->get_result()->fetch_assoc();
            if ($orgRow) {
                $orgName = (string) ($orgRow['name'] ?? '');
                $raw = fetchOrganizationUsers($mysqli, $eid, $orgId, $orgName, 500, 0);
                foreach ($raw as $u) {
                    $users[] = [
                        'id' => (int) ($u['id'] ?? 0),
                        'name' => trim((string) ($u['firstname'] ?? '') . ' ' . (string) ($u['lastname'] ?? '')),
                        'email' => (string) ($u['email'] ?? ''),
                    ];
                }
            }
        }
    }
    echo json_encode(['ok' => true, 'users' => $users]);
    exit;
}

$flashOk = '';
$flashErr = '';
if (!empty($_SESSION['flash_ok'])) {
    $flashOk = (string) $_SESSION['flash_ok'];
    unset($_SESSION['flash_ok']);
}
if (!empty($_SESSION['flash_error'])) {
    $flashErr = (string) $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

$view = ($_GET['a'] ?? '') === 'new' ? 'new' : 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_report') {
    if (!Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Token de seguridad inválido.';
        header('Location: informes_jefes.php?a=new');
        exit;
    }
    $orgId = (int) ($_POST['organization_id'] ?? 0);
    $targetUserRaw = trim((string) ($_POST['target_user_id'] ?? ''));
    $targetUserId = ($targetUserRaw !== '' && is_numeric($targetUserRaw)) ? (int) $targetUserRaw : null;
    if ($targetUserId !== null && $targetUserId <= 0) {
        $targetUserId = null;
    }
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $bodyHtml = trim((string) ($_POST['body_html'] ?? ''));

    $result = saveOrgBossReportWithAttachments(
        $mysqli,
        $eid,
        $sid,
        $orgId,
        $targetUserId,
        $subject,
        $bodyHtml,
        $_FILES['attachments'] ?? []
    );

    if (!empty($result['ok'])) {
        $_SESSION['flash_ok'] = 'Informe enviado correctamente a los jefes de la organización.';
        header('Location: informes_jefes.php');
        exit;
    }
    $_SESSION['flash_error'] = (string) ($result['error'] ?? 'No se pudo enviar el informe.');
    header('Location: informes_jefes.php?a=new');
    exit;
}

$organizations = [];
if (organizationMembershipEnabled($mysqli)) {
    $stmtOrgs = $mysqli->prepare('SELECT id, name FROM organizations WHERE empresa_id = ? ORDER BY name ASC');
    if ($stmtOrgs) {
        $stmtOrgs->bind_param('i', $eid);
        $stmtOrgs->execute();
        $resOrgs = $stmtOrgs->get_result();
        while ($resOrgs && ($row = $resOrgs->fetch_assoc())) {
            $organizations[] = $row;
        }
    }
}

$perPage = 15;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;
$search = trim((string) ($_GET['q'] ?? ''));
$searchLike = '%' . $search . '%';

$totalReports = 0;
$reports = [];
$searchWhere = $search !== '' ? ' AND (r.subject LIKE ? OR o.name LIKE ? OR CONCAT(s.firstname," ",s.lastname) LIKE ?)' : '';

$countSql = 'SELECT COUNT(*) c FROM org_boss_reports r'
    . ' JOIN organizations o ON o.id = r.organization_id AND o.empresa_id = r.empresa_id'
    . ' LEFT JOIN staff s ON s.id = r.staff_id'
    . ' WHERE r.empresa_id = ?' . $searchWhere;
$cStmt = $mysqli->prepare($countSql);
if ($cStmt) {
    if ($search !== '') {
        $cStmt->bind_param('isss', $eid, $searchLike, $searchLike, $searchLike);
    } else {
        $cStmt->bind_param('i', $eid);
    }
    $cStmt->execute();
    $totalReports = (int) ($cStmt->get_result()->fetch_assoc()['c'] ?? 0);
}

$totalPages = max(1, (int) ceil($totalReports / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$listSql = 'SELECT r.id, r.subject, r.created_at, r.organization_id, r.target_user_id,'
    . ' o.name AS org_name, s.firstname AS staff_first, s.lastname AS staff_last,'
    . ' tu.firstname AS target_first, tu.lastname AS target_last,'
    . ' (SELECT COUNT(*) FROM org_boss_report_attachments a WHERE a.report_id = r.id) AS att_count'
    . ' FROM org_boss_reports r'
    . ' JOIN organizations o ON o.id = r.organization_id AND o.empresa_id = r.empresa_id'
    . ' LEFT JOIN staff s ON s.id = r.staff_id'
    . ' LEFT JOIN users tu ON tu.id = r.target_user_id'
    . ' WHERE r.empresa_id = ?' . $searchWhere
    . ' ORDER BY r.created_at DESC LIMIT ? OFFSET ?';
$lStmt = $mysqli->prepare($listSql);
if ($lStmt) {
    if ($search !== '') {
        $lStmt->bind_param('isssii', $eid, $searchLike, $searchLike, $searchLike, $perPage, $offset);
    } else {
        $lStmt->bind_param('iii', $eid, $perPage, $offset);
    }
    $lStmt->execute();
    $resList = $lStmt->get_result();
    while ($resList && ($row = $resList->fetch_assoc())) {
        $reports[] = $row;
    }
}

$csrf = Auth::csrfToken();

ob_start();
?>

<style>
.obr-page { max-width: 1100px; }
.obr-header { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:20px; }
.obr-header h1 { font-size:1.35rem; font-weight:600; margin:0; color:#0f172a; }
.obr-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:20px; }
.obr-table th { font-size:.78rem; text-transform:uppercase; letter-spacing:.04em; color:#64748b; font-weight:600; }
.obr-badge-new { background:#dbeafe; color:#1d4ed8; font-size:.72rem; padding:2px 8px; border-radius:999px; }
.obr-form-label { font-weight:600; font-size:.88rem; color:#334155; }
.obr-hint { font-size:.8rem; color:#64748b; }
.obr-att-preview { display:flex; flex-wrap:wrap; gap:8px; margin-top:8px; }
.obr-att-chip { background:#f1f5f9; border-radius:8px; padding:6px 10px; font-size:.8rem; color:#475569; }
</style>

<div class="obr-page">
    <div class="obr-header">
        <div>
            <h1>Informes a jefes</h1>
            <div class="obr-hint">Envía informes detallados a los jefes de organización en su panel.</div>
        </div>
        <?php if ($view === 'list'): ?>
        <a href="informes_jefes.php?a=new" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nuevo informe</a>
        <?php else: ?>
        <a href="informes_jefes.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver al listado</a>
        <?php endif; ?>
    </div>

    <?php if ($flashOk !== ''): ?>
    <div class="alert alert-success"><?php echo html($flashOk); ?></div>
    <?php endif; ?>
    <?php if ($flashErr !== ''): ?>
    <div class="alert alert-danger"><?php echo html($flashErr); ?></div>
    <?php endif; ?>

    <?php if ($view === 'new'): ?>
    <div class="obr-card">
        <?php if (empty($organizations)): ?>
        <p class="text-muted mb-0">No hay organizaciones registradas. Crea organizaciones en <a href="index.php?page=orgs">Usuarios → Organizaciones</a> antes de enviar informes.</p>
        <?php else: ?>
        <form method="post" enctype="multipart/form-data" id="obrCreateForm">
            <input type="hidden" name="csrf_token" value="<?php echo html($csrf); ?>">
            <input type="hidden" name="action" value="create_report">

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="obr-form-label" for="organization_id">Organización / sucursal <span class="text-danger">*</span></label>
                    <select class="form-select" name="organization_id" id="organization_id" required>
                        <option value="">— Seleccionar —</option>
                        <?php foreach ($organizations as $org): ?>
                        <option value="<?php echo (int) $org['id']; ?>"><?php echo html((string) $org['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="obr-form-label" for="target_user_id">Usuario (opcional)</label>
                    <select class="form-select" name="target_user_id" id="target_user_id" disabled>
                        <option value="">— Todos / ninguno en particular —</option>
                    </select>
                    <div class="obr-hint mt-1">Si aplica, indica el usuario relacionado con el informe.</div>
                </div>
                <div class="col-12">
                    <label class="obr-form-label" for="subject">Asunto <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="subject" id="subject" maxlength="255" required placeholder="Ej. Resumen de visita técnica — marzo 2026">
                </div>
                <div class="col-12">
                    <label class="obr-form-label" for="body_html">Detalle / mensaje <span class="text-danger">*</span></label>
                    <textarea name="body_html" id="body_html" class="form-control" rows="8"></textarea>
                </div>
                <div class="col-12">
                    <label class="obr-form-label" for="attachments">Archivos y fotos</label>
                    <input type="file" class="form-control" name="attachments[]" id="attachments" multiple accept="image/*,.pdf,.doc,.docx,.txt,video/*">
                    <div class="obr-hint mt-1">Hasta 10 archivos, máx. 25 MB c/u (imágenes, PDF, documentos, video).</div>
                    <div class="obr-att-preview" id="attPreview"></div>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> Enviar informe</button>
                <a href="informes_jefes.php" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <script>
    (function() {
        var orgSel = document.getElementById('organization_id');
        var userSel = document.getElementById('target_user_id');
        if (!orgSel || !userSel) return;

        function loadUsers(orgId) {
            userSel.innerHTML = '<option value="">— Todos / ninguno en particular —</option>';
            userSel.disabled = true;
            if (!orgId) return;
            fetch('informes_jefes.php?action=org_users&org_id=' + encodeURIComponent(orgId))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data || !data.ok) return;
                    (data.users || []).forEach(function(u) {
                        var opt = document.createElement('option');
                        opt.value = u.id;
                        var label = u.name || ('Usuario #' + u.id);
                        if (u.email) label += ' (' + u.email + ')';
                        opt.textContent = label;
                        userSel.appendChild(opt);
                    });
                    userSel.disabled = false;
                })
                .catch(function() {});
        }

        orgSel.addEventListener('change', function() {
            loadUsers(orgSel.value);
        });

        var att = document.getElementById('attachments');
        var prev = document.getElementById('attPreview');
        if (att && prev) {
            att.addEventListener('change', function() {
                prev.innerHTML = '';
                Array.from(att.files || []).forEach(function(f) {
                    var chip = document.createElement('span');
                    chip.className = 'obr-att-chip';
                    chip.textContent = f.name;
                    prev.appendChild(chip);
                });
            });
        }

        if (typeof jQuery !== 'undefined' && jQuery().summernote && document.getElementById('body_html')) {
            jQuery('#body_html').summernote({
                height: 220,
                lang: 'es-ES',
                placeholder: 'Escribe el detalle del informe…',
                toolbar: [
                    ['style', ['bold', 'italic', 'underline', 'clear']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['insert', ['link']],
                    ['view', ['codeview']]
                ]
            });
            document.getElementById('obrCreateForm').addEventListener('submit', function() {
                var code = jQuery('#body_html').summernote('code');
                jQuery('#body_html').val(code);
            });
        }
    })();
    </script>

    <?php else: ?>

    <div class="obr-card mb-3">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-8">
                <label class="obr-form-label" for="q">Buscar</label>
                <input type="text" class="form-control" name="q" id="q" value="<?php echo html($search); ?>" placeholder="Asunto, organización o agente">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-outline-primary w-100">Buscar</button>
            </div>
        </form>
    </div>

    <div class="obr-card p-0 overflow-hidden">
        <?php if (empty($reports)): ?>
        <div class="p-4 text-muted">No hay informes enviados<?php echo $search !== '' ? ' con ese criterio' : ''; ?>.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0 obr-table">
                <thead class="table-light">
                    <tr>
                        <th>Fecha</th>
                        <th>Asunto</th>
                        <th>Organización</th>
                        <th>Usuario</th>
                        <th>Enviado por</th>
                        <th>Adj.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $r): ?>
                    <?php
                    $staffName = trim((string) ($r['staff_first'] ?? '') . ' ' . (string) ($r['staff_last'] ?? ''));
                    $targetName = trim((string) ($r['target_first'] ?? '') . ' ' . (string) ($r['target_last'] ?? ''));
                    $created = (string) ($r['created_at'] ?? '');
                    ?>
                    <tr>
                        <td class="text-nowrap"><?php echo html($created !== '' ? date('d/m/Y H:i', strtotime($created)) : '—'); ?></td>
                        <td><?php echo html((string) ($r['subject'] ?? '')); ?></td>
                        <td><?php echo html((string) ($r['org_name'] ?? '')); ?></td>
                        <td><?php echo $targetName !== '' ? html($targetName) : '<span class="text-muted">—</span>'; ?></td>
                        <td><?php echo $staffName !== '' ? html($staffName) : '—'; ?></td>
                        <td><?php echo (int) ($r['att_count'] ?? 0); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="p-3 border-top d-flex justify-content-between align-items-center">
            <span class="obr-hint">Página <?php echo $page; ?> de <?php echo $totalPages; ?> (<?php echo $totalReports; ?> informes)</span>
            <div class="btn-group btn-group-sm">
                <?php if ($page > 1): ?>
                <a class="btn btn-outline-secondary" href="informes_jefes.php?page=<?php echo $page - 1; ?><?php echo $search !== '' ? '&q=' . urlencode($search) : ''; ?>">Anterior</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                <a class="btn btn-outline-secondary" href="informes_jefes.php?page=<?php echo $page + 1; ?><?php echo $search !== '' ? '&q=' . urlencode($search) : ''; ?>">Siguiente</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout/layout.php';
