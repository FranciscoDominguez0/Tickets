<?php
require '../config.php';
require '../includes/helpers.php';
$_GET['view']='org'; 
$_GET['org_id']='10'; 
$_GET['list']='quotes'; 
$_SESSION['user_id']=30; 
$_SESSION['empresa_id']=1; 
ob_start(); 
require 'tickets.php';
ob_get_clean(); 
echo "\nMode is: " . $orgExplorerListMode;
