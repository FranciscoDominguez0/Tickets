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
    if (!in_array($activeTab, ['basic', 'logos', 'login'], true)) {
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
        if (!in_array($redirectTab, ['basic', 'logos', 'login'], true)) {
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
} elseif ($target === 'system') {
    if (isset($_SESSION['flash_msg'])) {
        $msg = (string)$_SESSION['flash_msg'];
        unset($_SESSION['flash_msg']);
    }
    if (isset($_SESSION['flash_error'])) {
        $error = (string)$_SESSION['flash_error'];
        unset($_SESSION['flash_error']);
    }

    $departments = [];
    if (isset($mysqli) && $mysqli) {
        $stmt = $mysqli->prepare('SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name');
        if ($stmt) {
            $stmt->execute();
            $departments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
    }

    if ($_POST) {
        if (!validateCSRF()) {
            $error = 'Token de seguridad inválido';
        } else {
            $helpdesk_status = (string)($_POST['helpdesk_status'] ?? 'online');
            if (!in_array($helpdesk_status, ['online', 'offline'], true)) {
                $helpdesk_status = 'online';
            }

            $helpdesk_url = trim((string)($_POST['helpdesk_url'] ?? ''));
            $helpdesk_title = trim((string)($_POST['helpdesk_title'] ?? ''));
            $default_dept_id = (string)($_POST['default_dept_id'] ?? '');

            $force_https = isset($_POST['force_https']) ? '1' : '0';
            $collision_duration = (string)($_POST['collision_duration'] ?? '3');
            $page_size = (string)($_POST['page_size'] ?? '25');
            $log_level = (string)($_POST['log_level'] ?? 'notice');
            $purge_logs_months = (string)($_POST['purge_logs_months'] ?? '12');
            $show_avatars = isset($_POST['show_avatars']) ? '1' : '0';
            $enable_rich_text = isset($_POST['enable_rich_text']) ? '1' : '0';
            $allow_iframe = trim((string)($_POST['allow_iframe'] ?? ''));
            $embed_whitelist = trim((string)($_POST['embed_whitelist'] ?? ''));
            $acl = trim((string)($_POST['acl'] ?? ''));
            $timezone = trim((string)($_POST['timezone'] ?? ''));
            $primary_language = trim((string)($_POST['primary_language'] ?? ''));
            $attachment_storage = (string)($_POST['attachment_storage'] ?? 'db');
            if (!in_array($attachment_storage, ['db', 'fs'], true)) {
                $attachment_storage = 'db';
            }
            $max_agent_file_mb = (string)($_POST['max_agent_file_mb'] ?? '32');
            $attachments_require_auth = isset($_POST['attachments_require_auth']) ? '1' : '0';

            if ($helpdesk_url === '') {
                $error = 'Helpdesk URL es requerido.';
            } elseif (!preg_match('~^https?://~i', $helpdesk_url)) {
                $error = 'Helpdesk URL debe iniciar con http:// o https://';
            } elseif ($helpdesk_title === '') {
                $error = 'Nombre/título de Helpdesk es requerido.';
            } elseif (!ctype_digit($collision_duration) || (int)$collision_duration < 1 || (int)$collision_duration > 60) {
                $error = 'Duración del filtro de colisión debe estar entre 1 y 60.';
            } elseif (!ctype_digit($page_size) || (int)$page_size < 5 || (int)$page_size > 200) {
                $error = 'Tamaño de página debe estar entre 5 y 200.';
            } elseif (!ctype_digit($purge_logs_months) || (int)$purge_logs_months < 1 || (int)$purge_logs_months > 120) {
                $error = 'Purgar logs debe estar entre 1 y 120 meses.';
            } elseif (!ctype_digit($max_agent_file_mb) || (int)$max_agent_file_mb < 1 || (int)$max_agent_file_mb > 256) {
                $error = 'Tamaño máximo de archivo (agente) debe estar entre 1 y 256 MB.';
            } else {
                setAppSetting('system.helpdesk_status', $helpdesk_status);
                setAppSetting('system.helpdesk_url', $helpdesk_url);
                setAppSetting('system.helpdesk_title', $helpdesk_title);
                setAppSetting('system.default_dept_id', $default_dept_id);
                setAppSetting('system.force_https', $force_https);
                setAppSetting('system.collision_duration', $collision_duration);
                setAppSetting('system.page_size', $page_size);
                setAppSetting('system.log_level', $log_level);
                setAppSetting('system.purge_logs_months', $purge_logs_months);
                setAppSetting('system.show_avatars', $show_avatars);
                setAppSetting('system.enable_rich_text', $enable_rich_text);
                setAppSetting('system.allow_iframe', $allow_iframe);
                setAppSetting('system.embed_whitelist', $embed_whitelist);
                setAppSetting('system.acl', $acl);
                setAppSetting('system.timezone', $timezone);
                setAppSetting('system.primary_language', $primary_language);
                setAppSetting('system.attachment_storage', $attachment_storage);
                setAppSetting('system.max_agent_file_mb', $max_agent_file_mb);
                setAppSetting('system.attachments_require_auth', $attachments_require_auth);
                $msg = 'Cambios guardados correctamente.';
            }
        }
    }

    if ($_POST) {
        $_SESSION['flash_msg'] = (string)$msg;
        $_SESSION['flash_error'] = (string)$error;
        header('Location: settings.php?t=system');
        exit;
    }

    $helpdesk_status = (string)getAppSetting('system.helpdesk_status', 'online');
    $helpdesk_url = (string)getAppSetting('system.helpdesk_url', (string)APP_URL . '/upload/');
    $helpdesk_title = (string)getAppSetting('system.helpdesk_title', (string)APP_NAME);
    $default_dept_id = (string)getAppSetting('system.default_dept_id', '');
    $force_https = (string)getAppSetting('system.force_https', '0') === '1';
    $collision_duration = (string)getAppSetting('system.collision_duration', '3');
    $page_size = (string)getAppSetting('system.page_size', '25');
    $log_level = (string)getAppSetting('system.log_level', 'notice');
    $purge_logs_months = (string)getAppSetting('system.purge_logs_months', '12');
    $show_avatars = (string)getAppSetting('system.show_avatars', '1') === '1';
    $enable_rich_text = (string)getAppSetting('system.enable_rich_text', '1') === '1';
    $allow_iframe = (string)getAppSetting('system.allow_iframe', '');
    $embed_whitelist = (string)getAppSetting('system.embed_whitelist', 'youtube.com, dailymotion.com, vimeo.com, player.vimeo.com, web.microsoftstream.com');
    $acl = (string)getAppSetting('system.acl', '');
    $timezone = (string)getAppSetting('system.timezone', (string)TIMEZONE);
    $primary_language = (string)getAppSetting('system.primary_language', 'es_MX');
    $attachment_storage = (string)getAppSetting('system.attachment_storage', 'db');
    $max_agent_file_mb = (string)getAppSetting('system.max_agent_file_mb', '32');
    $attachments_require_auth = (string)getAppSetting('system.attachments_require_auth', '1') === '1';

    $timezoneOptions = [
        'America/Mexico_City' => 'America/Mexico_City',
        'America/Panama' => 'America/Panama',
        'America/Bogota' => 'America/Bogota',
        'America/Lima' => 'America/Lima',
        'America/Caracas' => 'America/Caracas',
        'America/Santo_Domingo' => 'America/Santo_Domingo',
        'America/Guatemala' => 'America/Guatemala',
        'America/El_Salvador' => 'America/El_Salvador',
        'America/Costa_Rica' => 'America/Costa_Rica',
        'UTC' => 'UTC',
    ];
    if ($timezone !== '' && !isset($timezoneOptions[$timezone])) {
        $timezoneOptions = [$timezone => $timezone] + $timezoneOptions;
    }

    $languageOptions = [
        'es_MX' => 'español, castellano - MX',
        'es_ES' => 'español, castellano - ES',
        'en_US' => 'English - US',
    ];
    if ($primary_language !== '' && !isset($languageOptions[$primary_language])) {
        $languageOptions = [$primary_language => $primary_language] + $languageOptions;
    }

    ob_start();
?>
<div class="page-header">
    <h1>Configuración general</h1>
    <p>Ajustes esenciales del helpdesk y del sistema</p>
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

<form method="post" class="row g-3">
    <?php csrfField(); ?>

    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <strong>Helpdesk</strong>
                <span class="badge <?php echo $helpdesk_status === 'online' ? 'bg-success' : 'bg-secondary'; ?>">
                    <?php echo $helpdesk_status === 'online' ? 'En línea' : 'Fuera de línea'; ?>
                </span>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Estado de Helpdesk
                        <i class="bi bi-question-circle ms-1 text-muted" data-bs-toggle="tooltip" data-bs-placement="top" title="Si está Fuera de línea, se bloquea el acceso de clientes (login/registro/recuperación). Agentes y administradores aún pueden entrar."></i>
                    </label>
                    <div class="d-flex gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="helpdesk_status" id="helpdesk-online" value="online" <?php echo $helpdesk_status === 'online' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="helpdesk-online">En línea</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="helpdesk_status" id="helpdesk-offline" value="offline" <?php echo $helpdesk_status === 'offline' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="helpdesk-offline">Fuera de línea</label>
                        </div>
                    </div>
                    <div class="form-text">Úsalo para mantenimiento o cambios críticos sin permitir entradas de clientes.</div>
                </div>

                <div class="row g-3">
                    <div class="col-12 col-lg-7">
                        <label class="form-label">Helpdesk URL <span class="text-danger">*</span>
                            <i class="bi bi-question-circle ms-1 text-muted" data-bs-toggle="tooltip" data-bs-placement="top" title="URL principal del portal. Se usa en enlaces, notificaciones y redirecciones."></i>
                        </label>
                        <input type="text" name="helpdesk_url" class="form-control" value="<?php echo html($helpdesk_url); ?>">
                        <div class="form-text">Ej: <?php echo html((string)APP_URL . '/upload/'); ?></div>
                    </div>
                    <div class="col-12 col-lg-5">
                        <label class="form-label">Nombre/título de Helpdesk <span class="text-danger">*</span>
                            <i class="bi bi-question-circle ms-1 text-muted" data-bs-toggle="tooltip" data-bs-placement="top" title="Nombre que se muestra en el sistema y se puede usar en correos/encabezados."></i>
                        </label>
                        <input type="text" name="helpdesk_title" class="form-control" value="<?php echo html($helpdesk_title); ?>">
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-12 col-lg-6">
                        <label class="form-label">Departamento por defecto
                            <i class="bi bi-question-circle ms-1 text-muted" data-bs-toggle="tooltip" data-bs-placement="top" title="Departamento sugerido cuando no se especifica uno. Útil para enrutar tickets nuevos."></i>
                        </label>
                        <select name="default_dept_id" class="form-select">
                            <option value="">- Seleccionar -</option>
                            <?php foreach ($departments as $dept): $did = (string)($dept['id'] ?? ''); ?>
                                <option value="<?php echo html($did); ?>" <?php echo $default_dept_id === $did ? 'selected' : ''; ?>><?php echo html((string)($dept['name'] ?? '')); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-lg-6 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="force_https" id="force-https" value="1" <?php echo $force_https ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="force-https">Forzar HTTPS
                                <i class="bi bi-question-circle ms-1 text-muted" data-bs-toggle="tooltip" data-bs-placement="top" title="Recomendado en producción: protege credenciales y datos en tránsito. Requiere tener HTTPS configurado."></i>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-6">
        <div class="card h-100">
            <div class="card-header"><strong>Rendimiento y registros</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label">Duración del filtro de colisión
                            <i class="bi bi-question-circle ms-1 text-muted" data-bs-toggle="tooltip" data-bs-placement="top" title="Evita que se creen/editen registros duplicados por doble clic o envíos repetidos. Ventana en minutos."></i>
                        </label>
                        <div class="input-group">
                            <input type="number" name="collision_duration" class="form-control" value="<?php echo html($collision_duration); ?>" min="1" max="60">
                            <span class="input-group-text">min</span>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Tamaño de página predeterminado
                            <i class="bi bi-question-circle ms-1 text-muted" data-bs-toggle="tooltip" data-bs-placement="top" title="Cantidad de filas por página en listados. Más grande = más carga, menos paginación."></i>
                        </label>
                        <select name="page_size" class="form-select">
                            <?php foreach ([10,15,25,50,100] as $size): $s = (string)$size; ?>
                                <option value="<?php echo html($s); ?>" <?php echo $page_size === $s ? 'selected' : ''; ?>><?php echo html($s); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Nivel Log predeterminado
                            <i class="bi bi-question-circle ms-1 text-muted" data-bs-toggle="tooltip" data-bs-placement="top" title="Controla la verbosidad del registro del sistema. Valores altos generan más detalle."></i>
                        </label>
                        <select name="log_level" class="form-select">
                            <option value="debug" <?php echo $log_level === 'debug' ? 'selected' : ''; ?>>DEBUG</option>
                            <option value="info" <?php echo $log_level === 'info' ? 'selected' : ''; ?>>INFO</option>
                            <option value="notice" <?php echo $log_level === 'notice' ? 'selected' : ''; ?>>AVISO</option>
                            <option value="warning" <?php echo $log_level === 'warning' ? 'selected' : ''; ?>>ADVERTENCIA</option>
                            <option value="error" <?php echo $log_level === 'error' ? 'selected' : ''; ?>>ERROR</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Purgar Logs
                            <i class="bi bi-question-circle ms-1 text-muted" data-bs-toggle="tooltip" data-bs-placement="top" title="Define cuánto tiempo se conservan registros. Ayuda a controlar el tamaño de base de datos."></i>
                        </label>
                        <select name="purge_logs_months" class="form-select">
                            <?php foreach ([3,6,12,24,36] as $m): $ms = (string)$m; ?>
                                <option value="<?php echo html($ms); ?>" <?php echo $purge_logs_months === $ms ? 'selected' : ''; ?>>Después de <?php echo html($ms); ?> meses</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-6">
        <div class="card h-100">
            <div class="card-header"><strong>Experiencia y seguridad</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="show_avatars" id="show-avatars" value="1" <?php echo $show_avatars ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="show-avatars">Mostrar Avatares (hilo)
                                <i class="bi bi-question-circle ms-1 text-muted" data-bs-toggle="tooltip" data-bs-placement="top" title="Mejora la identificación visual de participantes en conversaciones."></i>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="enable_rich_text" id="enable-rich-text" value="1" <?php echo $enable_rich_text ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="enable-rich-text">Activar Texto Enriquecido (HTML)
                                <i class="bi bi-question-circle ms-1 text-muted" data-bs-toggle="tooltip" data-bs-placement="top" title="Permite formato en el hilo. Recomendado si confías en tus agentes; considera el riesgo de contenido HTML."></i>
                            </label>
                        </div>
                    </div>

                    <div class="col-12 col-lg-6">
                        <label class="form-label">Zona horaria por defecto
                            <i class="bi bi-question-circle ms-1 text-muted" data-bs-toggle="tooltip" data-bs-placement="top" title="Se usa para mostrar fechas/horas del sistema de forma consistente."></i>
                        </label>
                        <select name="timezone" class="form-select">
                            <?php foreach ($timezoneOptions as $tzKey => $tzLabel): ?>
                                <option value="<?php echo html($tzKey); ?>" <?php echo $timezone === $tzKey ? 'selected' : ''; ?>><?php echo html($tzLabel); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label">Idioma principal
                            <i class="bi bi-question-circle ms-1 text-muted" data-bs-toggle="tooltip" data-bs-placement="top" title="Idioma por defecto para la interfaz y textos del sistema."></i>
                        </label>
                        <select name="primary_language" class="form-select">
                            <?php foreach ($languageOptions as $langKey => $langLabel): ?>
                                <option value="<?php echo html($langKey); ?>" <?php echo $primary_language === $langKey ? 'selected' : ''; ?>><?php echo html($langLabel); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Permitir iFrame del sistema
                            <i class="bi bi-question-circle ms-1 text-muted" data-bs-toggle="tooltip" data-bs-placement="top" title="Define orígenes permitidos para incrustar contenido. Útil para integraciones y reportes."></i>
                        </label>
                        <input type="text" name="allow_iframe" class="form-control" value="<?php echo html($allow_iframe); ?>" placeholder="eg. https://domain.tld, *.domain.tld">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Lista Blanca de Dominios Incrustados
                            <i class="bi bi-question-circle ms-1 text-muted" data-bs-toggle="tooltip" data-bs-placement="top" title="Permite incrustar videos/recursos de dominios específicos. Reduce riesgos de contenido externo."></i>
                        </label>
                        <textarea name="embed_whitelist" class="form-control" rows="2"><?php echo html($embed_whitelist); ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">ACL
                            <i class="bi bi-question-circle ms-1 text-muted" data-bs-toggle="tooltip" data-bs-placement="top" title="Lista de IPs permitidas/restringidas. Útil para limitar acceso a redes internas."></i>
                        </label>
                        <textarea name="acl" class="form-control" rows="2" placeholder="eg. 192.168.1.1, 192.168.2.2"><?php echo html($acl); ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header"><strong>Adjuntos</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-lg-4">
                        <label class="form-label">Almacenar archivos adjuntos
                            <i class="bi bi-question-circle ms-1 text-muted" data-bs-toggle="tooltip" data-bs-placement="top" title="En BD: más simple de respaldar; en archivos: puede ser más eficiente si hay muchos adjuntos."></i>
                        </label>
                        <select name="attachment_storage" class="form-select">
                            <option value="db" <?php echo $attachment_storage === 'db' ? 'selected' : ''; ?>>En la base de datos</option>
                            <option value="fs" <?php echo $attachment_storage === 'fs' ? 'selected' : ''; ?>>En el sistema de archivos</option>
                        </select>
                    </div>
                    <div class="col-12 col-lg-4">
                        <label class="form-label">Tamaño máximo para fichero agente
                            <i class="bi bi-question-circle ms-1 text-muted" data-bs-toggle="tooltip" data-bs-placement="top" title="Limita el tamaño de archivos que los agentes pueden subir. Ayuda a proteger el almacenamiento y rendimiento."></i>
                        </label>
                        <div class="input-group">
                            <input type="number" name="max_agent_file_mb" class="form-control" value="<?php echo html($max_agent_file_mb); ?>" min="1" max="256">
                            <span class="input-group-text">mb</span>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="attachments_require_auth" id="attachments-require-auth" value="1" <?php echo $attachments_require_auth ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="attachments-require-auth">Se requiere autenticación para ver adjuntos
                                <i class="bi bi-question-circle ms-1 text-muted" data-bs-toggle="tooltip" data-bs-placement="top" title="Si está activo, solo usuarios autenticados pueden descargar/ver adjuntos."></i>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-primary">Guardar cambios</button>
        <a class="btn btn-outline-secondary" href="settings.php?t=system">Restaurar</a>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>
<?php
    $content = ob_get_clean();
} else {
    $content = '<div class="page-header"><h1>Configuración</h1><p>Sección en construcción.</p></div>';
}

require_once 'layout_admin.php';
?>