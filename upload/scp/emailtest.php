<?php
require_once '../../config.php';
require_once '../../includes/helpers.php';
require_once '../../includes/Auth.php';
require_once '../../includes/Mailer.php';

if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

requireLogin('agente');
requireRolePermission('admin.access');
$staff = getCurrentUser();
$currentRoute = 'emails';
$emailTab = 'test';

$eid = empresaId();
$emailAccountsHasEmpresaId = false;
if (isset($mysqli) && $mysqli) {
    try {
        $res = $mysqli->query("SHOW COLUMNS FROM email_accounts LIKE 'empresa_id'");
        $emailAccountsHasEmpresaId = ($res && $res->num_rows > 0);
    } catch (Throwable $e) {
        $emailAccountsHasEmpresaId = false;
    }
}

$collapseSettingsMenu = false;
$menuKey = 'admin_sidebar_menu_seen_' . (int)($_SESSION['staff_id'] ?? 0);
if ((string)($_SESSION['sidebar_panel_mode'] ?? '') !== 'admin') {
    unset($_SESSION[$menuKey]);
    $_SESSION['sidebar_panel_mode'] = 'admin';
}
if (!isset($_SESSION[$menuKey])) {
    $_SESSION[$menuKey] = 1;
    $collapseSettingsMenu = true;
}

// Asegurar tabla de cuentas
if (isset($mysqli) && $mysqli) {
    $mysqli->query("CREATE TABLE IF NOT EXISTS email_accounts (\n"
        . "  id INT PRIMARY KEY AUTO_INCREMENT,\n"
        . "  email VARCHAR(255) NOT NULL,\n"
        . "  name VARCHAR(255) NULL,\n"
        . "  priority VARCHAR(32) NULL,\n"
        . "  dept_id INT NULL,\n"
        . "  is_default TINYINT(1) NOT NULL DEFAULT 0,\n"
        . "  smtp_host VARCHAR(255) NULL,\n"
        . "  smtp_port INT NULL,\n"
        . "  smtp_secure VARCHAR(10) NULL,\n"
        . "  smtp_user VARCHAR(255) NULL,\n"
        . "  smtp_pass VARCHAR(255) NULL,\n"
        . "  created DATETIME DEFAULT CURRENT_TIMESTAMP,\n"
        . "  updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
        . "  KEY idx_email (email),\n"
        . "  KEY idx_default (is_default),\n"
        . "  KEY idx_dept (dept_id)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}

$msg = '';
$error = '';

$accounts = [];
$defaultAccount = null;
if (isset($mysqli) && $mysqli) {
    $sqlAcc = 'SELECT * FROM email_accounts';
    if ($emailAccountsHasEmpresaId) {
        $sqlAcc .= ' WHERE empresa_id = ' . (int)$eid;
    }
    $sqlAcc .= ' ORDER BY is_default DESC, id ASC';
    $res = $mysqli->query($sqlAcc);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $accounts[] = $row;
            if ((int)($row['is_default'] ?? 0) === 1 && !$defaultAccount) {
                $defaultAccount = $row;
            }
        }
    }
}

