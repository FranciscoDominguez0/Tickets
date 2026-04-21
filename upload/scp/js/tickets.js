// Inicialización del editor y adjuntos en la vista de ticket
document.addEventListener('DOMContentLoaded', function() {
  // Listado de tickets: búsqueda + acciones masivas (reemplaza <script> inline)
  (function () {
    var panel = document.querySelector('.tickets-panel[data-filter-key]') || document.querySelector('.tickets-panel') || document.body;

    // Vista previa estilo osTicket (desktop): popup al pasar el mouse sobre el número
    (function initTicketPreviewPane(){
      var table = document.getElementById('ticketsTable');
      var pop = document.getElementById('ticketHoverPreview');
      if (!table || !pop) return;

      function isDesktop() {
        try { return window.matchMedia && window.matchMedia('(min-width: 992px)').matches; } catch (e) { return true; }
      }

      var loadingEl = document.getElementById('ticketHoverLoading');
      var numEl = document.getElementById('ticketHoverNumber');
      var subjEl = document.getElementById('ticketHoverSubject');
      var metaEl = document.getElementById('ticketHoverMeta');
      var msgEl = document.getElementById('ticketHoverMsg');
      var openEl = document.getElementById('ticketHoverOpen');
      var closeEl = document.getElementById('ticketHoverClose');

      var inflight = null;
      var currentId = null;
      var hoverTimer = null;
      var hideTimer = null;

      function setLoading(loading) {
        if (loadingEl) loadingEl.classList.toggle('d-none', !loading);
      }

      function showPopup() {
        pop.classList.remove('d-none');
        pop.setAttribute('aria-hidden', 'false');
      }

      function hidePopup() {
        pop.classList.add('d-none');
        pop.setAttribute('aria-hidden', 'true');
      }

      function clearSelected() {
        table.querySelectorAll('tr.ticket-row.is-selected').forEach(function (tr) {
          tr.classList.remove('is-selected');
        });
      }

      function markSelected(ticketId) {
        clearSelected();
        var tr = table.querySelector('tr.ticket-row[data-ticket-id="' + String(ticketId).replace(/"/g, '') + '"]');
        if (tr) tr.classList.add('is-selected');
      }

      function delayHide() {
        if (hideTimer) clearTimeout(hideTimer);
        hideTimer = setTimeout(function() {
          closePopup();
        }, 400);
      }

      function formatWhen(whenStr) {
        try {
          if (!whenStr) return '';
          var d = new Date(whenStr.replace(' ', 'T'));
          if (isNaN(d.getTime())) return whenStr;
          return d.toLocaleString();
        } catch (e) {
          return whenStr || '';
        }
      }

      function loadPreview(ticketId) {
        ticketId = (ticketId || '').toString();
        if (!ticketId) return;

        currentId = ticketId;
        setLoading(true);
        showPopup();

        if (inflight && inflight.abort) {
          try { inflight.abort(); } catch (e) {}
        }

        inflight = new AbortController();
        var url = 'tickets.php?action=ticket_preview&id=' + encodeURIComponent(ticketId);

        fetch(url, { headers: { 'Accept': 'application/json' }, signal: inflight.signal })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            if (!data || !data.ok || !data.ticket) {
              throw new Error('bad');
            }
            if (currentId !== ticketId) return;
            var t = data.ticket;
            if (numEl) numEl.textContent = (t.ticket_number || ('#' + ticketId));
            if (subjEl) subjEl.textContent = (t.subject || '').toString();

            var parts = [];
            if (t.client) parts.push('Cliente: ' + t.client);
            if (t.author) parts.push('Último: ' + t.author);
            if (t.when) parts.push(formatWhen(t.when));
            if (t.is_internal) parts.push('Nota interna');
            if (metaEl) metaEl.textContent = parts.join(' · ');

            if (msgEl) {
              var entries = (t.entries && Array.isArray(t.entries)) ? t.entries : [];
              if (!entries.length) {
                msgEl.textContent = 'Sin mensajes para mostrar.';
              } else {
                var html = '';
                entries.forEach(function (e) {
                  try {
                    var author = (e.author || '—').toString();
                    var when = (e.when || '').toString();
                    var text = (e.text || '').toString();
                    var isInternal = !!e.is_internal;
                    var isStaff = !!e.is_staff;
                    var cls = 'item';
                    if (isInternal) cls += ' internal';
                    else if (isStaff) cls += ' staff';
                    else cls += ' user';
                    html += '<div class="th-item ' + cls + '">'
                      + '<div class="th-head">'
                      + '<span class="th-author">' + escapeHtml(author) + '</span>'
                      + (when ? '<span class="th-when">' + escapeHtml(formatWhen(when)) + '</span>' : '')
                      + '</div>'
                      + '<div class="th-body">' + escapeHtml(text) + '</div>'
                      + '</div>';
                  } catch (err) {}
                });
                msgEl.innerHTML = html;
              }
            }
            if (openEl) openEl.setAttribute('href', 'tickets.php?id=' + encodeURIComponent(ticketId));
          })
          .catch(function (err) {
            if (err && err.name === 'AbortError') return;
            if (currentId !== ticketId) return;
            if (msgEl) msgEl.textContent = 'No se pudo cargar la vista previa.';
            if (metaEl) metaEl.textContent = '';
          })
          .finally(function () {
            if (currentId !== ticketId) return;
            setLoading(false);
          });
      }

      function escapeHtml(str) {
        try {
          return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
        } catch (e) {
          return '';
        }
      }

      hidePopup();
      setLoading(false);

      function schedule(ticketId, anchorEl) {
        if (!isDesktop()) return;
        if (!ticketId) return;
        if (hoverTimer) {
          try { clearTimeout(hoverTimer); } catch (e) {}
        }
        hoverTimer = setTimeout(function () {
          if (!isDesktop()) return;
          if (hideTimer) clearTimeout(hideTimer);
          markSelected(ticketId);
          loadPreview(ticketId);
        }, 350);
      }

      function cancelSchedule() {
        if (hoverTimer) {
          try { clearTimeout(hoverTimer); } catch (e) {}
        }
        hoverTimer = null;
      }

      // Hover en el número (delegación de eventos para asegurar que siempre dispare)
      table.addEventListener('mouseover', function (ev) {
        if (!isDesktop()) return;
        var target = ev && ev.target ? ev.target : null;
        if (!target || !target.closest) return;
        var a = target.closest('.ticket-preview-trigger[data-ticket-id]');
        if (!a) return;
        var tid = (a.getAttribute('data-ticket-id') || '').toString();
        if (!tid) return;

        if (hideTimer) clearTimeout(hideTimer);
        if (currentId === tid && !pop.classList.contains('d-none')) {
          cancelSchedule();
          return;
        }

        schedule(tid, a);
      });

      table.addEventListener('mouseout', function (ev) {
        var target = ev && ev.target ? ev.target : null;
        if (!target || !target.closest) return;
        var a = target.closest('.ticket-preview-trigger[data-ticket-id]');
        if (!a) return;
        cancelSchedule();
        delayHide();
      });

      table.addEventListener('focusin', function (ev) {
        if (!isDesktop()) return;
        var target = ev && ev.target ? ev.target : null;
        if (!target || !target.closest) return;
        var a = target.closest('.ticket-preview-trigger[data-ticket-id]');
        if (!a) return;
        var tid = (a.getAttribute('data-ticket-id') || '').toString();
        if (!tid) return;
        if (hideTimer) clearTimeout(hideTimer);
        schedule(tid, a);
      });

      table.addEventListener('focusout', function (ev) {
        var target = ev && ev.target ? ev.target : null;
        if (!target || !target.closest) return;
        var a = target.closest('.ticket-preview-trigger[data-ticket-id]');
        if (!a) return;
        cancelSchedule();
        delayHide();
      });

      // Mantener abierto si el mouse está dentro del popup
      pop.addEventListener('mouseenter', function () {
        cancelSchedule();
        if (hideTimer) clearTimeout(hideTimer);
      });
      pop.addEventListener('mouseleave', function () {
        delayHide();
      });

      function closePopup() {
        cancelSchedule();
        if (hideTimer) clearTimeout(hideTimer);
        hidePopup();
        currentId = null;
        clearSelected();
      }

      if (closeEl) {
        closeEl.addEventListener('click', function (e) {
          try { if (e && e.preventDefault) e.preventDefault(); } catch (err) {}
          try { if (e && e.stopPropagation) e.stopPropagation(); } catch (err2) {}
          closePopup();
        });
      }

      // Permitir interacción/scroll dentro del popup sin cerrarlo
      pop.addEventListener('mousedown', function (e) {
        try { if (e && e.stopPropagation) e.stopPropagation(); } catch (err) {}
      });
      pop.addEventListener('click', function (e) {
        try { if (e && e.stopPropagation) e.stopPropagation(); } catch (err) {}
      });

      // Cerrar solo si se hace click fuera del popup
      document.addEventListener('mousedown', function (e) {
        try {
          if (pop.classList.contains('d-none')) return;
          var t = e && e.target ? e.target : null;
          if (!t) return;

          // Si el click fue dentro del popup, no cerrar
          if (pop.contains && pop.contains(t)) return;

          // Si el click fue en un trigger del ticket, no cerrar (el hover/carga lo manejará)
          if (t.closest && t.closest('.ticket-preview-trigger[data-ticket-id]')) return;

          closePopup();
        } catch (err) {}
      }, true);
    })();

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

    function getSelectedDeptInfo() {
      try {
        var checks = Array.prototype.slice.call(document.querySelectorAll('input.ticket-check:checked'));
        var deptIds = {};
        checks.forEach(function (c) {
          var did = parseInt(c.getAttribute('data-ticket-dept-id') || '0', 10) || 0;
          if (did > 0) deptIds[String(did)] = true;
        });
        var keys = Object.keys(deptIds);
        return {
          deptIds: keys.map(function (k) { return parseInt(k, 10) || 0; }).filter(function (v) { return v > 0; }),
          singleDeptId: (keys.length === 1 ? (parseInt(keys[0], 10) || 0) : 0),
          mixed: keys.length > 1
        };
      } catch (e) {
        return { deptIds: [], singleDeptId: 0, mixed: false };
      }
    }

    function showBulkWarn(msg) {
      try {
        var el = document.getElementById('bulkClientAlert');
        if (!el) return;
        if (el._autoHideTimer) {
          clearTimeout(el._autoHideTimer);
          el._autoHideTimer = null;
        }
        el.textContent = msg;
        el.classList.remove('d-none');
        el._autoHideTimer = setTimeout(function () {
          try { hideBulkWarn(); } catch (e) {}
        }, 5000);
      } catch (e) {}
    }

    function hideBulkWarn() {
      try {
        var el = document.getElementById('bulkClientAlert');
        if (!el) return;
        if (el._autoHideTimer) {
          clearTimeout(el._autoHideTimer);
          el._autoHideTimer = null;
        }
        el.classList.add('d-none');
        el.textContent = '';
      } catch (e) {}
    }

    function filterBulkAssignMenu() {
      try {
        var menu = document.getElementById('bulkAssignMenu');
        if (!menu) return;
        var emptyItem = document.getElementById('bulkAssignEmptyItem');
        var unassignItem = document.getElementById('bulkAssignUnassignItem');
        var dividerItem = document.getElementById('bulkAssignDivider');
        var panelEl = document.querySelector('.tickets-panel[data-general-dept-id]');
        var generalDeptId = panelEl ? (parseInt(panelEl.getAttribute('data-general-dept-id') || '0', 10) || 0) : 0;

        var info = getSelectedDeptInfo();
        if (!selectedTicketCount()) {
          hideBulkWarn();
          if (emptyItem) emptyItem.classList.remove('d-none');
          if (unassignItem) unassignItem.classList.add('d-none');
          if (dividerItem) dividerItem.classList.add('d-none');
          menu.querySelectorAll('.bulk-assign-staff-item').forEach(function (a) { a.classList.add('d-none'); });
          return;
        }

        if (emptyItem) emptyItem.classList.add('d-none');

        // If we can't detect dept_id, do not allow assignment
        if (!info.deptIds || !info.deptIds.length) {
          showBulkWarn('No es posible asignar un agente: no se pudo detectar el departamento del ticket seleccionado.');
          if (unassignItem) unassignItem.classList.add('d-none');
          if (dividerItem) dividerItem.classList.add('d-none');
          menu.querySelectorAll('.bulk-assign-staff-item').forEach(function (a) { a.classList.add('d-none'); });
          return;
        }

        if (info.mixed) {
          showBulkWarn('No es posible asignar un agente a tickets de distintos departamentos.');
          if (unassignItem) unassignItem.classList.add('d-none');
          if (dividerItem) dividerItem.classList.add('d-none');
          menu.querySelectorAll('.bulk-assign-staff-item').forEach(function (a) { a.classList.add('d-none'); });
          return;
        }

        hideBulkWarn();
        if (unassignItem) unassignItem.classList.remove('d-none');
        if (dividerItem) dividerItem.classList.remove('d-none');
        var deptId = info.singleDeptId;
        menu.querySelectorAll('.bulk-assign-staff-item').forEach(function (a) {
          var staffDept = parseInt(a.getAttribute('data-staff-dept-id') || '0', 10) || 0;
          var ok = false;
          if (deptId > 0) {
            ok = (staffDept === deptId);
          } else {
            ok = false;
          }
          if (!ok && generalDeptId > 0 && staffDept === generalDeptId) ok = true;
          a.classList.toggle('d-none', !ok);
        });
      } catch (e) {}
    }

    function toggleAllTickets(state) {
      document.querySelectorAll('.ticket-check').forEach(function (c) {
        c.checked = !!state;
      });
      var all = document.getElementById('check_all');
      if (all) all.checked = !!state;
      updateSelectionActionBar();
    }

    function updateSelectionActionBar() {
      var bar = document.getElementById('selectionActionBar');
      var badge = document.getElementById('selectedCountBadge');
      var text = document.getElementById('selectedCountText');
      if (!bar) return;

      var count = selectedTicketCount();
      if (count > 0) {
        if (badge) badge.textContent = count;
        if (text) text.textContent = count === 1 ? 'ticket seleccionado' : 'tickets seleccionados';
        bar.classList.add('show');
      } else {
        bar.classList.remove('show');
      }
    }

    function applyTicketSearch() {
      var input = document.getElementById('ticketSearchInput');
      var q = (input && input.value ? input.value : '').toString().trim();
      var filter = getFilterKey();
      var topicSel = document.getElementById('ticketTopicSelect');
      var topicId = (topicSel && topicSel.value ? topicSel.value : '').toString().trim();
      var params = new URLSearchParams();
      if (filter !== '') params.set('filter', filter);
      if (q !== '') params.set('q', q);
      if (topicId !== '' && topicId !== '0') params.set('topic_id', topicId);
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
        var overlay = document.getElementById('bulkLoadingOverlay');
        var overlayText = document.getElementById('bulkLoadingText');
        if (overlay && overlayText) {
          if (action === 'bulk_assign') overlayText.textContent = 'Asignando tickets…';
          else if (action === 'bulk_status') overlayText.textContent = 'Cambiando estado…';
          else if (action === 'bulk_delete') overlayText.textContent = 'Eliminando tickets…';
          else overlayText.textContent = 'Procesando…';
          overlay.classList.remove('d-none');
        }
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

    var topicSelect = document.getElementById('ticketTopicSelect');
    if (topicSelect) {
      topicSelect.addEventListener('change', function () {
        applyTicketSearch();
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
        filterBulkAssignMenu();
      });
    }

    document.querySelectorAll('input.ticket-check').forEach(function (c) {
      c.addEventListener('change', function () {
        filterBulkAssignMenu();
        updateSelectionActionBar();
      });
    });

    // Inicializar estado de la barra en carga
    updateSelectionActionBar();

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
        filterBulkAssignMenu();
        if (!selectedTicketCount()) {
          showInfoModal('Debes seleccionar un ticket.');
          return;
        }
        var info = getSelectedDeptInfo();
        if (!info.deptIds || !info.deptIds.length) {
          showInfoModal('No es posible asignar un agente: no se pudo detectar el departamento del ticket seleccionado.');
          return;
        }
        if (info.mixed) {
          showInfoModal('No es posible asignar un agente a tickets de distintos departamentos.');
          return;
        }
        var staffId = (a.getAttribute('data-staff-id') || '').toString();
        var label = (a.getAttribute('data-staff-label') || '').toString();
        var valEl = document.getElementById('bulk_staff_id');
        var labelEl = document.getElementById('bulk_staff_label');
        if (valEl) valEl.value = staffId;
        if (labelEl) labelEl.value = label;
        confirmBulk('bulk_assign');
      });
    });

    filterBulkAssignMenu();

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
      try {
        var params = new URLSearchParams(window.location.search || '');
        var tid = (params.get('id') || '').toString();
        if (!tid) return;
        window.open('print_ticket.php?id=' + encodeURIComponent(tid), '_blank');
      } catch (err) {}
    });
  });

  document.querySelectorAll('[data-action="attachments-browse"]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      // Permitir que anchors no naveguen
      if (el && el.tagName && el.tagName.toLowerCase() === 'a') {
        e.preventDefault();
      }

      var zone = null;
      try {
        zone = (el && el.closest) ? el.closest('.attach-zone') : null;
      } catch (err) {}

      var input = null;
      if (zone) {
        input = zone.querySelector('input[type="file"]');
      }
      if (!input) {
        input = document.getElementById('attachments');
      }

      try {
        if (input && typeof input.showPicker === 'function') {
          input.showPicker();
        } else if (input) {
          input.click();
        }
      } catch (err2) {
        try { if (input) input.click(); } catch (err3) {}
      }
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

