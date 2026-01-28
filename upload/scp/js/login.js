// Prevenir submit duplicado
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const btn = this.querySelector('.agent-btn-login');
            if (btn.disabled) {
                e.preventDefault();
                return false;
            }
            btn.disabled = true;
            btn.classList.add('loading');
            btn.textContent = 'Verificando...';
        });
    }
});