$selectedFromId = isset($_POST['from_id']) && is_numeric($_POST['from_id']) ? (int)$_POST['from_id'] : 0;
$toVal = (string)($_POST['to'] ?? '');
$subjectVal = (string)($_POST['subject'] ?? 'Prueba');
$bodyVal = (string)($_POST['body'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        $error = 'Token CSRF inválido.';
    } else {
        $to = trim((string)($_POST['to'] ?? ''));
        $subject = trim((string)($_POST['subject'] ?? 'Prueba'));
        $bodyHtml = (string)($_POST['body'] ?? '');

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email destino inválido.';
        } else {
            $fromAccount = null;
            if ($selectedFromId > 0) {
                foreach ($accounts as $a) {
                    if ((int)($a['id'] ?? 0) === $selectedFromId) {
                        $fromAccount = $a;
                        break;
                    }
                }
            }
            if (!$fromAccount && $defaultAccount) {
                $fromAccount = $defaultAccount;
            }

            $fromEmail = $fromAccount ? (string)($fromAccount['email'] ?? '') : (defined('MAIL_FROM') ? (string)MAIL_FROM : 'noreply@localhost');
            $fromName = $fromAccount ? (string)($fromAccount['name'] ?? '') : (defined('MAIL_FROM_NAME') ? (string)MAIL_FROM_NAME : 'Sistema');
            if ($fromName === '') {
                $fromName = defined('MAIL_FROM_NAME') ? (string)MAIL_FROM_NAME : 'Sistema';
            }

            $smtpHost = $fromAccount ? (string)($fromAccount['smtp_host'] ?? '') : (defined('SMTP_HOST') ? (string)SMTP_HOST : '');
            $smtpPort = $fromAccount && ($fromAccount['smtp_port'] ?? '') !== '' ? (int)$fromAccount['smtp_port'] : (defined('SMTP_PORT') ? (int)SMTP_PORT : 587);
            $smtpSecure = $fromAccount ? (string)($fromAccount['smtp_secure'] ?? '') : (defined('SMTP_SECURE') ? (string)SMTP_SECURE : 'tls');
            $smtpUser = $fromAccount ? (string)($fromAccount['smtp_user'] ?? '') : (defined('SMTP_USER') ? (string)SMTP_USER : '');
            $smtpPass = $fromAccount ? (string)($fromAccount['smtp_pass'] ?? '') : (defined('SMTP_PASS') ? (string)SMTP_PASS : '');

            $attachments = [];
            if (isset($_FILES['attachments']) && is_array($_FILES['attachments'])) {
                $files = $_FILES['attachments'];
                $n = is_array($files['name'] ?? null) ? count($files['name']) : 0;
                $maxSize = 25 * 1024 * 1024;
                for ($i = 0; $i < $n; $i++) {
                    $err = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;
                    if ($err !== UPLOAD_ERR_OK) continue;
                    $tmp = (string)($files['tmp_name'][$i] ?? '');
                    if ($tmp === '' || !is_readable($tmp)) continue;
                    $size = (int)($files['size'][$i] ?? 0);
                    if ($size <= 0 || $size > $maxSize) continue;
                    $orig = (string)($files['name'][$i] ?? 'archivo');
                    $mime = (string)($files['type'][$i] ?? 'application/octet-stream');
                    if (class_exists('finfo')) {
                        $finfoObj = new finfo(FILEINFO_MIME_TYPE);
                        $det = @$finfoObj->file($tmp);
                        if (is_string($det) && $det !== '') $mime = $det;
                    }
                    $content = @file_get_contents($tmp);
                    if (!is_string($content) || $content === '') continue;
                    $attachments[] = [
                        'filename' => $orig,
                        'contentType' => $mime,
                        'content' => $content,
                    ];
                }
            }

            if ($bodyHtml === '') {
                $bodyHtml = '<p>Mensaje de prueba</p>';
            }

            $bodyHtml = preg_replace_callback(
                '/<img([^>]*?)\bsrc=("|\')data:image\/([^;]+);base64,([A-Za-z0-9+\/\n\r\t\s=]+)\2([^>]*)>/i',
                function ($matches) use (&$attachments) {
                    $tagAttrs = $matches[1];
                    $subtype = strtolower((string)$matches[3]);
                    $base64 = (string)$matches[4];
                    $rest = $matches[5];

                    $base64 = preg_replace('/\s+/', '', $base64);
                    $data = base64_decode($base64);
                    if ($data === false || $data === '') {
                        return $matches[0];
                    }

                    $cid = 'img' . uniqid() . '@emailtest';
                    $ext = $subtype === 'jpeg' ? 'jpg' : $subtype;
                    $filename = 'image_' . uniqid() . '.' . $ext;
                    $attachments[] = [
                        'filename' => $filename,
                        'contentType' => 'image/' . $subtype,
                        'content' => $data,
                        'cid' => $cid,
                    ];

                    return '<img' . $tagAttrs . 'src="cid:' . $cid . '"' . $rest . '>';
                },
                $bodyHtml
            );

            $bodyHtml = preg_replace_callback(
                '/<video([^>]*?)\bsrc=("|\')data:video\/([^;]+);base64,([A-Za-z0-9+\/\n\r\t\s=]+)\2([^>]*)>(.*?)<\/video>/is',
                function ($matches) use (&$attachments) {
                    $subtype = strtolower((string)$matches[3]);
                    $base64 = (string)$matches[4];
                    $base64 = preg_replace('/\s+/', '', $base64);
                    $data = base64_decode($base64);
                    if ($data === false || $data === '') {
                        return $matches[0];
                    }

                    $filename = 'video_' . uniqid() . '.' . $subtype;
                    $attachments[] = [
                        'filename' => $filename,
                        'contentType' => 'video/' . $subtype,
                        'content' => $data,
                    ];

                    $downloadName = htmlspecialchars($filename, ENT_QUOTES, 'UTF-8');
                    return '<div style="border:1px solid #ddd;padding:8px;margin:4px 0;background:#f9f9f9;">'
                        . '<p style="margin:0;font-size:0.9em;color:#555;">Video adjunto: ' . $downloadName . '</p>'
                        . '</div>';
                },
                $bodyHtml
            );

            $bodyHtml = preg_replace_callback(
                '/<source([^>]*?)\bsrc=("|\')data:video\/([^;]+);base64,([A-Za-z0-9+\/\n\r\t\s=]+)\2([^>]*)>/i',
                function ($matches) use (&$attachments) {
                    $subtype = strtolower((string)$matches[3]);
                    $base64 = (string)$matches[4];
                    $base64 = preg_replace('/\s+/', '', $base64);
                    $data = base64_decode($base64);
                    if ($data === false || $data === '') {
                        return $matches[0];
                    }

                    $filename = 'video_' . uniqid() . '.' . $subtype;
                    $attachments[] = [
                        'filename' => $filename,
                        'contentType' => 'video/' . $subtype,
                        'content' => $data,
                    ];

                    return '<source' . $matches[1] . 'src=""' . $matches[5] . '>';
                },
                $bodyHtml
            );

            $opts = [
                'from' => $fromEmail,
                'fromName' => $fromName,
                'smtp' => [
                    'host' => $smtpHost,
                    'port' => $smtpPort,
                    'secure' => $smtpSecure,
                    'user' => $smtpUser,
                    'pass' => $smtpPass,
                ],
                'attachments' => $attachments,
            ];

            $ok = Mailer::sendWithOptions($to, $subject, $bodyHtml, strip_tags($bodyHtml), $opts);
            if ($ok) {
                $msg = 'Correo de prueba enviado correctamente.';
                $selectedFromId = 0;
                $toVal = '';
                $subjectVal = 'Prueba';
                $bodyVal = '';
            } else {
                $error = 'Falló el envío: ' . (Mailer::$lastError ?: 'Error desconocido');
            }
        }
    }
}

