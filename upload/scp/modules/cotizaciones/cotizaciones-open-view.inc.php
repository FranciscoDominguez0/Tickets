<style>
/* ── cotizaciones-open-view.inc.php – Modern Professional Design ── */
.open-ticket-shell { 
    max-width: 880px; 
    margin: 0 auto;
}

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
</style>

<div class="tickets-shell open-ticket-shell">
    <div class="tickets-header">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
            <div>
                <h1>Nueva Cotización</h1>
                <div class="sub">Complete los datos para generar una nueva cotización</div>
            </div>
            <a href="cotizaciones.php" class="btn-new" style="background: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.30); color: #fff; padding: 8px 16px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 6px;">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" action="cotizaciones.php?a=open" id="form-open-quote">
        <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
        
        <!-- Información General -->
        <div class="open-section">
            <div class="section-title"><i class="bi bi-file-earmark-text"></i> Información de Cotización</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Título <span class="required">*</span></label>
                    <input type="text" name="title" class="form-control" placeholder="Ej. Instalación de Cámaras" value="<?php echo html($_POST['title'] ?? ''); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Sucursal</label>
                    <input type="text" name="sucursal" class="form-control" placeholder="Ej. Penonomé" value="<?php echo html($_POST['sucursal'] ?? ''); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Organización <span class="required">*</span></label>
                    <select name="org_id" class="form-select" required>
                        <option value="">Seleccione una organización...</option>
                        <?php foreach ($orgs as $o): ?>
                            <option value="<?php echo $o['id']; ?>" <?php echo (($_POST['org_id'] ?? '') == $o['id']) ? 'selected' : ''; ?>>
                                <?php echo html($o['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Descripción -->
        <div class="open-section">
            <div class="section-title"><i class="bi bi-chat-left-text"></i> Descripción / Detalles</div>
            <div class="mb-0">
                <textarea name="description" class="form-control" rows="5" placeholder="Detalles de lo que incluye la cotización..."><?php echo html($_POST['description'] ?? ''); ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-submit" id="btnSubmitQuote"><i class="bi bi-plus-lg"></i> <span>Crear Cotización</span></button>
            <a href="cotizaciones.php" class="btn btn-cancel"><i class="bi bi-x-lg"></i> Cancelar</a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('form-open-quote');
    const btn = document.getElementById('btnSubmitQuote');
    if (form && btn) {
        form.addEventListener('submit', function(e) {
            if (form.checkValidity()) {
                // Timeout para asegurar que el evento submit se propague antes de deshabilitar
                setTimeout(() => {
                    btn.disabled = true;
                    btn.style.opacity = '0.8';
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Creando...';
                }, 50);
            }
        });
    }
});
</script>
