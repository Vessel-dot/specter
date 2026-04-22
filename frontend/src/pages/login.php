<?php
// ── login.php ────────────────────────────────────────────────
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/helpers/auth_guard.php';

// Si ya hay sesión activa, redirigir según rol
if (is_logged()) {
    header(current_rol() === 'moderador'
        ? 'Location: /specter/moderador/dashboard.php'
        : 'Location: /specter/index.php');
    exit;
}

$error    = '';
$redirect = $_GET['redirect'] ?? '/specter/index.php';

csrf_token(); // genera token si no existe

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $correo    = trim($_POST['correo']    ?? '');
    $password  = trim($_POST['contrasena'] ?? '');

    if ($correo && $password) {
        try {
            $pdo  = getDB();
            $stmt = $pdo->prepare(
                'SELECT id_jugador, nombre, contrasena, rol, tema_visual
                 FROM jugador WHERE correo = :correo LIMIT 1'
            );
            $stmt->execute([':correo' => $correo]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['contrasena'])) {
                session_regenerate_id(true);
                $_SESSION['id_jugador'] = $user['id_jugador'];
                $_SESSION['nombre']     = $user['nombre'];
                $_SESSION['rol']        = $user['rol'];
                $_SESSION['tema']       = $user['tema_visual'];

                setcookie('tema', $user['tema_visual'], time() + 60*60*24*365, '/');

                // Redirigir según rol
                if ($user['rol'] === 'moderador') {
                    header('Location: /specter/moderador/dashboard.php');
                } else {
                    // Redirigir a la página que quería acceder
                    $dest = filter_var(urldecode($redirect), FILTER_SANITIZE_URL);
                    header('Location: ' . (str_starts_with($dest, '/specter/') ? $dest : '/specter/index.php'));
                }
                exit;
            } else {
                $error = 'Correo o contraseña incorrectos.';
            }
        } catch (PDOException $e) {
            $error = 'Error de servidor. Intenta de nuevo.';
        }
    } else {
        $error = 'Por favor completa todos los campos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Specter — Iniciar sesión</title>
  <link rel="stylesheet" href="/specter/assets/css/style.css">
</head>
<body class="auth-body">

<div class="auth-container">
  <div class="auth-card">

    <div class="auth-logo">
      <span class="logo-diamond">◇</span>
      <span class="auth-logo-text">SPECTER</span>
    </div>
    <h1 class="auth-title">Iniciar sesión</h1>
    <p class="auth-subtitle">Bienvenido de vuelta</p>

    <?php if ($error): ?>
      <div class="auth-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="auth-form">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

      <div class="form-group">
        <label class="form-label">Correo electrónico</label>
        <input type="email" name="correo" class="form-input"
               placeholder="tu@correo.com" required
               value="<?= htmlspecialchars($_POST['correo'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label class="form-label">Contraseña</label>
        <input type="password" name="contrasena" class="form-input"
               placeholder="••••••••" required>
      </div>

      <button type="submit" class="btn-auth-primary">Iniciar sesión</button>
    </form>

    <div class="auth-footer">
      <p>¿No tienes cuenta?
        <a href="/specter/registro.php?redirect=<?= urlencode($redirect) ?>">Regístrate aquí</a>
      </p>
    </div>

    <!-- Credenciales de prueba -->
    <div class="auth-demo">
      <p class="auth-demo-title">Cuentas de prueba</p>
      <div class="auth-demo-row">
        <span>🎮 Jugador:</span>
        <code>jorgeusuario@specter.com</code>
      </div>
      <div class="auth-demo-row">
        <span>🛡 Moderador:</span>
        <code>jorgeadmin@specter.com</code>
      </div>
      <div class="auth-demo-row">
        <span>🔑 Password:</span>
        <code>vessel</code>
      </div>
    </div>

  </div>
</div>

<script src="/specter/assets/js/app.js"></script>
</body>
</html>
