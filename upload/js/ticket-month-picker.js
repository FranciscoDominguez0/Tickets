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

        function monthKey(year, monthIndex) {
            return String(year) + '-' + pad2(monthIndex);
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
            panel.style.position = 'fixed';
            panel.style.left = left + 'px';
            panel.style.top = top + 'px';
            panel.style.width = width + 'px';
            panel.style.right = 'auto';
        }

        function resetPanelPosition() {
            panel.style.position = '';
            panel.style.left = '';
            panel.style.top = '';
            panel.style.width = '';
            panel.style.right = '';
        }

        function openPanel() {
            positionPanel();
            panel.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
        }

        function closePanel() {
            panel.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
            resetPanelPosition();
        }

        function submitMonth(value) {
            hidden.value = value;
            selected = value;
            closePanel();
            form.submit();
        }

        trigger.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (panel.classList.contains('is-open')) {
                closePanel();
            } else {
                openPanel();
            }
        });

        if (prevBtn) {
            prevBtn.addEventListener('click', function (e) {
                e.preventDefault();
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
                if (btn.disabled) {
                    return;
                }
                submitMonth(btn.getAttribute('data-month-key') || '');
            });
        });

        if (allBtn) {
            allBtn.addEventListener('click', function (e) {
                e.preventDefault();
                submitMonth('');
            });
        }

        document.addEventListener('click', function (e) {
            if (!root.contains(e.target)) {
                closePanel();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
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
