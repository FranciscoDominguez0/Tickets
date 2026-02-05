<?php

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

    return [true, rtrim($publicPrefix, '/') . '/' . $name];
}
