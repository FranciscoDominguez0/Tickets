// Inicialización del editor y adjuntos en la vista de ticket
document.addEventListener('DOMContentLoaded', function() {
  // Listado de tickets: búsqueda + acciones masivas (reemplaza <script> inline)
  (function () {
    var panel = document.querySelector('.tickets-panel[data-filter-key]');
    if (!panel) return;

    function showInfoModal(msg) {
      var textEl = document.getElementById('bulkInfoText');
      var modalEl = document.getElementById('bulkInfoModal');
      if (textEl) textEl.textContent = (msg || '').toString();
      if (!modalEl) return;
      if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
      }
    }

    function getFilterKey() {
      return (panel.getAttribute('data-filter-key') || '').toString();
    }

    function selectedTicketCount() {
      return document.querySelectorAll('.ticket-check:checked').length;
    }

    function toggleAllTickets(state) {
      var checks = document.querySelectorAll('.ticket-check');
      checks.forEach(function (c) {
        c.checked = !!state;
      });
      var all = document.getElementById('check_all');
      if (all) all.checked = !!state;
    }

    function applyTicketSearch() {
      var input = document.getElementById('ticketSearchInput');
      var q = (input && input.value ? input.value : '').toString().trim();
      var filter = getFilterKey();
      var params = new URLSearchParams();
      if (filter !== '') params.set('filter', filter);
      if (q !== '') params.set('q', q);
      window.location.href = 'tickets.php?' + params.toString();
    }

    function confirmBulk(action) {
      var count = selectedTicketCount();
      if (!count) {
        showInfoModal('Selecciona al menos un ticket.');
        return;
      }

      var text = '';
      if (action === 'bulk_assign') {
        var val = (document.getElementById('bulk_staff_id') && document.getElementById('bulk_staff_id').value ? document.getElementById('bulk_staff_id').value : '').toString();
        var label = (document.getElementById('bulk_staff_label') && document.getElementById('bulk_staff_label').value ? document.getElementById('bulk_staff_label').value : '').toString();
        if (val === '' || label === '') {
          showInfoModal('Selecciona un agente para asignar.');
          return;
        }
        text = '¿Asignar ' + count + ' ticket(s) a "' + label + '"?';
      } else if (action === 'bulk_status') {
        var val2 = (document.getElementById('bulk_status_id') && document.getElementById('bulk_status_id').value ? document.getElementById('bulk_status_id').value : '').toString();
        var label2 = (document.getElementById('bulk_status_label') && document.getElementById('bulk_status_label').value ? document.getElementById('bulk_status_label').value : '').toString();
        if (val2 === '' || label2 === '') {
          showInfoModal('Selecciona un estado.');
          return;
        }
        text = '¿Cambiar el estado de ' + count + ' ticket(s) a "' + label2 + '"?';
      } else if (action === 'bulk_delete') {
        text = '¿Eliminar ' + count + ' ticket(s)? Esta acción no se puede deshacer.';
      }

      var textEl = document.getElementById('bulkConfirmText');
      if (textEl) textEl.textContent = text;
      var modalEl = document.getElementById('bulkConfirmModal');
      var btn = document.getElementById('bulkConfirmBtn');
      if (!modalEl || !btn) return;

      btn.classList.remove('btn-danger');
      btn.classList.add('btn-primary');
      btn.textContent = 'Confirmar';
      if (action === 'bulk_delete') {
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-danger');
        btn.textContent = 'Eliminar';
      }

      btn.onclick = function () {
        var doEl = document.getElementById('bulk_do');
        var confirmEl = document.getElementById('bulk_confirm');
        var form = document.getElementById('bulkForm');
        if (doEl) doEl.value = action;
        if (confirmEl) confirmEl.value = action === 'bulk_delete' ? '1' : '0';
        if (form) form.submit();
      };

      if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
      }
    }

    // Buscar
    document.querySelectorAll('[data-action="tickets-search"]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        applyTicketSearch();
      });
    });

    var searchInput = document.getElementById('ticketSearchInput');
    if (searchInput) {
      searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          applyTicketSearch();
        }
      });
    }

    // Seleccionar todos / ninguno
    document.querySelectorAll('[data-action="tickets-select-all"]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        toggleAllTickets(true);
      });
    });
    document.querySelectorAll('[data-action="tickets-select-none"]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        toggleAllTickets(false);
      });
    });
    var checkAll = document.getElementById('check_all');
    if (checkAll) {
      checkAll.addEventListener('change', function () {
        toggleAllTickets(checkAll.checked);
      });
    }

    // Acciones masivas
    document.querySelectorAll('[data-action="tickets-bulk-delete"]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        confirmBulk('bulk_delete');
      });
    });

    document.querySelectorAll('[data-action="tickets-bulk-assign"]').forEach(function (a) {
      a.addEventListener('click', function (e) {
        e.preventDefault();
        var staffId = (a.getAttribute('data-staff-id') || '').toString();
        var label = (a.getAttribute('data-staff-label') || '').toString();
        var valEl = document.getElementById('bulk_staff_id');
        var labelEl = document.getElementById('bulk_staff_label');
        if (valEl) valEl.value = staffId;
        if (labelEl) labelEl.value = label;
        confirmBulk('bulk_assign');
      });
    });

    document.querySelectorAll('[data-action="tickets-bulk-status"]').forEach(function (a) {
      a.addEventListener('click', function (e) {
        e.preventDefault();
        var statusId = (a.getAttribute('data-status-id') || '').toString();
        var label = (a.getAttribute('data-status-label') || '').toString();
        var valEl = document.getElementById('bulk_status_id');
        var labelEl = document.getElementById('bulk_status_label');
        if (valEl) valEl.value = statusId;
        if (labelEl) labelEl.value = label;
        confirmBulk('bulk_status');
      });
    });
  })();

  // Acciones generales en vista de ticket (reemplaza onclick inline)
  document.querySelectorAll('[data-action="print"]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      window.print();
    });
  });

  document.querySelectorAll('[data-action="attachments-browse"]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      // Permitir que anchors no naveguen
      e.preventDefault();
      var input = document.getElementById('attachments');
      if (input) input.click();
    });
  });

  // Editor de respuesta del ticket
  if (typeof jQuery !== 'undefined' && jQuery().summernote && document.getElementById('reply_body')) {
    jQuery('#reply_body').summernote({
      height: 260,
      lang: 'es-ES',
      placeholder: 'Empezar escribiendo su respuesta aquí. Usa respuestas predefinidas del menú desplegable de arriba si lo desea.',
      toolbar: [
        ['style', ['style', 'paragraph']],
        ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
        ['fontname', ['fontname']],
        ['color', ['color']],
        ['fontsize', ['fontsize']],
        ['insert', ['link', 'picture', 'video', 'table', 'hr']],
        ['view', ['codeview', 'fullscreen']],
        ['para', ['ul', 'ol', 'paragraph']]
      ],
      fontNames: ['Arial', 'Arial Black', 'Comic Sans MS', 'Courier New', 'Helvetica', 'Impact', 'Tahoma', 'Times New Roman', 'Verdana'],
      fontSizes: ['8', '9', '10', '11', '12', '14', '16', '18', '24', '36']
    });
  }

  // Adjuntos en respuesta de ticket
  var zone = document.getElementById('attach-zone');
  var input = document.getElementById('attachments');
  var list = document.getElementById('attach-list');
  function updateList() {
    if (!list || !input) return;
    list.innerHTML = '';
    if (input.files && input.files.length) {
      for (var i = 0; i < input.files.length; i++) {
        list.innerHTML += '<span class=\"d-inline-block me-2 mb-1\"><i class=\"bi bi-paperclip\"></i> ' + input.files[i].name + '</span> ';
      }
    }
  }
  if (input && zone && list) {
    input.addEventListener('change', updateList);
    zone.addEventListener('dragover', function(e) { e.preventDefault(); zone.classList.add('dragover'); });
    zone.addEventListener('dragleave', function() { zone.classList.remove('dragover'); });
    zone.addEventListener('drop', function(e) {
      e.preventDefault();
      zone.classList.remove('dragover');
      if (e.dataTransfer.files.length) {
        input.files = e.dataTransfer.files;
        updateList();
      }
    });
  }
  var btnReset = document.getElementById('btn-reset');
  if (btnReset) {
    btnReset.addEventListener('click', function() {
      if (typeof jQuery !== 'undefined' && jQuery('#reply_body').length && jQuery('#reply_body').summernote('code')) {
        jQuery('#reply_body').summernote('reset');
      }
      if (input && list) {
        input.value = '';
        list.innerHTML = '';
      }
    });
  }

  // Abrir ticket: enfocar búsqueda de usuario al abrir modal
  var userModal = document.getElementById('modalUserSearch');
  if (userModal) {
    userModal.addEventListener('shown.bs.modal', function() {
      var q = document.getElementById('open_user_query');
      if (q) q.focus();
    });

    // Si venimos de una búsqueda (uq != ''), abrir el modal automáticamente
    if (userModal.dataset.openDefault === '1' && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
      var modalInstance = bootstrap.Modal.getOrCreateInstance(userModal);
      modalInstance.show();
    }
  }

  if (typeof jQuery !== 'undefined' && jQuery().summernote && document.getElementById('open_body')) {
    jQuery('#open_body').summernote({
      height: 200,
      lang: 'es-ES',
      placeholder: 'Respuesta inicial para el ticket',
      toolbar: [
        ['style', ['bold', 'italic', 'underline']],
        ['para', ['ul', 'ol']],
        ['insert', ['link']]
      ]
    });
  }
});

