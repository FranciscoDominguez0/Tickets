<?php
require 'config.php';
$_GET['id'] = 1;
$_SESSION['user_id'] = 1;
$t = hash_hmac('sha256', "1", SECRET_KEY);
$_GET['t'] = $t;
require 'upload/ticket_pdf.php';
