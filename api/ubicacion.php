<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

$metodo = $_SERVER['REQUEST_METHOD'];

// ------------------------------------------------------------------
// POST — el conductor emite su posición GPS en segundo plano
// ------------------------------------------------------------------
if ($metodo === 'POST') {
    exigir_rol('conductor', 2);

    $body = leer_json_body();
    csrf_verify_api($body);

    $lat = filter_var($body['latitud'] ?? null, FILTER_VALIDATE_FLOAT);
    $lng = filter_var($body['longitud'] ?? null, FILTER_VALIDATE_FLOAT);
    $vel = filter_var($body['velocidad'] ?? 0, FILTER_VALIDATE_FLOAT);
    $estado = in_array($body['estado'] ?? 'activo', ['activo', 'inactivo', 'detenido'], true)
        ? $body['estado']
        : 'activo';

    if ($lat === false || $lng === false || $lat === null || $lng === null) {
        json_response(['ok' => false, 'error' => 'Coordenadas inválidas'], 422);
    }
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        json_response(['ok' => false, 'error' => 'Coordenadas fuera de rango'], 422);
    }

    try {
        $pdo = db();
        $conductorId = (int)$_SESSION['usuario_id'];

        $stmt = $pdo->prepare('SELECT id FROM buses WHERE "conductorId" = :cid LIMIT 1');
        $stmt->execute(['cid' => $conductorId]);
        $bus = $stmt->fetch();

        if (!$bus) {
            json_response(['ok' => false, 'error' => 'No tienes un bus asignado'], 404);
        }

        $upd = $pdo->prepare(
            'UPDATE buses
             SET latitud = :lat, longitud = :lng, velocidad = :vel, estado = :estado, "actualizadoEn" = NOW()
             WHERE id = :id'
        );
        $upd->execute([
            'lat'    => $lat,
            'lng'    => $lng,
            'vel'    => $vel ?: 0,
            'estado' => $estado,
            'id'     => $bus['id'],
        ]);

        json_response(['ok' => true]);
    } catch (Throwable $ex) {
        error_log('[BUSCONTROL][UBICACION_POST] ' . $ex->getMessage());
        json_response(['ok' => false, 'error' => 'Error al actualizar la ubicación'], 500);
    }
}

// ------------------------------------------------------------------
// GET — estudiante/admin consultan la posición del/los bus(es)
// ------------------------------------------------------------------
if ($metodo === 'GET') {
    exigir_sesion();

    try {
        $pdo = db();
        $busId = filter_input(INPUT_GET, 'busId', FILTER_VALIDATE_INT);
        $rutaId = filter_input(INPUT_GET, 'rutaId', FILTER_VALIDATE_INT);

        $sql = 'SELECT b.id, b.placa, b.latitud, b.longitud, b.velocidad, b.estado,
                       b."rutaId", b."conductorId", b."actualizadoEn", r.nombre AS "rutaNombre"
                FROM buses b
                LEFT JOIN rutas r ON r.id = b."rutaId"
                WHERE 1=1';
        $params = [];

        if ($busId) {
            $sql .= ' AND b.id = :busId';
            $params['busId'] = $busId;
        }
        if ($rutaId) {
            $sql .= ' AND b."rutaId" = :rutaId';
            $params['rutaId'] = $rutaId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $buses = $stmt->fetchAll();

        json_response(['ok' => true, 'buses' => $buses]);
    } catch (Throwable $ex) {
        error_log('[BUSCONTROL][UBICACION_GET] ' . $ex->getMessage());
        json_response(['ok' => false, 'error' => 'Error al consultar la ubicación'], 500);
    }
}

json_response(['ok' => false, 'error' => 'Método no permitido'], 405);
