document.addEventListener('DOMContentLoaded', function () {
  var dataEl = document.getElementById('tasks-data');

  // Acciones: cambiar estado / eliminar (reemplaza onclick inline)
  document.querySelectorAll('[data-action="task-change-status"]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      e.preventDefault();
      var status = (a.getAttribute('data-status') || '').toString();
      var label = (a.getAttribute('data-status-label') || '').toString();

      var statusValueEl = document.getElementById('confirm_status_value');
      var statusLabelEl = document.getElementById('confirm_status_label');
      if (statusValueEl) statusValueEl.value = status;
      if (statusLabelEl) statusLabelEl.textContent = label || status;

      var modalEl = document.getElementById('statusConfirmModal');
      if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
      }
    });
  });

  document.querySelectorAll('[data-action="task-delete"]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      e.preventDefault();
      var modalEl = document.getElementById('deleteModal');
      if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
      }
    });
  });

  // Auto-resize textarea
  ['edit_description', 'description'].forEach(function (id) {
    var desc = document.getElementById(id);
    if (!desc) return;
    desc.addEventListener('input', function () {
      desc.style.height = 'auto';
      desc.style.height = desc.scrollHeight + 'px';
    });
  });

  // Auto-ocultar alertas de éxito
  var successAlerts = document.querySelectorAll('.alert-success');
  successAlerts.forEach(function (alert) {
    setTimeout(function () {
      if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
        var bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
        bsAlert.close();
      }
    }, 4000);
  });

  // Depende de datos embebidos
  if (dataEl) {
    function b64decode(v) {
      try {
        return atob(v || '');
      } catch (e) {
        return '';
      }
    }

    var agentsByDept = {};
    var agentsB64 = (dataEl.getAttribute('data-agents-by-dept-b64') || '').toString();
    if (agentsB64) {
      try {
        agentsByDept = JSON.parse(b64decode(agentsB64) || '{}') || {};
      } catch (e) {
        agentsByDept = {};
      }
    }

    var variants = [
      {
        deptId: 'edit_dept_id',
        agentId: 'edit_assigned_to',
        noAgentsHintId: 'edit-no-agents-hint',
        selectDeptHintId: 'edit-select-dept-hint',
        currentAssigned: (dataEl.getAttribute('data-current-assigned') || '').toString(),
      },
      {
        deptId: 'dept_id',
        agentId: 'assigned_to',
        noAgentsHintId: 'no-agents-hint',
        selectDeptHintId: 'select-dept-hint',
        currentAssigned: '',
      },
    ];

    variants.forEach(function (v) {
      var deptSel = document.getElementById(v.deptId);
      var agentSel = document.getElementById(v.agentId);
      var hint = document.getElementById(v.noAgentsHintId);
      var selHint = document.getElementById(v.selectDeptHintId);
      if (!deptSel || !agentSel) return;

      var currentAssigned = (v.currentAssigned || '').toString();

      function setHint(show) {
        if (!hint) return;
        hint.style.display = show ? 'block' : 'none';
      }

      function fillAgents(deptId) {
        while (agentSel.options.length > 0) agentSel.remove(0);
        agentSel.add(new Option('Sin asignar', ''));

        var list = agentsByDept[String(deptId)] || [];
        if (!deptId) {
          agentSel.disabled = true;
          if (selHint) selHint.style.display = 'block';
          setHint(false);
          return;
        }
        if (selHint) selHint.style.display = 'none';
        if (!list.length) {
          agentSel.disabled = true;
          setHint(true);
          return;
        }

        list.forEach(function (a) {
          agentSel.add(new Option(a.name, String(a.id)));
        });
        agentSel.disabled = false;
        setHint(false);
        if (currentAssigned && String(currentAssigned) !== '0') {
          agentSel.value = String(currentAssigned);
        }
      }

      fillAgents(deptSel.value);
      deptSel.addEventListener('change', function () {
        currentAssigned = '';
        fillAgents(deptSel.value);
      });
    });
  }
});
