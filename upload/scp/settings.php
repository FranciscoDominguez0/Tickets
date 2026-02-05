<?php
require_once '../../config.php';
require_once '../../includes/helpers.php';
require_once '../../includes/Auth.php';

if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

requireLogin('agente');
$staff = getCurrentUser();
$currentRoute = 'settings';

$allowedTargets = ['pages','system','tickets','tasks','agents','users'];
$target = (string)($_GET['t'] ?? 'pages');
if (!in_array($target, $allowedTargets, true)) {
    $target = 'pages';
}

$msg = '';
$error = '';

function scpSettingsEnsureUploadsDir($dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return is_dir($dir) && is_writable($dir);
}

function scpSettingsHandleImageUpload($field, $uploadDirAbs, $publicPrefix) {
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [true, null];
    }

    $err = (int)($_FILES[$field]['error'] ?? UPLOAD_ERR_OK);
    if ($err !== UPLOAD_ERR_OK) {
        return [false, 'Error al subir el archivo.'];
    }

    $tmp = (string)($_FILES[$field]['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return [false, 'Archivo inválido.'];
    }

    $allowedMimes = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    $allowedExts = ['png', 'jpg', 'jpeg', 'webp', 'gif'];

    $ext = null;
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($tmp);
        if (isset($allowedMimes[$mime])) {
            $ext = $allowedMimes[$mime];
        }
    }

    if (!$ext) {
        $original = (string)($_FILES[$field]['name'] ?? '');
        $guess = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if ($guess === 'jpeg') $guess = 'jpg';
        if (in_array($guess, $allowedExts, true)) {
            $ext = $guess;
        }
    }

    if (!$ext) {
        return [false, 'Formato no permitido. Solo PNG/JPG/WEBP/GIF.'];
    }

    if (!scpSettingsEnsureUploadsDir($uploadDirAbs)) {
        return [false, 'No se pudo crear el directorio de uploads.'];
    }

    $name = $field . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
    $destAbs = rtrim($uploadDirAbs, '/\\') . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($tmp, $destAbs)) {
        return [false, 'No se pudo guardar el archivo subido.'];
    }

    return [true, rtrim($publicPrefix, '/').'/'.$name];
}

