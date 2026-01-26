<?php
/**
 * FUNCIONES AUXILIARES
 */

// Proteger página (requiere login)
function requireLogin($type = 'user') {
    if ($type === 'cliente' && !isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
    if ($type === 'agente' && !isset($_SESSION['staff_id'])) {
        header('Location: /sistema-tickets/agente/login.php');
        exit;
    }
}

// Validar CSRF
function validateCSRF() {
    if ($_POST && !Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
        return false;
    }
    return true;
}

// Campo CSRF en formulario
function csrfField() {
    echo '<input type="hidden" name="csrf_token" value="' . 
         htmlspecialchars($_SESSION['csrf_token']) . '">';
}

// Escapar output (XSS prevention)
function html($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

// Formatear fecha
function formatDate($date) {
    if (!$date) return '-';
    return date('d/m/Y H:i', strtotime($date));
}

// Redirect
function redirect($url) {
    header("Location: $url");
    exit;
}

// GET seguro
function getQuery($key, $default = null) {
    return $_GET[$key] ?? $default;
}

// POST seguro
function getPost($key, $default = null) {
    return $_POST[$key] ?? $default;
}

// Obtener usuario actual
function getCurrentUser() {
    if (isset($_SESSION['user_id'])) {
        return [
            'id' => $_SESSION['user_id'],
            'type' => 'cliente',
            'name' => $_SESSION['user_name'],
            'email' => $_SESSION['user_email']
        ];
    } elseif (isset($_SESSION['staff_id'])) {
        return [
            'id' => $_SESSION['staff_id'],
            'type' => 'agente',
            'name' => $_SESSION['staff_name'],
            'email' => $_SESSION['staff_email']
        ];
    }
    return null;
}

// Validar email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? true : false;
}

// Generar número de ticket
function generateTicketNumber() {
    return strtoupper(substr(md5(uniqid()), 0, 3)) . '-' . date('Ymd') . '-' . 
           str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
}
?>
