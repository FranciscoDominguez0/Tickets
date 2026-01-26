<?php
/**
 * PÁGINA DE INICIO - REDIRECCIONA A LOGIN
 */

// Si el usuario ya está logueado, redirige al dashboard correspondiente
session_start();

if (isset($_SESSION['user_type'])) {
    if ($_SESSION['user_type'] === 'cliente') {
        header('Location: /cliente/index.php');
    } elseif ($_SESSION['user_type'] === 'agente') {
        header('Location: /agente/index.php');
    }
    exit;
}

// Si no está logueado, redirige al login de cliente
header('Location: /cliente/login.php');
exit;
