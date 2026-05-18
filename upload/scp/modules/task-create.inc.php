<?php
// task-create.inc.php - Formulario para crear nueva tarea con diseño premium y unificado
?>

<?php
$agentsByDeptB64 = base64_encode((string)($agentsJson ?: '{}'));
?>

<div id="tasks-data" hidden data-agents-by-dept-b64="<?php echo html($agentsByDeptB64); ?>" data-current-assigned=""></div>

<style>
/* ── task-create.inc.php – Modern Professional Design ── */
@keyframes fadeInUpProfessional {
    0% { opacity: 0; transform: translateY(22px) scale(0.97); }
    100% { opacity: 1; transform: translateY(0) scale(1); }
}
@keyframes fadeInHeader {
    0%  { opacity: 0; transform: translateY(-10px); }
    100%{ opacity: 1; transform: translateY(0); }
}
.open-task-shell { 
    max-width: 880px; 
    margin: 0 auto;
}
.open-task-shell .tasks-header {
    animation: fadeInHeader 0.45s cubic-bezier(0.16, 1, 0.3, 1) both;
}
.open-task-shell .open-section:nth-child(1) { animation: fadeInUpProfessional 0.45s 0.10s cubic-bezier(0.16,1,0.3,1) both; opacity:0; }
.open-task-shell .open-section:nth-child(2) { animation: fadeInUpProfessional 0.45s 0.20s cubic-bezier(0.16,1,0.3,1) both; opacity:0; }
.open-task-shell .form-actions { animation: fadeInUpProfessional 0.4s 0.28s cubic-bezier(0.16,1,0.3,1) both; opacity:0; }

.open-task-shell .tasks-header {
    background: radial-gradient(circle at 0% 0%, #ef4444 0%, #1a0000 35%, #000000 100%);
    color: #fff;
    border-radius: 14px;
    padding: 24px 22px;
    margin-bottom: 20px;
    box-shadow: 0 8px 30px rgba(239, 68, 68, 0.2);
}
.open-task-shell .tasks-header h1 {
    margin: 0;
    font-size: 1.4rem;
    font-weight: 800;
    letter-spacing: -0.01em;
}
.open-task-shell .tasks-header .sub {
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
body.dark-mode .btn-cancel {
    background: #111;
    border-color: #333;
    color: #94a3b8;
}
body.dark-mode .text-muted {
    color: #64748b !important;
}
</style>

<div class="open-task-shell">
    <div class="tasks-header">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
            <div>
                <h1>Crear Nueva Tarea</h1>
                <div class="sub">Asigna una nueva tarea a un agente de tu equipo</div>
            </div>
            <a href="tasks.php" class="btn-new" style="background: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.30); color: #fff; padding: 8px 16px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 6px;">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo html($error); ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" id="form-create-task">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <input type="hidden" name="do" value="create">

        <!-- Detalles de la Tarea -->
        <div class="open-section">
            <div class="section-title"><i class="bi bi-list-task"></i> Detalles de la Tarea</div>
            
            <div class="mb-3">
                <label for="title" class="form-label">Título <span class="required">*</span></label>
                <input type="text" class="form-control" id="title" name="title" required placeholder="Ingresa un título descriptivo para la tarea"
                       value="<?php echo html($_POST['title'] ?? ''); ?>" maxlength="255">
            </div>

            <div class="mb-0">
                <label for="description" class="form-label">Descripción</label>
                <textarea class="form-control" id="description" name="description" rows="5" placeholder="Describe detalladamente en qué consiste la tarea..."><?php echo html($_POST['description'] ?? ''); ?></textarea>
            </div>
        </div>

        <!-- Asignación y Planificación -->
        <div class="open-section">
            <div class="section-title"><i class="bi bi-person-check"></i> Asignación y Planificación</div>
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="dept_id" class="form-label">Departamento <span class="required">*</span></label>
                    <select class="form-select" id="dept_id" name="dept_id" required>
                        <option value="">Seleccione un departamento...</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo (int) $d['id']; ?>" <?php echo (isset($_POST['dept_id']) && (int)$_POST['dept_id'] === (int)$d['id']) ? 'selected' : ''; ?>>
                                <?php echo html($d['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="assigned_to" class="form-label">Asignar a</label>
                    <select class="form-select" id="assigned_to" name="assigned_to" disabled>
                        <option value="">Sin asignar</option>
                    </select>
                    <div id="select-dept-hint" class="form-text mt-1 text-muted" style="display:none; font-size:0.78rem;"><i class="bi bi-info-circle"></i> Debe seleccionar un departamento primero.</div>
                    <div id="no-agents-hint" class="form-text mt-1 text-danger" style="display:none; font-size:0.78rem;"><i class="bi bi-exclamation-triangle"></i> No hay agentes disponibles en este departamento.</div>
                </div>

                <div class="col-md-6">
                    <label for="priority" class="form-label">Prioridad</label>
                    <select class="form-select" id="priority" name="priority">
                        <option value="low" <?php echo (($_POST['priority'] ?? 'normal') === 'low') ? 'selected' : ''; ?>>Baja</option>
                        <option value="normal" <?php echo (($_POST['priority'] ?? 'normal') === 'normal') ? 'selected' : ''; ?>>Normal</option>
                        <option value="high" <?php echo (($_POST['priority'] ?? 'normal') === 'high') ? 'selected' : ''; ?>>Alta</option>
                        <option value="urgent" <?php echo (($_POST['priority'] ?? 'normal') === 'urgent') ? 'selected' : ''; ?>>Urgente</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="due_date" class="form-label">Fecha Límite <span class="text-muted" style="font-weight: normal; font-size: 0.8rem;">(Opcional)</span></label>
                    <input type="datetime-local" class="form-control" id="due_date" name="due_date"
                           value="<?php echo html($_POST['due_date'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <!-- Acciones de Formulario -->
        <div class="form-actions">
            <button type="submit" class="btn btn-submit" id="btnSubmitTask">
                <i class="bi bi-plus-lg"></i> <span>Crear Tarea</span>
            </button>
            <a href="tasks.php" class="btn btn-cancel">
                <i class="bi bi-x-lg"></i> Cancelar
            </a>
        </div>
    </form>
</div>

<script>
  (function () {
    var form = document.getElementById('form-create-task');
    var btnSubmit = document.getElementById('btnSubmitTask');
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
            '<div style="font-weight: 800; font-size: 1.15rem; letter-spacing: -0.01em; color: var(--text-color, #0f172a); margin-top: 4px;" id="fullscreen-loading-title">Creando tarea...</div>' +
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