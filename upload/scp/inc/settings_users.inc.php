<?php

if (isset($_GET['action']) && $_GET['action'] === 'walkin_user_search') {
    header('Content-Type: application/json; charset=utf-8');
    $q = trim((string)($_GET['q'] ?? ''));
    $out = ['ok' => true, 'items' => []];
    if ($q !== '' && mb_strlen($q) >= 2 && isset($mysqli) && $mysqli) {
        $eid = empresaId();
        $term = '%' . $q . '%';
        $stmt = $mysqli->prepare(
            "SELECT id, firstname, lastname, email
             FROM users
             WHERE empresa_id = ? AND (firstname LIKE ? OR lastname LIKE ? OR email LIKE ?)
             ORDER BY firstname, lastname
             LIMIT 25"
        );
        if ($stmt) {
            $stmt->bind_param('isss', $eid, $term, $term, $term);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                while ($res && ($row = $res->fetch_assoc())) {
                    $id = (int)($row['id'] ?? 0);
                    if ($id <= 0) continue;
                    $name = trim((string)($row['firstname'] ?? '') . ' ' . (string)($row['lastname'] ?? ''));
                    $email = (string)($row['email'] ?? '');
                    $out['items'][] = ['id' => $id, 'name' => $name, 'email' => $email];
                }
            }
        }
    }
    echo json_encode($out);
    exit;
}

