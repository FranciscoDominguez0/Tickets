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
                    var attHtml = '';
                    if (e.attachments && Array.isArray(e.attachments) && e.attachments.length > 0) {
                      var imagesHtml = '';
                      e.attachments.forEach(function(att) {
                        if (att.is_image && att.url) {
                          if (!isDesktop()) {
                            // On mobile, show a beautiful click-to-load placeholder
                            var sizeStr = '';
                            if (att.size) {
                              var sizeBytes = parseInt(att.size, 10) || 0;
                              if (sizeBytes > 1024 * 1024) {
                                sizeStr = (sizeBytes / (1024 * 1024)).toFixed(1) + ' MB';
                              } else if (sizeBytes > 1024) {
                                sizeStr = (sizeBytes / 1024).toFixed(0) + ' KB';
                              } else {
                                sizeStr = sizeBytes + ' B';
                              }
                            } else {
                              sizeStr = 'Tamaño desconocido';
                            }
                            
                            imagesHtml += '<div class="th-img-wrapper" style="margin-top:10px;">'
                              + '<div class="th-preview-img-placeholder" data-url="' + escapeHtml(att.url) + '" style="display:inline-flex; align-items:center; gap:12px; background:#f8fafc; border:1px solid #e2e8f0; padding:10px 14px; border-radius:12px; max-width:100%; box-sizing:border-box; width:100%;">'
                              + '<div style="width:40px; height:40px; border-radius:10px; background:#eff6ff; color:#2563eb; display:flex; align-items:center; justify-content:center; font-size:1.3rem; flex-shrink:0;"><i class="bi bi-image"></i></div>'
                              + '<div style="display:flex; flex-direction:column; min-width:0; flex-grow:1; line-height:1.3; text-align:left;">'
                              + '<span style="font-size:0.85rem; font-weight:700; color:#334155; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="' + escapeHtml(att.filename) + '">' + escapeHtml(att.filename) + '</span>'
                              + '<span style="font-size:0.75rem; color:#64748b; font-weight:600;">' + escapeHtml(sizeStr) + '</span>'
                              + '</div>'
                              + '<button type="button" class="btn btn-sm btn-primary btn-load-preview-img" style="border-radius:8px; padding:4px 12px; font-size:0.78rem; font-weight:700; display:inline-flex; align-items:center; gap:4px; flex-shrink:0; background:#2563eb; color:#fff; border:none; box-shadow:0 2px 4px rgba(37,99,235,0.15);"><i class="bi bi-eye-fill"></i> Ver</button>'
                              + '</div>'
                              + '</div>';
                          } else {
                            imagesHtml += '<img src="' + escapeHtml(att.url) + '" style="max-width:100%; max-height:160px; border-radius:8px; border:1px solid #e2e8f0; margin-right:8px; margin-top:10px; object-fit:contain; background:#f8fafc;" alt="adjunto">';
                          }
                        }
                      });
                      if (imagesHtml) {
                        attHtml = '<div class="th-attachments">' + imagesHtml + '</div>';
                      }
                    }

                    html += '<div class="th-item ' + cls + '">'
                      + '<div class="th-head">'
                      + '<span class="th-author">' + escapeHtml(author) + '</span>'
                      + (when ? '<span class="th-when">' + escapeHtml(formatWhen(when)) + '</span>' : '')
                      + '</div>'
                      + '<div class="th-body">' + escapeHtml(text) + attHtml + '</div>'
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
        try {
          var btn = e.target.closest('.btn-load-preview-img');
          if (btn) {
            e.preventDefault();
            e.stopPropagation();

            var wrapper = btn.closest('.th-img-wrapper');
            var placeholder = btn.closest('.th-preview-img-placeholder');
            if (wrapper && placeholder) {
              var url = placeholder.getAttribute('data-url');
              if (url) {
                // Change button to spinner
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true" style="width:0.8rem; height:0.8rem; margin-right:4px;"></span> Cargando...';

                // Create image
                var img = new Image();
                img.style.cssText = 'max-width:100%; max-height:220px; border-radius:12px; border:1px solid #e2e8f0; object-fit:contain; background:#f8fafc; display:block; opacity:0; transition:opacity 0.2s;';
                img.alt = 'adjunto';
                img.onload = function () {
                  wrapper.innerHTML = '';
                  wrapper.appendChild(img);
                  img.style.opacity = '1';
                };
                img.onerror = function () {
                  btn.disabled = false;
                  btn.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> Error';
                };
                img.src = url;
              }
            }
            return;
          }
        } catch (err) {}
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

      // ── Gesto móvil: deslizar hacia la izquierda sobre la fila abre la preview ──
      (function initMobileSwipePreview() {
        if (!table) return;

        var mobilePreviewActive = false;
        var swipeStartX = 0;
        var swipeStartY = 0;
        var swipeRow = null;

        function isMobile() {
          return window.matchMedia && window.matchMedia('(max-width: 991px)').matches;
        }

        // Aplica estilos de bottom-sheet al popup para móvil
        function openMobilePopup() {
          pop.style.setProperty('display', 'block', 'important'); // Forzar la visualización sobreescribiendo display:none !important del CSS móvil
          pop.style.position     = 'fixed';
          pop.style.top          = '16px'; // Sale desde arriba flotando
          pop.style.bottom       = 'auto';
          pop.style.left         = '4%'; // Centrado horizontal
          pop.style.right        = '4%';
          pop.style.width        = '92%'; // Menos ancho
          pop.style.maxWidth     = '460px'; // Compacto
          pop.style.height       = '50vh'; // Menos alto
          pop.style.maxHeight    = '50vh';
          pop.style.transform    = 'translateY(-20px)'; // Inicio de animación de caída
          pop.style.borderRadius = '16px';
          pop.style.zIndex       = '9999';
          pop.style.overflowY    = 'auto';
          pop.style.boxShadow    = '0 0 25px rgba(239, 68, 68, 0.5), 0 12px 40px rgba(0, 0, 0, 0.45)';
          pop.style.transition   = 'all 0.3s cubic-bezier(.4,0,.2,1)';
          
          // Fuerza el reflow
          pop.offsetHeight;
          pop.style.transform    = 'translateY(0)'; // Termina animación de caída
          
          // Ajustar bordes del contenedor interno para que calce perfecto como bottom-sheet
          var inner = pop.querySelector('.ticket-hover-preview-inner');
          if (inner) {
            inner.style.borderRadius = '16px';
            inner.style.border = '2px solid rgba(239, 68, 68, 0.85)'; // Borde semi-transparente que combina con el glow
            inner.style.boxShadow = 'none';
          }
          
          mobilePreviewActive    = true;
        }

        function resetPopupStyles() {
          pop.style.removeProperty('display'); // Dejar que el CSS vuelva a ocultarlo
          pop.style.position = pop.style.bottom = pop.style.left = '';
          pop.style.right = pop.style.top = pop.style.width = '';
          pop.style.maxWidth = pop.style.maxHeight = pop.style.borderRadius = '';
          pop.style.height = pop.style.transform = '';
          pop.style.zIndex = pop.style.overflowY = pop.style.boxShadow = '';
          pop.style.transition = '';
          
          var inner = pop.querySelector('.ticket-hover-preview-inner');
          if (inner) {
            inner.style.borderRadius = '';
            inner.style.border = '';
            inner.style.boxShadow = '';
          }
          
          mobilePreviewActive = false;
        }

        // Muestra un indicador visual de swipe en la fila
        function showSwipeHint(row) {
          var hint = row.querySelector('.swipe-preview-hint');
          if (!hint) {
            hint = document.createElement('div');
            hint.className = 'swipe-preview-hint';
            hint.innerHTML = '<i class="bi bi-eye"></i>';
            hint.style.cssText = [
              'position:absolute', 'right:0', 'top:0', 'bottom:0',
              'width:56px', 'display:flex', 'align-items:center', 'justify-content:center',
              'background:linear-gradient(to left,rgba(37,99,235,0.18),transparent)',
              'color:#2563eb', 'font-size:1.3rem', 'pointer-events:none',
              'opacity:0', 'transition:opacity 0.15s', 'border-radius:0 8px 8px 0'
            ].join(';');
            // La fila debe tener position:relative para que funcione el absolute
            if (getComputedStyle(row).position === 'static') {
              row.style.position = 'relative';
            }
            row.appendChild(hint);
          }
          hint.style.opacity = '1';
          return hint;
        }

        function hideSwipeHint(row) {
          var hint = row && row.querySelector('.swipe-preview-hint');
          if (hint) {
            hint.style.opacity = '0';
            setTimeout(function() {
              try { if (hint.parentNode) hint.parentNode.removeChild(hint); } catch(e) {}
            }, 200);
          }
        }

        // — touchstart: registrar punto de inicio —
        table.addEventListener('touchstart', function (ev) {
          try {
            if (!isMobile()) return;
            if (!ev.touches || ev.touches.length !== 1) return;

            var target = ev.target;
            if (!target || !target.closest) return;

            // No iniciar gesto si se toca el checkbox
            if (target.closest('.check-cell')) return;

            var row = target.closest('tr.ticket-row[data-ticket-id]');
            if (!row) return;

            swipeStartX = ev.touches[0].clientX;
            swipeStartY = ev.touches[0].clientY;
            swipeRow    = row;
            swipeLocked = false;
          } catch (err) {}
        }, { passive: true });

        // — touchmove: solo feedback visual, bloquea scroll vertical si el swipe es horizontal —
        table.addEventListener('touchmove', function (ev) {
          try {
            if (!isMobile() || !swipeRow) return;
            if (!ev.touches || ev.touches.length !== 1) return;

            var dx = swipeStartX - ev.touches[0].clientX; // positivo = swipe izquierda
            var dy = Math.abs(ev.touches[0].clientY - swipeStartY);

            if (dx > 10 && dx > dy) {
              // Si el usuario está deslizando horizontalmente, prevenimos el scroll vertical de la página
              if (ev.cancelable) {
                ev.preventDefault();
              }
              showSwipeHint(swipeRow);
            } else if (dx < 0) {
              // Swipe hacia la derecha → ocultar hint
              hideSwipeHint(swipeRow);
            }
          } catch (err) {}
        }, { passive: false }); // ¡Importante: false para poder hacer preventDefault!

        // — touchend: si el desliz fue suficiente, abrir preview —
        table.addEventListener('touchend', function (ev) {
          try {
            if (!isMobile() || !swipeRow) return;

            var endX = (ev.changedTouches && ev.changedTouches[0])
              ? ev.changedTouches[0].clientX : swipeStartX;
            var endY = (ev.changedTouches && ev.changedTouches[0])
              ? ev.changedTouches[0].clientY : swipeStartY;

            var dx = swipeStartX - endX;  // positivo = desliz izquierda
            var dy = Math.abs(endY - swipeStartY);

            var row = swipeRow;
            swipeRow = null;

            hideSwipeHint(row);

            // Umbral: al menos 50px horizontal y menos de 80px vertical
            if (dx < 50 || dy > 80) return;

            var tid = (row.getAttribute('data-ticket-id') || '').toString();
            if (!tid) return;

            // Feedback en la fila
            row.style.transition = 'background 0.2s';
            row.style.background = 'rgba(37,99,235,0.07)';
            setTimeout(function() { try { row.style.background = ''; } catch(e) {} }, 350);

            openMobilePopup();
            markSelected(tid);
            loadPreview(tid);

          } catch (err) {}
        }, { passive: true });

        // — Cerrar tocando fuera del popup —
        document.addEventListener('touchstart', function (ev) {
          try {
            if (!mobilePreviewActive || pop.classList.contains('d-none')) return;
            if (!ev.touches || ev.touches.length !== 1) return;
            var el = document.elementFromPoint(ev.touches[0].clientX, ev.touches[0].clientY);
            if (!el || pop.contains(el)) return;
            closePopup();
            resetPopupStyles();
          } catch (err) {}
        }, { passive: true });

        // — Cerrar con botón X (touch) —
        if (closeEl) {
          closeEl.addEventListener('touchend', function (e) {
            try {
              if (e && e.preventDefault) e.preventDefault();
              closePopup();
              resetPopupStyles();
            } catch (err) {}
          }, { passive: false });
        }
      })();

    })(); // end initTicketPreviewPane


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
      var deptSel = document.getElementById('ticketDeptSelect');
      var deptId = (deptSel && deptSel.value ? deptSel.value : '').toString().trim();

      var params = new URLSearchParams();
      if (filter !== '') params.set('filter', filter);
      if (q !== '') params.set('q', q);
      if (deptId !== '' && deptId !== '0') params.set('dept_id', deptId);
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
        btn.textContent = isStaffAgent ? 'Enviar Solicitudes' : 'Eliminar';
        
        if (isStaffAgent) {
          text += '<div class="mt-3"><label class="form-label fw-bold">Motivo de la solicitud de borrado:</label>'
                + '<textarea id="bulkDeleteReason" class="form-control" rows="3" placeholder="Describe por qué deseas borrar estos tickets..." required></textarea></div>';
          // Convertimos el texto a HTML para que renderice el textarea
          if (textEl) {
              textEl.innerHTML = text;
              text = ''; // Limpiamos para que no se sobreescriba abajo
          }
        }
      }

      if (text !== '' && textEl) textEl.textContent = text;
      
      var modalEl = document.getElementById('bulkConfirmModal');
      var btn = document.getElementById('bulkConfirmBtn');
      if (!modalEl || !btn) return;

      btn.onclick = function () {
        var reason = '';
        if (action === 'bulk_delete' && isStaffAgent) {
            var reasonEl = document.getElementById('bulkDeleteReason');
            reason = (reasonEl ? reasonEl.value : '').trim();
            if (!reason) {
                alert('Por favor, indica un motivo para el borrado.');
                return;
            }
        }

        var overlay = document.getElementById('bulkLoadingOverlay');
        var overlayText = document.getElementById('bulkLoadingText');
        if (overlay && overlayText) {
          if (action === 'bulk_assign') overlayText.textContent = 'Asignando tickets…';
          else if (action === 'bulk_status') overlayText.textContent = 'Cambiando estado…';
          else if (action === 'bulk_delete') overlayText.textContent = isStaffAgent ? 'Enviando solicitudes…' : 'Eliminando tickets…';
          else overlayText.textContent = 'Procesando…';
          overlay.classList.remove('d-none');
        }

        var doEl = document.getElementById('bulk_do');
        var confirmEl = document.getElementById('bulk_confirm');
        var form = document.getElementById('bulkForm');
        
        // Agregar motivo al form si es necesario
        if (reason) {
            var reasonInput = document.createElement('input');
            reasonInput.type = 'hidden';
            reasonInput.name = 'bulk_delete_reason';
            reasonInput.value = reason;
            form.appendChild(reasonInput);
        }

        if (doEl) doEl.value = (action === 'bulk_delete' && isStaffAgent) ? 'bulk_delete_request' : action;
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

    var deptSelect = document.getElementById('ticketDeptSelect');
    if (deptSelect) {
      deptSelect.addEventListener('change', function () {
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

  // El manejo de adjuntos se ha movido al módulo de vista de ticket para soportar el nuevo diseño premium y validaciones avanzadas.
  var btnReset = document.getElementById('btn-reset');
  if (btnReset) {
    btnReset.addEventListener('click', function() {
      if (typeof jQuery !== 'undefined' && jQuery('#reply_body').length && jQuery('#reply_body').summernote('code')) {
        jQuery('#reply_body').summernote('reset');
      }
      var input = document.getElementById('attachments');
      var list = document.getElementById('attach-list');
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

