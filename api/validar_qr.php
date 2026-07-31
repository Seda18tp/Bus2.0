<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Método no permitido'], 405);
}

exigir_rol('conductor', 2);

$body = leer_json_body();
csrf_verify_api($body);

if (!rate_limit('validar_qr', 30, 60)) {
    json_response(['ok' => false, 'error' => 'Demasiadas solicitudes, espera un momento'], 429);
}

$token = limpiar((string)($body['token'] ?? ''));

if ($token === '' || !preg_match('/^[a-f0-9]{48}$/', $token)) {
    json_response(['ok' => false, 'error' => 'Código QR inválido'], 422);
}

try {
    $pdo = db();
    $pdo->beginTransaction();

    // Bloqueo de fila para evitar doble "quemado" por escaneos simultáneos (condición de carrera)
    $stmt = $pdo->prepare(
        'SELECT id, "estudianteId", usado, "expiraEn"
         FROM tokens_qr WHERE token = :token FOR UPDATE'
    );
    $stmt->execute(['token' => $token]);
    $qr = $stmt->fetch();

    if (!$qr) {
        $pdo->rollBack();
        json_response(['ok' => false, 'error' => 'El código QR no existe'], 404);
    }

    if ((int)$qr['usado'] === 1) {
        $pdo->rollBack();
        json_response(['ok' => false, 'error' => 'Este pase ya fue utilizado'], 409);
    }

    if (strtotime($qr['expiraEn']) < time()) {
        $pdo->rollBack();
        json_response(['ok' => false, 'error' => 'El código QR ha expirado'], 410);
    }

    $estudianteId = (int)$qr['estudianteId'];

    // Doble verificación del límite diario de 2 pases (defensa en profundidad)
    $stmtAsis = $pdo->prepare(
        'SELECT COUNT(*) AS total FROM asistencias
         WHERE "estudianteId" = :uid AND DATE("fechaAbordaje") = CURRENT_DATE'
    );
    $stmtAsis->execute(['uid' => $estudianteId]);
    $usadas = (int)$stmtAsis->fetch()['total'];

    if ($usadas >= 2) {
        $pdo->rollBack();
        json_response(['ok' => false, 'error' => 'El estudiante ya alcanzó el límite de 2 abordajes hoy'], 403);
    }

    // Obtener el bus asignado al conductor actual como "viaje" de referencia
    $stmtBus = $pdo->prepare('SELECT id FROM buses WHERE "conductorId" = :cid LIMIT 1');
    $stmtBus->execute(['cid' => (int)$_SESSION['usuario_id']]);
    $bus = $stmtBus->fetch();
    $viajeId = $bus['id'] ?? null;

    $stmtInsert = $pdo->prepare(
        'INSERT INTO asistencias ("estudianteId", "viajeId", "fechaAbordaje")
         VALUES (:uid, :viaje, NOW())'
    );
    $stmtInsert->execute(['uid' => $estudianteId, 'viaje' => $viajeId]);

    $stmtMarcar = $pdo->prepare('UPDATE tokens_qr SET usado = 1 WHERE id = :id');
    $stmtMarcar->execute(['id' => $qr['id']]);

    $stmtNombre = $pdo->prepare('SELECT nombre, "codigoEstudiante" FROM usuarios WHERE id = :id');
    $stmtNombre->execute(['id' => $estudianteId]);
    $estudiante = $stmtNombre->fetch();

    $pdo->commit();

    json_response([
        'ok' => true,
        'mensaje' => 'Abordaje registrado correctamente',
        'estudiante' => $estudiante['nombre'] ?? null,
        'codigo' => $estudiante['codigoEstudiante'] ?? null,
        'pases_usados_hoy' => $usadas + 1,
    ]);
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[BUSCONTROL][VALIDAR_QR] ' . $ex->getMessage());
    json_response(['ok' => false, 'error' => 'Error al validar el pase'], 500);
}
