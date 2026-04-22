<?php
// ── moderador/resenas.php ─────────────────────────────────────
require_once __DIR__ . '/../../../../backend/config/config.php';
require_once __DIR__ . '/../../../../backend/helpers/auth_guard.php';
require_auth('moderador');

$pdo = getDB();

$filtro  = $_GET['estado'] ?? 'all';
$busq    = trim($_GET['q'] ?? '');

$where  = ['1=1'];
$params = [];
if ($filtro !== 'all') { $where[] = "r.estado_moderacion = :estado"; $params[':estado'] = strtoupper($filtro); }
if ($busq)             { $where[] = "LOWER(v.titulo) LIKE :q";       $params[':q']      = '%'.strtolower($busq).'%'; }

$stmt = $pdo->prepare(
    'SELECT r.id_resena, r.texto, r.calificacion, r.estado_moderacion, r.fecha,
            v.titulo, v.steam_id, v.genero,
            j.nombre AS autor, j.correo AS autor_correo,
            ru.indice_confianza
     FROM resena r
     JOIN videojuego v ON v.id_videojuego = r.id_videojuego
     JOIN jugador j    ON j.id_jugador = r.id_jugador
     LEFT JOIN reputacion_usuario ru ON ru.id_jugador = r.id_jugador
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY r.fecha DESC'
);
$stmt->execute($params);
$resenas = $stmt->fetchAll();

$active_page = 'mod_resenas';
$page_title  = 'Reseñas — Moderación';
require_once __DIR__ . '/../../../../backend/helpers/header_mod.php';
?>

<section class="mod-section">
  <h1 class="page-heading">Gestión de Reseñas</h1>
  <p class="page-sub">Todas las reseñas de la plataforma · GestorAnalizadorFraudeIA</p>

  <!-- Filtros -->
  <form method="GET" class="mod-filters">
    <input type="text" name="q" class="filter-search" placeholder="Buscar por título..."
           value="<?= htmlspecialchars($busq) ?>">
    <div class="status-tabs">
      <?php foreach (['all'=>'Todas','LIMPIO'=>'Limpias','EN_REVISION'=>'En revisión','BLOQUEADO'=>'Bloqueadas'] as $v=>$l): ?>
        <a href="?estado=<?= $v ?><?= $busq ? '&q='.urlencode($busq):'' ?>"
           class="status-tab <?= strtoupper($filtro)===$v ? 'active':'' ?>">
          <?= $l ?>
        </a>
      <?php endforeach; ?>
    </div>
  </form>

  <!-- Tabla de reseñas -->
  <div class="mod-resenas-list">
    <?php foreach ($resenas as $r): ?>
    <div class="mod-resena-item estado-<?= strtolower($r['estado_moderacion']) ?>"
         id="moditem-<?= $r['id_resena'] ?>">
      <div class="mod-resena-cover"
           style="background-image:url('https://cdn.akamai.steamstatic.com/steam/apps/<?= $r['steam_id'] ?>/library_600x900.jpg')">
      </div>
      <div class="mod-resena-body">
        <div class="mod-resena-header">
          <span class="mod-resena-game"><?= htmlspecialchars($r['titulo']) ?></span>
          <span class="genre-badge"><?= htmlspecialchars($r['genero']) ?></span>
          <span class="estado-mod estado-<?= strtolower($r['estado_moderacion']) ?>">
            <?= $r['estado_moderacion'] ?>
          </span>
        </div>
        <div class="mod-resena-autor">
          por <strong><?= htmlspecialchars($r['autor']) ?></strong>
          <span class="confianza">(Confianza: <?= $r['indice_confianza'] ?? '5.0' ?>/10)</span>
        </div>
        <div class="resena-stars"><?= str_repeat('★', $r['calificacion']) ?></div>
        <p class="mod-resena-text"><?= htmlspecialchars($r['texto']) ?></p>
        <div class="mod-resena-footer">
          <span><?= date('d M Y H:i', strtotime($r['fecha'])) ?></span>
        </div>
      </div>
      <div class="mod-resena-actions">
        <?php if ($r['estado_moderacion'] !== 'LIMPIO'): ?>
          <button class="btn-aprobar" onclick="modAction(<?= $r['id_resena'] ?>, 'aprobar', this)">
            ✓ Aprobar
          </button>
        <?php endif; ?>
        <?php if ($r['estado_moderacion'] !== 'BLOQUEADO'): ?>
          <button class="btn-bloquear" onclick="modAction(<?= $r['id_resena'] ?>, 'bloquear', this)">
            ✕ Bloquear
          </button>
        <?php endif; ?>
        <?php if ($r['estado_moderacion'] !== 'EN_REVISION'): ?>
          <button class="btn-revision" onclick="modAction(<?= $r['id_resena'] ?>, 'revision', this)">
            ⚑ Revisar
          </button>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($resenas)): ?>
      <div class="empty-state"><p>No hay reseñas con ese criterio.</p></div>
    <?php endif; ?>
  </div>
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
    const item = document.getElementById('moditem-' + idResena);
    const map  = { aprobar:'limpio', bloquear:'bloqueado', revision:'en_revision' };
    const lbls = { aprobar:'LIMPIO', bloquear:'BLOQUEADO', revision:'EN_REVISION' };
    item.className = item.className.replace(/estado-\w+/, 'estado-' + map[accion]);
    item.querySelector('.estado-mod').textContent = lbls[accion];
    item.querySelector('.estado-mod').className = 'estado-mod estado-' + map[accion];
    // Actualizar botones
    item.querySelectorAll('.mod-resena-actions button').forEach(b => b.disabled = false);
  } else {
    btn.disabled = false;
    alert(data.error || 'Error.');
  }
}
</script>

<?php require_once __DIR__ . '/../../../../backend/helpers/footer.php'; ?>
