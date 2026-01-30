<?php
// Formulario: Abrir un nuevo ticket (tickets.php?a=open&uid=X)
// Usuario preseleccionado cuando se viene desde users.php?id=X
$preUser = $preSelectedUser ?? null;
$selected_uid = $preUser ? (int)$preUser['id'] : (isset($_POST['user_id']) && is_numeric($_POST['user_id']) ? (int)$_POST['user_id'] : 0);
$open_users = $open_users ?? [];
$open_departments = $open_departments ?? [];
$open_priorities = $open_priorities ?? [];
$open_staff = $open_staff ?? [];
?>
<style>
.ticket-open-wrap { max-width: 900px; margin: 0 auto; }
.ticket-open-wrap h1 { font-size: 1.5rem; font-weight: 700; color: #0f172a; margin-bottom: 24px; border-left: 4px solid #2563eb; padding-left: 16px; }
.ticket-open-section { background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; padding: 24px; margin-bottom: 24px; }
.ticket-open-section .section-title { font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 1px solid #e2e8f0; }
.ticket-open-section .form-label { font-weight: 600; color: #334155; }
.ticket-open-section .form-label .required { color: #dc2626; }
.ticket-open-user-display { display: inline-flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.ticket-open-user-display .user-text { font-size: 1rem; color: #0f172a; }
.ticket-open-user-display .btn-change { font-size: 0.85rem; padding: 6px 12px; border-radius: 8px; }
.ticket-open-section select.form-select, .ticket-open-section input.form-control { border-radius: 10px; }
.ticket-open-wrap .btn-submit { background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; border: none; padding: 12px 24px; border-radius: 10px; font-weight: 600; }
.ticket-open-wrap .btn-submit:hover { color: #fff; opacity: 0.95; }
.ticket-open-wrap .btn-back { color: #2563eb; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; margin-bottom: 16px; }
.ticket-open-wrap .btn-back:hover { text-decoration: underline; }
#open_user_select { max-width: 400px; display: none; }
</style>

<div class="ticket-open-wrap">
    <a href="users.php<?php echo $selected_uid ? '?id=' . $selected_uid : ''; ?>" class="btn-back"><i class="bi bi-arrow-left"></i> Volver</a>
    <h1>Abrir un nuevo Ticket</h1>

    <?php if (!empty($open_errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo implode(' ', array_map('htmlspecialchars', $open_errors)); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="post" action="tickets.php?a=open<?php echo $open_uid ? '&uid=' . $open_uid : ''; ?>" id="form-open-ticket">
        <input type="hidden" name="do" value="open">
        <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">

        <!-- Usuarios y colaboradores -->
        <div class="ticket-open-section">
            <div class="section-title">Usuarios y colaboradores</div>
            <div class="mb-3">
                <label class="form-label">Usuario: <span class="required">*</span></label>
                <div class="ticket-open-user-display">
                    <span class="user-text" id="open_user_display">
                        <?php
                        if ($selected_uid && $open_users) {
                            foreach ($open_users as $u) {
                                if ((int)$u['id'] === $selected_uid) {
                                    echo html(trim($u['firstname'] . ' ' . $u['lastname']) . ' <' . $u['email'] . '>');
                                    break;
                                }
                            }
                        }
                        if (!$selected_uid) echo '<span class="text-muted">Seleccione un usuario</span>';
                        ?>
                    </span>
                    <button type="button" class="btn btn-outline-secondary btn-change" id="btn_change_user">Cambiar</button>
                </div>
                <select name="user_id" id="open_user_select" class="form-select mt-2" required>
                    <option value="">— Seleccione el usuario —</option>
                    <?php foreach ($open_users as $u): ?>
                        <option value="<?php echo (int)$u['id']; ?>" <?php echo (int)$u['id'] === $selected_uid ? 'selected' : ''; ?>><?php echo html(trim($u['firstname'] . ' ' . $u['lastname']) . ' (' . $u['email'] . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-0">
                <label class="form-label">Aviso de Ticket:</label>
                <select class="form-select form-control" disabled style="max-width: 280px;">
                    <option>Alertar a todos</option>
                </select>
            </div>
        </div>

        <!-- Información y opciones del Ticket -->
        <div class="ticket-open-section">
            <div class="section-title">Información y opciones del Ticket</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Fuente del Ticket:</label>
                    <select class="form-select" name="source" id="open_source">
                        <option value="web">Web</option>
                        <option value="phone">Teléfono</option>
                        <option value="email">Email</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Departamento: <span class="required">*</span></label>
                    <select name="dept_id" class="form-select" required>
                        <?php foreach ($open_departments as $d): ?>
                            <option value="<?php echo (int)$d['id']; ?>" <?php echo (int)$d['id'] === 1 ? 'selected' : ''; ?>><?php echo html($d['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Prioridad:</label>
                    <select name="priority_id" class="form-select">
                        <?php foreach ($open_priorities as $p): ?>
                            <option value="<?php echo (int)$p['id']; ?>" <?php echo (int)$p['id'] === 2 ? 'selected' : ''; ?>><?php echo html($p['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Asignar a:</label>
                    <select name="staff_id" class="form-select">
                        <option value="0">— Sin asignar —</option>
                        <?php foreach ($open_staff as $s): ?>
                            <option value="<?php echo (int)$s['id']; ?>"><?php echo html(trim($s['firstname'] . ' ' . $s['lastname'])); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Asunto: <span class="required">*</span></label>
                    <input type="text" name="subject" class="form-control" placeholder="Asunto del ticket" required value="<?php echo html($_POST['subject'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <!-- Respuesta inicial -->
        <div class="ticket-open-section">
            <div class="section-title">Respuesta</div>
            <p class="text-muted small mb-2">Respuesta inicial para el ticket (opcional).</p>
            <div class="mb-3">
                <textarea name="body" id="open_body" class="form-control" placeholder="Respuesta inicial para el ticket" rows="6"></textarea>
            </div>
        </div>

        <div class="d-flex gap-2 align-items-center">
            <button type="submit" class="btn btn-submit"><i class="bi bi-plus-lg me-1"></i> Abrir Ticket</button>
            <a href="tickets.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/lang/summernote-es-ES.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var display = document.getElementById('open_user_display');
    var select = document.getElementById('open_user_select');
    var btnChange = document.getElementById('btn_change_user');
    if (btnChange && select) {
        btnChange.addEventListener('click', function() {
            if (select.style.display === 'none') {
                select.style.display = 'block';
                display.style.display = 'none';
                btnChange.textContent = 'Ocultar';
            } else {
                select.style.display = 'none';
                display.style.display = 'inline';
                var opt = select.options[select.selectedIndex];
                display.innerHTML = opt.value ? opt.text : '<span class="text-muted">Seleccione un usuario</span>';
                btnChange.textContent = 'Cambiar';
            }
        });
        select.addEventListener('change', function() {
            var opt = select.options[select.selectedIndex];
            if (opt.value) {
                var m = opt.text.match(/^(.+)\s+\(([^)]+)\)$/);
                display.innerHTML = m ? (m[1] + ' &lt;' + m[2] + '&gt;') : opt.text;
            } else {
                display.innerHTML = '<span class="text-muted">Seleccione un usuario</span>';
            }
        });
    }
    if (typeof jQuery !== 'undefined' && jQuery().summernote) {
        jQuery('#open_body').summernote({ height: 200, lang: 'es-ES', placeholder: 'Respuesta inicial para el ticket', toolbar: [ ['style', ['bold', 'italic', 'underline']], ['para', ['ul', 'ol']], ['insert', ['link']] ] });
    }
});
</script>
