<?php
/**
 * Panel Oculto de Restauración de Backups
 * 
 * Uso: /restore_panel_v2026.php?token=TICKETAdmin2026!
 */

$SECRET_TOKEN = 'TICKETAdmin2026!';

if (!isset($_GET['token']) || $_GET['token'] !== $SECRET_TOKEN) {
    header('HTTP/1.1 403 Forbidden');
    die("<h1>403 Forbidden</h1>");
}

require_once __DIR__ . '/config.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['backup_zip'])) {
    $zipFile = $_FILES['backup_zip']['tmp_name'];
    
    if (!$zipFile || $_FILES['backup_zip']['error'] !== UPLOAD_ERR_OK) {
        $error = "Error al subir el archivo.";
    } else {
        $zip = new ZipArchive;
        if ($zip->open($zipFile) === TRUE) {
            
            $tmpDir = sys_get_temp_dir() . '/backup_' . uniqid();
            mkdir($tmpDir);
            
            $validFiles = [];
            $hasDb = false;
            
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $origFilename = $zip->getNameIndex($i);
                $filename = str_replace('\\', '/', $origFilename);
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                if (in_array($ext, ['php', 'php3', 'php4', 'php5', 'phtml', 'exe', 'sh', 'bat', 'js', 'html', 'htm'])) {
                    continue;
                }
                
                if (strpos($filename, 'database/') !== false && $ext === 'sql') {
                    $hasDb = true;
                    $validFiles[] = $origFilename;
                } else if (strpos($filename, 'firmas/') !== false && $ext === 'png') {
                    $validFiles[] = $origFilename;
                } else if (strpos($filename, 'attachments/') !== false && $ext !== '') {
                    $validFiles[] = $origFilename;
                }
            }
            
            if (!$hasDb) {
                $error = "El archivo ZIP no contiene un volcado de base de datos válido (.sql en carpeta database/).";
            } else {
                $zip->extractTo($tmpDir, $validFiles);
                $zip->close();
                
                $successLog = [];
                
                function searchFiles($dir, $pattern) {
                    $files = [];
                    $ite = new RecursiveDirectoryIterator($dir);
                    foreach (new RecursiveIteratorIterator($ite) as $filename => $cur) {
                        if (preg_match($pattern, $filename)) {
                            $files[] = $filename;
                        }
                    }
                    return $files;
                }
                
                if (!isset($mysqli) || $mysqli->connect_error) {
                    $error = "Error conectando a la base de datos.";
                } else {
                    $sqlFiles = searchFiles($tmpDir, '/\.sql$/i');
                    if (!empty($sqlFiles)) {
                        $sqlFile = $sqlFiles[0];
                        
                        // VERIFICACIÓN DE SEGURIDAD (Es DB de tickets?)
                        $sqlContentHeader = file_get_contents($sqlFile, false, null, 0, 20480);
                        if (stripos($sqlContentHeader, 'tickets_db') === false && stripos($sqlContentHeader, 'ticket_status') === false && stripos($sqlContentHeader, 'users') === false) {
                            $error = "Error: El archivo SQL no pertenece a este sistema.";
                        } else {
                            // LIMPIEZA TOTAL DE DB (dejame la db limpia)
                            $mysqli->query("DROP DATABASE IF EXISTS `" . DB_NAME . "`");
                            $mysqli->query("CREATE DATABASE `" . DB_NAME . "`");
                            $mysqli->select_db(DB_NAME);
                            
                            $fp = fopen($sqlFile, 'r');
                            if ($fp) {
                                $templine = '';
                                $mysqli->query("SET FOREIGN_KEY_CHECKS=0;");
                                while (($line = fgets($fp)) !== false) {
                                    if (substr(trim($line), 0, 2) == '--' || trim($line) == '') continue;
                                    
                                    $line = preg_replace('/DEFINER\s*=\s*`?[a-zA-Z0-9_\-\.]+`?@`?[a-zA-Z0-9_\-\.\%]+`?/i', '', $line);
                                    
                                    $templine .= $line;
                                    if (substr(trim($line), -1, 1) == ';') {
                                        if (!$mysqli->query($templine)) {
                                            $error .= "Error SQL: " . $mysqli->error . "<br>";
                                        }
                                        $templine = '';
                                    }
                                }
                                $mysqli->query("SET FOREIGN_KEY_CHECKS=1;");
                                fclose($fp);
                                $successLog[] = "Base de datos restaurada correctamente en limpio.";
                            }
                        }
                    }
                    
                    if (empty($error)) {
                        $firmasDir = rtrim(__DIR__ . '/firmas', '/\\') . '/';
                        if (!is_dir($firmasDir)) mkdir($firmasDir, 0755, true);
                        $extractedFirmas = searchFiles($tmpDir, '/firmas[\\\\\/].*\.png$/i');
                        $countFirmas = 0;
                        foreach ($extractedFirmas as $file) {
                            $pos = stripos($file, 'firmas/');
                            if ($pos === false) $pos = stripos($file, 'firmas\\');
                            if ($pos !== false) {
                                $relativePath = substr($file, $pos + 7);
                                $relativePath = str_replace('\\', '/', $relativePath);
                                $destPath = $firmasDir . $relativePath;
                                $destDir = dirname($destPath);
                                if (!is_dir($destDir)) mkdir($destDir, 0755, true);
                                if (copy($file, $destPath)) {
                                    $countFirmas++;
                                }
                            }
                        }
                        $successLog[] = "$countFirmas firmas restauradas.";
                        
                        $attachmentsDir = rtrim(__DIR__ . '/upload/uploads/attachments', '/\\') . '/';
                        if (!is_dir($attachmentsDir)) mkdir($attachmentsDir, 0755, true);
                        $extractedAttachments = searchFiles($tmpDir, '/attachments[\\\\\/].+$/i');
                        $countAtt = 0;
                        foreach ($extractedAttachments as $file) {
                            if (is_file($file)) {
                                $pos = stripos($file, 'attachments/');
                                if ($pos === false) $pos = stripos($file, 'attachments\\');
                                if ($pos !== false) {
                                    $relativePath = substr($file, $pos + 12);
                                    $relativePath = str_replace('\\', '/', $relativePath);
                                    $destPath = $attachmentsDir . $relativePath;
                                    $destDir = dirname($destPath);
                                    if (!is_dir($destDir)) mkdir($destDir, 0755, true);
                                    if (copy($file, $destPath)) {
                                        $countAtt++;
                                    }
                                }
                            }
                        }
                        $successLog[] = "$countAtt adjuntos restaurados.";
                    }
                    
                    function rrmdir($dir) { 
                        if (is_dir($dir)) { 
                            $objects = scandir($dir);
                            foreach ($objects as $object) { 
                                if ($object != "." && $object != "..") { 
                                    if (is_dir($dir. DIRECTORY_SEPARATOR .$object) && !is_link($dir."/".$object))
                                        rrmdir($dir. DIRECTORY_SEPARATOR .$object);
                                    else
                                        unlink($dir. DIRECTORY_SEPARATOR .$object); 
                                } 
                            }
                            rmdir($dir); 
                        } 
                    }
                    rrmdir($tmpDir);
                    
                    if (empty($error)) {
                        $message = implode("<br>", $successLog);
                    }
                }
            }
        } else {
            $error = "No se pudo abrir el archivo ZIP.";
        }
    }
}

