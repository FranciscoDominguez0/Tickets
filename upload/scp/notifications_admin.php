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
$currentRoute = 'notifications_admin';

$eid = empresaId();

// Asegurar tabla de destinatarios
if (isset($mysqli) && $mysqli) {
    ensureNotificationRecipientsTable();
}

// Candidatos de destinatarios (agentes/admins activos con email válido)
$notificationCandidates = [];
if (isset($mysqli) && $mysqli) {
    $sqlCandidates = 'SELECT id, firstname, lastname, email FROM staff WHERE is_active = 1';
    if (true) /* empresa_id se filtra más abajo */ $sqlCandidates .= ' AND role IN (\'agent\',\'admin\')';
    $sqlCandidates .= ' ORDER BY firstname ASC, lastname ASC, email ASC';

    $stmtC = $mysqli->prepare($sqlCandidates);
    if ($stmtC) {
        if ($stmtC->execute()) {
            $rsC = $stmtC->get_result();
            while ($rsC && ($r = $rsC->fetch_assoc())) {
                $email = trim((string)($r['email'] ?? ''));
                if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
                $notificationCandidates[] = $r;
            }
        }
    }
}

// IDs de destinatarios actualmente seleccionados
$selectedRecipientIds = [];
if (isset($mysqli) && $mysqli && ensureNotificationRecipientsTable()) {
    $stmtSel = $mysqli->prepare('SELECT staff_id FROM notification_recipients WHERE empresa_id = ?');
    if ($stmtSel) {
        $stmtSel->bind_param('i', $eid);
        if ($stmtSel->execute()) {
            $rsSel = $stmtSel->get_result();
            while ($rsSel && ($r = $rsSel->fetch_assoc())) {
                $sid = (int)($r['staff_id'] ?? 0);
                if ($sid > 0) $selectedRecipientIds[$sid] = true;
            }
        }
    }
}
$staffHasEmpresaId = false;
if (isset($mysqli) && $mysqli) {
    try {
        $res = $mysqli->query("SHOW COLUMNS FROM staff LIKE 'empresa_id'");
        $staffHasEmpresaId = ($res && $res->num_rows > 0);
    } catch (Throwable $e) {
        $staffHasEmpresaId = false;
    }
}

$collapseSettingsMenu = false;
$menuKey = 'admin_sidebar_menu_seen_' . (int)($_SESSION['staff_id'] ?? 0);
if ((string)($_SESSION['sidebar_panel_mode'] ?? '') !== 'admin') {
    unset($_SESSION[$menuKey]);
    $_SESSION['sidebar_panel_mode'] = 'admin';
}
if (!isset($_SESSION[$menuKey])) {
    $_SESSION[$menuKey] = 1;
    $collapseSettingsMenu = true;
}

// Solo admins
$meRole = '';
$meId = (int)($_SESSION['staff_id'] ?? 0);
if ($meId > 0) {
    $sqlMe = 'SELECT role FROM staff WHERE id = ?';
    if ($staffHasEmpresaId) {
        $sqlMe .= ' AND empresa_id = ?';
    }
    $sqlMe .= ' LIMIT 1';
    $stmtMe = $mysqli->prepare($sqlMe);
    if ($stmtMe) {
        if ($staffHasEmpresaId) {
            $stmtMe->bind_param('ii', $meId, $eid);
        } else {
            $stmtMe->bind_param('i', $meId);
        }
        if ($stmtMe->execute()) {
            $meRow = $stmtMe->get_result()->fetch_assoc();
            $meRole = (string)($meRow['role'] ?? '');
        }
    }
}
if ($meRole !== 'admin') {
    $_SESSION['flash_error'] = 'No tienes permisos para acceder a Notificaciones.';
    header('Location: index.php');
    exit;
}

$errors = [];
$msg = '';



