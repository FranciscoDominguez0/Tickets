(function() {
  // Lista de usuarios: seleccionar todos / ninguno
  var selectAll = document.getElementById('selectAll');
  var rowCbs = document.querySelectorAll('.user-row-cb');
  if (selectAll) {
    selectAll.addEventListener('change', function() {
      rowCbs.forEach(function(cb) { cb.checked = selectAll.checked; });
    });
  }
  document.querySelectorAll('.select-links a[data-select="all"]').forEach(function(a) {
    a.addEventListener('click', function(e) {
      e.preventDefault();
      rowCbs.forEach(function(cb) { cb.checked = true; });
      if (selectAll) selectAll.checked = true;
    });
  });
  document.querySelectorAll('.select-links a[data-select="none"]').forEach(function(a) {
    a.addEventListener('click', function(e) {
      e.preventDefault();
      rowCbs.forEach(function(cb) { cb.checked = false; });
      if (selectAll) selectAll.checked = false;
    });
  });
})();

(function() {
  // Pestañas en la vista de usuario
  var tab = typeof USER_ACTIVE_TAB !== 'undefined' ? USER_ACTIVE_TAB : null;
  if (!tab) return;
  document.querySelectorAll('.user-view-tabs .tab').forEach(function(el) {
    var href = (el.getAttribute('href') || '').toString();
    if (href.indexOf('t=' + tab) !== -1) el.classList.add('active');
    else el.classList.remove('active');
  });
})();

