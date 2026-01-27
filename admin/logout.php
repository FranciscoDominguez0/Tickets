<?php
/**
 * LOGOUT ADMIN/AGENTE
 */

require_once '../config.php';

session_destroy();
header('Location: ../upload/scp/login.php?msg=logout_success');
exit;
?>
