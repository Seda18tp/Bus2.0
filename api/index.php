<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

if (usuario_autenticado()) {
    $destino = match (rol_actual()) {
        'admin'      => '/admin/dashboard',
        'conductor'  => '/conductor/dashboard',
        'estudiante' => '/estudiante/dashboard',
        default      => '/login',
    };
    header('Location: ' . $destino);
    exit;
}

header('Location: /login');
exit;
