<?php
/**
 * Endpoint de errores HTTP
 *
 * Apache llama a este script a través de los ErrorDocument configurados en .htaccess.
 * Lee el código HTTP real desde REDIRECT_STATUS (variable que Apache inyecta
 * automáticamente cuando hace el redirect interno al ErrorDocument).
 *
 * NO usa query strings (?code=X) porque Apache a veces las descarta.
 */

require_once dirname(__DIR__) . '/config.php';

// REDIRECT_STATUS es la variable que Apache inyecta con el código HTTP real
// cuando procesa un ErrorDocument. Es más fiable que parámetros GET.
$httpCode = (int)($_SERVER['REDIRECT_STATUS'] ?? 0);

// También aceptar el parámetro ?code= como fallback (llamadas directas)
if ($httpCode === 0 && isset($_GET['code'])) {
    $httpCode = (int)$_GET['code'];
}

// Normalizar a códigos conocidos
$validCodes = [400, 403, 404, 500, 503];
if (!in_array($httpCode, $validCodes, true)) {
    // Si no podemos determinar el código, asumir 500
    $httpCode = $httpCode > 0 ? $httpCode : 500;
}

// Generar código ERR- único y loguear
$errorCode = ErrorHandler::generateErrorCode();

ErrorHandler::logError($errorCode, [
    'type'    => "HTTP{$httpCode}",
    'errno'   => $httpCode,
    'message' => "HTTP {$httpCode} — " . ($_SERVER['REDIRECT_URL'] ?? $_SERVER['REQUEST_URI'] ?? '(URL desconocida)'),
    'file'    => 'error.php (vía Apache ErrorDocument)',
    'line'    => 0,
]);

// Renderizar la página de error amigable
ErrorHandler::renderErrorPage($httpCode, $errorCode);
