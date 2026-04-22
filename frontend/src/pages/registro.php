<?php
// ── registro.php ─────────────────────────────────────────────
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/helpers/auth_guard.php';

if (is_logged()) {
    header(current_rol() === 'moderador'
        ? 'Location: /specter/moderador/dashboard.php'
        : 'Location: /specter/index.php');
    exit;
}

$error    = '';
$success  = '';
$redirect = $_GET['redirect'] ?? '/specter/index.php';

csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $nombre   = trim($_POST['nombre']    ?? '');
    $correo   = trim($_POST['correo']    ?? '');
    $password = trim($_POST['contrasena'] ?? '');
    $confirm  = trim($_POST['confirmar'] ?? '');
    $rol      = in_array($_POST['rol'] ?? '', ['jugador','moderador'])
                ? $_POST['rol'] : 'jugador';

    if (!$nombre || !$correo || !$password || !$confirm) {
        $error = 'Por favor completa todos los campos.';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = 'El correo no tiene un formato válido.';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif ($password !== $confirm) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        try {
            $pdo  = getDB();

            // Verificar si el correo ya existe
            $check = $pdo->prepare('SELECT id_jugador FROM jugador WHERE correo = :correo');
            $check->execute([':correo' => $correo]);
            if ($check->fetch()) {
                $error = 'Ya existe una cuenta con ese correo electrónico.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $pdo->beginTransaction();

                // Insertar jugador
                $ins = $pdo->prepare(
                    'INSERT INTO jugador (nombre, correo, contrasena, rol)
                     VALUES (:nombre, :correo, :hash, :rol)
                     RETURNING id_jugador'
                );
                $ins->execute([
                    ':nombre' => $nombre,
                    ':correo' => $correo,
                    ':hash'   => $hash,
                    ':rol'    => $rol,
                ]);
                $newId = (int)$ins->fetchColumn();

                // Crear carrito y reputación iniciales (solo para jugadores)
                if ($rol === 'jugador') {
                    $pdo->prepare('INSERT INTO carrito (id_jugador) VALUES (:id)')
                        ->execute([':id' => $newId]);
                    $pdo->prepare('INSERT INTO reputacion_usuario (id_jugador) VALUES (:id)')
                        ->execute([':id' => $newId]);

                    // Actividad de bienvenida
                    $pdo->prepare(
                        "INSERT INTO actividad (id_jugador, texto, tipo)
                         VALUES (:id, '¡Bienvenido a Specter!', 'sistema')"
                    )->execute([':id' => $newId]);
                }

                $pdo->commit();

                // Iniciar sesión automáticamente
                session_regenerate_id(true);
                $_SESSION['id_jugador'] = $newId;
                $_SESSION['nombre']     = $nombre;
                $_SESSION['rol']        = $rol;
                $_SESSION['tema']       = 'dark';
                setcookie('tema', 'dark', time() + 60*60*24*365, '/');

                header($rol === 'moderador'
                    ? 'Location: /specter/moderador/dashboard.php'
                    : 'Location: /specter/index.php');
                exit;
            }
        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $error = 'Error al crear la cuenta: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Specter — Crear cuenta</title>
  <link rel="stylesheet" href="/specter/assets/css/style.css">
</head>
<body class="auth-body">

<div class="auth-container">
  <div class="auth-card auth-card-wide">

    <div class="auth-logo">
      <span class="logo-diamond">◇</span>
      <span class="auth-logo-text">SPECTER</span>
    </div>
    <h1 class="auth-title">Crear cuenta</h1>
    <p class="auth-subtitle">Únete a la comunidad</p>

    <?php if ($error): ?>
      <div class="auth-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="auth-form">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

      <!-- Selector de rol -->
      <div class="form-group">
        <label class="form-label">Tipo de cuenta</label>
        <div class="role-selector">
          <label class="role-option <?= ($_POST['rol'] ?? 'jugador') === 'jugador' ? 'selected' : '' ?>">
            <input type="radio" name="rol" value="jugador"
                   <?= ($_POST['rol'] ?? 'jugador') === 'jugador' ? 'checked' : '' ?>
                   onchange="selectRole(this)">
            <div class="role-card">
              <span class="role-icon">🎮</span>
              <span class="role-name">Jugador</span>
              <span class="role-desc">Explora, compra y reseña videojuegos</span>
            </div>
          </label>
          <label class="role-option <?= ($_POST['rol'] ?? '') === 'moderador' ? 'selected' : '' ?>">
            <input type="radio" name="rol" value="moderador"
                   <?= ($_POST['rol'] ?? '') === 'moderador' ? 'checked' : '' ?>
                   onchange="selectRole(this)">
            <div class="role-card">
              <span class="role-icon">🛡</span>
              <span class="role-name">Moderador</span>
              <span class="role-desc">Supervisa reseñas y gestiona la comunidad</span>
            </div>
          </label>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Nombre completo</label>
        <input type="text" name="nombre" class="form-input"
               placeholder="Tu nombre" required
               value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label class="form-label">Correo electrónico</label>
        <input type="email" name="correo" class="form-input"
               placeholder="tu@correo.com" required
               value="<?= htmlspecialchars($_POST['correo'] ?? '') ?>">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Contraseña</label>
          <input type="password" name="contrasena" class="form-input"
                 placeholder="Mínimo 6 caracteres" required>
        </div>
        <div class="form-group">
          <label class="form-label">Confirmar contraseña</label>
          <input type="password" name="confirmar" class="form-input"
                 placeholder="Repite tu contraseña" required>
        </div>
      </div>

      <button type="submit" class="btn-auth-primary">Crear cuenta</button>
    </form>

    <div class="auth-footer">
      <p>¿Ya tienes cuenta?
        <a href="/specter/login.php?redirect=<?= urlencode($redirect) ?>">Inicia sesión</a>
      </p>
    </div>

  </div>
</div>

<script>
function selectRole(input) {
  document.querySelectorAll('.role-option').forEach(el => el.classList.remove('selected'));
  input.closest('.role-option').classList.add('selected');
}
</script>
<script src="/specter/assets/js/app.js"></script>
</body>
</html>
