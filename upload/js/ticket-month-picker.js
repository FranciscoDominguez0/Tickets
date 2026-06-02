(function () {
    'use strict';

    function pad2(n) {
        return n < 10 ? '0' + n : String(n);
    }

    function initPicker(root) {
        if (!root || root.dataset.initialized === '1') {
            return;
        }
        root.dataset.initialized = '1';

        var form = root.querySelector('form');
        var trigger = root.querySelector('[data-picker-trigger]');
        var panel = root.querySelector('[data-picker-panel]');
        var hidden = root.querySelector('[data-picker-value]');
        var yearLabel = root.querySelector('[data-picker-year]');
        var prevBtn = root.querySelector('[data-picker-prev]');
        var nextBtn = root.querySelector('[data-picker-next]');
        var monthBtns = root.querySelectorAll('[data-picker-month]');
        var allBtn = root.querySelector('[data-picker-all]');
        var panelHome = panel ? panel.parentNode : null;

        if (!form || !trigger || !panel || !hidden) {
            return;
        }

        var years = [];
        var available = {};
        try {
            years = JSON.parse(root.getAttribute('data-years') || '[]');
        } catch (e) {
            years = [];
        }
        try {
            available = JSON.parse(root.getAttribute('data-available') || '{}');
        } catch (e2) {
            available = {};
        }
        if (!years.length) {
            years = [new Date().getFullYear()];
        }

        var currentYear = parseInt(root.getAttribute('data-initial-year') || String(years[years.length - 1]), 10);
        if (years.indexOf(currentYear) < 0) {
            currentYear = years[years.length - 1];
        }

        var selected = root.getAttribute('data-selected') || hidden.value || '';
        var outsideHandler = null;

        function monthKey(year, monthIndex) {
            return String(year) + '-' + pad2(monthIndex);
        }

        function isInsidePicker(target) {
            if (!target) {
                return false;
            }
            return root.contains(target) || panel.contains(target);
        }

        function syncYearUI() {
            if (yearLabel) {
                yearLabel.textContent = String(currentYear);
            }
            if (prevBtn) {
                prevBtn.disabled = years.indexOf(currentYear) <= 0;
            }
            if (nextBtn) {
                nextBtn.disabled = years.indexOf(currentYear) >= years.length - 1;
            }
            monthBtns.forEach(function (btn) {
                var idx = parseInt(btn.getAttribute('data-month-index') || '0', 10);
                if (!idx) {
                    return;
                }
                var key = monthKey(currentYear, idx);
                btn.setAttribute('data-month-key', key);
                btn.disabled = !available[key];
                btn.classList.toggle('is-selected', key === selected);
            });
        }

        function positionPanel() {
            var rect = trigger.getBoundingClientRect();
            var width = Math.min(320, window.innerWidth - 24);
            var left = rect.left;
            if (left + width > window.innerWidth - 12) {
                left = window.innerWidth - width - 12;
            }
            if (left < 12) {
                left = 12;
            }
            var top = rect.bottom + 8;
            var maxTop = window.innerHeight - 20;
            if (top + 280 > maxTop) {
                top = Math.max(12, rect.top - 8 - 280);
            }
            panel.style.position = 'fixed';
            panel.style.left = left + 'px';
            panel.style.top = top + 'px';
            panel.style.width = width + 'px';
            panel.style.right = 'auto';
            panel.style.zIndex = '1050';
        }

        function restorePanelHome() {
            if (panelHome && panel.parentNode !== panelHome) {
                panelHome.appendChild(panel);
            }
            panel.style.position = '';
            panel.style.left = '';
            panel.style.top = '';
            panel.style.width = '';
            panel.style.right = '';
            panel.style.zIndex = '';
        }

        function unbindOutsideClick() {
            if (!outsideHandler) {
                return;
            }
            document.removeEventListener('mousedown', outsideHandler, true);
            document.removeEventListener('touchstart', outsideHandler, true);
            outsideHandler = null;
        }

        function bindOutsideClick() {
            unbindOutsideClick();
            outsideHandler = function (e) {
                if (isInsidePicker(e.target)) {
                    return;
                }
                closePanel();
            };
            document.addEventListener('mousedown', outsideHandler, true);
            document.addEventListener('touchstart', outsideHandler, true);
        }

        function openPanel() {
            if (!panelHome) {
                panelHome = panel.parentNode;
            }
            document.body.appendChild(panel);
            positionPanel();
            panel.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            setTimeout(bindOutsideClick, 0);
        }

        function closePanel() {
            unbindOutsideClick();
            panel.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
            restorePanelHome();
        }

        function submitMonth(value) {
            hidden.value = value;
            selected = value;
            closePanel();
            form.submit();
        }

        function stopInside(e) {
            e.stopPropagation();
        }

        trigger.addEventListener('mousedown', stopInside);
        trigger.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (panel.classList.contains('is-open')) {
                closePanel();
            } else {
                openPanel();
            }
        });

        panel.addEventListener('mousedown', stopInside);
        panel.addEventListener('click', stopInside);

        if (prevBtn) {
            prevBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var idx = years.indexOf(currentYear);
                if (idx > 0) {
                    currentYear = years[idx - 1];
                    syncYearUI();
                }
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var idx = years.indexOf(currentYear);
                if (idx >= 0 && idx < years.length - 1) {
                    currentYear = years[idx + 1];
                    syncYearUI();
                }
            });
        }

        monthBtns.forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (btn.disabled) {
                    return;
                }
                submitMonth(btn.getAttribute('data-month-key') || '');
            });
        });

        if (allBtn) {
            allBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                submitMonth('');
            });
        }

        var clearCompact = root.querySelector('.ticket-month-picker__clear-compact');
        if (clearCompact) {
            clearCompact.addEventListener('mousedown', stopInside);
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && panel.classList.contains('is-open')) {
                closePanel();
            }
        });

        window.addEventListener('resize', function () {
            if (panel.classList.contains('is-open')) {
                positionPanel();
            }
        });

        syncYearUI();
    }

    function boot() {
        document.querySelectorAll('.ticket-month-picker').forEach(initPicker);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
