(function () {
  function onReady(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  function showEditModalWhenReady() {
    function tryShow() {
      if (window.bootstrap && typeof window.bootstrap.Modal === 'function') {
        var modalEl = document.getElementById('editTopicModal');
        if (modalEl) {
          var m = new bootstrap.Modal(modalEl);
          m.show();
          try {
            history.replaceState(null, '', window.location.pathname);
          } catch (e) {}
          return;
        }
      }
      if (!window._editModalRetries) window._editModalRetries = 0;
      if (window._editModalRetries++ < 50) {
        setTimeout(tryShow, 100);
      }
    }
    tryShow();
  }

  onReady(function () {
    // Auto-open edit modal when ?id=... (flag set by PHP)
    if (window.HELP_TOPICS_AUTO_OPEN_EDIT_MODAL) {
      showEditModalWhenReady();
    }

    var selectAll = document.getElementById('selectAll');
    var selectedCountEl = document.getElementById('selectedCount');

    function getTopicCheckboxes() {
      return Array.prototype.slice.call(document.querySelectorAll('.topic-checkbox'));
    }

    function updateSelectedCount() {
      if (!selectedCountEl) return;
      var count = document.querySelectorAll('.topic-checkbox:checked').length;
      selectedCountEl.textContent = String(count);
    }

    if (selectAll) {
      selectAll.addEventListener('change', function () {
        var checked = !!selectAll.checked;
        getTopicCheckboxes().forEach(function (cb) {
          cb.checked = checked;
        });
        updateSelectedCount();
      });
    }

    getTopicCheckboxes().forEach(function (cb) {
      cb.addEventListener('change', updateSelectedCount);
    });

    var deleteModalEl = document.getElementById('deleteTopicsModal');
    var deleteModalBodyEl = document.getElementById('deleteTopicsModalBody');
    var confirmDeleteBtn = document.getElementById('confirmDeleteTopicsBtn');
    var openDeleteSelectedBtn = document.getElementById('openDeleteTopicsModalBtn');

    var pendingDeleteIds = [];
    var pendingDeleteName = '';

    function getSelectedTopicIds() {
      return Array.prototype.slice
        .call(document.querySelectorAll('.topic-checkbox:checked'))
        .map(function (el) {
          return el.value;
        });
    }

    function showDeleteTopicsModal(ids, name) {
      pendingDeleteIds = ids || [];
      pendingDeleteName = name || '';

      if (deleteModalBodyEl) {
        if (!pendingDeleteIds.length) {
          deleteModalBodyEl.innerHTML =
            '<div class="alert alert-warning mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Debe seleccionar al menos un tema.</div>';
        } else if (pendingDeleteIds.length === 1 && pendingDeleteName) {
          deleteModalBodyEl.innerHTML =
            '<p class="mb-0">¿Está seguro de que desea eliminar el tema <strong>' +
            pendingDeleteName +
            '</strong>? Esta acción no se puede deshacer.</p>';
        } else {
          deleteModalBodyEl.innerHTML =
            '<p class="mb-0">¿Está seguro de que desea eliminar <strong>' +
            pendingDeleteIds.length +
            '</strong> tema(s) seleccionado(s)? Esta acción no se puede deshacer.</p>';
        }
      }

      if (confirmDeleteBtn) {
        confirmDeleteBtn.style.display = pendingDeleteIds.length ? '' : 'none';
      }

      if (!deleteModalEl || !window.bootstrap || !window.bootstrap.Modal) return;
      try {
        if (typeof window.bootstrap.Modal.getOrCreateInstance === 'function') {
          window.bootstrap.Modal.getOrCreateInstance(deleteModalEl).show();
        } else {
          new window.bootstrap.Modal(deleteModalEl).show();
        }
      } catch (e) {}
    }

    if (openDeleteSelectedBtn) {
      openDeleteSelectedBtn.addEventListener('click', function (e) {
        e.preventDefault();
        showDeleteTopicsModal(getSelectedTopicIds(), '');
      });
    }

    document.querySelectorAll('.js-delete-topic').forEach(function (el) {
      el.addEventListener('click', function (e) {
        e.preventDefault();
        var id = el.getAttribute('data-id');
        var name = el.getAttribute('data-name') || '';
        showDeleteTopicsModal(id ? [id] : [], name);
      });
    });

    window.massAction = function (action, ids) {
      var form = document.createElement('form');
      form.method = 'post';
      form.action = 'helptopics.php';

      function addHidden(name, value) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = String(value);
        form.appendChild(input);
      }

      addHidden('do', 'mass_process');
      addHidden('a', action);

      (ids || []).forEach(function (id) {
        addHidden('ids[]', id);
      });

      document.body.appendChild(form);
      form.submit();
    };

    if (confirmDeleteBtn) {
      confirmDeleteBtn.addEventListener('click', function () {
        if (!pendingDeleteIds.length) {
          showDeleteTopicsModal([], '');
          return;
        }
        window.massAction('delete', pendingDeleteIds);
      });
    }
  });
})();