// Asegurar CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Guardar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        $errors[] = 'Token de seguridad inválido.';
    } else {
        $ticketArr = isset($_POST['email_ticket_assigned']) && is_array($_POST['email_ticket_assigned']) ? $_POST['email_ticket_assigned'] : [];
        $taskArr   = isset($_POST['email_task_assigned'])  && is_array($_POST['email_task_assigned'])  ? $_POST['email_task_assigned']  : [];

        $staffIds = [];
        foreach ($ticketArr as $sid => $val) { if (is_numeric($sid)) $staffIds[(int)$sid] = true; }
        foreach ($taskArr  as $sid => $val) { if (is_numeric($sid)) $staffIds[(int)$sid] = true; }

        $resAll = $mysqli->query("SELECT id FROM staff WHERE is_active = 1 AND role IN ('agent','admin') ORDER BY firstname, lastname");
        if ($resAll) {
            while ($row = $resAll->fetch_assoc()) {
                $sid = (int)($row['id'] ?? 0);
                if ($sid > 0) $staffIds[$sid] = true;
            }
        }

        foreach (array_keys($staffIds) as $sid) {
            $tEnabled   = isset($ticketArr[$sid]) && (string)$ticketArr[$sid] === '1';
            $taskEnabled = isset($taskArr[$sid])  && (string)$taskArr[$sid]  === '1';
            setAppSetting('staff.' . (int)$sid . '.email_ticket_assigned', $tEnabled   ? '1' : '0');
            setAppSetting('staff.' . (int)$sid . '.email_task_assigned',   $taskEnabled ? '1' : '0');
        }

        // Guardar destinatarios de notificación (notification_recipients)
        $recipientIds = $_POST['notification_recipients'] ?? [];
        if (!is_array($recipientIds)) $recipientIds = [];
        $recipientIds = array_values(array_unique(array_filter(array_map('intval', $recipientIds), function ($v) {
            return $v > 0;
        })));

        $allowedMap = [];
        foreach ($notificationCandidates as $cand) {
            $allowedMap[(int)($cand['id'] ?? 0)] = true;
        }
        $validRecipientIds = [];
        foreach ($recipientIds as $sid) {
            if (isset($allowedMap[$sid])) $validRecipientIds[] = $sid;
        }

        if (ensureNotificationRecipientsTable()) {
            $stmtDel = $mysqli->prepare('DELETE FROM notification_recipients WHERE empresa_id = ?');
            if ($stmtDel) { $stmtDel->bind_param('i', $eid); $stmtDel->execute(); }
            if (!empty($validRecipientIds)) {
                $stmtIns = $mysqli->prepare('INSERT INTO notification_recipients (empresa_id, staff_id, created_at) VALUES (?, ?, NOW())');
                if ($stmtIns) {
                    foreach ($validRecipientIds as $sid) {
                        $stmtIns->bind_param('ii', $eid, $sid);
                        $stmtIns->execute();
                    }
                }
            }
            $selectedRecipientIds = [];
            foreach ($validRecipientIds as $sid) { $selectedRecipientIds[$sid] = true; }
        }

        $_SESSION['flash_msg'] = 'Preferencias actualizadas. Destinatarios: ' . (string)count($validRecipientIds);
        header('Location: notifications_admin.php');
        exit;
    }
}

// Listar agentes + administradores
$agents = [];
$sqlStaff = "SELECT id, firstname, lastname, email, role FROM staff WHERE is_active = 1 AND role IN ('agent','admin')";
if ($staffHasEmpresaId) {
    $sqlStaff .= ' AND empresa_id = ' . (int)$eid;
}
$sqlStaff .= ' ORDER BY role DESC, firstname, lastname';
$res = $mysqli->query($sqlStaff);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $sid = (int)($row['id'] ?? 0);
        $agents[] = [
            'id' => $sid,
            'name' => trim((string)($row['firstname'] ?? '') . ' ' . (string)($row['lastname'] ?? '')),
            'email' => (string)($row['email'] ?? ''),
            'role' => (string)($row['role'] ?? ''),
            'ticket' => ((string)getAppSetting('staff.' . $sid . '.email_ticket_assigned', '1') === '1'),
            'task' => ((string)getAppSetting('staff.' . $sid . '.email_task_assigned', '1') === '1'),
        ];
    }
}

