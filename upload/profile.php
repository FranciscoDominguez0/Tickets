<?php
/**
 * PERFIL USUARIO
 * Editar información del usuario
 */

require_once '../config.php';
require_once '../includes/helpers.php';

// Validar que sea cliente
requireLogin('cliente');

$user = getCurrentUser();
$error = '';
$success = '';

$eid = (int)($_SESSION['empresa_id'] ?? 0);
if ($eid <= 0) $eid = 1;

// Obtener datos completos del usuario
$stmt = $mysqli->prepare('SELECT * FROM users WHERE id = ? AND empresa_id = ?');
$uid = (int)($_SESSION['user_id'] ?? 0);
$stmt->bind_param('ii', $uid, $eid);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();

if (!$userData) {
    http_response_code(404);
    exit;
}

if ($_POST) {
    if (!validateCSRF()) {
        $error = 'Token de seguridad inválido';
    } else {
        $firstname = trim($_POST['firstname'] ?? '');
        $lastname = trim($_POST['lastname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $company = trim($_POST['company'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if (empty($firstname) || empty($lastname) || empty($email)) {
            $error = 'Nombre, apellido y email son requeridos';
        } else {
            $stmt = $mysqli->prepare('UPDATE users SET firstname = ?, lastname = ?, email = ?, company = ?, phone = ?, address = ?, updated = NOW() WHERE id = ? AND empresa_id = ?');
            $stmt->bind_param('ssssssii', $firstname, $lastname, $email, $company, $phone, $address, $uid, $eid);
            
            if ($stmt->execute()) {
                $_SESSION['user_name'] = $firstname . ' ' . $lastname;
                $_SESSION['user_email'] = $email;
                $success = 'Perfil actualizado exitosamente';
                $userData['firstname'] = $firstname;
                $userData['lastname'] = $lastname;
                $userData['email'] = $email;
                $userData['company'] = $company;
                $userData['phone'] = $phone;
                $userData['address'] = $address;
            } else {
                error_log('[profile] update failed: ' . (string)$mysqli->error);
                $error = 'Error al actualizar. Intenta nuevamente.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - <?php echo APP_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo html(rtrim(defined('APP_URL') ? APP_URL : '', '/')); ?>/publico/img/favicon.ico">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background: #f6f7fb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 62px;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            pointer-events: none;
            background:
                radial-gradient(700px circle at 12% 0%, rgba(245, 158, 11, 0.08), transparent 52%),
                radial-gradient(900px circle at 88% 10%, rgba(99, 102, 241, 0.10), transparent 55%),
                repeating-linear-gradient(135deg, rgba(15, 23, 42, 0.02) 0px, rgba(15, 23, 42, 0.02) 1px, transparent 1px, transparent 14px);
            z-index: -1;
        }

        .container-main {
            max-width: 1100px;
            margin: 18px auto;
            padding: 0 18px;
        }

        .topbar {
            background: linear-gradient(135deg, #0b1220, #111827);
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        }
        .topbar.navbar {
            padding-top: 0 !important;
            padding-bottom: 0 !important;
        }
        .topbar .container-fluid {
            padding-top: 2px;
            padding-bottom: 2px;
        }
        .topbar .navbar-brand { font-weight: 900; letter-spacing: 0.02em; }
        .topbar .profile-brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: #fff;
            text-decoration: none;
        }
        .topbar .profile-brand .brand-logo-wrap {
            height: 46px;
            padding: 0;
            border-radius: 0;
            background: transparent;
            border: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: none;
        }
        .topbar .profile-brand .brand-logo {
            height: 30px;
            width: auto;
            max-width: 320px;
            object-fit: contain;
            display: block;
        }
        @media (max-width: 420px) {
            .topbar .profile-brand .brand-logo { max-width: 200px; }
        }
        .topbar .user-menu-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            border-radius: 999px;
            font-weight: 800;
        }
        .topbar .user-menu-btn .uavatar {
            width: 30px;
            height: 30px;
            border-radius: 12px;
            background: rgba(255,255,255,0.92);
            color: #0b1220;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 1000;
            letter-spacing: 0.08em;
            flex: 0 0 auto;
        }
        .topbar .profile-brand .avatar {
            width: 36px;
            height: 36px;
            border-radius: 14px;
            background: rgba(255,255,255,0.92);
            color: #0b1220;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 1000;
            letter-spacing: 0.08em;
            flex: 0 0 auto;
        }
        .topbar .profile-brand .name {
            font-weight: 900;
            font-size: 0.98rem;
            line-height: 1.1;
        }
        .topbar .btn { border-radius: 999px; font-weight: 700; }

        .profile-shell {
            max-width: 980px;
            margin: 0 auto;
        }

        .panel {
            background: #ffffff;
            border-radius: 22px;
            border: 1px solid rgba(226, 232, 240, 0.9);
            box-shadow: 0 22px 60px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .panel {
            background-image:
                radial-gradient(900px circle at 0% 0%, rgba(37, 99, 235, 0.05), transparent 52%),
                radial-gradient(700px circle at 100% 0%, rgba(245, 158, 11, 0.05), transparent 55%);
            transition: box-shadow .15s ease, border-color .15s ease;
        }
        .panel:hover {
            box-shadow: 0 26px 70px rgba(15, 23, 42, 0.10);
            border-color: rgba(203, 213, 225, 0.95);
        }

        .sidebar-sub { color: #64748b; font-weight: 700; font-size: 0.9rem; }

        .profile-content {
            padding: 18px;
        }

        .profile-hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            padding: 16px;
            background: rgba(255,255,255,0.78);
            border: 1px solid rgba(226,232,240,0.95);
            border-radius: 18px;
            box-shadow: 0 14px 40px rgba(15, 23, 42, 0.08);
            margin-bottom: 14px;
        }
        .profile-hero-left {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 240px;
        }
        .profile-hero-avatar {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            background: rgba(59,130,246,0.12);
            border: 1px solid rgba(59,130,246,0.22);
            color: #0b1220;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 1000;
            letter-spacing: 0.08em;
            flex: 0 0 auto;
        }
        .profile-hero-name {
            font-weight: 1000;
            color: #0f172a;
            font-size: 1.15rem;
            line-height: 1.15;
            margin: 0;
        }
        .profile-hero-email {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #64748b;
            font-weight: 800;
            font-size: 0.92rem;
            margin-top: 2px;
        }
        .profile-hero-email i { opacity: .9; }
        .profile-hero-right {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        .profile-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(59,130,246,0.10);
            border: 1px solid rgba(59,130,246,0.18);
            color: #1e293b;
            font-weight: 900;
            white-space: nowrap;
        }
        .profile-badge i { color: #2563eb; }

        .content-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 18px;
        }
        .section-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }
        .section-title h5 { margin: 0; font-weight: 900; color: #0f172a; }
        .soft-sep { border-top: 1px solid #e2e8f0; margin: 12px 0; }
        .btn-row { display:flex; gap:10px; flex-wrap: wrap; }

        .content-card .form-control {
            border-radius: 14px;
            padding: 10px 12px;
        }
        .content-card .form-label { font-weight: 800; color: #0f172a; }

        .notif-dd {
            border-radius: 18px;
            border: 1px solid rgba(226,232,240,0.95);
            overflow: hidden;
            box-shadow: 0 22px 55px rgba(15, 23, 42, 0.22);
        }
        .notif-dd-head {
            background: radial-gradient(900px circle at 0% 0%, rgba(255,255,255,0.35), transparent 55%),
                        linear-gradient(135deg, #2563eb, #0ea5e9);
            color: #fff;
        }
        .notif-dd-flex.show {
            display: flex !important;
            flex-direction: column;
        }
        .notif-dd-title { font-weight: 900; letter-spacing: 0.02em; }
        .notif-dd-sub { opacity: .85; font-weight: 700; font-size: .85rem; }
        .notif-dd-count {
            background: rgba(255,255,255,0.22);
            border: 1px solid rgba(255,255,255,0.28);
            color: #fff;
            padding: 3px 10px;
            border-radius: 999px;
            font-weight: 900;
            font-size: .78rem;
        }
        .notif-empty { border: 1px dashed rgba(148, 163, 184, 0.6); background: rgba(248, 250, 252, 0.7); border-radius: 16px; }
        .notif-item { border: 1px solid rgba(226,232,240,0.95); background: #fff; transition: transform .12s ease, box-shadow .12s ease, background .12s ease; }
        .notif-item:hover { transform: translateY(-1px); box-shadow: 0 12px 26px rgba(15, 23, 42, 0.10); background: #f1f5f9; }
        .notif-item + .notif-item { margin-top: 10px; }

        @media (max-width: 992px) {
            .profile-shell { max-width: 100%; }
        }
    
    </style>
</head>
<body>
    <?php
        $fullName = trim((string)($userData['firstname'] ?? '') . ' ' . (string)($userData['lastname'] ?? ''));
        $companyName = trim((string)getAppSetting('company.name', ''));
        $companyLogoUrl = (string)getCompanyLogoUrl('publico/img/vigitec-logo.png');
        $initials = '';
        $parts = preg_split('/\s+/', trim($fullName));
        $sub1 = function ($str) {
            if ($str === null) return '';
            $str = (string)$str;
            if ($str === '') return '';
            return function_exists('mb_substr') ? mb_substr($str, 0, 1) : substr($str, 0, 1);
        };
        if (!empty($parts[0])) $initials .= $sub1($parts[0]);
        if (!empty($parts[1])) $initials .= $sub1($parts[1]);
        $initials = strtoupper($initials ?: 'U');
    ?>
    <nav class="navbar navbar-dark topbar" style="position: fixed; top: 0; left: 0; width: 100%; z-index: 1030;">
        <div class="container-fluid">
            <a class="navbar-brand profile-brand" href="tickets.php">
                <span class="brand-logo-wrap" aria-hidden="true">
                    <img class="brand-logo" src="<?php echo html($companyLogoUrl); ?>" alt="<?php echo html($companyName !== '' ? $companyName : 'Logo'); ?>">
                </span>
            </a>
            <div class="d-flex align-items-center gap-2">
                <div class="dropdown">
                    <button class="btn btn-outline-light btn-sm user-menu-btn" type="button" id="notifBellBtn" data-bs-toggle="dropdown" aria-expanded="false" title="Notificaciones">
                        <i class="bi bi-bell"></i>
                        <span id="notifBellBadge" class="badge bg-danger ms-1" style="display:none; font-size:.7rem;">0</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-0 notif-dd notif-dd-flex" style="min-width: 380px; max-height: 420px;" aria-labelledby="notifBellBtn">
                        <div class="p-3 notif-dd-head" style="flex-shrink: 0;">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-2">
                                    <div style="width:36px;height:36px;border-radius:14px;background:rgba(255,255,255,0.18);display:flex;align-items:center;justify-content:center;border:1px solid rgba(255,255,255,0.22);">
                                        <i class="bi bi-bell" style="font-size:1.05rem;"></i>
                                    </div>
                                    <div>
                                        <div class="notif-dd-title">Notificaciones</div>
                                        <div class="notif-dd-sub" id="notifBellSub">Respuestas a tus tickets</div>
                                    </div>
                                </div>
                                <div id="notifBellCountPill" class="notif-dd-count" style="display:none;">0 nuevas</div>
                            </div>
                        </div>
                        <div id="notifBellList" class="p-3" style="flex: 1; overflow-y: auto; min-height: 0;">
                            <div class="notif-empty text-center text-muted py-3" style="font-size:.92rem">
                                <div class="mb-1" style="font-weight:900;color:#0f172a;">Todo al día</div>
                                <div style="color:#64748b;">Cuando el equipo responda, te aparecerá aquí.</div>
                            </div>
                        </div>
                        <div class="p-2 border-top" style="background:#f8f9fa; flex-shrink: 0;">
                            <button id="notifMarkAllRead" class="btn btn-sm btn-outline-secondary w-100" type="button" style="font-size:.85rem;">
                                <i class="bi bi-check-all"></i> Marcar todas como leídas
                            </button>
                        </div>
                    </div>
                </div>
                <div class="dropdown">
                    <button class="btn btn-outline-light btn-sm dropdown-toggle user-menu-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="uavatar" aria-hidden="true"><?php echo html($initials); ?></span>
                        <span class="d-none d-sm-inline"><?php echo html($fullName !== '' ? $fullName : 'Mi Perfil'); ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="tickets.php"><i class="bi bi-inboxes"></i> Mis Tickets</a></li>
                        <li><a class="dropdown-item" href="open.php"><i class="bi bi-plus-circle"></i> Crear Ticket</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person"></i> Mi perfil</a></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-main">
        <div class="profile-shell">
            <main class="panel profile-content">
                <div class="profile-hero">
                    <div class="profile-hero-left">
                        <div class="profile-hero-avatar" aria-hidden="true"><?php echo html($initials); ?></div>
                        <div>
                            <h2 class="profile-hero-name"><?php echo html($fullName !== '' ? $fullName : 'Mi Perfil'); ?></h2>
                            <div class="profile-hero-email"><i class="bi bi-envelope"></i> <?php echo html($userData['email'] ?? ''); ?></div>
                        </div>
                    </div>
                    <div class="profile-hero-right">
                        <div class="profile-badge"><i class="bi bi-shield-check"></i> Portal de Cliente</div>
                        <a href="tickets.php" class="btn btn-outline-primary btn-sm" style="border-radius: 999px; font-weight: 900;"><i class="bi bi-arrow-left"></i> Volver</a>
                    </div>
                </div>

                <div class="content-card">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo html($error); ?></div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo html($success); ?></div>
                    <?php endif; ?>

                    <div class="section-title">
                        <h5><i class="bi bi-pencil-square"></i> Datos de contacto</h5>
                        <div class="sidebar-sub">Campos con * son obligatorios</div>
                    </div>

                    <form method="post" id="profileForm">
                        <div class="row g-3 mb-2">
                            <div class="col-md-6">
                                <label for="firstname" class="form-label">Nombre</label>
                                <input type="text" class="form-control" id="firstname" name="firstname" value="<?php echo html($userData['firstname'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="lastname" class="form-label">Apellido</label>
                                <input type="text" class="form-control" id="lastname" name="lastname" value="<?php echo html($userData['lastname'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="mb-2">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo html($userData['email'] ?? ''); ?>" required>
                        </div>

                        <div class="mb-2">
                            <label for="address" class="form-label">Dirección</label>
                            <input type="text" class="form-control" id="address" name="address" value="<?php echo html($userData['address'] ?? ''); ?>">
                        </div>

                        <div class="row g-3 mb-2">
                            <div class="col-md-6">
                                <label for="company" class="form-label">Empresa</label>
                                <input type="text" class="form-control" id="company" name="company" value="<?php echo html($userData['company'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Teléfono</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo html($userData['phone'] ?? ''); ?>">
                            </div>
                        </div>

                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                        <div class="soft-sep"></div>

                        <div class="btn-row">
                            <button type="submit" class="btn btn-primary" style="border-radius: 999px; font-weight: 900;"><i class="bi bi-save"></i> Guardar cambios</button>
                            <a href="tickets.php" class="btn btn-outline-secondary" style="border-radius: 999px; font-weight: 900;"><i class="bi bi-x-circle"></i> Cancelar</a>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <script>
        (function () {
            var success = document.getElementById('profileSuccess');
            var form = document.getElementById('profileForm');
            if (!success) return;

            var hide = function () {
                try { success.style.display = 'none'; } catch (e) {}
            };

            setTimeout(hide, 3500);

            if (form) {
                form.addEventListener('input', hide, true);
                form.addEventListener('change', hide, true);
            }
        })();
    </script>
    <footer style="text-align: center; padding: 20px 0; background-color: #f8f9fa; border-top: 1px solid #dee2e6; margin-top: 40px; color: #6c757d; font-size: 12px;">
        <p style="margin: 0;">
            Derechos de autor &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(getAppSetting('company.name', 'Vigitec Panama')); ?> - Sistema de Tickets - Todos los derechos reservados.
        </p>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function(){
            var POLL_MS = 12000;

            function setBellCount(n) {
                try {
                    var badge = document.getElementById('notifBellBadge');
                    var pill = document.getElementById('notifBellCountPill');
                    if (!badge) return;
                    var v = parseInt(n || 0, 10) || 0;
                    badge.textContent = String(v);
                    badge.style.display = v > 0 ? '' : 'none';
                    if (pill) {
                        pill.textContent = String(v) + ' nuevas';
                        pill.style.display = v > 0 ? '' : 'none';
                    }
                } catch (e) {}
            }

            function renderBell(items) {
                try {
                    var list = document.getElementById('notifBellList');
                    if (!list) return;
                    if (!items || !items.length) {
                        list.innerHTML = ''
                            + '<div class="notif-empty text-center text-muted py-3" style="font-size:.92rem">'
                            +   '<div class="mb-1" style="font-weight:900;color:#0f172a;">Todo al día</div>'
                            +   '<div style="color:#64748b;">Cuando el equipo responda, te aparecerá aquí.</div>'
                            + '</div>';
                        return;
                    }
                    var html = '';
                    items.forEach(function(it){
                        var msg = (it.message || '').toString();
                        var when = (it.created_at || '').toString();
                        var href = it.ticket_id ? ('view-ticket.php?id=' + String(it.ticket_id)) : 'tickets.php';
                        html += ''
                            + '<div class="notif-item rounded-3 px-2 py-2" style="cursor:pointer;">'
                            +   '<div class="d-flex align-items-start gap-2">'
                            +     '<div class="flex-shrink-0" style="width:34px;height:34px;border-radius:12px;background:rgba(37,99,235,.12);display:flex;align-items:center;justify-content:center;color:#2563eb;">'
                            +       '<i class="bi bi-chat-dots"></i>'
                            +     '</div>'
                            +     '<div class="flex-grow-1">'
                            +       '<div class="text-dark" style="font-weight:800;font-size:.92rem;line-height:1.15;">' + msg.replace(/</g,'&lt;') + '</div>'
                            +       '<div class="text-muted" style="font-size:.78rem;">' + when.replace(/</g,'&lt;') + '</div>'
                            +     '</div>'
                            +     '<div class="flex-shrink-0">'
                            +       '<button class="btn btn-sm btn-outline-primary" data-mark-read="' + String(it.id) + '" data-href="' + href + '" style="border-radius:999px;">Ver</button>'
                            +     '</div>'
                            +   '</div>'
                            + '</div>';
                    });
                    list.innerHTML = html;
                } catch (e) {}
            }

            function poll() {
                fetch('tickets.php?action=user_notifs_count', { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                    .then(function(r){ return r.json(); })
                    .then(function(data){
                        if (!data || !data.ok) return;
                        setBellCount(data.count || 0);
                        var cnt = (parseInt(data.count || 0, 10) || 0);
                        if (cnt <= 0) {
                            renderBell([]);
                            return;
                        }
                        return fetch('tickets.php?action=user_notifs_list', { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                            .then(function(r){ return r.json(); })
                            .then(function(d2){
                                if (!d2 || !d2.ok) return;
                                renderBell(Array.isArray(d2.items) ? d2.items : []);
                            });
                    })
                    .catch(function(){});
            }

            document.addEventListener('click', function(ev){
                try {
                    var btn = ev.target && ev.target.getAttribute ? ev.target.getAttribute('data-mark-read') : null;
                    if (!btn) return;
                    ev.preventDefault();
                    var id = parseInt(btn, 10) || 0;
                    if (!id) return;
                    var href = ev.target.getAttribute('data-href') || 'tickets.php';
                    var fd = new FormData();
                    fd.append('id', String(id));
                    fetch('tickets.php?action=user_notifs_mark_read', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                        .then(function(){ window.location.href = href; })
                        .catch(function(){ window.location.href = href; });
                } catch (e) {}
            });

            // Botón "Marcar todas como leídas"
            (function(){
                var markAllBtn = document.getElementById('notifMarkAllRead');
                if (!markAllBtn) return;
                markAllBtn.addEventListener('click', function(ev){
                    ev.preventDefault();
                    ev.stopPropagation();
                    fetch('tickets.php?action=user_notifs_mark_all_read', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                        .then(function(r){ return r.json(); })
                        .then(function(data){
                            if (data && data.ok) {
                                setBellCount(0);
                                renderBell([]);
                                var list = document.getElementById('notifBellList');
                                if (list) {
                                    list.innerHTML = '<div class="notif-empty text-center text-muted py-3" style="font-size:.92rem"><div class="mb-1" style="font-weight:900;color:#0f172a;">Todo al día</div><div style="color:#64748b;">Todas las notificaciones fueron marcadas como leídas.</div></div>';
                                }
                            }
                        })
                        .catch(function(){});
                });
            })();

            poll();
            window.setInterval(poll, POLL_MS);
        })();
    </script>
</body>
</html>
