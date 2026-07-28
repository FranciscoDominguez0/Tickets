window.addEventListener('load', function () {
    var all = document.getElementById('checkAll');
    if (all) {
        all.addEventListener('change', function () {
            document.querySelectorAll('.row-check').forEach(function (cb) {
                cb.checked = all.checked;
            });
        });
    }

    var openDeleteBtn = document.getElementById('openDeleteLogsModalBtn');
    var confirmDeleteBtn = document.getElementById('confirmDeleteLogsBtn');
    var deleteModalEl = document.getElementById('deleteLogsModal');
    var deleteModalBody = document.getElementById('deleteLogsModalBody');

    function getSelectedLogIds() {
        return Array.prototype.slice
            .call(document.querySelectorAll('.row-check:checked'))
            .map(function (el) {
                return el.value;
            });
    }

    function showDeleteModal(selectedCount) {
        if (!deleteModalEl) return;
        if (!window.bootstrap || !window.bootstrap.Modal) {
            return;
        }

        if (deleteModalBody) {
            if (selectedCount <= 0) {
                deleteModalBody.innerHTML = '<div class="alert alert-warning mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Debe seleccionar al menos un log.</div>';
            } else {
                deleteModalBody.innerHTML = '<p class="mb-0">¿Está seguro de que desea eliminar <strong>' + selectedCount + '</strong> log(s) seleccionado(s)? Esta acción no se puede deshacer.</p>';
            }
        }

        if (confirmDeleteBtn) {
            confirmDeleteBtn.style.display = selectedCount > 0 ? '' : 'none';
        }

        try {
            if (typeof window.bootstrap.Modal.getOrCreateInstance === 'function') {
                window.bootstrap.Modal.getOrCreateInstance(deleteModalEl).show();
            } else {
                new window.bootstrap.Modal(deleteModalEl).show();
            }
        } catch (e) {
            // Si por alguna razón falla el modal, no romper el resto del JS
        }
    }

    if (openDeleteBtn) {
        openDeleteBtn.addEventListener('click', function (e) {
            e.preventDefault();
            var selected = getSelectedLogIds();
            showDeleteModal(selected.length);
        });
    }

    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function () {
            var selected = getSelectedLogIds();
            if (!selected.length) {
                showDeleteModal(0);
                return;
            }
            var form = document.getElementById('massDeleteForm');
            if (form) form.submit();
        });
    }


});