if ($target === 'pages') {
    $uploadsAbs = realpath(__DIR__ . '/../../publico') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'settings';
    $uploadsPublicPrefix = '../publico/uploads/settings';

    if ($_POST) {
        if (!validateCSRF()) {
            $error = 'Token de seguridad inválido';
        } else {
            $company_name = trim((string)($_POST['company_name'] ?? ''));
            $company_website = trim((string)($_POST['company_website'] ?? ''));
            $company_phone = trim((string)($_POST['company_phone'] ?? ''));
            $company_address = trim((string)($_POST['company_address'] ?? ''));

            $hasLogoUpload = isset($_FILES['company_logo']) && (int)($_FILES['company_logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
            $hasBgUpload = isset($_FILES['login_background']) && (int)($_FILES['login_background']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
            $hasAnyUpload = $hasLogoUpload || $hasBgUpload;

            if ($company_name === '' && !$hasAnyUpload) {
                $error = 'El nombre de la empresa es requerido.';
            } else {
                if ($company_name !== '') {
                    setAppSetting('company.name', $company_name);
                }
                if ($company_website !== '') {
                    setAppSetting('company.website', $company_website);
                }
                if ($company_phone !== '') {
                    setAppSetting('company.phone', $company_phone);
                }
                if ($company_address !== '') {
                    setAppSetting('company.address', $company_address);
                }

                list($okLogo, $logoPathOrErr) = scpSettingsHandleImageUpload('company_logo', $uploadsAbs, $uploadsPublicPrefix);
                if (!$okLogo) {
                    $error = (string)$logoPathOrErr;
                } else {
                    if ($logoPathOrErr) {
                        setAppSetting('company.logo', $logoPathOrErr);
                    }

                    list($okBg, $bgPathOrErr) = scpSettingsHandleImageUpload('login_background', $uploadsAbs, $uploadsPublicPrefix);
                    if (!$okBg) {
                        $error = (string)$bgPathOrErr;
                    } else {
                        if ($bgPathOrErr) {
                            setAppSetting('login.background', $bgPathOrErr);
                        }
                        if ($error === '') {
                            $msg = 'Cambios guardados correctamente.';
                        }
                    }
                }
            }
        }
    }

    $company_name = (string)getAppSetting('company.name', '');
    $company_website = (string)getAppSetting('company.website', '');
    $company_phone = (string)getAppSetting('company.phone', '');
    $company_address = (string)getAppSetting('company.address', '');
    $company_logo = (string)getAppSetting('company.logo', '../publico/img/vigitec-logo.png');
    $login_background = (string)getAppSetting('login.background', '');

    ob_start();
    ?>
    <div class="page-header">
        <h1>Perfil de la empresa</h1>
        <p>Administrar información y branding</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo html($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if ($msg): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo html($msg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link active" href="#tab-basic" data-bs-toggle="tab">Información básica</a></li>
        <li class="nav-item"><a class="nav-link" href="#tab-site" data-bs-toggle="tab">Páginas del sitio</a></li>
        <li class="nav-item"><a class="nav-link" href="#tab-logos" data-bs-toggle="tab">Logos</a></li>
        <li class="nav-item"><a class="nav-link" href="#tab-login" data-bs-toggle="tab">Fondo del login</a></li>
    </ul>

    <form method="post" enctype="multipart/form-data" class="tab-content">
        <?php csrfField(); ?>

        <div class="tab-pane fade show active" id="tab-basic">
            <div class="card">
                <div class="card-header"><strong>Información de la empresa</strong></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre de la empresa <span class="text-danger">*</span></label>
                        <input type="text" name="company_name" class="form-control" value="<?php echo html($company_name); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Website</label>
                        <input type="text" name="company_website" class="form-control" value="<?php echo html($company_website); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="company_phone" class="form-control" value="<?php echo html($company_phone); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Dirección</label>
                        <textarea name="company_address" class="form-control" rows="3"><?php echo html($company_address); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tab-site">
            <div class="card">
                <div class="card-header"><strong>Páginas del sitio</strong></div>
                <div class="card-body">
                    <div class="alert alert-secondary mb-0">Este módulo se implementará en el siguiente paso.</div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tab-logos">
            <div class="card">
                <div class="card-header"><strong>Logos</strong></div>
                <div class="card-body">
                    <div class="row g-3 align-items-center">
                        <div class="col-md-6">
                            <label class="form-label">Logo actual</label>
                            <div class="border rounded p-3 bg-white">
                                <img src="<?php echo html($company_logo); ?>" alt="Logo" style="max-height:70px; width:auto;">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Subir nuevo logo</label>
                            <input type="file" name="company_logo" class="form-control" accept="image/*">
                            <div class="form-text">Formatos: PNG/JPG/WEBP/GIF</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tab-login">
            <div class="card">
                <div class="card-header"><strong>Fondo del login</strong></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Imagen actual</label>
                        <?php if ($login_background): ?>
                            <div class="border rounded p-3 bg-white">
                                <img src="<?php echo html($login_background); ?>" alt="Fondo" style="max-height:160px; width:auto; max-width:100%;">
                            </div>
                        <?php else: ?>
                            <div class="alert alert-secondary">No hay fondo personalizado.</div>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subir nuevo fondo</label>
                        <input type="file" name="login_background" class="form-control" accept="image/*">
                        <div class="form-text">Formatos: PNG/JPG/WEBP/GIF</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
            <a class="btn btn-outline-secondary" href="settings.php?t=pages">Restaurar</a>
        </div>
    </form>
    <?php
    $content = ob_get_clean();
} else {
    $content = '<div class="page-header"><h1>Configuración</h1><p>Sección en construcción.</p></div>';
}

require_once 'layout_admin.php';
?>