<?php
require 'config.php';
require 'includes/helpers.php';
$_GET['view'] = 'org';
$_GET['org_id'] = '10';
$_GET['list'] = 'quotes';
$_SESSION['user_id'] = 30; // Boss of org 10
$_SESSION['empresa_id'] = 1;
ob_start();
require 'upload/tickets.php';
$html = ob_get_clean();
file_put_contents('test_output.html', $html);
echo "HTML written to test_output.html. Length: " . strlen($html) . "\n";
