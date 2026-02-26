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
$subjectVal = (string)($_POST['subject'] ?? 'osTicket test email');
$bodyVal = (string)($_POST['body'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        $error = 'Token CSRF inválido.';
    } else {
        $to = trim((string)($_POST['to'] ?? ''));
        $subject = trim((string)($_POST['subject'] ?? 'osTicket test email'));
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
                    if (function_exists('finfo_open')) {
                        $fi = @finfo_open(FILEINFO_MIME_TYPE);
                        if ($fi) {
                            $det = @finfo_file($fi, $tmp);
                            @finfo_close($fi);
                            if (is_string($det) && $det !== '') $mime = $det;
                        }
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
                $subjectVal = 'osTicket test email';
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
        <div class="card settings-card">
            <div class="card-header">
                <strong><i class="bi bi-send"></i> Comprobar el correo electrónico saliente</strong>
            </div>
            <div class="card-body" style="padding: 12px;">
                <div style="max-width: 920px; margin: 0 auto;">
                <form id="emailtest-form" method="post" action="emailtest.php" enctype="multipart/form-data">
                    <?php csrfField(); ?>
                    <div class="alert alert-info py-2 mb-2">Utilice el siguiente formulario para comprobar que su configuración de <strong>Correo electrónico saliente</strong> esté establecida correctamente.</div>

                    <div class="mb-2">
                        <label class="form-label">De:</label>
                        <select class="form-select form-select-sm" name="from_id">
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
                        <div class="text-muted small mt-1">Si no seleccionas ninguno, se usará el correo por defecto.</div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Para:</label>
                        <input type="email" name="to" class="form-control form-control-sm" required value="<?php echo html($toVal); ?>" placeholder="destino@correo.com">
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Asunto:</label>
                        <input type="text" name="subject" class="form-control form-control-sm" value="<?php echo html($subjectVal); ?>" placeholder="osTicket test email">
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Mensaje:</label>
                        <textarea name="body" id="emailtest_body" class="form-control" rows="10"><?php echo html($bodyVal); ?></textarea>
                    </div>

                    <div class="mb-2">
                        <input type="file" name="attachments[]" class="form-control form-control-sm" multiple>
                    </div>

                    <div class="d-flex gap-2">
                        <button id="emailtest-submit" type="submit" class="btn btn-primary btn-sm"><i class="bi bi-envelope"></i> Enviar mensaje</button>
                        <a href="emailtest.php" class="btn btn-outline-secondary btn-sm">Restablecer</a>
                        <a href="emails.php" class="btn btn-outline-secondary btn-sm">Cancelar</a>
                    </div>
                </form>
                </div>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/lang/summernote-es-ES.min.js"></script>
<script>
  (function(){
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

    if (typeof window.jQuery === 'undefined') return;
    try {
      jQuery(function(){
        var el = jQuery('#emailtest_body');
        if (!el.length) return;
        el.summernote({
          height: 180,
          lang: 'es-ES',
          toolbar: [
            ['style', ['style']],
            ['font', ['bold', 'italic', 'underline', 'clear']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['insert', ['link', 'picture', 'video']],
            ['view', ['codeview']]
          ],
          callbacks: {
            onImageUpload: function(files) {
              for (var i = 0; i < files.length; i++) {
                var reader = new FileReader();
                reader.onload = function(e) {
                  jQuery('#emailtest_body').summernote('insertImage', e.target.result);
                };
                reader.readAsDataURL(files[i]);
              }
            }
          }
        });
      });
    } catch (e) {}
  })();
</script>

<?php
$content = ob_get_clean();
require_once 'layout_admin.php';
?>
