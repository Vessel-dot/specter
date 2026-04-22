<?php
// ── moderador/dashboard.php ───────────────────────────────────
require_once __DIR__ . '/../../../../backend/config/config.php';
require_once __DIR__ . '/../../../../backend/helpers/auth_guard.php';
require_auth('moderador');

$pdo = getDB();

// Stats del panel
$stats = [
    'total_jugadores'  => $pdo->query("SELECT COUNT(*) FROM jugador WHERE rol='jugador'")->fetchColumn(),
    'total_moderadores'=> $pdo->query("SELECT COUNT(*) FROM jugador WHERE rol='moderador'")->fetchColumn(),
    'total_resenas'    => $pdo->query("SELECT COUNT(*) FROM resena")->fetchColumn(),
    'en_revision'      => $pdo->query("SELECT COUNT(*) FROM resena WHERE estado_moderacion='EN_REVISION'")->fetchColumn(),
    'bloqueadas'       => $pdo->query("SELECT COUNT(*) FROM resena WHERE estado_moderacion='BLOQUEADO'")->fetchColumn(),
    'total_ventas'     => $pdo->query("SELECT COALESCE(SUM(total),0) FROM compra")->fetchColumn(),
];

// Reseñas recientes en revisión
$pendientes = $pdo->query(
    "SELECT r.id_resena, r.texto, r.calificacion, r.fecha,
            v.titulo, v.steam_id,
            j.nombre AS autor
     FROM resena r
     JOIN videojuego v ON v.id_videojuego = r.id_videojuego
     JOIN jugador j ON j.id_jugador = r.id_jugador
     WHERE r.estado_moderacion = 'EN_REVISION'
     ORDER BY r.fecha DESC LIMIT 5"
)->fetchAll();

// Últimas reseñas globales
$ultimasResenas = $pdo->query(
    "SELECT r.id_resena, r.texto, r.calificacion, r.estado_moderacion, r.fecha,
            v.titulo, j.nombre AS autor
     FROM resena r
     JOIN videojuego v ON v.id_videojuego = r.id_videojuego
     JOIN jugador j ON j.id_jugador = r.id_jugador
     ORDER BY r.fecha DESC LIMIT 10"
)->fetchAll();

$active_page = 'mod_dashboard';
$page_title  = 'Panel General';
require_once __DIR__ . '/../../../../backend/helpers/header_mod.php';
?>