ob_start();
?>

<div class="settings-hero">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-3">
            <span class="settings-hero-icon"><i class="bi bi-envelope"></i></span>
            <div>
                <h1>Correos Electrónicos</h1>
                <p>Diagnóstico / Envío de prueba</p>
            </div>
        </div>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?php echo html($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i><?php echo html($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<style>
  #emailtest-loading-overlay{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.25);z-index:2000}
  #emailtest-loading-overlay .box{background:#fff;border-radius:14px;padding:18px 22px;min-width:320px;max-width:92vw;box-shadow:0 10px 30px rgba(0,0,0,.25)}
  body.dark-mode #emailtest-loading-overlay .box{background:#000000; color:#eee;}
  
  .compose-container {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.05);
      overflow: hidden;
      border: 1px solid #e2e8f0;
  }
  body.dark-mode .compose-container {
      background: #000000;
      border-color: #333;
  }
  .compose-header {
      background: #f8fafc;
      padding: 16px 20px;
      border-bottom: 1px solid #e2e8f0;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 10px;
      color: #1e293b;
  }
  body.dark-mode .compose-header {
      background: #000000;
      border-color: #333;
      color: #eee;
  }
  .compose-body {
      padding: 24px;
  }
  .compose-row {
      display: flex;
      align-items: center;
      border-bottom: 1px solid #e2e8f0;
      padding: 8px 0;
      transition: border-color 0.2s;
      flex-wrap: wrap;
  }
  body.dark-mode .compose-row { border-color: #333; }
  .compose-row:focus-within { border-color: #3b82f6; }
  .compose-label {
      width: 80px;
      color: #64748b;
      font-weight: 500;
      font-size: 0.9rem;
      flex-shrink: 0;
  }
  @media (max-width: 576px) {
      .compose-label { width: 100%; margin-bottom: 4px; font-size: 0.85rem; }
      .compose-input, .select2-container { flex: 0 0 100% !important; width: 100% !important; min-width: 100% !important; }
  }
  body.dark-mode .compose-label { color: #aaa; }
  .compose-input {
      border: none;
      background: transparent;
      outline: none;
      flex: 1;
      font-size: 0.95rem;
      color: #1e293b;
      min-width: 0;
  }
  body.dark-mode .compose-input { color: #eee; }
  
  /* Select2/Select styling reset for inline feel */
  .compose-select {
      border: none;
      background: transparent;
      outline: none;
      flex: 1;
      font-size: 0.95rem;
      color: #1e293b;
      cursor: pointer;
      appearance: none;
      -webkit-appearance: none;
      background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
      background-repeat: no-repeat;
      background-position: right 0.5rem center;
      background-size: 16px 12px;
      padding-right: 2rem;
      min-width: 0;
      text-overflow: ellipsis;
      overflow: hidden;
      white-space: nowrap;
  }
  body.dark-mode .compose-select {
      color: #eee;
      background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23eeeeee' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
  }
  .compose-select:focus { outline: none; box-shadow: none; }

  /* Dropzone & Preview */
  .upload-dropzone {
      border: 2px dashed #cbd5e1;
      border-radius: 12px;
      padding: 30px;
      text-align: center;
      background-color: #f8fafc;
      cursor: pointer;
      transition: all 0.2s ease;
      margin-top: 20px;
  }
  .upload-dropzone:hover, .upload-dropzone.dragover {
      background-color: #f1f5f9;
      border-color: #3b82f6;
  }
  body.dark-mode .upload-dropzone {
      background-color: #000000;
      border-color: #444;
  }
  body.dark-mode .upload-dropzone:hover, body.dark-mode .upload-dropzone.dragover {
      background-color: #333;
      border-color: #3b82f6;
  }
  .file-preview-card {
      position: relative;
      width: 100px;
      height: 100px;
      border-radius: 8px;
      border: 1px solid #e2e8f0;
      overflow: hidden;
      background: #fff;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      box-shadow: 0 2px 4px rgba(0,0,0,0.05);
  }
  body.dark-mode .file-preview-card {
      background: #000000;
      border-color: #444;
  }
  .file-preview-card img {
      width: 100%;
      height: 100%;
      object-fit: cover;
  }
  .file-preview-card .file-icon {
      font-size: 2.5rem;
      color: #94a3b8;
  }
  .file-preview-card .file-name {
      font-size: 0.65rem;
      text-align: center;
      padding: 4px;
      width: 100%;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      background: rgba(255,255,255,0.9);
      position: absolute;
      bottom: 0;
      color: #333;
  }
  body.dark-mode .file-preview-card .file-name {
      background: rgba(30,30,30,0.9);
      color: #ccc;
  }
  .file-remove-btn {
      position: absolute;
      top: 4px;
      right: 4px;
      background: rgba(239, 68, 68, 0.9);
      color: white;
      border: none;
      border-radius: 50%;
      width: 22px;
      height: 22px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      font-size: 0.75rem;
      z-index: 10;
      transition: background 0.2s;
  }
  .file-remove-btn:hover { background: #dc2626; color: white;}
  .note-editor.note-frame {
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      margin-top: 16px;
      overflow: hidden;
  }
  body.dark-mode .note-editor.note-frame { border-color: #333; }
</style>

<div id="emailtest-loading-overlay" role="status" aria-live="polite" aria-busy="true">
  <div class="box">
    <div class="d-flex align-items-center gap-3 mb-3">
      <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
      <div>
        <div class="fw-semibold">Enviando correo…</div>
        <div class="text-muted small">Por favor espera</div>
      </div>
    </div>
    <div class="progress" style="height:8px;">
      <div class="progress-bar progress-bar-striped progress-bar-animated" style="width:100%"></div>
    </div>
  </div>
</div>

<div class="row">
    <div class="col-12">
        <div style="max-width: 920px; margin: 0 auto;">
            <form id="emailtest-form" method="post" action="emailtest.php" enctype="multipart/form-data" class="compose-container">
                <?php csrfField(); ?>
                
                <div class="compose-header">
                    <i class="bi bi-envelope-paper"></i> Nuevo Mensaje de Prueba
                </div>
                
                <div class="compose-body">
                    <div class="compose-row">
                        <div class="compose-label">De</div>
                        <select class="compose-select" name="from_id">
                            <option value="0">— Seleccione correo electrónico del emisor —</option>
                            <?php foreach ($accounts as $a): ?>
                                <?php
                                $aid = (int)($a['id'] ?? 0);
                                $label = trim((string)($a['name'] ?? ''));
                                if ($label === '') $label = (string)($a['email'] ?? '');
                                $label .= ' <' . (string)($a['email'] ?? '') . '>';
                                if ((int)($a['is_default'] ?? 0) === 1) $label .= ' (Por defecto)';
                                ?>
                                <option value="<?php echo $aid; ?>" <?php echo $selectedFromId === $aid ? 'selected' : ''; ?>><?php echo html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="compose-row">
                        <div class="compose-label">Para</div>
                        <input type="email" name="to" class="compose-input" required value="<?php echo html($toVal); ?>" placeholder="destino@correo.com">
                    </div>

                    <div class="compose-row border-0 mb-2">
                        <div class="compose-label">Asunto</div>
                        <input type="text" name="subject" class="compose-input" value="<?php echo html($subjectVal); ?>" placeholder="Escribe el asunto aquí...">
                    </div>

                    <textarea name="body" id="emailtest_body" class="form-control" rows="8" placeholder="Escribe tu mensaje aquí..." style="border: none; border-top: 1px solid #e2e8f0; border-radius: 0; padding: 16px 0; resize: none; background: transparent; outline: none; box-shadow: none;"><?php echo html($bodyVal); ?></textarea>

                    <div class="upload-dropzone mt-0" id="file-dropzone">
                        <i class="bi bi-cloud-arrow-up display-4 text-muted mb-2"></i>
                        <h6 class="mb-1 fw-bold">Adjuntar archivos</h6>
                        <p class="mb-0 text-muted small">Arrastra tus archivos aquí o haz clic para subir</p>
                        <input type="file" name="attachments[]" id="attachments-input" class="d-none" multiple>
                    </div>
                    <div id="file-preview-container" class="mt-3 d-flex flex-wrap gap-2"></div>

                    <div class="d-flex gap-2 mt-4 pt-3 border-top" style="border-color: #e2e8f0 !important;">
                        <button id="emailtest-submit" type="submit" class="btn btn-primary px-4 py-2" style="border-radius: 8px; font-weight: 600;">
                            <i class="bi bi-send-fill me-2"></i> Enviar
                        </button>
                        <a href="emailtest.php" class="btn btn-light px-3 py-2" style="border-radius: 8px;">Limpiar</a>
                    </div>
                </div>
            </form>
        </div>
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
/* Select2 Custom Inline Styling */
.select2-container {
    flex: 1;
    min-width: 0;
}
.select2-container--default .select2-selection--single {
    border: none !important;
    background: transparent !important;
    height: auto !important;
    padding: 0 !important;
    outline: none !important;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: normal !important;
    padding-left: 0 !important;
    color: #1e293b !important;
    font-size: 0.95rem;
}
body.dark-mode .select2-container--default .select2-selection--single .select2-selection__rendered {
    color: #eee !important;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 100% !important;
    right: 0 !important;
}
.select2-container--default .select2-selection--single .select2-selection__arrow b {
    border-color: #888 transparent transparent transparent !important;
    border-width: 5px 4px 0 4px !important;
}
body.dark-mode .select2-container--default .select2-selection--single .select2-selection__arrow b {
    border-color: #ccc transparent transparent transparent !important;
}
.select2-dropdown {
    border: 1px solid #e2e8f0 !important;
    border-radius: 8px !important;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
    overflow: hidden;
    padding: 4px 0;
}
body.dark-mode .select2-dropdown {
    background-color: #000000 !important;
    border-color: #444 !important;
}
.select2-container--default .select2-results__option {
    padding: 8px 16px !important;
    font-size: 0.9rem;
    transition: background 0.1s;
}
.select2-container--default .select2-results__option--highlighted.select2-results__option--selectable {
    background-color: #eff6ff !important;
    color: #1d4ed8 !important;
}
body.dark-mode .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable {
    background-color: #3b82f6 !important;
    color: #fff !important;
}
.select2-container--default .select2-results__option[aria-selected=true] {
    background-color: #f8fafc !important;
    color: #1e293b !important;
    font-weight: 600;
}
body.dark-mode .select2-container--default .select2-results__option[aria-selected=true] {
    background-color: #000000 !important;
    color: #eee !important;
}
.select2-search--dropdown .select2-search__field {
    border: 1px solid #cbd5e1 !important;
    border-radius: 6px !important;
    padding: 6px 10px !important;
    outline: none !important;
}
body.dark-mode .select2-search--dropdown .select2-search__field {
    background: #000000 !important;
    border-color: #444 !important;
    color: #eee !important;
}
</style>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/lang/summernote-es-ES.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
  (function(){
    // --- UI Loading ---
    function showEmailTestLoading(){
      var overlay = document.getElementById('emailtest-loading-overlay');
      if (overlay) overlay.style.display = 'flex';
      var btn = document.getElementById('emailtest-submit');
      if (btn) btn.disabled = true;
    }

    try {
      var form = document.getElementById('emailtest-form');
      if (form) {
        form.addEventListener('submit', function(){
          showEmailTestLoading();
        });
      }
    } catch (e) {}

    // Init Select2
    if (typeof window.jQuery !== 'undefined') {
        jQuery(function() {
            jQuery('.compose-select').select2({
                minimumResultsForSearch: 5,
                width: '100%'
            });
        });
    }

    // Estilo dark mode para textarea simple
    if(document.body.classList.contains('dark-mode')) {
        var textarea = document.getElementById('emailtest_body');
        if (textarea) {
            textarea.style.color = '#eee';
            textarea.style.borderTopColor = '#333';
        }
        var formRows = document.querySelectorAll('.compose-row');
        formRows.forEach(function(row) {
            row.style.borderColor = '#333';
        });
        var composeHeader = document.querySelector('.compose-header');
        if(composeHeader) {
            composeHeader.style.borderColor = '#333';
        }
        var btnGroup = document.querySelector('.compose-body > .d-flex.border-top');
        if(btnGroup) {
            btnGroup.style.borderColor = '#333 !important';
        }
    }

    // --- Upload Dropzone & Preview ---
    var dropzone = document.getElementById('file-dropzone');
    var fileInput = document.getElementById('attachments-input');
    var previewContainer = document.getElementById('file-preview-container');
    var dt = new DataTransfer(); // Permite manipular archivos seleccionados

    if(dropzone && fileInput) {
        dropzone.addEventListener('click', function() {
            fileInput.click();
        });

        dropzone.addEventListener('dragover', function(e) {
            e.preventDefault();
            dropzone.classList.add('dragover');
        });

        dropzone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            dropzone.classList.remove('dragover');
        });

        dropzone.addEventListener('drop', function(e) {
            e.preventDefault();
            dropzone.classList.remove('dragover');
            handleFiles(e.dataTransfer.files);
        });

        fileInput.addEventListener('change', function() {
            handleFiles(this.files);
        });
    }

    function handleFiles(files) {
        for (var i = 0; i < files.length; i++) {
            dt.items.add(files[i]);
            createPreview(files[i], dt.items.length - 1);
        }
        fileInput.files = dt.files;
    }

    function createPreview(file, index) {
        var card = document.createElement('div');
        card.className = 'file-preview-card';
        card.dataset.index = index;

        var removeBtn = document.createElement('button');
        removeBtn.className = 'file-remove-btn';
        removeBtn.innerHTML = '<i class="bi bi-x"></i>';
        removeBtn.type = 'button';
        removeBtn.onclick = function(e) {
            e.stopPropagation();
            removeFile(file.name, card);
        };

        var content = document.createElement('div');
        
        if (file.type.startsWith('image/')) {
            var img = document.createElement('img');
            var reader = new FileReader();
            reader.onload = function(e) { img.src = e.target.result; }
            reader.readAsDataURL(file);
            content.appendChild(img);
        } else {
            var icon = document.createElement('i');
            icon.className = 'bi bi-file-earmark-text file-icon';
            if (file.type.includes('pdf')) icon.className = 'bi bi-file-earmark-pdf file-icon text-danger';
            else if (file.type.includes('zip') || file.type.includes('rar')) icon.className = 'bi bi-file-earmark-zip file-icon text-warning';
            else if (file.type.includes('word') || file.name.endsWith('.docx')) icon.className = 'bi bi-file-earmark-word file-icon text-primary';
            else if (file.type.includes('excel') || file.name.endsWith('.xlsx')) icon.className = 'bi bi-file-earmark-excel file-icon text-success';
            
            content.appendChild(icon);
        }

        var nameBar = document.createElement('div');
        nameBar.className = 'file-name';
        nameBar.textContent = file.name;
        nameBar.title = file.name;

        card.appendChild(removeBtn);
        card.appendChild(content);
        card.appendChild(nameBar);
        previewContainer.appendChild(card);
    }

    function removeFile(fileName, cardEl) {
        var newDt = new DataTransfer();
        for (var i = 0; i < dt.files.length; i++) {
            if (dt.files[i].name !== fileName) {
                newDt.items.add(dt.files[i]);
            }
        }
        dt = newDt;
        fileInput.files = dt.files;
        cardEl.remove();
    }
  })();
</script>

<?php
$content = ob_get_clean();
require_once 'layout_admin.php';
?>
