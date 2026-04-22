<?php
// ── includes/config.php ──────────────────────────────────────
// Cambia 'localhost' por 'db' (que es el nombre de tu servicio en docker-compose)
define('DB_HOST', 'db');
define('DB_PORT', '5432');
define('DB_NAME', 'specter_db');
define('DB_USER', 'postgres');
define('DB_PASS', 'vessel');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'pgsql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['error' => 'Error de conexión: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

/** Convierte bool PHP al literal string que PostgreSQL PDO acepta en BOOLEAN. */
function db_bool(bool $val): string { return $val ? 'true' : 'false'; }

/** Genera o recupera el token CSRF de sesión. */
function csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Valida el token CSRF de $_POST. Sale con 403 si falla. */
function csrf_verify(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        echo json_encode(['error' => 'Token CSRF inválido.']);
        exit;
    }
}