<section class="mod-section">
  <h1 class="page-heading">Panel de Moderación</h1>

  <!-- Stats -->
  <div class="stats-grid stats-mod">
    <div class="stat-card stat-amber">
      <div class="stat-icon">⚠</div>
      <div class="stat-value"><?= $stats['en_revision'] ?></div>
      <div class="stat-label">En revisión</div>
    </div>
    <div class="stat-card stat-green">
      <div class="stat-icon">✓</div>
      <div class="stat-value"><?= $stats['total_resenas'] - $stats['en_revision'] - $stats['bloqueadas'] ?></div>
      <div class="stat-label">Aprobadas</div>
    </div>
    <div class="stat-card stat-red">
      <div class="stat-icon">⊗</div>
      <div class="stat-value"><?= $stats['bloqueadas'] ?></div>
      <div class="stat-label">Bloqueadas</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">🎮</div>
      <div class="stat-value"><?= $stats['total_jugadores'] ?></div>
      <div class="stat-label">Jugadores</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">🛡</div>
      <div class="stat-value"><?= $stats['total_moderadores'] ?></div>
      <div class="stat-label">Moderadores</div>
    </div>
    <div class="stat-card stat-accent">
      <div class="stat-icon">$</div>
      <div class="stat-value">$<?= number_format((float)$stats['total_ventas'], 2) ?></div>
      <div class="stat-label">Ingresos totales</div>
    </div>
  </div>

  <!-- Pendientes de revisión -->
  <?php if (!empty($pendientes)): ?>
  <h2 class="mod-section-title">⚠ Reseñas en revisión</h2>
  <div class="mod-pendientes">
    <?php foreach ($pendientes as $r): ?>
    <div class="mod-item" id="moditem-<?= $r['id_resena'] ?>">
      <div class="mod-item-cover"
           style="background-image:url('https://cdn.akamai.steamstatic.com/steam/apps/<?= $r['steam_id'] ?>/library_600x900.jpg')">
      </div>
      <div class="mod-item-info">
        <div class="mod-item-game"><?= htmlspecialchars($r['titulo']) ?></div>
        <div class="mod-item-author">por <?= htmlspecialchars($r['autor']) ?></div>
        <p class="mod-item-text"><?= htmlspecialchars(substr($r['texto'], 0, 150)) ?>...</p>
        <div class="mod-item-meta">
          <span><?= str_repeat('★', $r['calificacion']) ?></span>
          <span><?= date('d M Y', strtotime($r['fecha'])) ?></span>
        </div>
      </div>
      <div class="mod-item-actions">
        <button class="btn-aprobar" onclick="modAction(<?= $r['id_resena'] ?>, 'aprobar', this)">Aprobar</button>
        <button class="btn-bloquear" onclick="modAction(<?= $r['id_resena'] ?>, 'bloquear', this)">Bloquear</button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Últimas reseñas -->
  <h2 class="mod-section-title">Últimas reseñas del sistema</h2>
  <table class="mod-table">
    <thead>
      <tr><th>Juego</th><th>Autor</th><th>★</th><th>Estado</th><th>Fecha</th><th>Acciones</th></tr>
    </thead>
    <tbody>
      <?php foreach ($ultimasResenas as $r): ?>
      <tr id="row-<?= $r['id_resena'] ?>">
        <td><?= htmlspecialchars($r['titulo']) ?></td>
        <td><?= htmlspecialchars($r['autor']) ?></td>
        <td><?= str_repeat('★', $r['calificacion']) ?></td>
        <td><span class="estado-mod estado-<?= strtolower($r['estado_moderacion']) ?>">
          <?= $r['estado_moderacion'] ?>
        </span></td>
        <td><?= date('d/m/Y', strtotime($r['fecha'])) ?></td>
        <td>
          <?php if ($r['estado_moderacion'] !== 'LIMPIO'): ?>
            <button class="btn-xs btn-aprobar" onclick="modAction(<?= $r['id_resena'] ?>, 'aprobar', this)">✓</button>
          <?php endif; ?>
          <?php if ($r['estado_moderacion'] !== 'BLOQUEADO'): ?>
            <button class="btn-xs btn-bloquear" onclick="modAction(<?= $r['id_resena'] ?>, 'bloquear', this)">✕</button>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>

<script>
async function modAction(idResena, accion, btn) {
  btn.disabled = true;
  const fd = csrfForm ? csrfForm() : new FormData();
  fd.append('action', accion);
  fd.append('id_resena', idResena);
  const res  = await fetch('/specter/api/moderador.php', { method:'POST', body:fd });
  const data = await res.json();
  if (data.success) {
    const el = document.getElementById('moditem-' + idResena);
    if (el) { el.style.opacity = '0'; el.style.transition = '.3s'; setTimeout(() => el.remove(), 300); }
    const row = document.getElementById('row-' + idResena);
    if (row) {
      const badge = row.querySelector('.estado-mod');
      if (badge) {
        badge.className = 'estado-mod estado-' + (accion === 'aprobar' ? 'limpio' : 'bloqueado');
        badge.textContent = accion === 'aprobar' ? 'LIMPIO' : 'BLOQUEADO';
      }
    }
  } else {
    btn.disabled = false;
    alert(data.error || 'Error al procesar.');
  }
}
</script>

<?php require_once __DIR__ . '/../../../../backend/helpers/footer.php'; ?>