ob_start();
?>

<div class="settings-hero">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-3">
            <span class="settings-hero-icon"><i class="bi bi-bell"></i></span>
            <div>
                <h1>Notificaciones</h1>
                <p>Preferencias de correos para asignaciones y notificaciones admin</p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge bg-info"><?php echo (int)count($agents); ?> Staff</span>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $e): ?>
            <div><?php echo html((string)$e); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card settings-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong><i class="bi bi-sliders"></i> Preferencias</strong>
    </div>
    <div class="card-body">
        <form method="post" action="notifications_admin.php">
            <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">

            <div class="row g-3">
                <!-- Destinatarios de notificaciones -->
                <div class="col-12">
                    <!-- Tarjeta resumen (siempre visible) -->
                    <div class="card" style="border-radius:14px;">
                        <div class="card-body" style="padding:18px 20px;">
                            <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                                <div style="flex:1;min-width:0;">
                                    <div class="fw-semibold mb-1"><i class="bi bi-people me-1"></i> Seleccionar destinatarios</div>
                                    <div class="text-muted mb-2" style="font-size:0.78rem;">Agentes y admins que recibirán notificaciones globales del sistema.</div>
                                    <!-- Chips resumen (solo lectura) -->
                                    <div id="recipientsChipsSummary" class="d-none d-md-flex" style="flex-wrap:wrap;gap:6px;min-height:24px;">
                                        <?php if (empty($selectedRecipientIds)): ?>
                                            <span style="font-size:0.78rem;color:#94a3b8;">Sin destinatarios seleccionados</span>
                                        <?php else: ?>
                                            <?php foreach ($notificationCandidates as $cand):
                                                $sid = (int)($cand['id'] ?? 0);
                                                if (!isset($selectedRecipientIds[$sid])) continue;
                                                $fn = trim(trim((string)($cand['firstname'] ?? '')) . ' ' . trim((string)($cand['lastname'] ?? ''))) ?: 'Sin nombre';
                                            ?>
                                            <span style="display:inline-flex;align-items:center;gap:4px;background:linear-gradient(135deg,#3b82f6,#6366f1);color:#fff;padding:3px 10px;border-radius:20px;font-size:0.73rem;font-weight:600;">
                                                <span style="width:16px;height:16px;border-radius:50%;background:rgba(255,255,255,0.25);display:inline-flex;align-items:center;justify-content:center;font-size:0.6rem;font-weight:800;"><?php echo strtoupper(mb_substr($fn,0,1)); ?></span>
                                                <?php echo html($fn); ?>
                                            </span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm" style="border-radius:10px;white-space:nowrap;" data-bs-toggle="modal" data-bs-target="#recipientsModal">
                                    <i class="bi bi-person-check me-1"></i>Gestionar
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Hidden inputs para POST (gestionados por el modal JS) -->
                    <div id="recipHiddenInputs" style="display:none;">
                        <?php foreach ($notificationCandidates as $cand):
                            $sid = (int)($cand['id'] ?? 0);
                            $isSelected = isset($selectedRecipientIds[$sid]);
                        ?>
                        <input type="hidden" class="recip-hidden-input" name="notification_recipients[]"
                               value="<?php echo $sid; ?>" <?php echo $isSelected ? '' : 'disabled'; ?>>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- MODAL de gestión de destinatarios -->
                <div class="modal fade" id="recipientsModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog" style="max-width:480px;">
                        <div class="modal-content" style="border-radius:18px;border:none;box-shadow:0 20px 60px rgba(0,0,0,0.15);overflow:visible;">
                            <div class="modal-header" style="border-bottom:1px solid #e2e8f0;padding:18px 22px 14px;">
                                <div class="d-flex align-items-center gap-3">
                                    <div style="background:linear-gradient(135deg,#3b82f6,#6366f1);width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;">
                                        <i class="bi bi-people-fill" style="color:#fff;font-size:1rem;"></i>
                                    </div>
                                    <div>
                                        <h5 class="modal-title mb-0" style="font-weight:700;font-size:1rem;">Destinatarios de notificaciones</h5>
                                        <div class="text-muted" style="font-size:0.72rem;">Busca y agrega agentes o admins</div>
                                    </div>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                            <div class="modal-body" style="padding:18px 22px;overflow:visible;">
                                <div style="font-size:0.72rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">Seleccionados</div>
                                <div id="modalChipsBox" style="display:flex;flex-wrap:wrap;gap:6px;min-height:36px;padding:8px 10px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:16px;">
                                    <span id="modalEmptyMsg" style="font-size:0.78rem;color:#94a3b8;align-self:center;">Ninguno seleccionado</span>
                                    <?php foreach ($notificationCandidates as $cand):
                                        $sid = (int)($cand['id'] ?? 0);
                                        $fn  = trim(trim((string)($cand['firstname'] ?? '')) . ' ' . trim((string)($cand['lastname'] ?? ''))) ?: 'Sin nombre';
                                        $email = trim((string)($cand['email'] ?? ''));
                                        $isSel = isset($selectedRecipientIds[$sid]);
                                    ?>
                                    <span class="modal-chip" data-id="<?php echo $sid; ?>"
                                          style="display:<?php echo $isSel ? 'inline-flex' : 'none'; ?>;align-items:center;gap:5px;background:linear-gradient(135deg,#3b82f6,#6366f1);color:#fff;padding:4px 8px 4px 6px;border-radius:20px;font-size:0.75rem;font-weight:600;cursor:default;user-select:none;">
                                        <span style="width:18px;height:18px;border-radius:50%;background:rgba(255,255,255,0.25);display:inline-flex;align-items:center;justify-content:center;font-size:0.62rem;font-weight:800;"><?php echo strtoupper(mb_substr($fn,0,1)); ?></span>
                                        <?php echo html($fn); ?>
                                        <button type="button" class="modal-chip-remove" data-id="<?php echo $sid; ?>" aria-label="Quitar"
                                                style="background:rgba(255,255,255,0.25);border:none;color:#fff;border-radius:50%;width:14px;height:14px;padding:0;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;font-size:0.65rem;line-height:1;">
                                            <i class="bi bi-x"></i>
                                        </button>
                                    </span>
                                    <?php endforeach; ?>
                                </div>

                                <div style="font-size:0.72rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">Agregar</div>
                                <div class="position-relative" id="modalSearchWrapper">
                                    <i class="bi bi-search" style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:0.82rem;pointer-events:none;"></i>
                                    <input type="text" id="modalSearchInput" autocomplete="off"
                                           placeholder="Buscar por nombre o correo..."
                                           style="width:100%;padding:8px 12px 8px 32px;border:1px solid #e2e8f0;border-radius:10px;background:#fff;font-size:0.83rem;outline:none;transition:border-color .15s;"
                                           onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e2e8f0'">
                                    <div id="modalDropdown"
                                         style="display:none;position:absolute;left:0;right:0;top:calc(100% + 4px);background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,0.1);z-index:1060;max-height:240px;overflow-y:auto;">
                                        <?php foreach ($notificationCandidates as $cand):
                                            $sid   = (int)($cand['id'] ?? 0);
                                            $fn    = trim(trim((string)($cand['firstname'] ?? '')) . ' ' . trim((string)($cand['lastname'] ?? ''))) ?: 'Sin nombre';
                                            $email = trim((string)($cand['email'] ?? ''));
                                            $isSel = isset($selectedRecipientIds[$sid]);
                                        ?>
                                        <div class="modal-suggestion" data-id="<?php echo $sid; ?>"
                                             data-name="<?php echo strtolower(html($fn)); ?>"
                                             data-email="<?php echo strtolower(html($email)); ?>"
                                             style="display:<?php echo $isSel ? 'none' : 'flex'; ?>;align-items:center;gap:10px;padding:9px 14px;cursor:pointer;border-bottom:1px solid #f1f5f9;transition:background .1s;"
                                             onmouseenter="this.style.background='#f8fafc'" onmouseleave="this.style.background=''">
                                            <div style="flex-shrink:0;width:30px;height:30px;border-radius:8px;background:linear-gradient(135deg,#3b82f6,#6366f1);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:0.78rem;">
                                                <?php echo strtoupper(mb_substr($fn,0,1)); ?>
                                            </div>
                                            <div style="min-width:0;flex:1;">
                                                <div style="font-weight:600;font-size:0.83rem;color:#1e293b;"><?php echo html($fn); ?></div>
                                                <div style="font-size:0.71rem;color:#64748b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo html($email); ?></div>
                                            </div>
                                            <i class="bi bi-plus-circle" style="color:#3b82f6;flex-shrink:0;font-size:1rem;"></i>
                                        </div>
                                        <?php endforeach; ?>
                                        <div id="modalNoResults" style="display:none;padding:14px;text-align:center;color:#94a3b8;font-size:0.8rem;">
                                            <i class="bi bi-search me-1"></i>Sin resultados
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer" style="border-top:1px solid #e2e8f0;padding:14px 22px;gap:8px;">
                                <button type="button" class="btn btn-light" id="modalCancelBtn" style="border-radius:10px;font-weight:600;">Cancelar</button>
                                <button type="button" class="btn btn-primary" id="modalSaveBtn" style="border-radius:10px;font-weight:600;padding:8px 22px;">
                                    <i class="bi bi-check-circle me-1"></i>Confirmar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

