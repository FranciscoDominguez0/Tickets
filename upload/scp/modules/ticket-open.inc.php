<?php
// Formulario: Abrir un nuevo ticket (tickets.php?a=open&uid=X)
// Usuario preseleccionado cuando se viene desde users.php?id=X
$preUser = $preSelectedUser ?? null;
$selected_uid = $preUser ? (int)$preUser['id'] : (isset($_POST['user_id']) && is_numeric($_POST['user_id']) ? (int)$_POST['user_id'] : 0);
$selected_dept_id = isset($_POST['dept_id']) && is_numeric($_POST['dept_id']) ? (int) $_POST['dept_id'] : 0;
$selected_staff_id = isset($_POST['staff_id']) && is_numeric($_POST['staff_id']) ? (int) $_POST['staff_id'] : 0;
$selected_topic_id = isset($_POST['topic_id']) && is_numeric($_POST['topic_id']) ? (int) $_POST['topic_id'] : 0;
$open_departments = $open_departments ?? [];
$open_priorities = $open_priorities ?? [];
$open_staff = $open_staff ?? [];
$open_user_query = $open_user_query ?? '';
$open_user_results = $open_user_results ?? [];
$open_hasTopics = $open_hasTopics ?? false;
$open_topics = $open_topics ?? [];
$walkinDefaultUserId = isset($walkinDefaultUserId) ? (int)$walkinDefaultUserId : 0;
$walkinDefaultUser = $walkinDefaultUser ?? null;
$walkinSelected = ($walkinDefaultUserId > 0 && $selected_uid > 0 && $selected_uid === $walkinDefaultUserId);

$userName = '';
$userEmail = '';
if ($preUser) {
    $userName = trim($preUser['firstname'] . ' ' . $preUser['lastname']);
    $userEmail = $preUser['email'];
}

$parts = preg_split('/\s+/', trim($userName));
$i1 = strtoupper((string)($parts[0][0] ?? ''));
$i2 = '';
if (count($parts) > 1) {
    $i2 = strtoupper((string)($parts[1][0] ?? ''));
} elseif (strlen($userName) > 1) {
    $i2 = strtoupper(substr($userName, 1, 1));
}
$initials = trim($i1 . $i2);
if ($initials === '') $initials = 'U';
$avatarColors = ['#ef4444','#7c3aed','#db2777','#ea580c','#16a34a','#0891b2'];
$avatarColor = $avatarColors[($selected_uid ?: 0) % count($avatarColors)];
?>

<style>
/* ── ticket-open.inc.php – Modern Professional Design ── */
@keyframes fadeInUpProfessional {
    0% { opacity: 0; transform: translateY(22px) scale(0.97); }
    100% { opacity: 1; transform: translateY(0) scale(1); }
}
@keyframes fadeInHeader {
    0%  { opacity: 0; transform: translateY(-10px); }
    100%{ opacity: 1; transform: translateY(0); }
}
.open-ticket-shell { 
    max-width: 880px; 
    margin: 0 auto;
}
.open-ticket-shell .tickets-header {
    animation: fadeInHeader 0.45s cubic-bezier(0.16, 1, 0.3, 1) both;
}
.open-ticket-shell .open-section:nth-child(1) { animation: fadeInUpProfessional 0.45s 0.10s cubic-bezier(0.16,1,0.3,1) both; opacity:0; }
.open-ticket-shell .open-section:nth-child(2) { animation: fadeInUpProfessional 0.45s 0.20s cubic-bezier(0.16,1,0.3,1) both; opacity:0; }
.open-ticket-shell .open-section:nth-child(3) { animation: fadeInUpProfessional 0.45s 0.30s cubic-bezier(0.16,1,0.3,1) both; opacity:0; }
.open-ticket-shell .open-section:nth-child(4) { animation: fadeInUpProfessional 0.45s 0.38s cubic-bezier(0.16,1,0.3,1) both; opacity:0; }
.open-ticket-shell .form-actions { animation: fadeInUpProfessional 0.4s 0.44s cubic-bezier(0.16,1,0.3,1) both; opacity:0; }

