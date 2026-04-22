<?php
// ── index.php — Dashboard Principal (CU-01) ──────────────────
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/helpers/auth_guard.php';

if (is_logged() && current_rol() === 'moderador') {
    header('Location: /specter/moderador/dashboard.php'); exit;
}

$pdo       = getDB();
$loggedIn  = is_logged();
$jugadorId = current_id();

// Stats
$totalCatalogo = (int)$pdo->query('SELECT COUNT(*) FROM videojuego')->fetchColumn();
$totalBiblio   = $totalResenas = $jugandoAhora = 0;
if ($loggedIn) {
    $s = $pdo->prepare('SELECT COUNT(*) FROM biblioteca WHERE id_jugador = :id');
    $s->execute([':id' => $jugadorId]); $totalBiblio = (int)$s->fetchColumn();

    $s = $pdo->prepare('SELECT COUNT(*) FROM resena WHERE id_jugador = :id');
    $s->execute([':id' => $jugadorId]); $totalResenas = (int)$s->fetchColumn();

    $s = $pdo->prepare("SELECT COUNT(*) FROM biblioteca WHERE id_jugador = :id AND estado='Jugando'");
    $s->execute([':id' => $jugadorId]); $jugandoAhora = (int)$s->fetchColumn();
}

// Juegos destacados — LEFT JOIN parametrizado (sin interpolación de $jugadorId)
$destacadosStmt = $pdo->prepare(
    'SELECT v.id_videojuego, v.titulo, v.genero, v.anio, v.calificacion,
            v.precio, v.descripcion, v.steam_id,
            b.estado, b.progreso
     FROM videojuego v
     LEFT JOIN biblioteca b ON b.id_videojuego = v.id_videojuego
                            AND b.id_jugador = :jid
     ORDER BY v.calificacion DESC LIMIT 10'
);
$destacadosStmt->execute([':jid' => $jugadorId]);
$destacados = $destacadosStmt->fetchAll();

// Actividad reciente
$actividad = [];
if ($loggedIn) {
    $s = $pdo->prepare('SELECT texto, tipo, fecha FROM actividad WHERE id_jugador = :id ORDER BY fecha DESC LIMIT 5');
    $s->execute([':id' => $jugadorId]);
    $actividad = $s->fetchAll();
}

$heroGames   = array_slice($destacados, 0, 3);
$active_page = 'dashboard';
$page_title  = 'Dashboard';
require_once __DIR__ . '/../../../backend/helpers/header.php';
?>

