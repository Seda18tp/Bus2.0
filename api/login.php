<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

// Si ya hay sesión activa, redirigir directo a su panel
if (usuario_autenticado()) {
    header('Location: ' . ruta_dashboard_por_rol(rol_actual()));
    exit;
}

function ruta_dashboard_por_rol(string $rol): string
{
    return match ($rol) {
        'admin'      => '/admin/dashboard',
        'conductor'  => '/conductor/dashboard',
        'estudiante' => '/estudiante/dashboard',
        default      => '/login',
    };
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Sesión de formulario expirada. Recarga la página e intenta de nuevo.';
    } elseif (!rate_limit('login', 6, 300)) {
        $error = 'Demasiados intentos fallidos. Espera unos minutos antes de volver a intentar.';
    } else {
        $email    = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        $password = (string)($_POST['password'] ?? '');

        if (!$email || $password === '') {
            $error = 'Ingresa un correo y una contraseña válidos.';
        } else {
            try {
                $stmt = db()->prepare(
                    'SELECT id, nombre, email, password_hash, rol, "rolId", activo
                     FROM usuarios WHERE email = :email LIMIT 1'
                );
                $stmt->execute(['email' => $email]);
                $usuario = $stmt->fetch();

                // Mensaje genérico deliberado: no revelar si el correo existe o no
                if (!$usuario || !password_verify($password, $usuario['password_hash'])) {
                    $error = 'Correo o contraseña incorrectos.';
                } elseif (!$usuario['activo']) {
                    $error = 'Esta cuenta está inhabilitada. Contacta al administrador.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['usuario_id'] = $usuario['id'];
                    $_SESSION['nombre']     = $usuario['nombre'];
                    $_SESSION['email']      = $usuario['email'];
                    $_SESSION['rol']        = strtolower($usuario['rol']);
                    $_SESSION['rolid']      = (int)$usuario['rolId'];
                    $_SESSION['_last_regen'] = time();

                    header('Location: ' . ruta_dashboard_por_rol(strtolower($usuario['rol'])));
                    exit;
                }
            } catch (Throwable $ex) {
                error_log('[BUSCONTROL][LOGIN] ' . $ex->getMessage());
                $error = 'Ocurrió un error al iniciar sesión. Intenta más tarde.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Iniciar sesión · BUSCONTROL</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center px-4">
<div class="w-full max-w-md bg-white rounded-3xl shadow-sm border border-slate-200 p-8">
    <div class="text-center mb-6">
        <div class="w-14 h-14 rounded-2xl bg-blue-600 text-white flex items-center justify-center mx-auto mb-3">
            <i class="fa-solid fa-bus text-2xl"></i>
        </div>
        <h1 class="text-2xl font-bold text-slate-800">BUSCONTROL</h1>
        <p class="text-slate-500 text-sm mt-1">Inicia sesión para continuar</p>
    </div>

    <?php if (($_GET['msg'] ?? '') === 'sesion_requerida'): ?>
        <div class="bg-amber-50 text-amber-700 border border-amber-200 rounded-2xl p-4 text-sm mb-4">
            <i class="fa-solid fa-lock mr-1"></i> Debes iniciar sesión para continuar.
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-50 text-red-600 border border-red-200 rounded-2xl p-4 text-sm mb-4">
            <i class="fa-solid fa-circle-exclamation mr-1"></i><?= e($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="space-y-4" autocomplete="off">
        <?= csrf_field() ?>
        <div>
            <label class="text-sm font-medium text-slate-700">Correo electrónico</label>
            <input type="email" name="email" required
                   value="<?= e($_POST['email'] ?? '') ?>"
                   class="mt-1 w-full rounded-2xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:outline-none">
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">Contraseña</label>
            <input type="password" name="password" required
                   class="mt-1 w-full rounded-2xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:outline-none">
        </div>
        <button type="submit"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-2xl py-3 transition">
            Iniciar sesión
        </button>
    </form>

    <p class="text-center text-sm text-slate-500 mt-6">
        ¿No tienes cuenta? <a href="/register" class="text-blue-600 font-semibold">Regístrate</a>
    </p>
</div>
</body>
</html>
