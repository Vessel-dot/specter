<?php
// ── includes/auth_guard.php ──────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();

function require_auth(string $rol = ''): void {
    if (empty($_SESSION['id_jugador'])) {
        $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '/specter/');
        header("Location: /specter/login.php?redirect={$redirect}");
        exit;
    }

    // Verificar que la cuenta todavía exista en BD con el rol correcto
    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare('SELECT rol FROM jugador WHERE id_jugador = :id');
        $stmt->execute([':id' => (int)$_SESSION['id_jugador']]);
        $dbRol = $stmt->fetchColumn();

        if ($dbRol === false) {
            // Cuenta eliminada — destruir sesión y redirigir
            session_unset();
            session_destroy();
            header('Location: /specter/login.php');
            exit;
        }

        // Sincronizar el rol en sesión por si fue cambiado desde otro panel
        $_SESSION['rol'] = $dbRol;
    } catch (PDOException $e) {
        // Si la BD falla no bloqueamos; continuamos con la sesión existente
    }

    $sesionRol = $_SESSION['rol'] ?? '';
    if ($rol !== '' && $sesionRol !== $rol) {
        header($sesionRol === 'moderador'
            ? 'Location: /specter/moderador/dashboard.php'
            : 'Location: /specter/index.php');
        exit;
    }
}

function is_logged(): bool  { return !empty($_SESSION['id_jugador']); }
function current_rol(): string  { return $_SESSION['rol']    ?? ''; }
function current_nombre(): string { return $_SESSION['nombre'] ?? 'Usuario'; }
function current_id(): ?int {
    return isset($_SESSION['id_jugador']) ? (int)$_SESSION['id_jugador'] : null;
}