if (isset($_POST['auto_destruct'])) {
    unlink(__FILE__);
    header('Location: /');
    exit;
}

// Configuración visual desde Helpers (igual que login.php)
require_once __DIR__ . '/includes/helpers.php';
$brandLogo = (string)getCompanyLogoUrl('publico/img/vigitec-logo.webp');
$bgMode = (string)getAppSetting('login.background_mode', 'default');
$loginBg = $bgMode === 'custom' ? (string)getBrandAssetUrl('login.background', '') : '';
$bodyStyle = $loginBg !== ''
    ? ('background-color: #f6f7fb; background-image: linear-gradient(135deg, rgba(240, 244, 248, 0.92) 0%, rgba(226, 232, 240, 0.92) 100%), url(' . html($loginBg) . '); background-size: cover, cover; background-position: center, center; background-repeat: no-repeat, no-repeat;')
    : '';
$isPortalDarkModeEnabled = (string)getAppSetting('portal.dark_mode_enabled', '1') === '1';
if ($isPortalDarkModeEnabled) {
    $isDarkMode = !isset($_COOKIE['client_dark_mode']) || $_COOKIE['client_dark_mode'] === '1';
} else {
    $isDarkMode = false;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Restauración - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="scp/css/vendor/bootstrap-icons-1.11.1.css">
    <link rel="stylesheet" href="publico/css/login.css?v=<?php echo (int)@filemtime(__DIR__ . '/publico/css/login.css'); ?>">
</head>
<body style="<?php echo $bodyStyle; ?>" class="<?php echo $isDarkMode ? 'dark-mode' : ''; ?>">
    <link rel="stylesheet" href="upload/css/client_dark.css?v=<?php echo (int)@filemtime(__DIR__ . '/upload/css/client_dark.css'); ?>">
    <div class="support-center-wrapper">
        <div class="support-header">
            <div class="support-header-left">
                <img src="<?php echo html($brandLogo); ?>" alt="Logo" class="vigitec-logo">
            </div>
            <div class="support-header-right d-flex align-items-center gap-3">
                <a href="upload/login.php" class="header-login-link">Volver</a>
            </div>
        </div>

        <div class="support-nav">
            <button class="nav-item active">Panel de Restauración Seguro</button>
        </div>

        <div class="support-content">
            <div class="welcome-section">
                <h2 class="welcome-title">Restauración del Sistema</h2>
                <p class="welcome-text">Sube el archivo .zip de backup. El sistema se limpiará automáticamente y se restaurarán los datos originales.</p>
            </div>

            <div class="login-panel" style="max-width: 600px; margin: 0 auto; display: block;">
                <div class="login-form-header text-center" style="margin-bottom: 25px;">
                    <h2 class="login-form-title">Importar Backup</h2>
                </div>
                <form method="post" enctype="multipart/form-data" class="login-form">
                    <?php if ($error): ?>
                        <div class="alert alert-danger" style="background:#7f1d1d; color:#fecaca; border:1px solid #991b1b; padding:15px; border-radius:6px; margin-bottom:20px;"><?php echo $error; ?></div>
                    <?php endif; ?>
                    <?php if ($message): ?>
                        <div class="alert alert-success" style="background:#14532d; color:#bbf7d0; border:1px solid #166534; padding:15px; border-radius:6px; margin-bottom:20px;"><?php echo $message; ?></div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="backup_zip" style="font-weight: 500; display:block; margin-bottom: 10px;">Archivo de Backup (.zip)</label>
                        <input type="file" id="backup_zip" name="backup_zip" accept=".zip" required style="width:100%; padding: 12px; background: rgba(0,0,0,0.1); border: 1px solid rgba(255,255,255,0.2); border-radius: 6px; color: inherit;">
                    </div>

                    <button type="submit" class="btn-login" style="margin-top: 15px;">Iniciar Restauración</button>
                </form>

                <?php if ($message): ?>
                <hr style="border-color: rgba(255,255,255,0.1); margin: 30px 0;">
                <form method="post" class="text-center">
                    <input type="hidden" name="auto_destruct" value="1">
                    <button type="submit" class="btn-login" style="background: #dc2626;" onclick="return confirm('¿Estás seguro de eliminar este panel de restauración?');">🗑️ Autodestruir Panel</button>
                    <p class="agent-text" style="margin-top:10px;">Por seguridad, destruye este archivo tras finalizar la restauración.</p>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
