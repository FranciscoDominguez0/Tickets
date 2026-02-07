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

// Obtener datos completos del usuario
$stmt = $mysqli->prepare('SELECT * FROM users WHERE id = ?');
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();

if ($_POST) {
    if (!validateCSRF()) {
        $error = 'Token de seguridad inválido';
    } else {
        $firstname = trim($_POST['firstname'] ?? '');
        $lastname = trim($_POST['lastname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $company = trim($_POST['company'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (empty($firstname) || empty($lastname) || empty($email)) {
            $error = 'Nombre, apellido y email son requeridos';
        } else {
            $stmt = $mysqli->prepare('UPDATE users SET firstname = ?, lastname = ?, email = ?, company = ?, phone = ? WHERE id = ?');
            $stmt->bind_param('sssssi', $firstname, $lastname, $email, $company, $phone, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $_SESSION['user_name'] = $firstname . ' ' . $lastname;
                $_SESSION['user_email'] = $email;
                $success = 'Perfil actualizado exitosamente';
                $userData['firstname'] = $firstname;
                $userData['lastname'] = $lastname;
                $userData['email'] = $email;
                $userData['company'] = $company;
                $userData['phone'] = $phone;
            } else {
                $error = 'Error al actualizar: ' . $mysqli->error;
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background: #f1f5f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 56px;
        }
        .container-main {
            max-width: 800px;
            margin: 18px auto;
            padding: 0 20px;
        }
        .page-header {
            background: linear-gradient(135deg, #0f172a, #1d4ed8);
            padding: 18px 20px;
            border-radius: 16px;
            margin-bottom: 12px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.25);
            color: #fff;
        }
        .page-header .sub { color: rgba(255,255,255,0.85); }

        .profile-card {
            background: #fff;
            padding: 18px;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.06);
            border: 1px solid #e2e8f0;
        }
        .profile-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }
        .profile-badge {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .profile-avatar {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            background: #dbeafe;
            border: 1px solid #bfdbfe;
            color: #1e3a8a;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            letter-spacing: 0.08em;
            box-shadow: 0 4px 14px rgba(0,0,0,0.06);
            flex: 0 0 auto;
        }
        .profile-title { margin: 0; font-weight: 900; color: #0f172a; }
        .profile-meta { color: #64748b; font-weight: 600; font-size: 0.95rem; }
        .section-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }
        .section-title h5 { margin: 0; font-weight: 900; color: #0f172a; }
        .soft-sep { border-top: 1px solid #e2e8f0; margin: 10px 0; }
        .btn-row { display:flex; gap:10px; flex-wrap: wrap; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark" style="position: fixed; top: 0; left: 0; width: 100%; z-index: 1030;">
        <div class="container-fluid">
            <span class="navbar-brand"><?php echo APP_NAME; ?></span>
            <div>
                <a href="tickets.php" class="btn btn-outline-light btn-sm">Mis Tickets</a>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Cerrar Sesión</a>
            </div>
        </div>
    </nav>

    <div class="container-main">
        <div class="page-header">
            <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                <div>
                    <h2 class="mb-1">Mi Perfil</h2>
                    <div class="sub">Actualiza tus datos para que podamos ayudarte mejor.</div>
                </div>
                <div>
                    <a href="tickets.php" class="btn btn-light btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
                </div>
            </div>
        </div>

        <div class="profile-card">
            <?php
                $fullName = trim((string)($userData['firstname'] ?? '') . ' ' . (string)($userData['lastname'] ?? ''));
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

            <div class="profile-top">
                <div class="profile-badge">
                    <div class="profile-avatar" aria-hidden="true"><?php echo html($initials); ?></div>
                    <div>
                        <h4 class="profile-title"><?php echo html($fullName !== '' ? $fullName : 'Mi Perfil'); ?></h4>
                        <div class="profile-meta"><i class="bi bi-envelope"></i> <?php echo html((string)($userData['email'] ?? '')); ?></div>
                    </div>
                </div>
                <div class="profile-meta"><i class="bi bi-shield-check"></i> Portal de Cliente</div>
            </div>

            <div class="soft-sep"></div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success" id="profileSuccess"><?php echo $success; ?></div>
            <?php endif; ?>

            <div class="section-title">
                <h5><i class="bi bi-pencil-square"></i> Datos de contacto</h5>
                <div class="profile-meta">Campos con * son obligatorios</div>
            </div>

            <form method="post" id="profileForm">
                <div class="row mb-2">
                    <div class="col-md-6">
                        <label for="firstname" class="form-label">Nombre</label>
                        <input type="text" class="form-control" id="firstname" name="firstname" 
                               value="<?php echo html($userData['firstname'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="lastname" class="form-label">Apellido</label>
                        <input type="text" class="form-control" id="lastname" name="lastname" 
                               value="<?php echo html($userData['lastname'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="mb-2">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo html($userData['email'] ?? ''); ?>" required>
                </div>

                <div class="row mb-2">
                    <div class="col-md-6">
                        <label for="company" class="form-label">Empresa</label>
                        <input type="text" class="form-control" id="company" name="company" 
                               value="<?php echo html($userData['company'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="phone" class="form-label">Teléfono</label>
                        <input type="tel" class="form-control" id="phone" name="phone" 
                               value="<?php echo html($userData['phone'] ?? ''); ?>">
                    </div>
                </div>

                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div class="btn-row">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Guardar cambios</button>
                    <a href="tickets.php" class="btn btn-secondary"><i class="bi bi-x-circle"></i> Cancelar</a>
                </div>
            </form>
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
</body>
</html>
