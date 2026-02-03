<?php
require_once '../config.php';
header('Content-Type: text/plain; charset=utf-8');
echo "PHP version: " . PHP_VERSION . PHP_EOL;
echo "Loaded ini: " . php_ini_loaded_file() . PHP_EOL;
echo "OpenSSL loaded: " . (extension_loaded('openssl') ? 'YES' : 'NO') . PHP_EOL;
if (function_exists('openssl_get_cert_locations')) {
    print_r(openssl_get_cert_locations());
}
