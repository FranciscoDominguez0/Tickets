<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/helpers.php';

$ok = syncAllEmpresasBillingStatus();

if (PHP_SAPI === 'cli') {
    $ts = date('Y-m-d H:i:s');
    fwrite(STDOUT, ($ok ? 'OK' : 'ERROR') . " billing_daily {$ts}\n");
}

exit($ok ? 0 : 1);
