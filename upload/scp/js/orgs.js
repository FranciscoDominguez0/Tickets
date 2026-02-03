/**
 * JavaScript para el módulo de Organizaciones
 * Maneja modales, validaciones y acciones
 */

(function() {
    'use strict';

    // Inicialización cuando el DOM está listo
    document.addEventListener('DOMContentLoaded', function() {
        initAddOrgModal();
        initDeleteOrgModal();
        initFormValidation();
        initSearchClear();
    });

    /**
     * Inicializar modal de añadir organización
     */
    function initAddOrgModal() {
        const modal = document.getElementById('addOrgModal');
        if (!modal) return;

        const form = document.getElementById('addOrgForm');
        if (!form) return;

        // Limpiar formulario al cerrar el modal
        modal.addEventListener('hidden.bs.modal', function() {
            form.reset();
            // Remover clases de error
            const errorInputs = form.querySelectorAll('.is-invalid');
            errorInputs.forEach(input => {
                input.classList.remove('is-invalid');
            });
            const errorMessages = form.querySelectorAll('.invalid-feedback');
            errorMessages.forEach(msg => msg.remove());
        });

    }

    /**
     * Inicializar modal de eliminar organización
     */
    function initDeleteOrgModal() {
        const deleteButtons = document.querySelectorAll('.btn-delete-org');
        const deleteModal = document.getElementById('deleteOrgModal');
        const deleteForm = document.getElementById('deleteOrgForm');
        const deleteOrgNameInput = document.getElementById('delete_org_name');
        const deleteOrgDisplay = document.getElementById('delete_org_display');

        if (!deleteModal || !deleteForm || !deleteOrgNameInput) return;

        deleteButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const orgName = this.getAttribute('data-org-name');
                if (orgName) {
                    deleteOrgNameInput.value = orgName;
                    if (deleteOrgDisplay) deleteOrgDisplay.textContent = orgName;
                    const bsModal = new bootstrap.Modal(deleteModal);
                    bsModal.show();
                }
            });
        });
    }

    /**
     * Inicializar validación de formularios
     */
    function initFormValidation() {
        const forms = document.querySelectorAll('.needs-validation');
        
        forms.forEach(form => {
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }

    /**
     * Inicializar botón de limpiar búsqueda
     */
    function initSearchClear() {
        const clearBtn = document.querySelector('.org-search-clear');
        if (clearBtn) {
            clearBtn.addEventListener('click', function(e) {
                e.preventDefault();
                window.location.href = 'orgs.php';
            });
        }
    }

    /**
     * Animación suave al hacer scroll a las tarjetas
     */
    function initCardAnimations() {
        const cards = document.querySelectorAll('.org-card');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry, index) => {
                if (entry.isIntersecting) {
                    setTimeout(() => {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }, index * 50);
                }
            });
        }, { threshold: 0.1 });

        cards.forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            observer.observe(card);
        });
    }

    // Inicializar animaciones si hay tarjetas
    if (document.querySelectorAll('.org-card').length > 0) {
        initCardAnimations();
    }

    /**
     * Confirmación mejorada para eliminar organización
     */
    const deleteForm = document.getElementById('deleteOrgForm');
    if (deleteForm) {
        deleteForm.addEventListener('submit', function(e) {
            const orgName = document.getElementById('delete_org_name')?.value || document.getElementById('delete_org_display')?.textContent || 'esta organización';
            if (!confirm('¿Estás seguro de eliminar la organización "' + orgName + '"?\n\nEsta acción no se puede deshacer.')) {
                e.preventDefault();
            }
        });
    }

})();
