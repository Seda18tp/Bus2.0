<?php
/**
 * BUSCONTROL - Configuración central
 * Conexión a PostgreSQL (Supabase) vía PDO + cabeceras de seguridad + sesión segura.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------
// Reporte de errores
// ---------------------------------------------------------------------
error_reporting(E_ALL);
ini_set('display_errors', (getenv('APP_ENV') === 'production' || ($_ENV['APP_ENV'] ?? '') === 'production') ? '0' : '1');

// ---------------------------------------------------------------------
// Cabeceras de seguridad
// ---------------------------------------------------------------------
// ---------------------------------------------------------------------
// Cabeceras de seguridad (corregidas con font-src e img-src ampliado)
// ---------------------------------------------------------------------
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // CSP con permisos explícitos para FontAwesome, Google Fonts, QuickChart y Leaflet Maps
    header("Content-Security-Policy: " .
        "default-src 'self'; " .
        "font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com data:; " .
        "style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com https://fonts.googleapis.com; " .
        "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com https://unpkg.com; " .
        "img-src * data: blob: https://quickchart.io https://*.tile.openstreetmap.org; " .
        "connect-src 'self' https://quickchart.io https://*.tile.openstreetmap.org; " .
        "frame-ancestors 'none'"
    );
    
    header('Permissions-Policy: geolocation=(self), camera=(self)');
}

// ---------------------------------------------------------------------
// Configuración de sesión segura
// ---------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    // En Vercel siempre es HTTPS
    session_set_cookie_params([
        'lifetime' => 86400, // 24 horas
        'path'     => '/',
        'domain'   => '',
        'secure'   => true,   // Obligatorio para HTTPS en Vercel
        'httponly' => true,
        'samesite' => 'Lax',  // Permite mantener sesión entre redirecciones
    ]);
    session_name('BC_SESSID');
    session_start();
}

if (!isset($_SESSION['_last_regen'])) {
    $_SESSION['_last_regen'] = time();
} elseif (time() - $_SESSION['_last_regen'] > 900) {
    session_regenerate_id(true);
    $_SESSION['_last_regen'] = time();
}

// ---------------------------------------------------------------------
// Variables de entorno con soporte multi-fuente (Vercel / Local)
// ---------------------------------------------------------------------
define('DB_HOST', getenv('DB_HOST') ?: $_ENV['DB_HOST'] ?? 'aws-1-us-west-2.pooler.supabase.com');
define('DB_PORT', getenv('DB_PORT') ?: $_ENV['DB_PORT'] ?? '6543');
define('DB_NAME', getenv('DB_NAME') ?: $_ENV['DB_NAME'] ?? 'postgres');
define('DB_USER', getenv('DB_USER') ?: $_ENV['DB_USER'] ?? 'postgres.galpttrydwvtwvgbnsoz');
define('DB_PASS', getenv('DB_PASS') ?: $_ENV['DB_PASS'] ?? 'BusControl2.0SEDA');

/**
 * Devuelve una conexión PDO singleton a PostgreSQL (Supabase).
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
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        error_log('[BUSCONTROL][DB_ERROR] ' . $e->getMessage());
        http_response_code(500);
        if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Error de conexión con la base de datos: ' . $e->getMessage()]);
        } else {
            echo 'Error de conexión con la base de datos: ' . htmlspecialchars($e->getMessage());
        }
        exit;
    }
}

// Instanciar variable global para scripts legados que usen $pdo directo
$pdo = db();

/**
 * Envía una respuesta JSON estandarizada.
 */
function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}