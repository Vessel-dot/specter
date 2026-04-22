<?php
// ── moderador/usuarios.php ────────────────────────────────────
require_once __DIR__ . '/../../../../backend/config/config.php';
require_once __DIR__ . '/../../../../backend/helpers/auth_guard.php';
require_auth('moderador');

$pdo = getDB();

// Acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $accion = $_POST['action'] ?? '';
    $idObj  = (int)($_POST['id_jugador'] ?? 0);
    $miId   = current_id();

    if ($idObj === $miId) {
        echo json_encode(['error' => 'No puedes modificar tu propia cuenta.']); exit;
    }
    try {
        if ($accion === 'cambiar_rol') {
            $nuevoRol = $_POST['rol'] ?? '';
            if (!in_array($nuevoRol, ['jugador','moderador'])) {
                echo json_encode(['error' => 'Rol inválido.']); exit;
            }
            // Si se está degradando a un moderador, verificar que quede al menos uno
            if ($nuevoRol === 'jugador') {
                $rolActual = $pdo->prepare('SELECT rol FROM jugador WHERE id_jugador = :id');
                $rolActual->execute([':id' => $idObj]);
                if ($rolActual->fetchColumn() === 'moderador') {
                    $restantes = $pdo->prepare(
                        "SELECT COUNT(*) FROM jugador WHERE rol = 'moderador' AND id_jugador != :id"
                    );
                    $restantes->execute([':id' => $idObj]);
                    if ((int)$restantes->fetchColumn() < 1) {
                        echo json_encode(['error' => 'No se puede degradar al único moderador del sistema. Debe quedar al menos uno.']); exit;
                    }
                }
            }
            $pdo->prepare('UPDATE jugador SET rol = :rol WHERE id_jugador = :id')
                ->execute([':rol' => $nuevoRol, ':id' => $idObj]);
            echo json_encode(['success' => true]); exit;
        }
        if ($accion === 'eliminar') {
            // Si el usuario a eliminar es moderador, verificar que quede al menos uno
            $rolTarget = $pdo->prepare('SELECT rol FROM jugador WHERE id_jugador = :id');
            $rolTarget->execute([':id' => $idObj]);
            if ($rolTarget->fetchColumn() === 'moderador') {
                // Contar moderadores que quedarían DESPUÉS de eliminar al objetivo
                $restantes = $pdo->prepare(
                    "SELECT COUNT(*) FROM jugador WHERE rol = 'moderador' AND id_jugador != :id"
                );
                $restantes->execute([':id' => $idObj]);
                if ((int)$restantes->fetchColumn() < 1) {
                    echo json_encode(['error' => 'No se puede eliminar al único moderador del sistema. Debe quedar al menos uno.']); exit;
                }
            }
            $pdo->prepare('DELETE FROM jugador WHERE id_jugador = :id AND id_jugador != :mid')
                ->execute([':id' => $idObj, ':mid' => $miId]);
            echo json_encode(['success' => true]); exit;
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Error de base de datos.']); exit;
    }
    echo json_encode(['error' => 'Acción desconocida.']); exit;
}

$busq = trim($_GET['q'] ?? '');
$rol  = $_GET['rol'] ?? 'all';

$where  = ['j.id_jugador != :mid'];
$params = [':mid' => current_id()];
if ($busq) { $where[] = "(LOWER(j.nombre) LIKE :q OR LOWER(j.correo) LIKE :q2)"; $params[':q'] = '%'.strtolower($busq).'%'; $params[':q2'] = '%'.strtolower($busq).'%'; }
if ($rol !== 'all') { $where[] = 'j.rol = :rol'; $params[':rol'] = $rol; }

$stmt = $pdo->prepare(
    'SELECT j.id_jugador, j.nombre, j.correo, j.rol, j.fecha_registro,
            ru.indice_confianza,
            (SELECT COUNT(*) FROM resena r WHERE r.id_jugador = j.id_jugador) AS total_resenas
     FROM jugador j
     LEFT JOIN reputacion_usuario ru ON ru.id_jugador = j.id_jugador
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY j.fecha_registro DESC'
);
$stmt->execute($params);
$usuarios = $stmt->fetchAll();