if (isset($_SESSION['flash_msg'])) {
    $msg = (string)$_SESSION['flash_msg'];
    unset($_SESSION['flash_msg']);
}
if (isset($_SESSION['flash_error'])) {
    $error = (string)$_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

if ($_POST) {
    if (!validateCSRF()) {
        $error = 'Token de seguridad inválido';
    } else {
        $user_max_login_attempts = (string)($_POST['user_max_login_attempts'] ?? '10');
        $user_lockout_minutes = (string)($_POST['user_lockout_minutes'] ?? '1');
        $user_session_timeout_minutes = (string)($_POST['user_session_timeout_minutes'] ?? '30');
        $registration_required = isset($_POST['registration_required']) ? '1' : '0';
        $walkin_default_user_id_raw = (string)($_POST['walkin_default_user_id'] ?? '0');
        $walkin_default_user_id = is_numeric($walkin_default_user_id_raw) ? (int)$walkin_default_user_id_raw : 0;

        if (!ctype_digit($user_max_login_attempts) || (int)$user_max_login_attempts < 1 || (int)$user_max_login_attempts > 50) {
            $error = 'Intentos fallidos permitidos debe estar entre 1 y 50.';
        } elseif (!ctype_digit($user_lockout_minutes) || (int)$user_lockout_minutes < 0 || (int)$user_lockout_minutes > 120) {
            $error = 'Minutos de bloqueo debe estar entre 0 y 120.';
        } elseif (!ctype_digit($user_session_timeout_minutes) || (int)$user_session_timeout_minutes < 0 || (int)$user_session_timeout_minutes > 1440) {
            $error = 'Tiempo de sesión debe estar entre 0 y 1440 minutos.';
        } else {
            setAppSetting('users.max_login_attempts', $user_max_login_attempts);
            setAppSetting('users.lockout_minutes', $user_lockout_minutes);
            setAppSetting('users.session_timeout_minutes', $user_session_timeout_minutes);

            setAppSetting('users.registration_required', $registration_required);
            setAppSetting('tickets.walkin_default_user_id', (string)($walkin_default_user_id > 0 ? $walkin_default_user_id : 0));

            $msg = 'Cambios guardados correctamente.';
        }
    }
}

$user_max_login_attempts = (string)getAppSetting('users.max_login_attempts', '10');
$user_lockout_minutes = (string)getAppSetting('users.lockout_minutes', '1');
$user_session_timeout_minutes = (string)getAppSetting('users.session_timeout_minutes', '30');
$registration_required = (string)getAppSetting('users.registration_required', '0') === '1';
$walkin_default_user_id = (int)getAppSetting('tickets.walkin_default_user_id', '0');

$walkin_selected_user = null;
if ($walkin_default_user_id > 0 && isset($mysqli) && $mysqli) {
    $eid = empresaId();
    $stmt = $mysqli->prepare('SELECT id, firstname, lastname, email FROM users WHERE empresa_id = ? AND id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('ii', $eid, $walkin_default_user_id);
        if ($stmt->execute()) {
            $walkin_selected_user = $stmt->get_result()->fetch_assoc();
        }
    }
}

ob_start();
?>

<div class="settings-hero">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-3">
            <span class="settings-hero-icon"><i class="bi bi-people"></i></span>
            <div>
                <h1>Usuarios</h1>
                <p>Ajustes de identificación y sesión de clientes</p>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo html($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!empty($msg)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo html($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<form method="post" class="row g-3">
    <?php csrfField(); ?>

    <div class="col-12">
        <div class="card settings-card">
            <div class="card-header"><strong>Configuración de Identificación</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="registration_required" name="registration_required" value="1" <?php echo $registration_required ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="registration_required">Registro requerido</label>
                        </div>
                        <div class="form-text">Se requiere registrarse para crear Tickets.</div>
                    </div>

                    <div class="col-12 col-lg-6">
                        <label class="form-label">Inicios de sesión de usuario excesivo</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="user_max_login_attempts" value="<?php echo html($user_max_login_attempts); ?>" min="1" max="50">
                            <span class="input-group-text">intento(s)</span>
                        </div>
                        <div class="form-text">Intentos fallidos permitidos antes de bloquear temporalmente.</div>
                    </div>

                    <div class="col-12 col-lg-6">
                        <label class="form-label">Minutos de bloqueo</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="user_lockout_minutes" value="<?php echo html($user_lockout_minutes); ?>" min="0" max="120">
                            <span class="input-group-text">min</span>
                        </div>
                    </div>

                    <div class="col-12 col-lg-6">
                        <label class="form-label">Tiempo de sesión de un usuario</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="user_session_timeout_minutes" value="<?php echo html($user_session_timeout_minutes); ?>" min="0" max="1440">
                            <span class="input-group-text">min</span>
                        </div>
                        <div class="form-text">(0 para desactivar)</div>
                    </div>

                    <div class="col-12 mt-4 pt-3 border-top" style="position: relative;">
                        <label class="form-label fw-bold">Usuario por defecto (cliente no recurrente)</label>
                        <input type="hidden" name="walkin_default_user_id" id="walkin_default_user_id" value="<?php echo (int)$walkin_default_user_id; ?>">
                        <div class="input-group">
                            <input type="text" class="form-control" id="walkin_user_search" placeholder="Buscar por nombre o email..." value="<?php
                                $nm = '';
                                $em = '';
                                if ($walkin_selected_user) {
                                    $nm = trim((string)($walkin_selected_user['firstname'] ?? '') . ' ' . (string)($walkin_selected_user['lastname'] ?? ''));
                                    $em = (string)($walkin_selected_user['email'] ?? '');
                                }
                                echo html(trim($nm . ($em !== '' ? ' — ' . $em : '')));
                            ?>">
                            <button type="button" class="btn btn-outline-secondary" id="walkin_user_clear">Quitar</button>
                        </div>
                        <div class="list-group mt-1 shadow-sm" id="walkin_user_results" style="display:none; position: absolute; z-index: 1050; width: calc(100% - 24px);"></div>
                        <div class="form-text">Se usará al abrir tickets desde el panel de agentes cuando el cliente sea no recurrente.</div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script>
      (function(){
        var input = document.getElementById('walkin_user_search');
        var hid = document.getElementById('walkin_default_user_id');
        var results = document.getElementById('walkin_user_results');
        var clearBtn = document.getElementById('walkin_user_clear');
        if (!input || !hid || !results) return;

        var lastReq = 0;
        var hideResults = function(){ results.style.display = 'none'; results.innerHTML = ''; };
        var showResults = function(){ results.style.display = 'block'; };

        var esc = function(s){
          return (s||'').toString().replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        };

        var pick = function(item){
          hid.value = (item && item.id) ? item.id : '0';
          var label = ((item && item.name) ? item.name : '').trim();
          if (item && item.email) label = (label ? (label + ' — ') : '') + item.email;
          input.value = label;
          hideResults();
        };

        if (clearBtn) {
          clearBtn.addEventListener('click', function(){
            hid.value = '0';
            input.value = '';
            hideResults();
            input.focus();
          });
        }

        input.addEventListener('input', function(){
          var q = (input.value || '').trim();
          if (q.length < 2) { hideResults(); return; }
          var myReq = ++lastReq;
          fetch('settings.php?t=users&action=walkin_user_search&q=' + encodeURIComponent(q), {credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(data){
              if (myReq !== lastReq) return;
              var items = (data && data.items) ? data.items : [];
              if (!items.length) { hideResults(); return; }
              results.innerHTML = '';
              items.forEach(function(it){
                var a = document.createElement('button');
                a.type = 'button';
                a.className = 'list-group-item list-group-item-action py-2';
                a.innerHTML = '<div class="fw-semibold">' + esc(it.name || '') + '</div>' + (it.email ? ('<div class="small text-muted">' + esc(it.email) + '</div>') : '');
                a.addEventListener('click', function(){ pick(it); });
                results.appendChild(a);
              });
              showResults();
            })
            .catch(function(){ hideResults(); });
        });

        document.addEventListener('click', function(e){
          if (!results.contains(e.target) && e.target !== input) hideResults();
        });
      })();
    </script>

    <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-primary">Guardar cambios</button>
        <a class="btn btn-outline-secondary" href="settings.php?t=users#settings">Restaurar</a>
    </div>
</form>

<?php
$content = ob_get_clean();
