<?php
/**
 * BUSCONTROL - Helpers de autenticación, autorización, CSRF y utilidades.
 * Requiere config.php ya incluido (sesión ya iniciada).
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

// ---------------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

function csrf_verify(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Valida CSRF para peticiones API (espera cabecera X-CSRF-Token o campo csrf_token en el body).
 * Corta la ejecución con 403 si falla.
 */
function csrf_verify_api(array $body = []): void
{
    $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    $token = $headerToken ?? ($body['csrf_token'] ?? null);
    if (!csrf_verify($token)) {
        json_response(['ok' => false, 'error' => 'Token CSRF inválido o ausente'], 403);
    }
}

// ---------------------------------------------------------------------
// Rate limiting básico (por sesión) para login/registro — mitiga fuerza bruta
// ---------------------------------------------------------------------
function rate_limit(string $key, int $maxIntentos = 5, int $ventanaSegundos = 300): bool
{
    $now = time();
    $bucket = $_SESSION['_rl_' . $key] ?? ['count' => 0, 'start' => $now];

    if ($now - $bucket['start'] > $ventanaSegundos) {
        $bucket = ['count' => 0, 'start' => $now];
    }

    $bucket['count']++;
    $_SESSION['_rl_' . $key] = $bucket;

    return $bucket['count'] <= $maxIntentos;
}

// ---------------------------------------------------------------------
// Sesión / roles
// ---------------------------------------------------------------------
function usuario_autenticado(): bool
{
    return isset($_SESSION['usuario_id']);
}

function rol_actual(): string
{
    return strtolower((string)($_SESSION['rol'] ?? ''));
}

function rolid_actual(): int
{
    return (int)($_SESSION['rolid'] ?? 0);
}

/**
 * Determina si el script actualmente en ejecución es un endpoint JSON puro
 * (no una página HTML). Se basa en el archivo físico invocado, no en la URL,
 * porque en Vercel todos los .php viven bajo /api/ y el enrutamiento (routes)
 * reescribe la URL — comprobar el prefijo /api/ en REQUEST_URI no es fiable.
 */
function es_endpoint_json(): bool
{
    $endpointsJson = ['ubicacion.php', 'generar_qr.php', 'validar_qr.php'];
    $archivo = basename((string)($_SERVER['SCRIPT_FILENAME'] ?? ''));
    return in_array($archivo, $endpointsJson, true);
}

/**
 * Exige un rol específico. Comparación flexible por nombre O por rolId
 * para evitar cierres de sesión accidentales por inconsistencias de mayúsculas.
 *
 * @param string $rolNombre  ej. 'estudiante', 'conductor', 'admin'
 * @param int    $rolId      ej. 3, 2, 1
 */
function exigir_rol(string $rolNombre, int $rolId): void
{
    if (!isset($_SESSION['usuario_id']) || (rol_actual() !== strtolower($rolNombre) && rolid_actual() !== $rolId)) {
        if (es_endpoint_json()) {
            json_response(['ok' => false, 'error' => 'No autorizado'], 401);
        } else {
            header('Location: /login?msg=sesion_requerida');
            exit;
        }
    }
}

/** Exige sesión activa (cualquier rol). */
function exigir_sesion(): void
{
    if (!usuario_autenticado()) {
        if (es_endpoint_json()) {
            json_response(['ok' => false, 'error' => 'No autorizado'], 401);
        } else {
            header('Location: /login?msg=sesion_requerida');
            exit;
        }
    }
}

// ---------------------------------------------------------------------
// Utilidades varias
// ---------------------------------------------------------------------
function limpiar(string $valor): string
{
    return trim(strip_tags($valor));
}

function e(?string $valor): string
{
    return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
}

/** Lee y decodifica un body JSON de una petición API de forma segura. */
function leer_json_body(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