<section class="dashboard">

  <!-- Hero Slider -->
  <div class="hero-slider" id="heroSlider">
    <?php foreach ($heroGames as $i => $g): ?>
    <div class="hero-slide <?= $i === 0 ? 'active' : '' ?>"
         style="background-image:url('https://cdn.akamai.steamstatic.com/steam/apps/<?= $g['steam_id'] ?>/library_hero.jpg')">
      <div class="hero-overlay"></div>
      <div class="hero-content">
        <div class="hero-badge">DESTACADO</div>
        <h2 class="hero-title"><?= htmlspecialchars($g['titulo']) ?></h2>
        <p class="hero-desc"><?= htmlspecialchars(substr($g['descripcion'] ?? '', 0, 110)) ?>...</p>
        <div class="hero-meta">
          <span class="hero-rating">★ <?= $g['calificacion'] ?></span>
          <span class="hero-genre"><?= htmlspecialchars($g['genero']) ?></span>
          <?php if ($g['estado']): ?>
            <span class="status-badge status-<?= $g['estado'] ?>"><?= $g['estado'] ?></span>
          <?php endif; ?>
        </div>
        <div class="hero-actions">
          <?php if (!$g['estado']): ?>
            <?php if ($loggedIn): ?>
              <button class="btn-primary"
                      onclick="toggleCarrito(<?= $g['id_videojuego'] ?>, false, this)">
                🛒 Agregar al carrito
              </button>
            <?php else: ?>
              <a href="/specter/login.php?redirect=%2Fspecter%2Fcarrito.php" class="btn-primary">
                🛒 Agregar al carrito
              </a>
            <?php endif; ?>
          <?php else: ?>
            <a href="/specter/biblioteca.php" class="btn-primary">Ir a mi biblioteca</a>
          <?php endif; ?>
          <a href="/specter/catalogo.php" class="btn-ghost">Ver catálogo</a>
        </div>
      </div>
      <img class="hero-cover"
           src="https://cdn.akamai.steamstatic.com/steam/apps/<?= $g['steam_id'] ?>/library_600x900.jpg"
           alt="<?= htmlspecialchars($g['titulo']) ?>"
           onerror="this.style.display='none'">
    </div>
    <?php endforeach; ?>
    <div class="hero-dots">
      <?php for ($i = 0; $i < count($heroGames); $i++): ?>
        <button class="hero-dot <?= $i === 0 ? 'active' : '' ?>" onclick="goToSlide(<?= $i ?>)"></button>
      <?php endfor; ?>
    </div>
    <button class="hero-arrow hero-prev" onclick="prevSlide()">‹</button>
    <button class="hero-arrow hero-next" onclick="nextSlide()">›</button>
  </div>

  <!-- Stats -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-icon">🎮</div>
      <div class="stat-value"><?= number_format($totalCatalogo) ?></div>
      <div class="stat-label">Juegos en catálogo</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">📚</div>
      <div class="stat-value"><?= $totalBiblio ?></div>
      <div class="stat-label">En mi biblioteca</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">★</div>
      <div class="stat-value"><?= $totalResenas ?></div>
      <div class="stat-label">Reseñas escritas</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">▶</div>
      <div class="stat-value"><?= $jugandoAhora ?></div>
      <div class="stat-label">Jugando ahora</div>
    </div>
  </div>

  <!-- Destacados + Actividad -->
  <div class="section-header">
    <h2 class="section-title">DESTACADOS</h2>
    <a href="/specter/catalogo.php" class="section-link">Ver todo →</a>
    <div class="activity-label">ACTIVIDAD</div>
  </div>

  <div class="dashboard-columns">
    <div class="featured-grid">
      <?php foreach (array_slice($destacados, 0, 6) as $g): ?>
      <div class="game-card" onclick="openModal(<?= $g['id_videojuego'] ?>)">
        <div class="game-cover"
             style="background-image:url('https://cdn.akamai.steamstatic.com/steam/apps/<?= $g['steam_id'] ?>/library_600x900.jpg')">
          <?php if ($g['estado']): ?>
            <div class="owned-badge">✓</div>
          <?php endif; ?>
        </div>
        <div class="game-info">
          <div class="game-title"><?= htmlspecialchars($g['titulo']) ?></div>
          <div class="game-meta">
            <span><?= htmlspecialchars($g['genero']) ?></span>
            <span class="rating">★<?= $g['calificacion'] ?></span>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="activity-panel">
      <?php if ($loggedIn && $actividad): ?>
        <?php foreach ($actividad as $act): ?>
        <div class="activity-item activity-<?= htmlspecialchars($act['tipo'] ?? 'sistema') ?>">
          <div class="activity-text"><?= htmlspecialchars($act['texto']) ?></div>
          <div class="activity-time"><?= date('d M', strtotime($act['fecha'])) ?></div>
        </div>
        <?php endforeach; ?>
      <?php elseif ($loggedIn): ?>
        <div class="activity-empty">
          <p>Aún no hay actividad.</p>
          <a href="/specter/catalogo.php" class="btn-ghost btn-sm">Explorar</a>
        </div>
      <?php else: ?>
        <div class="activity-empty">
          <p>Inicia sesión para ver tu actividad.</p>
          <a href="/specter/login.php" class="btn-primary btn-sm">Iniciar sesión</a>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Acciones rápidas -->
  <div class="quick-actions">
    <a href="/specter/catalogo.php" class="quick-btn">◫ Explorar catálogo</a>
    <?php if ($loggedIn): ?>
      <a href="/specter/biblioteca.php" class="quick-btn">⊟ Mi biblioteca</a>
      <a href="/specter/resenas.php"    class="quick-btn">✎ Escribir reseña</a>
      <a href="/specter/carrito.php"    class="quick-btn">⊡ Mi carrito</a>
    <?php else: ?>
      <a href="/specter/login.php"    class="quick-btn">→ Iniciar sesión</a>
      <a href="/specter/registro.php" class="quick-btn">+ Crear cuenta</a>
    <?php endif; ?>
  </div>

</section>

<?php require_once __DIR__ . '/../../../backend/helpers/footer.php'; ?>
