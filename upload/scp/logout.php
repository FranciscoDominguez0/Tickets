<?php
/**
 * LOGOUT AGENTE
 */

require_once '../../config.php';

session_destroy();
header('Location: login.php?msg=logout_success');
exit;
?>
