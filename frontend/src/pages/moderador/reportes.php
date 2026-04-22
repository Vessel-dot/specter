<?php
// ── moderador/reportes.php ────────────────────────────────────
require_once __DIR__ . '/../../../../backend/config/config.php';
require_once __DIR__ . '/../../../../backend/helpers/auth_guard.php';
require_auth('moderador');

$pdo = getDB();

// Ingresos totales
$ingresos = (float)$pdo->query('SELECT COALESCE(SUM(total),0) FROM compra')->fetchColumn();
$totalCompras = (int)$pdo->query('SELECT COUNT(*) FROM compra')->fetchColumn();

// Distribución por género
$generos = $pdo->query(
    'SELECT genero, COUNT(*) AS total FROM videojuego
     WHERE genero IS NOT NULL GROUP BY genero ORDER BY total DESC'
)->fetchAll();

// Juegos más comprados
$masComprados = $pdo->query(
    'SELECT v.titulo, v.steam_id, COUNT(*) AS veces
     FROM compra_detalle cd JOIN videojuego v ON v.id_videojuego = cd.id_videojuego
     GROUP BY v.titulo, v.steam_id ORDER BY veces DESC LIMIT 5'
)->fetchAll();

// Reseñas por estado
$resenasStats = $pdo->query(
    "SELECT estado_moderacion, COUNT(*) AS total FROM resena GROUP BY estado_moderacion"
)->fetchAll();

// Usuarios registrados últimos 7 días
$registrosRecientes = $pdo->query(
    "SELECT DATE(fecha_registro) AS dia, COUNT(*) AS total
     FROM jugador WHERE fecha_registro >= NOW() - INTERVAL '7 days'
     GROUP BY dia ORDER BY dia"
)->fetchAll();

$active_page = 'mod_reportes';
$page_title  = 'Reportes y Métricas';
require_once __DIR__ . '/../../../../backend/helpers/header_mod.php';
?>

<section class="mod-section">
  <h1 class="page-heading">Reportes y Métricas</h1>

  <!-- KPIs -->
  <div class="stats-grid stats-mod">
    <div class="stat-card stat-accent">
      <div class="stat-icon">$</div>
      <div class="stat-value">$<?= number_format($ingresos, 2) ?></div>
      <div class="stat-label">Ingresos totales</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">🛒</div>
      <div class="stat-value"><?= $totalCompras ?></div>
      <div class="stat-label">Compras realizadas</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">🎮</div>
      <div class="stat-value"><?= $pdo->query('SELECT COUNT(*) FROM videojuego')->fetchColumn() ?></div>
      <div class="stat-label">Juegos en catálogo</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">★</div>
      <div class="stat-value"><?= $pdo->query('SELECT COUNT(*) FROM resena')->fetchColumn() ?></div>
      <div class="stat-label">Reseñas publicadas</div>
    </div>
  </div>

  <div class="reportes-grid">

    <!-- Géneros del catálogo -->
    <div class="reporte-card">
      <h3>Distribución por género</h3>
      <?php $maxGen = max(array_column($generos, 'total')) ?: 1; ?>
      <?php foreach ($generos as $g): ?>
      <div class="bar-row">
        <span class="bar-label"><?= htmlspecialchars($g['genero']) ?></span>
        <div class="bar-track">
          <div class="bar-fill" style="width:<?= round($g['total']/$maxGen*100) ?>%"></div>
        </div>
        <span class="bar-val"><?= $g['total'] ?></span>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Juegos más comprados -->
    <div class="reporte-card">
      <h3>Top 5 — Más comprados</h3>
      <div class="top-list">
        <?php foreach ($masComprados as $i => $j): ?>
        <div class="top-item">
          <span class="top-rank">#<?= $i+1 ?></span>
          <div class="top-cover"
               style="background-image:url('https://cdn.akamai.steamstatic.com/steam/apps/<?= $j['steam_id'] ?>/library_600x900.jpg')">
          </div>
          <div class="top-info">
            <div class="top-name"><?= htmlspecialchars($j['titulo']) ?></div>
            <div class="top-count"><?= $j['veces'] ?> compras</div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($masComprados)): ?>
          <p class="empty-note">Aún no hay compras registradas.</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Estado de reseñas -->
    <div class="reporte-card">
      <h3>Reseñas por estado</h3>
      <?php
      $estadoMap = ['LIMPIO' => 'Aprobadas', 'EN_REVISION' => 'En revisión', 'BLOQUEADO' => 'Bloqueadas'];
      $totRes    = array_sum(array_column($resenasStats, 'total')) ?: 1;
      foreach ($resenasStats as $rs):
      ?>
      <div class="bar-row">
        <span class="bar-label"><?= $estadoMap[$rs['estado_moderacion']] ?? $rs['estado_moderacion'] ?></span>
        <div class="bar-track">
          <div class="bar-fill bar-fill-<?= strtolower($rs['estado_moderacion']) ?>"
               style="width:<?= round($rs['total']/$totRes*100) ?>%"></div>
        </div>
        <span class="bar-val"><?= $rs['total'] ?></span>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Registros recientes -->
    <div class="reporte-card">
      <h3>Registros — Últimos 7 días</h3>
      <?php if (empty($registrosRecientes)): ?>
        <p class="empty-note">No hay registros en los últimos 7 días.</p>
      <?php else: ?>
        <?php $maxReg = max(array_column($registrosRecientes,'total')) ?: 1; ?>
        <?php foreach ($registrosRecientes as $reg): ?>
        <div class="bar-row">
          <span class="bar-label"><?= date('d/m', strtotime($reg['dia'])) ?></span>
          <div class="bar-track">
            <div class="bar-fill" style="width:<?= round($reg['total']/$maxReg*100) ?>%"></div>
          </div>
          <span class="bar-val"><?= $reg['total'] ?></span>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div>
</section>

<?php require_once __DIR__ . '/../../../../backend/helpers/footer.php'; ?>
