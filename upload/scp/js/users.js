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
  var tab = null;
  if (document.body && document.body.dataset && document.body.dataset.userActiveTab) {
    tab = document.body.dataset.userActiveTab;
  } else if (typeof USER_ACTIVE_TAB !== 'undefined') {
    tab = USER_ACTIVE_TAB;
  }
  if (!tab) return;
  document.querySelectorAll('.user-view-tabs .tab').forEach(function(el) {
    var href = (el.getAttribute('href') || '').toString();
    if (href.indexOf('t=' + tab) !== -1) el.classList.add('active');
    else el.classList.remove('active');
  });
})();

(function () {
  // Búsqueda de organizaciones (user view)
  var input = document.getElementById('orgSearch');
  var suggestions = document.getElementById('orgSuggestions');
  if (!input || !suggestions) return;

  var lastController = null;
  input.addEventListener('input', function () {
    var query = (input.value || '').toString().trim();
    if (query.length < 2) {
      suggestions.innerHTML = '';
      return;
    }

    if (lastController && typeof lastController.abort === 'function') {
      lastController.abort();
    }
    lastController = (typeof AbortController !== 'undefined') ? new AbortController() : null;

    var url = 'users.php?ajax=search_orgs&q=' + encodeURIComponent(query);
    fetch(url, lastController ? { signal: lastController.signal } : undefined)
      .then(function (response) { return response.json(); })
      .then(function (data) {
        suggestions.innerHTML = '';
        if (!Array.isArray(data)) return;
        data.forEach(function (org) {
          var item = document.createElement('a');
          item.href = '#';
          item.className = 'list-group-item list-group-item-action';
          item.textContent = (org && org.name ? org.name : '').toString();
          item.addEventListener('click', function (e) {
            e.preventDefault();
            input.value = item.textContent;
            suggestions.innerHTML = '';
          });
          suggestions.appendChild(item);
        });
      })
      .catch(function () {
        // Ignorar errores de abort / red
      });
  });
})();

