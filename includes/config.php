<?php
/**
 * BUSCONTROL - Configuración central
 * Conexión a PostgreSQL (Supabase) vía PDO + cabeceras de seguridad + sesión segura.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------
// Reporte de errores (desactivar display_errors en producción real)
// ---------------------------------------------------------------------
error_reporting(E_ALL);
ini_set('display_errors', getenv('APP_ENV') === 'production' ? '0' : '1');

// ---------------------------------------------------------------------
// Cabeceras de seguridad (aplican a todas las respuestas HTML/JSON)
// ---------------------------------------------------------------------
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; img-src * data:; style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com https://unpkg.com; connect-src 'self'; frame-ancestors 'none'");
    header('Permissions-Policy: geolocation=(self), camera=(self)');
}

// ---------------------------------------------------------------------
// Configuración de sesión segura (antes de session_start)
// ---------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    $secureCookies = (getenv('APP_ENV') === 'production');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secureCookies, // true en producción (HTTPS obligatorio con Vercel)
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('BC_SESSID');
    session_start();
}

// Regeneración periódica del id de sesión (mitiga fixation) sin destruir datos
if (!isset($_SESSION['_last_regen'])) {
    $_SESSION['_last_regen'] = time();
} elseif (time() - $_SESSION['_last_regen'] > 900) {
    session_regenerate_id(true);
    $_SESSION['_last_regen'] = time();
}

// ---------------------------------------------------------------------
// Variables de entorno de conexión (definir en Vercel: Settings > Env Vars)
// ---------------------------------------------------------------------
define('DB_HOST', getenv('DB_HOST') ?: '');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: 'postgres');
define('DB_USER', getenv('DB_USER') ?: '');
define('DB_PASS', getenv('DB_PASS') ?: '');

/**
 * Devuelve una conexión PDO singleton a PostgreSQL (Supabase).
 * Usa siempre PDO::prepare()/execute() con parámetros — nunca concatenar SQL.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s;sslmode=require',
        DB_HOST,
        DB_PORT,
        DB_NAME
    );

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // consultas preparadas reales -> anti SQL injection
            PDO::ATTR_PERSISTENT         => false, // recomendado en entorno serverless (sin estado persistente)
        ]);
        return $pdo;
    } catch (PDOException $e) {
        error_log('[BUSCONTROL][DB_ERROR] ' . $e->getMessage());
        http_response_code(500);
        if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Error de conexión con la base de datos']);
        } else {
            echo 'Error de conexión con la base de datos. Intenta más tarde.';
        }
        exit;
    }
}

/**
 * Envía una respuesta JSON estandarizada y termina la ejecución.
 */
function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