$active_page = 'mod_usuarios';
$page_title  = 'Gestión de Usuarios';
require_once __DIR__ . '/../../../../backend/helpers/header_mod.php';
?>

<section class="mod-section">
  <h1 class="page-heading">Gestión de Usuarios</h1>

  <form method="GET" class="mod-filters">
    <input type="text" name="q" class="filter-search"
           placeholder="Buscar usuario..." value="<?= htmlspecialchars($busq) ?>">
    <div class="status-tabs">
      <a href="?rol=all"       class="status-tab <?= $rol==='all'?'active':'' ?>">Todos</a>
      <a href="?rol=jugador"   class="status-tab <?= $rol==='jugador'?'active':'' ?>">Jugadores</a>
      <a href="?rol=moderador" class="status-tab <?= $rol==='moderador'?'active':'' ?>">Moderadores</a>
    </div>
  </form>

  <table class="mod-table">
    <thead>
      <tr>
        <th>ID</th><th>Nombre</th><th>Correo</th><th>Rol</th>
        <th>Reseñas</th><th>Confianza</th><th>Registro</th><th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($usuarios as $u): ?>
      <tr id="urow-<?= $u['id_jugador'] ?>">
        <td>#<?= $u['id_jugador'] ?></td>
        <td><?= htmlspecialchars($u['nombre']) ?></td>
        <td><small><?= htmlspecialchars($u['correo']) ?></small></td>
        <td>
          <span class="rol-badge rol-<?= $u['rol'] ?>" id="rolbadge-<?= $u['id_jugador'] ?>">
            <?= $u['rol'] === 'moderador' ? '🛡' : '🎮' ?> <?= $u['rol'] ?>
          </span>
        </td>
        <td><?= $u['total_resenas'] ?></td>
        <td><?= $u['indice_confianza'] ?? '—' ?></td>
        <td><small><?= date('d/m/Y', strtotime($u['fecha_registro'])) ?></small></td>
        <td>
          <button class="btn-xs btn-mod-role"
                  data-id="<?= $u['id_jugador'] ?>"
                  data-rol="<?= $u['rol'] ?>"
                  onclick="cambiarRol(this)">
            <?= $u['rol'] === 'jugador' ? '→ Moderador' : '→ Jugador' ?>
          </button>
          <button class="btn-xs btn-bloquear"
                  onclick="eliminarUsuario(<?= $u['id_jugador'] ?>, this)">
            Eliminar
          </button>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>

<script>
async function cambiarRol(btn) {
  const id      = btn.dataset.id;
  const rolActual = btn.dataset.rol;
  const nuevoRol  = rolActual === 'jugador' ? 'moderador' : 'jugador';
  if (!confirm(`¿Cambiar rol de este usuario a ${nuevoRol}?`)) return;

  const fd = csrfForm ? csrfForm() : new FormData();
  fd.append('action','cambiar_rol'); fd.append('id_jugador', id); fd.append('rol', nuevoRol);
  const data = await (await fetch('/specter/moderador/usuarios.php',{method:'POST',body:fd})).json();
  if (data.success) {
    btn.dataset.rol = nuevoRol;
    btn.textContent = nuevoRol === 'jugador' ? '→ Moderador' : '→ Jugador';
    const badge = document.getElementById('rolbadge-' + id);
    badge.className = 'rol-badge rol-' + nuevoRol;
    badge.textContent = (nuevoRol === 'moderador' ? '🛡 ' : '🎮 ') + nuevoRol;
  } else { alert(data.error); }
}

async function eliminarUsuario(id, btn) {
  if (!confirm('¿Eliminar esta cuenta permanentemente?')) return;
  btn.disabled = true;
  const fd = csrfForm ? csrfForm() : new FormData();
  fd.append('action','eliminar'); fd.append('id_jugador', id);
  const data = await (await fetch('/specter/moderador/usuarios.php',{method:'POST',body:fd})).json();
  if (data.success) {
    const row = document.getElementById('urow-' + id);
    row.style.opacity = '0'; row.style.transition = '.3s';
    setTimeout(() => row.remove(), 300);
  } else { btn.disabled = false; alert(data.error); }
}
</script>

<?php require_once __DIR__ . '/../../../../backend/helpers/footer.php'; ?>
