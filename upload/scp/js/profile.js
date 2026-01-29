/**
 * Mi perfil: validación y comportamiento del formulario y modal de cambio de contraseña
 */

(function () {
    'use strict';

    var formChangePassword = document.getElementById('form-change-password');
    var newPassword = document.getElementById('new_password');
    var confirmPassword = document.getElementById('confirm_password');
    var modalChangePassword = document.getElementById('modalChangePassword');

    // Validar coincidencia de contraseñas al enviar el modal
    if (formChangePassword) {
        formChangePassword.addEventListener('submit', function (e) {
            if (newPassword && confirmPassword && newPassword.value !== confirmPassword.value) {
                e.preventDefault();
                confirmPassword.setCustomValidity('Las contraseñas no coinciden.');
                confirmPassword.reportValidity();
                return;
            }
            if (confirmPassword) {
                confirmPassword.setCustomValidity('');
            }
        });
    }

    // Quitar mensaje de validación al escribir en confirmación
    if (confirmPassword) {
        confirmPassword.addEventListener('input', function () {
            this.setCustomValidity('');
        });
    }

    // Limpiar campos del modal al cerrar
    if (modalChangePassword) {
        modalChangePassword.addEventListener('hidden.bs.modal', function () {
            if (formChangePassword) {
                formChangePassword.reset();
            }
            if (confirmPassword) {
                confirmPassword.setCustomValidity('');
            }
        });
    }

    // Restablecer del formulario principal: opcional feedback visual
    var profileForm = document.getElementById('profile-form');
    if (profileForm) {
        var resetBtn = profileForm.querySelector('button[type="reset"]');
        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                setTimeout(function () {
                    var firstInvalid = profileForm.querySelector('.is-invalid');
                    if (firstInvalid) {
                        firstInvalid.classList.remove('is-invalid');
                    }
                }, 0);
            });
        }
    }
})();
