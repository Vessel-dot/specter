<?php
// ── biblioteca.php (CU-05) ────────────────────────────────────
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/helpers/auth_guard.php';
require_auth('jugador');

$pdo       = getDB();
$jugadorId = current_id();

$filtroEstado = $_GET['estado'] ?? 'all';
$busqueda     = trim($_GET['q'] ?? '');

$where  = ['b.id_jugador = :id'];
$params = [':id' => $jugadorId];

if ($filtroEstado !== 'all') {
    $where[]           = 'b.estado = :estado';
    $params[':estado'] = $filtroEstado;
}
if ($busqueda) {
    $where[]      = 'LOWER(v.titulo) LIKE :q';
    $params[':q'] = '%' . strtolower($busqueda) . '%';
}

// Sin parámetro duplicado — reseña en sub-query separada
$stmt = $pdo->prepare(
    'SELECT b.id_biblioteca, b.estado, b.progreso, b.fecha_agregado,
            v.id_videojuego, v.titulo, v.genero, v.anio, v.calificacion, v.steam_id
     FROM biblioteca b
     JOIN videojuego v ON v.id_videojuego = b.id_videojuego
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY b.fecha_agregado DESC'
);
$stmt->execute($params);
$juegos = $stmt->fetchAll();

// Cargar reseñas del jugador en una sola query aparte
$resenaMap = [];
if (!empty($juegos)) {
    $r = $pdo->prepare(
        'SELECT id_videojuego, id_resena, calificacion AS mis_estrellas
         FROM resena WHERE id_jugador = :id'
    );
    $r->execute([':id' => $jugadorId]);
    foreach ($r->fetchAll() as $row) {
        $resenaMap[$row['id_videojuego']] = $row;
    }
}

$active_page = 'biblioteca';
$page_title  = 'Mi Biblioteca';
require_once __DIR__ . '/../../../backend/helpers/header.php';
?>

<section class="biblioteca-section">

  <div class="section-header-row">
    <h1 class="page-heading">Mi Biblioteca</h1>
    <a href="/specter/catalogo.php" class="btn-ghost btn-sm">+ Explorar catálogo</a>
  </div>

  <form method="GET" class="bib-filters">
    <input type="text" name="q" class="filter-search"
           placeholder="Buscar en biblioteca..."
           value="<?= htmlspecialchars($busqueda) ?>">
    <div class="status-tabs">
      <?php foreach (['all' => 'Todos', 'Jugando' => 'Jugando', 'Completado' => 'Completado', 'Pendiente' => 'Pendiente'] as $val => $lbl): ?>
        <a href="?estado=<?= $val ?><?= $busqueda ? '&q='.urlencode($busqueda) : '' ?>"
           class="status-tab <?= $filtroEstado === $val ? 'active' : '' ?>">
          <?= $lbl ?>
        </a>
      <?php endforeach; ?>
    </div>
    <button type="submit" class="btn-ghost btn-sm">Buscar</button>
  </form>

  <div class="bib-grid">
    <?php foreach ($juegos as $j):
      $resena = $resenaMap[$j['id_videojuego']] ?? null;
    ?>
    <div class="bib-card">
      <div class="bib-cover"
           style="background-image:url('https://cdn.akamai.steamstatic.com/steam/apps/<?= $j['steam_id'] ?>/library_600x900.jpg')">
        <span class="status-badge status-<?= $j['estado'] ?>"><?= $j['estado'] ?></span>
      </div>
      <div class="bib-info">
        <div class="bib-title"><?= htmlspecialchars($j['titulo']) ?></div>
        <div class="bib-meta">
          <span class="genre-badge"><?= htmlspecialchars($j['genero']) ?></span>
          <span><?= $j['anio'] ?></span>
          <span class="rating">★<?= $j['calificacion'] ?></span>
        </div>

        <div class="progress-row">
          <span class="progress-label">Progreso — <?= $j['progreso'] ?>%</span>
          <input type="range" class="progress-slider" min="0" max="100"
                 value="<?= $j['progreso'] ?>"
                 data-id="<?= $j['id_videojuego'] ?>"
                 onchange="actualizarProgreso(this)">
          <div class="progress-bar">
            <div class="progress-fill"
                 style="width:<?= $j['progreso'] ?>%;
                        background:<?= $j['estado']==='Completado' ? 'var(--green)':'var(--accent)' ?>">
            </div>
          </div>
        </div>

        <select class="estado-select"
                data-id="<?= $j['id_videojuego'] ?>"
                onchange="actualizarEstado(this)">
          <?php foreach (['Pendiente','Jugando','Completado'] as $e): ?>
            <option value="<?= $e ?>" <?= $j['estado']===$e ? 'selected':'' ?>><?= $e ?></option>
          <?php endforeach; ?>
        </select>

        <div class="bib-actions">
          <?php if (!$resena): ?>
            <a href="/specter/resenas.php?juego=<?= $j['id_videojuego'] ?>"
               class="btn-ghost btn-sm">✎ Escribir reseña</a>
          <?php else: ?>
            <span class="reviewed-badge">★ <?= $resena['mis_estrellas'] ?>/5 — Reseñado</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($juegos)): ?>
      <div class="empty-state full-width">
        <p>Tu biblioteca está vacía.</p>
        <a href="/specter/catalogo.php" class="btn-primary">Explorar catálogo</a>
      </div>
    <?php endif; ?>
  </div>

</section>

<?php require_once __DIR__ . '/../../../backend/helpers/footer.php'; ?>
