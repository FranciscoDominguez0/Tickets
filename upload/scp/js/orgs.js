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
        initOrgDetailTabs();
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
     * Tabs en detalle de organización (Usuarios / Tickets)
     * Fallback para instalaciones donde el markup no activa correctamente los tabs de Bootstrap.
     */
    function initOrgDetailTabs() {
        const tabsWrap = document.querySelector('.org-detail-container .user-view-tabs');
        if (!tabsWrap) return;

        const links = tabsWrap.querySelectorAll('a.tab');
        const panesWrap = document.querySelector('.org-detail-container .user-view-card .tab-content');
        if (!links.length || !panesWrap) return;

        function stabilizeScroll(beforeTop, cb) {
            try {
                cb();
                window.requestAnimationFrame(function () {
                    try {
                        if (typeof beforeTop === 'number' && isFinite(beforeTop)) {
                            window.scrollTo(0, beforeTop);
                        }
                    } catch (e2) {}
                });
            } catch (e) {
                try { cb(); } catch (e3) {}
            }
        }

        function getTabsTop() {
            try {
                const r = tabsWrap.getBoundingClientRect();
                return Math.max(0, Math.round((window.scrollY || window.pageYOffset || 0) + r.top));
            } catch (e) {
                return (window.scrollY || window.pageYOffset || 0);
            }
        }

        function activate(tabKey, preventScroll) {
            const targetSel = tabKey === 'tickets' ? '#org-tickets' : '#org-users';

            // Comportamiento de estabilización de scroll (solo al hacer click en tabs, no al cargar)
            const beforeTop = preventScroll ? null : getTabsTop();
            
            function doToggle() {
                links.forEach(function (a) {
                    try {
                        const href = (a.getAttribute('href') || '').toString();
                        const u = new URL(href, window.location.href);
                        const t = (u.searchParams.get('t') || 'users').toString();
                        a.classList.toggle('active', t === tabKey);
                    } catch (e) {
                        a.classList.toggle('active', tabKey === 'users');
                    }
                });

                panesWrap.querySelectorAll('.tab-pane').forEach(function (p) {
                    const isTarget = ('#' + p.id) === targetSel;
                    p.classList.toggle('show', isTarget);
                    p.classList.toggle('active', isTarget);
                });
            }

            if (preventScroll) {
                doToggle();
            } else {
                stabilizeScroll(beforeTop, doToggle);
            }
        }

        function getTabFromUrl(href) {
            try {
                const u = new URL(href, window.location.href);
                const t = (u.searchParams.get('t') || 'users').toString();
                return (t === 'tickets') ? 'tickets' : 'users';
            } catch (e) {
                return 'users';
            }
        }

        // Activar según URL actual (AL CARGAR LA PÁGINA) -> Pasamos true para evitar que baje el scroll
        activate(getTabFromUrl(window.location.href), true);

        links.forEach(function (a) {
            a.addEventListener('click', function (e) {
                const href = (a.getAttribute('href') || '').toString();
                if (!href) return;
                const tabKey = getTabFromUrl(href);
                e.preventDefault();
                activate(tabKey, false); // Permitir estabilizar scroll al clickear
                try {
                    const u = new URL(href, window.location.href);
                    u.hash = '';
                    window.history.pushState({ t: tabKey }, '', u.toString());
                } catch (e2) {}
            });
        });

        window.addEventListener('popstate', function () {
            activate(getTabFromUrl(window.location.href));
        });
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
        // La confirmación se realiza con el modal Bootstrap.
        // No agregar confirm() aquí para evitar doble confirmación.
    }

})();
