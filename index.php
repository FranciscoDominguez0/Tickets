<?php
/**
 * PÁGINA DE INICIO - REDIRECCIONA A LOGIN
 */

require_once 'config.php';

// Si el usuario ya está logueado, redirige al dashboard correspondiente
if (isset($_SESSION['user_type'])) {
    if ($_SESSION['user_type'] === 'cliente') {
        header('Location: upload/tickets.php');
    } elseif ($_SESSION['user_type'] === 'agente') {
        header('Location: upload/scp/index.php');
    }
    exit;
}

// Si no está logueado, redirige al login de cliente
header('Location: upload/login.php');
exit;
