<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

$errores = [];
$exito = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errores[] = 'Sesión de formulario expirada. Recarga la página e intenta de nuevo.';
    } elseif (!rate_limit('register', 5, 600)) {
        $errores[] = 'Demasiados intentos. Intenta de nuevo en unos minutos.';
    } else {
        $nombre   = limpiar((string)($_POST['nombre'] ?? ''));
        $email    = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        $password = (string)($_POST['password'] ?? '');
        $password2 = (string)($_POST['password2'] ?? '');
        $codigo   = limpiar((string)($_POST['codigo_estudiante'] ?? ''));

        if ($nombre === '' || mb_strlen($nombre) < 3) {
            $errores[] = 'El nombre debe tener al menos 3 caracteres.';
        }
        if (!$email) {
            $errores[] = 'El correo electrónico no es válido.';
        }
        if (mb_strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $errores[] = 'La contraseña debe tener mínimo 8 caracteres, una mayúscula y un número.';
        }
        if ($password !== $password2) {
            $errores[] = 'Las contraseñas no coinciden.';
        }
        if ($codigo === '') {
            $errores[] = 'El código de estudiante es obligatorio.';
        }

        if (!$errores) {
            try {
                $pdo = db();

                $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email');
                $stmt->execute(['email' => $email]);
                if ($stmt->fetch()) {
                    $errores[] = 'Ya existe una cuenta registrada con ese correo.';
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT);

                    $stmt = $pdo->prepare(
                        'INSERT INTO usuarios (nombre, email, password_hash, rol, "rolId", "codigoEstudiante", activo, "creadoEn")
                         VALUES (:nombre, :email, :hash, \'estudiante\', 3, :codigo, TRUE, NOW())'
                    );
                    $stmt->execute([
                        'nombre' => $nombre,
                        'email'  => $email,
                        'hash'   => $hash,
                        'codigo' => $codigo,
                    ]);

                    $exito = true;
                }
            } catch (Throwable $ex) {
                error_log('[BUSCONTROL][REGISTER] ' . $ex->getMessage());
                $errores[] = 'Ocurrió un error al crear la cuenta. Intenta más tarde.';
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
<title>Crear cuenta · BUSCONTROL</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center px-4">
<div class="w-full max-w-md bg-white rounded-3xl shadow-sm border border-slate-200 p-8">
    <div class="text-center mb-6">
        <div class="w-14 h-14 rounded-2xl bg-blue-600 text-white flex items-center justify-center mx-auto mb-3">
            <i class="fa-solid fa-bus text-2xl"></i>
        </div>
        <h1 class="text-2xl font-bold text-slate-800">Crear cuenta</h1>
        <p class="text-slate-500 text-sm mt-1">Regístrate como estudiante para usar BUSCONTROL</p>
    </div>

    <?php if ($exito): ?>
        <div class="bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-2xl p-4 text-sm mb-4">
            <i class="fa-solid fa-circle-check mr-1"></i>
            Cuenta creada con éxito. Ya puedes <a href="/login" class="font-semibold underline">iniciar sesión</a>.
        </div>
    <?php else: ?>
        <?php if ($errores): ?>
            <div class="bg-red-50 text-red-600 border border-red-200 rounded-2xl p-4 text-sm mb-4 space-y-1">
                <?php foreach ($errores as $err): ?>
                    <p><i class="fa-solid fa-circle-exclamation mr-1"></i><?= e($err) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4" autocomplete="off">
            <?= csrf_field() ?>
            <div>
                <label class="text-sm font-medium text-slate-700">Nombre completo</label>
                <input type="text" name="nombre" required minlength="3" maxlength="150"
                       value="<?= e($_POST['nombre'] ?? '') ?>"
                       class="mt-1 w-full rounded-2xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Código de estudiante</label>
                <input type="text" name="codigo_estudiante" required maxlength="30"
                       value="<?= e($_POST['codigo_estudiante'] ?? '') ?>"
                       class="mt-1 w-full rounded-2xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Correo electrónico</label>
                <input type="email" name="email" required maxlength="150"
                       value="<?= e($_POST['email'] ?? '') ?>"
                       class="mt-1 w-full rounded-2xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Contraseña</label>
                <input type="password" name="password" required minlength="8"
                       class="mt-1 w-full rounded-2xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                <p class="text-xs text-slate-400 mt-1">Mínimo 8 caracteres, una mayúscula y un número.</p>
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Confirmar contraseña</label>
                <input type="password" name="password2" required minlength="8"
                       class="mt-1 w-full rounded-2xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>
            <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-2xl py-3 transition">
                Crear cuenta
            </button>
        </form>
    <?php endif; ?>

    <p class="text-center text-sm text-slate-500 mt-6">
        ¿Ya tienes cuenta? <a href="/login" class="text-blue-600 font-semibold">Inicia sesión</a>
    </p>
</div>
</body>
</html>