.open-ticket-shell .tickets-header {
    background: radial-gradient(circle at 0% 0%, #ef4444 0%, #1a0000 35%, #000000 100%);
    color: #fff;
    border-radius: 14px;
    padding: 24px 22px;
    margin-bottom: 20px;
    box-shadow: 0 8px 30px rgba(239, 68, 68, 0.2);
}
.open-ticket-shell .tickets-header h1 {
    margin: 0;
    font-size: 1.4rem;
    font-weight: 800;
    letter-spacing: -0.01em;
}
.open-ticket-shell .tickets-header .sub {
    margin-top: 4px;
    opacity: 0.92;
    font-size: 0.9rem;
    font-weight: 500;
}

/* Section cards */
.open-section {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    padding: 22px 24px;
    margin-bottom: 16px;
    position: relative;
    transition: all 0.3s ease;
}
body.dark-mode .open-section {
    background: #111111;
    border-color: #333;
    box-shadow: 0 4px 20px rgba(0,0,0,0.4);
}
.open-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(180deg, #ef4444, #991b1b);
    border-radius: 14px 0 0 14px;
}
.open-section .section-title {
    font-size: 0.82rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #475569;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 8px;
}
body.dark-mode .open-section .section-title {
    color: #cbd5e1;
}
.open-section .section-title i {
    color: #ef4444;
    font-size: 1rem;
}

/* User card */
.user-select-card {
    display: flex;
    align-items: center;
    gap: 14px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 14px 16px;
}
body.dark-mode .user-select-card {
    background: #0a0a0a;
    border-color: #222;
}
.user-select-card .user-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: <?php echo html($avatarColor); ?>;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    font-weight: 700;
    flex-shrink: 0;
}
.user-select-card .user-info {
    flex: 1;
    min-width: 0;
}
.user-select-card .user-name {
    font-weight: 700;
    color: #0f172a;
    font-size: 0.95rem;
}
body.dark-mode .user-select-card .user-name {
    color: #f1f5f9;
}
.user-select-card .user-email {
    font-size: 0.82rem;
    color: #64748b;
}
.user-select-card .btn-change {
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.85rem;
    padding: 6px 14px;
}

/* Form inputs */
.open-section .form-label {
    font-weight: 600;
    color: #334155;
    font-size: 0.88rem;
    margin-bottom: 6px;
}
body.dark-mode .open-section .form-label {
    color: #94a3b8;
}
.open-section .form-label .required {
    color: #dc2626;
}
.open-section .form-select,
.open-section .form-control {
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    font-size: 0.92rem;
    padding: 10px 14px;
}
.open-section .form-select:focus,
.open-section .form-control:focus {
    border-color: #ef4444;
    box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1);
}
body.dark-mode .open-section .form-select,
body.dark-mode .open-section .form-control {
    background: #000;
    border-color: #333;
    color: #fff;
}

/* Actions */
.form-actions {
    display: flex;
    gap: 10px;
    align-items: center;
    padding-top: 8px;
}
.form-actions .btn-submit {
    background: linear-gradient(135deg, #dc2626, #ef4444);
    color: #fff;
    border: none;
    padding: 12px 28px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 0.95rem;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
}
.form-actions .btn-submit:hover {
    opacity: 0.95;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.25);
}
.form-actions .btn-cancel {
    background: #fff;
    color: #475569;
    border: 1px solid #e2e8f0;
    padding: 12px 24px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.95rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.form-actions .btn-cancel:hover {
    background: #f8fafc;
    color: #0f172a;
}

/* Responsive */
@media (max-width: 576px) {
    .open-section { padding: 16px; }
    .user-select-card { flex-wrap: wrap; }
    .form-actions { flex-direction: column; align-items: stretch; }
    .form-actions .btn-submit,
    .form-actions .btn-cancel { width: 100%; justify-content: center; }
}

/* Loading state for double-submit prevention */
.btn-submit.processing {
    pointer-events: none;
    opacity: 0.8;
}
.loading-fullscreen-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(15, 23, 42, 0.4);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
}
.loading-fullscreen-card {
    background: #ffffff;
    padding: 28px 40px;
    border-radius: 20px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15), 0 10px 10px -5px rgba(0, 0, 0, 0.05);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
    border: 1px solid #f1f5f9;
}
body.dark-mode .loading-fullscreen-overlay {
    background: rgba(0, 0, 0, 0.6);
}
body.dark-mode .loading-fullscreen-card {
    background: #111111;
    border-color: #222;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
}
.loading-fullscreen-card .spinner-border {
    color: #ef4444 !important;
}
body.dark-mode .open-section {
    background: #111111 !important;
    border-color: #222 !important;
    color: #f1f5f9;
}
body.dark-mode .user-select-card {
    background: #0a0a0a !important;
    border-color: #222 !important;
}
body.dark-mode .section-title {
    color: #94a3b8 !important;
}
body.dark-mode .form-label {
    color: #cbd5e1 !important;
}
body.dark-mode .form-control,
body.dark-mode .form-select {
    background: #000 !important;
    border-color: #333 !important;
    color: #fff !important;
}
body.dark-mode .btn-outline-danger {
    color: #ef4444;
    border-color: #ef4444;
}
body.dark-mode .btn-outline-danger:hover {
    background: #ef4444;
    color: #fff;
}
body.dark-mode .btn-cancel {
    background: #111;
    border-color: #333;
    color: #94a3b8;
}
body.dark-mode .text-muted {
    color: #64748b !important;
}
</style>

