<?php
// global_toast.php
// Mostrar notificaciones flotantes globales en todo el sistema.

$toastError = $_SESSION['flash_error'] ?? '';
$toastMsg = $_SESSION['flash_msg'] ?? '';
$toastWarning = $_SESSION['flash_warning'] ?? '';

unset($_SESSION['flash_error']);
unset($_SESSION['flash_msg']);
unset($_SESSION['flash_warning']);

// Mapear variables viejas a flash si las definen scripts locales
global $flashMsg, $flashError, $reply_error, $reply_success, $msg, $error, $warn;
if (isset($flashMsg) && $flashMsg !== '' && $toastMsg === '') {
    $toastMsg = $flashMsg;
}
if (isset($msg) && $msg !== '' && $toastMsg === '') {
    $toastMsg = $msg;
}
if (isset($flashError) && $flashError !== '' && $toastError === '') {
    $toastError = $flashError;
}
if (isset($reply_error) && $reply_error !== '' && $toastError === '') {
    $toastError = $reply_error;
}
if (isset($error) && $error !== '' && $toastError === '') {
    $toastError = $error;
}
if (isset($warn) && $warn !== '' && $toastWarning === '') {
    $toastWarning = $warn;
}
if (!empty($reply_success) && $toastMsg === '') {
    $toastMsg = is_string($reply_success) ? $reply_success : 'Operación exitosa.';
}
?>
<style>
/* Estilos para el Global Toast Profesional */
.global-toast-container {
    position: fixed;
    top: 24px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 1090; /* Sobre modales y navbars */
    display: flex;
    flex-direction: column;
    gap: 12px;
    pointer-events: none;
    width: max-content;
    max-width: 90vw;
}
.global-toast {
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 10px 40px -10px rgba(0,0,0,0.15), 0 2px 6px rgba(0,0,0,0.05);
    padding: 14px 22px;
    display: flex;
    align-items: center;
    gap: 12px;
    pointer-events: auto;
    opacity: 0;
    transform: translateY(-20px) scale(0.95);
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    border: 1px solid rgba(0,0,0,0.05);
}
.global-toast.show {
    opacity: 1;
    transform: translateY(0) scale(1);
}
.global-toast.hide {
    opacity: 0;
    transform: translateY(-20px) scale(0.95);
    pointer-events: none;
}
.global-toast-icon {
    font-size: 1.6rem;
    display: flex;
    align-items: center;
    justify-content: center;
}
.global-toast-success { border-left: 5px solid #10b981; }
.global-toast-success .global-toast-icon { color: #10b981; }
.global-toast-error { border-left: 5px solid #ef4444; }
.global-toast-error .global-toast-icon { color: #ef4444; }
.global-toast-warning { border-left: 5px solid #f59e0b; }
.global-toast-warning .global-toast-icon { color: #f59e0b; }
.global-toast-content {
    font-size: 0.95rem;
    color: #374151;
    font-weight: 500;
    margin-right: 12px;
    line-height: 1.4;
}
.global-toast-close {
    background: none;
    border: none;
    color: #9ca3af;
    cursor: pointer;
    font-size: 1.3rem;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.2s ease;
}
.global-toast-close:hover { color: #4b5563; }
body.dark-mode .global-toast,
body.superadmin-dark .global-toast {
    background: #1f2937;
    border-color: rgba(255,255,255,0.05);
    box-shadow: 0 10px 40px -10px rgba(0,0,0,0.5);
}
body.dark-mode .global-toast-content,
body.superadmin-dark .global-toast-content { color: #f3f4f6; }

/* Prevenir destello (flash) de alertas antiguas antes de que el JS las convierta en Toasts */
.alert.alert-success:not([data-alert-static="1"]):not([data-static="1"]):not(#tickets-flash-success),
.alert.alert-danger:not([data-alert-static="1"]):not([data-static="1"]):not(#tickets-flash-success),
.alert.alert-warning:not([data-alert-static="1"]):not([data-static="1"]):not(#tickets-flash-success) {
    display: none !important;
}
</style>

<div class="global-toast-container" id="globalToastContainer">
    <?php if ($toastError !== ''): ?>
    <div class="global-toast global-toast-error">
        <div class="global-toast-icon"><i class="bi bi-x-circle-fill"></i></div>
        <div class="global-toast-content"><?php echo html($toastError); ?></div>
        <button class="global-toast-close" onclick="closeGlobalToast(this)"><i class="bi bi-x"></i></button>
    </div>
    <?php endif; ?>
    <?php if ($toastMsg !== ''): ?>
    <div class="global-toast global-toast-success">
        <div class="global-toast-icon"><i class="bi bi-check-circle-fill"></i></div>
        <div class="global-toast-content"><?php echo html($toastMsg); ?></div>
        <button class="global-toast-close" onclick="closeGlobalToast(this)"><i class="bi bi-x"></i></button>
    </div>
    <?php endif; ?>
    <?php if ($toastWarning !== ''): ?>
    <div class="global-toast global-toast-warning">
        <div class="global-toast-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
        <div class="global-toast-content"><?php echo html($toastWarning); ?></div>
        <button class="global-toast-close" onclick="closeGlobalToast(this)"><i class="bi bi-x"></i></button>
    </div>
    <?php endif; ?>
</div>

<script>
function closeGlobalToast(btn) {
    const toast = btn.closest('.global-toast');
    if (toast) {
        toast.classList.remove('show');
        toast.classList.add('hide');
        setTimeout(() => toast.remove(), 400);
    }
}
function showGlobalToast(message, type = 'success') {
    const container = document.getElementById('globalToastContainer');
    if (!container) return;
    const toast = document.createElement('div');
    toast.className = `global-toast global-toast-${type}`;
    
    let iconClass = 'bi-check-circle-fill';
    if(type === 'error') iconClass = 'bi-x-circle-fill';
    if(type === 'warning') iconClass = 'bi-exclamation-triangle-fill';
    
    toast.innerHTML = `
        <div class="global-toast-icon"><i class="bi ${iconClass}"></i></div>
        <div class="global-toast-content">${message}</div>
        <button class="global-toast-close" onclick="closeGlobalToast(this)"><i class="bi bi-x"></i></button>
    `;
    container.appendChild(toast);
    
    // Animate in
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            toast.classList.add('show');
        });
    });
    // Auto dismiss
    setTimeout(() => {
        closeGlobalToast(toast.querySelector('.global-toast-close'));
    }, 5000);
}

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('globalToastContainer');
    if (container) {
        if (container.parentElement !== document.body) {
            document.body.appendChild(container);
        }
        
        // Centrar exactamente en el medio del contenido, respetando el ancho del sidebar
        function adjustToastPosition() {
            const mainContent = document.querySelector('.main-shell') || document.querySelector('.container-main') || document.querySelector('.page-wrapper');
            if (mainContent) {
                const rect = mainContent.getBoundingClientRect();
                const center = rect.left + (rect.width / 2);
                container.style.left = center + 'px';
                container.style.transform = 'translateX(-50%)';
            }
        }
        adjustToastPosition();
        window.addEventListener('resize', adjustToastPosition);
        
        // Ajustar también cuando se abre/cierra el sidebar
        const sidebar = document.querySelector('.scp-sidebar');
        if (sidebar) {
            const sidebarObserver = new MutationObserver(adjustToastPosition);
            sidebarObserver.observe(sidebar, { attributes: true, childList: true, subtree: true });
        }
    }

    // 1. Activar Toasts que vinieron desde el servidor
    const toasts = document.querySelectorAll('.global-toast');
    toasts.forEach(toast => {
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                toast.classList.add('show');
            });
        });
        setTimeout(() => {
            if(toast.parentElement) {
                closeGlobalToast(toast.querySelector('.global-toast-close'));
            }
        }, 5000);
    });

    // 2. Interceptar alertas legacy (Bootstrap) y convertirlas a Toast
    const oldAlerts = document.querySelectorAll('.alert-success, .alert-danger, .alert-warning');
    oldAlerts.forEach(alert => {
        // Ignorar alertas estáticas o que estén dentro de un modal (suelen ser informativas, no flash messages)
        if (alert.hasAttribute('data-alert-static') || alert.getAttribute('data-static') === '1' || alert.id === 'tickets-flash-success' || alert.closest('.modal')) return;
        
        let type = 'success';
        if (alert.classList.contains('alert-danger')) type = 'error';
        if (alert.classList.contains('alert-warning')) type = 'warning';
        
        let clone = alert.cloneNode(true);
        clone.querySelectorAll('button, i, svg, strong, span.visually-hidden').forEach(el => el.remove());
        let text = clone.textContent.trim();
        
        if (text) {
            // Evitar duplicados (ej: PHP ya generó un Toast con este texto y además interceptamos el div antiguo)
            let isDuplicate = false;
            document.querySelectorAll('.global-toast-content').forEach(et => {
                if (et.textContent.trim() === text) {
                    isDuplicate = true;
                }
            });
            
            if (!isDuplicate) {
                showGlobalToast(text, type);
            }
            alert.style.display = 'none';
            alert.remove();
        }
    });
});
</script>
