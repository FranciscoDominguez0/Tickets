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

    $activeTab = (string)($_GET['tab'] ?? 'basic');
    if (!in_array($activeTab, ['basic', 'site', 'logos', 'login'], true)) {
        $activeTab = 'basic';
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
            $company_name = trim((string)($_POST['company_name'] ?? ''));
            $company_website = trim((string)($_POST['company_website'] ?? ''));
            $company_phone = trim((string)($_POST['company_phone'] ?? ''));
            $company_address = trim((string)($_POST['company_address'] ?? ''));

            $existingCompanyName = trim((string)getAppSetting('company.name', ''));

            $logoMode = (string)($_POST['company_logo_mode'] ?? 'default');
            if (!in_array($logoMode, ['default', 'custom'], true)) {
                $logoMode = 'default';
            }
            setAppSetting('company.logo_mode', $logoMode);

            $bgMode = (string)($_POST['login_bg_mode'] ?? 'default');
            if (!in_array($bgMode, ['default', 'custom'], true)) {
                $bgMode = 'default';
            }
            setAppSetting('login.background_mode', $bgMode);

            $hasLogoUpload = isset($_FILES['company_logo']) && (int)($_FILES['company_logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
            $hasBgUpload = isset($_FILES['login_background']) && (int)($_FILES['login_background']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
            $hasAnyUpload = $hasLogoUpload || $hasBgUpload;

            $isEditingCompanyInfo = ($company_name !== '' || $company_website !== '' || $company_phone !== '' || $company_address !== '');

            if ($isEditingCompanyInfo && $company_name === '' && $existingCompanyName === '' && !$hasAnyUpload) {
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

                if ($logoMode === 'default') {
                    setAppSetting('company.logo', '');
                }

                if ($bgMode === 'default') {
                    setAppSetting('login.background', '');
                }

                list($okLogo, $logoPathOrErr) = scpSettingsHandleImageUpload('company_logo', $uploadsAbs, $uploadsPublicPrefix);
                if (!$okLogo) {
                    $error = (string)$logoPathOrErr;
                } else {
                    if ($logoPathOrErr) {
                        setAppSetting('company.logo', $logoPathOrErr);
                        setAppSetting('company.logo_mode', 'custom');
                    }

                    list($okBg, $bgPathOrErr) = scpSettingsHandleImageUpload('login_background', $uploadsAbs, $uploadsPublicPrefix);
                    if (!$okBg) {
                        $error = (string)$bgPathOrErr;
                    } else {
                        if ($bgPathOrErr) {
                            setAppSetting('login.background', $bgPathOrErr);
                            setAppSetting('login.background_mode', 'custom');
                        }
                        if ($error === '') {
                            $msg = 'Cambios guardados correctamente.';
                        }
                    }
                }
            }
        }
    }

    if ($_POST) {
        $_SESSION['flash_msg'] = (string)$msg;
        $_SESSION['flash_error'] = (string)$error;
        $redirectTab = (string)($_POST['active_tab'] ?? $activeTab);
        if (!in_array($redirectTab, ['basic', 'site', 'logos', 'login'], true)) {
            $redirectTab = 'basic';
        }
        header('Location: settings.php?t=pages&tab=' . urlencode($redirectTab));
        exit;
    }

    $company_name = (string)getAppSetting('company.name', '');
    $company_website = (string)getAppSetting('company.website', '');
    $company_phone = (string)getAppSetting('company.phone', '');
    $company_address = (string)getAppSetting('company.address', '');
    $company_logo = (string)getAppSetting('company.logo', '');
    $company_logo_mode = (string)getAppSetting('company.logo_mode', $company_logo !== '' ? 'custom' : 'default');
    if (!in_array($company_logo_mode, ['default', 'custom'], true)) {
        $company_logo_mode = 'default';
    }
    $default_company_logo = (string)toAppAbsoluteUrl('publico/img/vigitec-logo.png');
    $login_background = (string)getAppSetting('login.background', '');
    $login_bg_mode = (string)getAppSetting('login.background_mode', $login_background !== '' ? 'custom' : 'default');
    if (!in_array($login_bg_mode, ['default', 'custom'], true)) {
        $login_bg_mode = 'default';
    }
    $default_staff_bg = (string)toAppAbsoluteUrl('publico/img/agent-background.jpg');

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
    <li class="nav-item"><a class="nav-link <?php echo $activeTab === 'basic' ? 'active' : ''; ?>" href="#tab-basic" data-bs-toggle="tab" data-tab="basic">Información básica</a></li>
    <li class="nav-item"><a class="nav-link <?php echo $activeTab === 'site' ? 'active' : ''; ?>" href="#tab-site" data-bs-toggle="tab" data-tab="site">Páginas del sitio</a></li>
    <li class="nav-item"><a class="nav-link <?php echo $activeTab === 'logos' ? 'active' : ''; ?>" href="#tab-logos" data-bs-toggle="tab" data-tab="logos">Logos</a></li>
    <li class="nav-item"><a class="nav-link <?php echo $activeTab === 'login' ? 'active' : ''; ?>" href="#tab-login" data-bs-toggle="tab" data-tab="login">Fondo del login</a></li>
</ul>

<form method="post" enctype="multipart/form-data" class="tab-content">
    <?php csrfField(); ?>
    <input type="hidden" name="active_tab" id="active_tab" value="<?php echo html($activeTab); ?>">

    <div class="tab-pane fade <?php echo $activeTab === 'basic' ? 'show active' : ''; ?>" id="tab-basic">
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

    <div class="tab-pane fade <?php echo $activeTab === 'site' ? 'show active' : ''; ?>" id="tab-site">
        <div class="card">
            <div class="card-header"><strong>Páginas del sitio</strong></div>
            <div class="card-body">
                <div class="alert alert-secondary mb-0">Este módulo se implementará en el siguiente paso.</div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade <?php echo $activeTab === 'logos' ? 'show active' : ''; ?>" id="tab-logos">
        <div class="card">
            <div class="card-header"><strong>Logos</strong></div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="fw-semibold mb-2">Logo de sistema por defecto</div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="company_logo_mode" id="logo-mode-default" value="default" <?php echo $company_logo_mode === 'default' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="logo-mode-default">Default</label>
                    </div>
                    <div class="border rounded p-3 bg-white" style="max-width:520px;">
                        <img src="<?php echo html($default_company_logo); ?>" alt="Logo default" style="max-height:70px; width:auto; max-width:100%;">
                    </div>
                </div>

                <div class="mb-3">
                    <div class="fw-semibold mb-2">Utilizar un logotipo personalizado</div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="company_logo_mode" id="logo-mode-custom" value="custom" <?php echo $company_logo_mode === 'custom' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="logo-mode-custom">Personalizado</label>
                    </div>

                    <?php if ($company_logo): ?>
                        <div class="border rounded p-3 bg-white mb-2" style="max-width:520px;">
                            <img src="<?php echo html(toAppAbsoluteUrl($company_logo)); ?>" alt="Logo personalizado" style="max-height:70px; width:auto; max-width:100%;">
                        </div>
                    <?php else: ?>
                        <div class="alert alert-secondary" style="max-width:520px;">No hay logo personalizado aún.</div>
                    <?php endif; ?>

                    <label class="form-label">Subir un nuevo logo</label>
                    <input type="file" name="company_logo" class="form-control" accept="image/*">
                    <div class="form-text">Formatos: PNG/JPG/WEBP/GIF</div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade <?php echo $activeTab === 'login' ? 'show active' : ''; ?>" id="tab-login">
        <div class="card">
            <div class="card-header"><strong>Fondo del login</strong></div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="fw-semibold mb-2">Fondo por defecto del sistema</div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="login_bg_mode" id="bg-mode-default" value="default" <?php echo $login_bg_mode === 'default' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="bg-mode-default">Staff</label>
                    </div>
                    <div class="border rounded p-3 bg-white" style="max-width:520px;">
                        <img src="<?php echo html($default_staff_bg); ?>" alt="Backdrop" style="height:110px; width:auto; max-width:100%; object-fit:cover;">
                    </div>
                </div>

                <div class="mb-3">
                    <div class="fw-semibold mb-2">Use un fondo personalizado</div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="login_bg_mode" id="bg-mode-custom" value="custom" <?php echo $login_bg_mode === 'custom' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="bg-mode-custom">Personalizado</label>
                    </div>

                    <?php if ($login_background): ?>
                        <div class="border rounded p-3 bg-white mb-2" style="max-width:520px;">
                            <img src="<?php echo html(toAppAbsoluteUrl($login_background)); ?>" alt="Fondo personalizado" style="height:110px; width:auto; max-width:100%; object-fit:cover;">
                        </div>
                    <?php else: ?>
                        <div class="alert alert-secondary" style="max-width:520px;">No hay fondo personalizado aún.</div>
                    <?php endif; ?>

                    <label class="form-label">Subir archivo nuevo diseño de fondo</label>
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