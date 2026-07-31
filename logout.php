<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';

// Vaciar todas las variables de sesión activa
$_SESSION = [];

// Invalidar la cookie de sesión en el navegador del usuario actual
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]
    );
}

// Destruir los datos de la sesión activa en el servidor
session_destroy();

header('Location: /login.php');
exit;
