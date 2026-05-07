<?php
require_once '../../config.php';
require_once '../../includes/helpers.php';
require_once '../../includes/Auth.php';

if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

// La configuración de destinatarios de notificación fue movida a notifications_admin.php
header('Location: notifications_admin.php');
exit;