<script>
(function(){
    var modalEl    = document.getElementById('recipientsModal');
    if (!modalEl) return;
    var chipsBox   = document.getElementById('modalChipsBox');
    var emptyMsg   = document.getElementById('modalEmptyMsg');
    var searchInp  = document.getElementById('modalSearchInput');
    var dropdown   = document.getElementById('modalDropdown');
    var noResults  = document.getElementById('modalNoResults');
    var saveBtn    = document.getElementById('modalSaveBtn');
    var cancelBtn  = document.getElementById('modalCancelBtn');
    var hiddenWrap = document.getElementById('recipHiddenInputs');
    var summary    = document.getElementById('recipientsChipsSummary');
    var snapshot   = {};

    function getHidden(id) { return hiddenWrap.querySelector('.recip-hidden-input[value="'+id+'"]'); }
    function getChip(id)   { return chipsBox.querySelector('.modal-chip[data-id="'+id+'"]'); }
    function getSug(id)    { return dropdown.querySelector('.modal-suggestion[data-id="'+id+'"]'); }
    function isSelected(id){ var h=getHidden(id); return h?!h.disabled:false; }

    function refreshEmpty(){
        var any=Array.from(chipsBox.querySelectorAll('.modal-chip')).some(function(c){return c.style.display!=='none';});
        emptyMsg.style.display=any?'none':'inline';
    }

    function addRecipient(id){
        if(isSelected(id))return;
        var h=getHidden(id);if(h)h.disabled=false;
        var c=getChip(id);if(c)c.style.display='inline-flex';
        var s=getSug(id);if(s)s.style.display='none';
        refreshEmpty();
    }

    function removeRecipient(id){
        var h=getHidden(id);if(h)h.disabled=true;
        var c=getChip(id);if(c)c.style.display='none';
        var s=getSug(id);
        if(s){
            var q=searchInp.value.toLowerCase();
            s.style.display=(!q||(s.dataset.name||'').includes(q)||(s.dataset.email||'').includes(q))?'flex':'none';
        }
        refreshEmpty();
        filterDropdown(searchInp.value);
    }

    function filterDropdown(q){
        q=q.trim().toLowerCase();
        var any=false;
        dropdown.querySelectorAll('.modal-suggestion').forEach(function(s){
            if(isSelected(s.dataset.id)){s.style.display='none';return;}
            var m = q && ((s.dataset.name||'').includes(q)||(s.dataset.email||'').includes(q));
            s.style.display=m?'flex':'none';
            if(m)any=true;
        });
        if(noResults)noResults.style.display=(q && !any)?'block':'none';
    }

    function buildSummary(){
        var frags=[];
        chipsBox.querySelectorAll('.modal-chip').forEach(function(c){
            if(c.style.display==='none')return;
            // extraer texto del chip (sin el botón X)
            var spans=c.querySelectorAll('span');
            var init=spans[0]?spans[0].textContent.trim():'?';
            // el label es el textNode entre los spans
            var label='';
            c.childNodes.forEach(function(n){if(n.nodeType===3)label+=n.textContent;});
            label=label.trim();
            frags.push('<span style="display:inline-flex;align-items:center;gap:4px;background:linear-gradient(135deg,#3b82f6,#6366f1);color:#fff;padding:3px 10px;border-radius:20px;font-size:0.73rem;font-weight:600;">'
                +'<span style="width:16px;height:16px;border-radius:50%;background:rgba(255,255,255,0.25);display:inline-flex;align-items:center;justify-content:center;font-size:0.6rem;font-weight:800;">'+init+'</span>'
                +label+'</span>');
        });
        summary.innerHTML=frags.length?frags.join(''):'<span style="font-size:0.78rem;color:#94a3b8;">Sin destinatarios seleccionados</span>';
    }

    function saveSnapshot(){
        snapshot={};
        hiddenWrap.querySelectorAll('.recip-hidden-input').forEach(function(h){snapshot[h.value]=!h.disabled;});
    }

    function restoreSnapshot(){
        Object.keys(snapshot).forEach(function(id){
            var h=getHidden(id);if(h)h.disabled=!snapshot[id];
            var c=getChip(id);if(c)c.style.display=snapshot[id]?'inline-flex':'none';
            var s=getSug(id);if(s)s.style.display=snapshot[id]?'none':'flex';
        });
        refreshEmpty();
    }

    modalEl.addEventListener('show.bs.modal',function(){
        saveSnapshot();
        searchInp.value='';
        filterDropdown('');
        dropdown.style.display='none';
        refreshEmpty();
    });

    searchInp.addEventListener('focus',function(){
        var v = searchInp.value.trim();
        if(v) { filterDropdown(v); dropdown.style.display='block'; }
        else { dropdown.style.display='none'; }
    });
    searchInp.addEventListener('input',function(){
        var v = searchInp.value.trim();
        if(v) { filterDropdown(v); dropdown.style.display='block'; }
        else { dropdown.style.display='none'; }
    });

    modalEl.addEventListener('click',function(e){
        if(!e.target.closest('#modalSearchWrapper')) dropdown.style.display='none';
    });

    dropdown.addEventListener('click',function(e){
        var s=e.target.closest('.modal-suggestion');if(!s)return;
        addRecipient(s.dataset.id);
        searchInp.value='';filterDropdown('');searchInp.focus();
    });

    chipsBox.addEventListener('click',function(e){
        var btn=e.target.closest('.modal-chip-remove');if(!btn)return;
        removeRecipient(btn.dataset.id);
    });

    cancelBtn.addEventListener('click',function(){
        restoreSnapshot();
        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
    });

    saveBtn.addEventListener('click',function(){
        buildSummary();
        var modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
        modalInstance.hide();
        // Enviar el formulario principal para guardar en BD de inmediato
        modalEl.closest('form').submit();
    });

    refreshEmpty();
})();
</script>

                <div class="col-12">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Staff</th>
                                    <th>Correo</th>
                                    <th style="width: 220px;">Email ticket asignado</th>
                                    <th style="width: 220px;">Email tarea asignada</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($agents)): ?>
                                    <tr><td colspan="4" class="text-center text-muted py-4">No hay registros.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($agents as $a): ?>
                                    <tr>
                                        <!-- VISTA MÓVIL (Tarjeta Premium) -->
                                        <td class="d-md-none p-0">
                                            <div style="padding: 16px; background: #ffffff;">
                                                <div class="d-flex justify-content-between align-items-start mb-3">
                                                    <div class="d-flex align-items-center gap-3">
                                                        <div style="background: rgba(37,99,235,0.08); color: #2563eb; width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; font-weight: 800;">
                                                            <?php echo strtoupper(substr($a['name'] !== '' ? $a['name'] : 'U', 0, 1)); ?>
                                                        </div>
                                                        <div style="line-height: 1.2;">
                                                            <div style="font-weight: 800; color: #0f172a; font-size: 1.05rem;">
                                                                <?php echo html($a['name'] !== '' ? $a['name'] : ('Usuario #' . (int)$a['id'])); ?>
                                                            </div>
                                                            <div style="font-size: 0.8rem; color: #64748b; margin-top: 4px; font-weight: 500;">
                                                                <?php echo html($a['email']); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php if (($a['role'] ?? '') !== ''): ?>
                                                        <span style="background: #f1f5f9; color: #475569; padding: 4px 10px; border-radius: 20px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">
                                                            <?php echo html((string)$a['role']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>

                                                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 16px;">
                                                    <div style="color: #64748b; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 14px;">
                                                        Avisos por Correo
                                                    </div>
                                                    
                                                    <div class="d-flex justify-content-between align-items-center mb-3 pb-3" style="border-bottom: 1px dashed #cbd5e1;">
                                                        <div style="font-size: 0.9rem; font-weight: 600; color: #1e293b;">
                                                            <i class="bi bi-ticket-detailed me-2" style="color: #2563eb;"></i>Nuevos Tickets
                                                        </div>
                                                        <div class="form-check form-switch m-0" style="padding-left: 0;">
                                                            <input class="form-check-input ms-0 shadow-sm" type="checkbox" role="switch" name="email_ticket_assigned[<?php echo (int)$a['id']; ?>]" value="1" <?php echo $a['ticket'] ? 'checked' : ''; ?> style="width: 2.8em; height: 1.4em; cursor: pointer;">
                                                        </div>
                                                    </div>

                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div style="font-size: 0.9rem; font-weight: 600; color: #1e293b;">
                                                            <i class="bi bi-list-check me-2" style="color: #10b981;"></i>Nuevas Tareas
                                                        </div>
                                                        <div class="form-check form-switch m-0" style="padding-left: 0;">
                                                            <input class="form-check-input ms-0 shadow-sm" type="checkbox" role="switch" name="email_task_assigned[<?php echo (int)$a['id']; ?>]" value="1" <?php echo $a['task'] ? 'checked' : ''; ?> style="width: 2.8em; height: 1.4em; cursor: pointer;">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- VISTA ESCRITORIO -->
                                        <td class="d-none d-md-table-cell">
                                            <strong><?php echo html($a['name'] !== '' ? $a['name'] : ('Usuario #' . (int)$a['id'])); ?></strong>
                                            <?php if (($a['role'] ?? '') !== ''): ?>
                                                <div class="text-muted small"><?php echo html((string)$a['role']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="d-none d-md-table-cell"><?php echo html($a['email']); ?></td>
                                        <td class="d-none d-md-table-cell">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" role="switch" name="email_ticket_assigned[<?php echo (int)$a['id']; ?>]" value="1" <?php echo $a['ticket'] ? 'checked' : ''; ?>>
                                            </div>
                                        </td>
                                        <td class="d-none d-md-table-cell">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" role="switch" name="email_task_assigned[<?php echo (int)$a['id']; ?>]" value="1" <?php echo $a['task'] ? 'checked' : ''; ?>>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end mt-3">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Guardar</button>
            </div>
        </form>
    </div>
</div>

<style>
/* Responsive Table -> Cards for Mobile */
@media (max-width: 768px) {
    .settings-card { background: transparent !important; box-shadow: none !important; }
    .settings-card .card-header { border-radius: 12px; margin-bottom: 12px; }
    .settings-card .table-responsive { border: none !important; overflow: visible; }
    .settings-card .table { background: transparent; }
    .settings-card .table thead { display: none; }
    .settings-card .table tbody tr {
        display: block;
        margin-bottom: 1rem;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        overflow: hidden;
    }
    .settings-card .table tbody td {
        border: none !important;
        padding: 0 !important;
    }
}
</style>

<?php
$content = ob_get_clean();
require_once 'layout_admin.php';
exit;

