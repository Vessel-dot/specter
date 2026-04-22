<?php
// ── catalogo.php ─────────────────────────────────────────────
require_once(__DIR__ . '/../../../backend/config/config.php');
require_once(__DIR__ . '/../../../backend/helpers/auth_guard.php');

if (is_logged() && current_rol() === 'moderador') {
    header('Location: /specter/moderador/dashboard.php'); exit;
}

$pdo       = getDB();
$jugadorId = current_id();

// Filtros
$q      = trim($_GET['q']      ?? '');
$genero = trim($_GET['genero'] ?? '');
$anio   = trim($_GET['anio']   ?? '');

// Filtros dinámicos + LEFT JOIN biblioteca completamente parametrizado
$where  = ['1=1'];
$params = [':jid' => $jugadorId]; // siempre presente; NULL es válido en PDO pgsql

if ($q) {
    $where[]      = 'LOWER(v.titulo) LIKE :q';
    $params[':q'] = '%' . strtolower($q) . '%';
}
if ($genero) {
    $where[]           = 'v.genero = :genero';
    $params[':genero'] = $genero;
}
if ($anio) {
    $where[]        = 'v.anio = :anio';
    $params[':anio'] = (int)$anio;
}

$sql = 'SELECT v.id_videojuego, v.titulo, v.genero, v.anio, v.calificacion,
               v.precio, v.steam_id, v.descripcion, b.estado AS en_biblioteca
        FROM videojuego v
        LEFT JOIN biblioteca b ON b.id_videojuego = v.id_videojuego
                               AND b.id_jugador = :jid
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY v.calificacion DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$juegos = $stmt->fetchAll();

// Géneros y años para filtros
$generos = $pdo->query("SELECT DISTINCT genero FROM videojuego WHERE genero IS NOT NULL ORDER BY genero")
               ->fetchAll(PDO::FETCH_COLUMN);
$anios   = $pdo->query("SELECT DISTINCT anio FROM videojuego WHERE anio IS NOT NULL ORDER BY anio DESC")
               ->fetchAll(PDO::FETCH_COLUMN);

// Ítems en carrito del jugador
$enCarrito = [];
if ($jugadorId) {
    $c = $pdo->prepare(
        'SELECT ci.id_videojuego FROM carrito_item ci
         JOIN carrito c ON c.id_carrito = ci.id_carrito
         WHERE c.id_jugador = :id AND ci.es_regalo = FALSE'
    );
    $c->execute([':id' => $jugadorId]);
    $enCarrito = array_column($c->fetchAll(), 'id_videojuego');
}

$active_page = 'catalogo';
$page_title  = 'Catálogo';
require_once __DIR__ . '/../../../backend/helpers/header.php';
?>

<section class="catalogo-section">

  <!-- Filtros -->
  <form method="GET" class="catalogo-filters">
    <div class="filter-form">
      <input type="text" name="q" class="filter-search"
             placeholder="🔍 Buscar juego..." value="<?= htmlspecialchars($q) ?>"
             oninput="if(!this.value) this.form.submit()">

      <div class="genre-pills">
        <a href="/specter/catalogo.php<?= $q ? '?q='.urlencode($q) : '' ?>"
           class="pill <?= !$genero ? 'active' : '' ?>">Todos</a>
        <?php foreach ($generos as $g): ?>
          <a href="?genero=<?= urlencode($g) ?><?= $q ? '&q='.urlencode($q) : '' ?>"
             class="pill <?= $genero === $g ? 'active' : '' ?>">
            <?= htmlspecialchars($g) ?>
          </a>
        <?php endforeach; ?>
      </div>

      <div class="filter-row">
        <select name="anio" class="filter-select" onchange="this.form.submit()">
          <option value="">Todos los años</option>
          <?php foreach ($anios as $a): ?>
            <option value="<?= $a ?>" <?= $anio == $a ? 'selected' : '' ?>><?= $a ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-ghost btn-sm">Buscar</button>
      </div>
    </div>
  </form>

  <!-- Grid -->
  <div class="catalogo-grid">
    <?php foreach ($juegos as $j):
      $inLib    = !empty($j['en_biblioteca']);
      $inCart   = in_array($j['id_videojuego'], $enCarrito);
    ?>
    <div class="game-card-lg">
      <div class="game-cover-lg"
           style="background-image:url('https://cdn.akamai.steamstatic.com/steam/apps/<?= $j['steam_id'] ?>/library_600x900.jpg')"
           onclick="openModal(<?= $j['id_videojuego'] ?>)">
        <?php if ($inLib): ?>
          <div class="owned-badge-catalog">✓ En biblioteca</div>
        <?php endif; ?>
        <div class="cover-hover">Ver detalles</div>
      </div>
      <div class="game-info-lg">
        <div class="badge-row">
          <span class="genre-badge"><?= htmlspecialchars($j['genero']) ?></span>
          <?php if ($inCart): ?>
            <span class="incart-badge">En carrito</span>
          <?php endif; ?>
        </div>
        <div class="game-title-lg"><?= htmlspecialchars($j['titulo']) ?></div>
        <div class="game-sub">
          <span><?= $j['anio'] ?></span>
          <span class="rating">★<?= $j['calificacion'] ?></span>
        </div>

        <?php if (!$inLib): ?>
          <div class="price-row">
            <span class="price">$<?= number_format((float)$j['precio'], 2) ?></span>
          </div>
          <?php if (is_logged()): ?>
            <button class="btn-cart <?= $inCart ? 'btn-cart-remove' : '' ?>"
                    onclick="toggleCarrito(<?= $j['id_videojuego'] ?>, <?= $inCart ? 'true':'false' ?>, this)">
              <?= $inCart ? '✕ Quitar' : '🛒 Agregar' ?>
            </button>
          <?php else: ?>
            <a href="/specter/login.php?redirect=%2Fspecter%2Fcarrito.php"
               class="btn-cart">🛒 Agregar</a>
          <?php endif; ?>
        <?php else: ?>
          <?php if (is_logged()): ?>
            <button class="btn-gift" onclick="agregarRegalo(<?= $j['id_videojuego'] ?>, this)">
              🎁 Comprar como regalo
            </button>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($juegos)): ?>
      <div class="empty-state full-width">
        <p>No se encontraron juegos con ese criterio.</p>
        <a href="/specter/catalogo.php" class="btn-ghost">Limpiar filtros</a>
      </div>
    <?php endif; ?>
  </div>

</section>

<?php require_once __DIR__ . '/../../../backend/helpers/footer.php'; ?>
