<?php
// ── includes/header.php ──────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
if (!function_exists('getDB')) require_once __DIR__ . '/../config/config.php';

$nombre   = current_nombre();
$rol      = current_rol();
$loggedIn = is_logged();

$cartCount = 0;
if ($loggedIn && $rol === 'jugador') {
    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM carrito_item ci
             JOIN carrito c ON c.id_carrito = ci.id_carrito
             WHERE c.id_jugador = :id'
        );
        $stmt->execute([':id' => current_id()]);
        $cartCount = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {}
}

// Saldo para el badge
$saldoBadge = '';
if ($loggedIn && $rol === 'jugador') {
    try {
        $sw = getDB()->prepare('SELECT saldo_specter FROM jugador WHERE id_jugador = :id');
        $sw->execute([':id' => current_id()]);
        $saldoVal = (float)$sw->fetchColumn();
        $saldoBadge = '$' . number_format($saldoVal, 0);
    } catch (PDOException $e) {}
}

$nav = [
    'dashboard' => ['icon' => '⊞', 'label' => 'Dashboard',  'href' => '/specter/index.php'],
    'catalogo'  => ['icon' => '◫', 'label' => 'Catálogo',   'href' => '/specter/catalogo.php'],
    'biblioteca'=> ['icon' => '⊟', 'label' => 'Biblioteca', 'href' => '/specter/biblioteca.php'],
    'resenas'   => ['icon' => '✎', 'label' => 'Reseñas',    'href' => '/specter/resenas.php'],
    'carrito'   => ['icon' => '⊡', 'label' => 'Carrito',    'href' => '/specter/carrito.php', 'badge' => $cartCount],
    'wallet'    => ['icon' => '◈', 'label' => 'Cartera',    'href' => '/specter/wallet.php', 'badge' => $saldoBadge],
    'tarjetas'  => ['icon' => '◉', 'label' => 'Tarjetas',   'href' => '/specter/tarjetas_specter.php'],
];
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?= htmlspecialchars($_COOKIE['tema'] ?? 'dark') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Specter — <?= htmlspecialchars($page_title ?? 'Sistema de Videojuegos') ?></title>
  <link rel="stylesheet" href="/specter/assets/css/style.css">
  <meta name="csrf-token" content="<?= csrf_token() ?>">
</head>
<body data-loggedin="<?= $loggedIn ? '1' : '0' ?>">

<nav class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <a href="/specter/index.php" class="logo-link">
      <span class="logo-diamond">◇</span>
      <span class="logo-text">SPECTER</span>
    </a>
    <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
  </div>

  <div class="sidebar-search">
    <input type="text" class="search-input" placeholder="Buscar juego..."
           onkeydown="if(event.key==='Enter') window.location='/specter/catalogo.php?q='+encodeURIComponent(this.value)">
  </div>

  <div class="sidebar-section-label">MENÚ</div>
  <ul class="sidebar-nav">
    <?php foreach ($nav as $key => $item): ?>
    <li>
      <a href="<?= $item['href'] ?>"
         class="nav-item <?= ($active_page ?? '') === $key ? 'active' : '' ?>">
        <span class="nav-icon"><?= $item['icon'] ?></span>
        <span class="nav-label"><?= $item['label'] ?></span>
        <?php if ($key === 'carrito'): ?>
          <span class="nav-badge" id="cartNavBadge"
                <?= $cartCount > 0 ? '' : 'style="display:none"' ?>><?= $cartCount ?></span>
        <?php elseif (!empty($item['badge']) && $item['badge'] > 0): ?>
          <span class="nav-badge"><?= $item['badge'] ?></span>
        <?php endif; ?>
      </a>
    </li>
    <?php endforeach; ?>
  </ul>

  <?php if ($loggedIn): ?>
  <div class="sidebar-section-label">RECIENTES</div>
  <div class="sidebar-recientes">
    <?php
    try {
        $pdo2 = getDB();
        $rec  = $pdo2->prepare(
            'SELECT v.titulo, v.steam_id FROM biblioteca b
             JOIN videojuego v ON v.id_videojuego = b.id_videojuego
             WHERE b.id_jugador = :id ORDER BY b.fecha_agregado DESC LIMIT 3'
        );
        $rec->execute([':id' => current_id()]);
        foreach ($rec->fetchAll() as $r): ?>
        <a href="/specter/catalogo.php" class="reciente-item">
          <div class="reciente-cover"
               style="background-image:url('https://cdn.akamai.steamstatic.com/steam/apps/<?= $r['steam_id'] ?>/library_600x900.jpg')">
          </div>
          <span class="reciente-title"><?= htmlspecialchars(mb_substr($r['titulo'], 0, 18)) ?></span>
        </a>
        <?php endforeach;
    } catch (PDOException $e) {}
    ?>
  </div>
  <?php endif; ?>

  <div class="sidebar-bottom">
    <div class="theme-switcher">
      <button onclick="setTheme('dark')"   class="theme-btn" title="Dark">●</button>
      <button onclick="setTheme('light')"  class="theme-btn" title="Light">○</button>
      <button onclick="setTheme('vision')" class="theme-btn" title="Vision">◎</button>
    </div>
    <?php if ($loggedIn): ?>
      <a href="/specter/logout.php" class="nav-item sidebar-logout">
        <span class="nav-icon">⊗</span>
        <span class="nav-label">Cerrar sesión</span>
      </a>
      <div class="sidebar-user">
        <div class="user-avatar"><?= strtoupper(substr($nombre, 0, 2)) ?></div>
        <div class="user-info">
          <div class="user-name"><?= htmlspecialchars($nombre) ?></div>
          <div class="user-role">Jugador</div>
        </div>
      </div>
    <?php else: ?>
      <a href="/specter/login.php" class="nav-item">
        <span class="nav-icon">→</span>
        <span class="nav-label">Iniciar sesión</span>
      </a>
    <?php endif; ?>
  </div>
</nav>

<main class="main-content" id="mainContent">
  <div class="topbar">
    <div class="topbar-breadcrumb"><?= htmlspecialchars($page_title ?? 'Dashboard') ?></div>
    <div class="topbar-actions">
      <?php if (!$loggedIn): ?>
        <a href="/specter/login.php"    class="btn-topbar">Iniciar sesión</a>
        <a href="/specter/registro.php" class="btn-topbar btn-topbar-primary">Registrarse</a>
      <?php else: ?>
        <span class="topbar-user">Hola, <?= htmlspecialchars(explode(' ', $nombre)[0]) ?></span>
      <?php endif; ?>
    </div>
  </div>
  <div class="page-content">

<!-- Modal global detalle de juego -->
<div class="modal-overlay" id="modalOverlay" onclick="closeModal(event)" style="display:none">
  <div class="modal-box" id="modalBox"></div>
</div>
