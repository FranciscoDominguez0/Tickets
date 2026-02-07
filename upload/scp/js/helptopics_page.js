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

    window.massAction = function (action, ids) {
      if (action === 'delete' && !confirm('¿Está seguro de eliminar este tema? Esta acción no se puede deshacer.')) {
        return false;
      }

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
  });
})();
