<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

$correo = trim($_POST['correo'] ?? $_POST['email'] ?? '');
$password = trim($_POST['password'] ?? $_POST['contrasena'] ?? '');

if (empty($correo) || empty($password)) {
    echo json_encode(['status' => 'error', 'message' => 'Por favor ingresa correo y contraseña']);
    exit;
}

try {
    $db = db();
    
    // Buscar usuario por correo (case-insensitive)
    $stmt = $pdo->prepare('SELECT id, nombre, correo, password, rol, "rolId" FROM usuarios WHERE LOWER(correo) = LOWER(?)');
$stmt->execute([$correo]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        echo json_encode(['status' => 'error', 'message' => 'El correo electrónico no está registrado']);
        exit;
    }

    // Comprobar contraseña (Soporta Hash de password_hash() o Texto Plano si aún no se ha hasheado)
    $passwordValida = false;
    if (password_verify($password, $usuario['password'])) {
        $passwordValida = true;
    } elseif ($password === $usuario['password']) {
        // Respaldo por si creaste usuarios de prueba en texto plano
        $passwordValida = true;
    }

    if (!$passwordValida) {
        echo json_encode(['status' => 'error', 'message' => 'Contraseña incorrecta']);
        exit;
    }

    // Iniciar y guardar datos en la sesión
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['usuario_id'] = $usuario['id'];
    $_SESSION['id']         = $usuario['id'];
    $_SESSION['nombre']     = $usuario['nombre'];
    $_SESSION['correo']     = $usuario['correo'];
    $_SESSION['rol']        = strtolower($usuario['rol'] ?? '');
    $_SESSION['rolid']      = $usuario['rolId'] ?? $usuario['rolid'] ?? 3;

    // Determinar redirección
    $redirect = match ($_SESSION['rol']) {
        'admin'      => '/admin/dashboard.php',
        'conductor'  => '/conductor/dashboard.php',
        'estudiante' => '/estudiante/dashboard.php',
        default      => '/estudiante/dashboard.php',
    };

    echo json_encode([
        'status' => 'success',
        'message' => 'Inicio de sesión exitoso',
        'redirect' => $redirect
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error de servidor: ' . $e->getMessage()]);
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