<div class="tickets-shell open-ticket-shell">
    <div class="tickets-header">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
            <div>
                <h1>Abrir nuevo Ticket</h1>
                <div class="sub">Crea un ticket de soporte para un cliente</div>
            </div>
            <a href="tickets.php<?php echo $selected_uid ? '?id=' . $selected_uid : ''; ?>" class="btn-new" style="background: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.30); color: #fff; padding: 8px 16px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 6px;">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <?php if (!empty($open_errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo implode(' ', array_map('htmlspecialchars', $open_errors)); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="post" action="tickets.php?a=open<?php echo $open_uid ? '&uid=' . $open_uid : ''; ?>" id="form-open-ticket">
        <input type="hidden" name="do" value="open">
        <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
        <input type="hidden" name="user_id" value="<?php echo $selected_uid ? (int)$selected_uid : ''; ?>">
        <input type="hidden" name="dept_id" id="open_dept_id" value="<?php echo (int)$selected_dept_id; ?>">
        <input type="hidden" name="walkin_default_user_id" id="walkin_default_user_id" value="<?php echo (int)$walkinDefaultUserId; ?>">

        <!-- Cliente -->
        <div class="open-section">
            <div class="section-title"><i class="bi bi-person"></i> Cliente</div>
            <div class="mb-0">
                <label class="form-label">Usuario solicitante <span class="required">*</span></label>
                <div class="user-select-card">
                    <div class="user-avatar" id="open_user_avatar"><?php echo html($initials); ?></div>
                    <div class="user-info" id="open_user_display">
                        <?php if ($preUser): ?>
                            <div class="user-name"><?php echo html($userName); ?></div>
                            <div class="user-email"><?php echo html($userEmail); ?></div>
                        <?php else: ?>
                            <div class="user-name" style="color:#94a3b8; font-weight:500;">Seleccione un usuario</div>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="btn btn-outline-danger btn-change" id="btn_change_user" data-bs-toggle="modal" data-bs-target="#modalUserSearch" style="border-radius: 10px; font-weight: 600;">
                        <i class="bi bi-search text-danger"></i> Buscar
                    </button>
                </div>

                <?php if ($walkinDefaultUserId > 0 && $walkinDefaultUser): ?>
                    <div class="mt-2">
                        <button type="button" class="btn btn-outline-danger btn-sm" id="btn_walkin_user" data-user-id="<?php echo (int)$walkinDefaultUserId; ?>" data-user-name="<?php echo html(trim((string)($walkinDefaultUser['firstname'] ?? '') . ' ' . (string)($walkinDefaultUser['lastname'] ?? ''))); ?>" data-user-email="<?php echo html((string)($walkinDefaultUser['email'] ?? '')); ?>" style="border-radius: 10px; font-weight: 700;">
                            Usar cliente no recurrente
                        </button>
                    </div>
                <?php endif; ?>

                <div class="mt-3" id="walkin_fields" style="display: <?php echo $walkinSelected ? 'block' : 'none'; ?>;">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Teléfono del cliente <span class="required">*</span></label>
                            <input type="text" name="walkin_phone" class="form-control" value="<?php echo html($_POST['walkin_phone'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Dirección del cliente <span class="required">*</span></label>
                            <input type="text" name="walkin_address" class="form-control" value="<?php echo html($_POST['walkin_address'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información del Ticket -->
        <div class="open-section">
            <div class="section-title"><i class="bi bi-ticket-perforated"></i> Información del Ticket</div>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label"><?php echo $walkinSelected ? 'Nombre del cliente' : 'Asunto'; ?> <span class="required">*</span></label>
                    <input type="text" name="subject" class="form-control" placeholder="<?php echo $walkinSelected ? 'Nombre del cliente no recurrente' : 'Describe brevemente el problema'; ?>" required value="<?php echo html($_POST['subject'] ?? ''); ?>">
                    <input type="hidden" name="source" value="web">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tema:</label>
                    <select name="topic_id" class="form-select" id="open_topic_id">
                        <option value="0" <?php echo $selected_topic_id === 0 ? 'selected' : ''; ?>>— General —</option>
                        <?php if ($open_hasTopics && !empty($open_topics)): ?>
                            <?php foreach ($open_topics as $tp): ?>
                                <option value="<?php echo (int)$tp['id']; ?>" data-dept-id="<?php echo (int)($tp['dept_id'] ?? 0); ?>" <?php echo (int)$tp['id'] === $selected_topic_id ? 'selected' : ''; ?>><?php echo html($tp['name']); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Prioridad:</label>
                    <select name="priority_id" class="form-select">
                        <?php foreach ($open_priorities as $p): ?>
                            <option value="<?php echo (int)$p['id']; ?>" <?php echo (int)$p['id'] === 2 ? 'selected' : ''; ?>><?php echo html($p['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Asignar a:</label>
                    <select name="staff_id" class="form-select" id="open_staff_id" <?php echo $selected_dept_id > 0 ? '' : 'disabled'; ?>>
                        <option value="0">— Sin asignar —</option>
                        <?php foreach ($open_staff as $s): ?>
                            <option value="<?php echo (int)$s['id']; ?>" data-dept-id="<?php echo (int)($s['dept_id'] ?? 0); ?>" <?php echo (int)$s['id'] === $selected_staff_id ? 'selected' : ''; ?>><?php echo html(trim($s['firstname'] . ' ' . $s['lastname'])); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Respuesta inicial -->
        <div class="open-section">
            <div class="section-title"><i class="bi bi-chat-left-text"></i> Respuesta inicial</div>
            <p class="text-muted small mb-2">Describe el problema o deja una nota inicial (opcional).</p>
            <div class="mb-0">
                <textarea name="body" id="open_body" class="form-control" placeholder="Escribe aquí los detalles del ticket..." rows="5"></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-submit" id="btnSubmitTicket"><i class="bi bi-plus-lg"></i> <span>Abrir Ticket</span></button>
            <a href="tickets.php" class="btn btn-cancel"><i class="bi bi-x-lg"></i> Cancelar</a>
        </div>
    </form>
</div>

<script>
  (function () {
    var deptSel = document.getElementById('open_dept_id');
    var staffSel = document.getElementById('open_staff_id');
    var topicSel = document.getElementById('open_topic_id');
    if (!deptSel || !staffSel) return;

    var syncDeptFromTopic = function () {
      if (!topicSel) return;
      var opt = topicSel.options[topicSel.selectedIndex];
      if (!opt) return;
      var tdept = (opt.getAttribute('data-dept-id') || '').toString();
      if (tdept !== '' && tdept !== '0') {
        deptSel.value = tdept;
      } else {
        deptSel.value = '';
      }
    };

    var applyStaffFilter = function () {
      var deptId = (deptSel.value || '').toString();
      var hasDept = deptId !== '' && !isNaN(parseInt(deptId, 10));
      staffSel.disabled = !hasDept;

      var opts = staffSel.querySelectorAll('option');
      opts.forEach(function (opt) {
        if (!opt || opt.value === '0') return;
        var sdept = (opt.getAttribute('data-dept-id') || '').toString();
        opt.hidden = !hasDept || sdept !== deptId;
      });

      if (!hasDept) {
        staffSel.value = '0';
        return;
      }

      var selected = staffSel.options[staffSel.selectedIndex];
      if (selected && selected.hidden) staffSel.value = '0';
    };

    deptSel.addEventListener('change', applyStaffFilter);

    if (topicSel) {
      topicSel.addEventListener('change', function () {
        var opt = topicSel.options[topicSel.selectedIndex];
        if (!opt) return;
        var tdept = (opt.getAttribute('data-dept-id') || '').toString();
        if (tdept !== '' && tdept !== '0') {
          deptSel.value = tdept;
          applyStaffFilter();
        } else {
          deptSel.value = '';
          applyStaffFilter();
        }
      });
    }

    syncDeptFromTopic();
    applyStaffFilter();

    // Prevención de doble envío
    var form = document.getElementById('form-open-ticket');
    var btnSubmit = document.getElementById('btnSubmitTicket');
    var btnWalkin = document.getElementById('btn_walkin_user');
    var walkinFields = document.getElementById('walkin_fields');
    var walkinDefaultUserId = document.getElementById('walkin_default_user_id');
    var userIdInput = form ? form.querySelector('input[name="user_id"]') : null;
    var userDisplay = document.getElementById('open_user_display');
    var userAvatar = document.getElementById('open_user_avatar');

    var makeInitials = function (name) {
      name = (name || '').trim();
      if (!name) return 'U';
      var parts = name.split(/\s+/).filter(Boolean);
      var i1 = (parts[0] || '').charAt(0).toUpperCase();
      var i2 = '';
      if (parts.length > 1) i2 = (parts[1] || '').charAt(0).toUpperCase();
      else if (name.length > 1) i2 = name.charAt(1).toUpperCase();
      return (i1 + i2).trim() || 'U';
    };

    var showWalkin = function (show) {
      if (!walkinFields) return;
      walkinFields.style.display = show ? 'block' : 'none';
      var subjectInput = document.querySelector('input[name="subject"]');
      if (subjectInput) {
        var subjectLabel = subjectInput.previousElementSibling;
        if (subjectLabel && subjectLabel.tagName === 'LABEL') {
          subjectLabel.innerHTML = show ? 'Nombre del cliente <span class="required">*</span>' : 'Asunto <span class="required">*</span>';
        }
        subjectInput.placeholder = show ? 'Nombre del cliente no recurrente' : 'Describe brevemente el problema';
      }
    };

    if (btnWalkin && userIdInput && userDisplay && userAvatar) {
      btnWalkin.addEventListener('click', function () {
        var uid = (btnWalkin.getAttribute('data-user-id') || '').toString();
        var nm = (btnWalkin.getAttribute('data-user-name') || '').toString();
        var em = (btnWalkin.getAttribute('data-user-email') || '').toString();

        userIdInput.value = uid;
        userDisplay.innerHTML = '<div class="user-name">' + nm.replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</div>'
          + '<div class="user-email">' + em.replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</div>';
        userAvatar.textContent = makeInitials(nm);

        var defaultId = walkinDefaultUserId ? (walkinDefaultUserId.value || '').toString() : '';
        showWalkin(uid !== '' && defaultId !== '' && uid === defaultId);
      });
    }
    if (form && btnSubmit) {
      form.addEventListener('submit', function (e) {
        if (form.getAttribute('data-submitting') === '1') {
          e.preventDefault();
          return false;
        }

        form.setAttribute('data-submitting', '1');
        btnSubmit.classList.add('processing');
        btnSubmit.disabled = true;
        
        var span = btnSubmit.querySelector('span');
        var icon = btnSubmit.querySelector('i');
        if (span) span.textContent = 'Procesando...';
        if (icon) {
          icon.className = 'spinner-border spinner-border-sm me-1';
          icon.style.width = '1rem';
          icon.style.height = '1rem';
        }

        // Crear un único overlay de pantalla completa centrado profesional
        var fullscreenOverlay = document.createElement('div');
        fullscreenOverlay.className = 'loading-fullscreen-overlay';
        fullscreenOverlay.innerHTML = 
          '<div class="loading-fullscreen-card">' +
            '<div class="spinner-border text-danger" role="status" style="width: 3rem; height: 3rem; border-width: 0.35rem;"></div>' +
            '<div style="font-weight: 800; font-size: 1.15rem; letter-spacing: -0.01em; color: var(--text-color, #0f172a); margin-top: 4px;" id="fullscreen-loading-title">Creando ticket...</div>' +
            '<div style="font-size: 0.85rem; color: #64748b; font-weight: 500;">Por favor espera un momento</div>' +
          '</div>';

        // Soportar modo oscuro en el texto del modal
        if (document.body.classList.contains('dark-mode')) {
          var titleEl = fullscreenOverlay.querySelector('#fullscreen-loading-title');
          if (titleEl) titleEl.style.color = '#ffffff';
        }

        document.body.appendChild(fullscreenOverlay);
      });
    }
  })();
</script>

<!-- Modal: Buscar usuario -->
<div class="modal fade" id="modalUserSearch" tabindex="-1" aria-labelledby="modalUserSearchLabel" aria-hidden="true" data-open-default="<?php echo $open_user_query !== '' ? '1' : '0'; ?>">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="border-radius: 16px; border: 1px solid #e2e8f0; transition: all 0.3s ease;">
      <style>
        body.dark-mode #modalUserSearch .modal-content { background: #111; border-color: #333; color: #fff; }
        body.dark-mode #modalUserSearch .modal-header, 
        body.dark-mode #modalUserSearch .modal-footer { border-color: #222; }
        body.dark-mode #modalUserSearch .alert-info { background: #1e1b4b; border-color: #312e81; color: #e0e7ff; }
        body.dark-mode #modalUserSearch .list-group-item { background: #111; border-color: #222; color: #fff; }
        body.dark-mode #modalUserSearch .list-group-item:hover { background: #1a1a1a; }
        body.dark-mode #modalUserSearch .form-control { background: #000; border-color: #333; color: #fff; }
        body.dark-mode #modalUserSearch .input-group-text { background: #0a0a0a !important; border-color: #333; color: #94a3b8; }
      </style>
      <div class="modal-header" style="border-bottom: 1px solid #f1f5f9;">
        <h5 class="modal-title" id="modalUserSearchLabel" style="font-weight: 700; color: #0f172a;"><i class="bi bi-search me-2 text-danger"></i>Buscar usuario</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info py-2 mb-3" style="border-radius: 10px; font-size: 0.9rem;">
            <i class="bi bi-info-circle me-1"></i> Busca usuarios por email, teléfono o nombre.
        </div>

        <form method="get" action="tickets.php" class="mb-3">
          <input type="hidden" name="a" value="open">
          <div class="input-group">
            <span class="input-group-text bg-white" style="border-right: none; border-radius: 10px 0 0 10px;"><i class="bi bi-search text-muted"></i></span>
            <input type="text" class="form-control" style="border-left: none; border-radius: 0 10px 10px 0;" name="uq" id="open_user_query" placeholder="Buscar por email, teléfono o nombre" value="<?php echo html($open_user_query); ?>">
            <button class="btn btn-primary" type="submit" style="border-radius: 0 10px 10px 0; margin-left: 6px; background: linear-gradient(135deg,#dc2626,#ef4444); border: none;">Buscar</button>
          </div>
        </form>

        <?php if ($open_user_query !== '' && empty($open_user_results)): ?>
          <div class="text-muted text-center py-3"><i class="bi bi-inbox" style="font-size: 1.5rem; opacity: 0.5;"></i><br>No se encontraron usuarios.</div>
        <?php endif; ?>

        <?php if (!empty($open_user_results)): ?>
          <div class="list-group" style="border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0;">
            <?php foreach ($open_user_results as $u):
                $uParts = preg_split('/\s+/', trim($u['firstname'] . ' ' . $u['lastname']));
                $ui1 = strtoupper((string)($uParts[0][0] ?? ''));
                $ui2 = count($uParts) > 1 ? strtoupper((string)($uParts[1][0] ?? '')) : (strlen(trim($u['firstname'] . ' ' . $u['lastname'])) > 1 ? strtoupper(substr(trim($u['firstname'] . ' ' . $u['lastname']), 1, 1)) : '');
                $uInitials = trim($ui1 . $ui2) ?: 'U';
                $uColor = $avatarColors[($u['id'] ?? 0) % count($avatarColors)];
            ?>
              <div class="list-group-item d-flex justify-content-between align-items-center" style="border: none; border-bottom: 1px solid #f1f5f9; padding: 12px 16px;">
                <div class="d-flex align-items-center gap-3">
                  <div style="width: 36px; height: 36px; border-radius: 50%; background: <?php echo html($uColor); ?>; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 700; flex-shrink: 0;"><?php echo html($uInitials); ?></div>
                  <div>
                    <div style="font-weight: 700; color: #0f172a; font-size: 0.9rem;"><?php echo html(trim($u['firstname'] . ' ' . $u['lastname'])); ?></div>
                    <div style="font-size: 0.82rem; color: #64748b;"><?php echo html($u['email']); ?><?php if (!empty($u['phone'])): ?> · <?php echo html($u['phone']); ?><?php endif; ?></div>
                  </div>
                </div>
                <a class="btn btn-sm btn-outline-danger" style="border-radius: 8px; font-weight: 600;" href="tickets.php?a=open&uid=<?php echo (int)$u['id']; ?>">Seleccionar</a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer" style="border-top: 1px solid #f1f5f9;">
        <button type="button" class="btn btn-secondary" style="border-radius: 10px;" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
