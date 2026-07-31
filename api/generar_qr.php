<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Método no permitido'], 405);
}

exigir_rol('estudiante', 3);

$body = leer_json_body();
csrf_verify_api($body);

if (!rate_limit('generar_qr', 10, 60)) {
    json_response(['ok' => false, 'error' => 'Demasiadas solicitudes, espera un momento'], 429);
}

$estudianteId = (int)$_SESSION['usuario_id'];

try {
    $pdo = db();

    // 1. Verificar que el pago esté al día
    $stmtPago = $pdo->prepare(
        'SELECT id FROM pagos
         WHERE "usuarioId" = :uid AND estado = \'al_dia\' AND "validoHasta" >= CURRENT_DATE
         ORDER BY "validoHasta" DESC LIMIT 1'
    );
    $stmtPago->execute(['uid' => $estudianteId]);
    if (!$stmtPago->fetch()) {
        json_response(['ok' => false, 'error' => 'Tu pago está vencido. Regulariza tu situación para generar el pase.'], 403);
    }

    // 2. Contar asistencias registradas hoy (pases ya "quemados")
    $stmtAsis = $pdo->prepare(
        'SELECT COUNT(*) AS total FROM asistencias
         WHERE "estudianteId" = :uid AND DATE("fechaAbordaje") = CURRENT_DATE'
    );
    $stmtAsis->execute(['uid' => $estudianteId]);
    $usadas = (int)$stmtAsis->fetch()['total'];

    // 3. Contar tokens vigentes (no usados ni expirados) generados hoy, para no acumular infinitos QR
    $stmtVigentes = $pdo->prepare(
        'SELECT COUNT(*) AS total FROM tokens_qr
         WHERE "estudianteId" = :uid AND usado = 0 AND "expiraEn" > NOW()
           AND DATE("creadoEn") = CURRENT_DATE'
    );
    $stmtVigentes->execute(['uid' => $estudianteId]);
    $vigentes = (int)$stmtVigentes->fetch()['total'];

    if ($usadas + $vigentes >= 2) {
        json_response(['ok' => false, 'error' => 'Ya alcanzaste el límite de 2 pases (ida y vuelta) por hoy.'], 403);
    }

    // 4. Generar token criptográficamente seguro
    $token = bin2hex(random_bytes(24));
    $expiraMinutos = 10;

    $stmtInsert = $pdo->prepare(
        'INSERT INTO tokens_qr (token, usado, "estudianteId", "creadoEn", "expiraEn")
         VALUES (:token, 0, :uid, NOW(), NOW() + INTERVAL \'' . $expiraMinutos . ' minutes\')'
    );
    $stmtInsert->execute(['token' => $token, 'uid' => $estudianteId]);

    $qrUrl = 'https://quickchart.io/qr?text=' . urlencode($token) . '&size=300&margin=2';

    json_response([
        'ok'            => true,
        'qr_url'        => $qrUrl,
        'expira_en_min' => $expiraMinutos,
        'pases_restantes' => max(0, 2 - ($usadas + $vigentes + 1)),
    ]);
} catch (Throwable $ex) {
    error_log('[BUSCONTROL][GENERAR_QR] ' . $ex->getMessage());
    json_response(['ok' => false, 'error' => 'Error al generar el pase QR'], 500);
}
