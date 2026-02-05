// Toggle del submenú de Panel de control
document.addEventListener('DOMContentLoaded', function () {
    var toggles = document.querySelectorAll('.sidebar-toggle');
    toggles.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = btn.getAttribute('data-subnav');
            if (!targetId) return;
            var subnav = document.getElementById(targetId);
            if (!subnav) return;
            var isOpen = subnav.classList.toggle('open');
            btn.classList.toggle('expanded', isOpen);
        });
    });

    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function (tooltipTriggerEl) {
            new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
});
