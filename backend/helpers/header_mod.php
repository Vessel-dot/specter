<?php
// ── includes/header_mod.php ──────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
if (!function_exists('getDB')) require_once __DIR__ . '/../config/config.php';

$nombre = current_nombre();

$nav_mod = [
    'mod_dashboard' => ['icon' => '⊞', 'label' => 'Panel General', 'href' => '/specter/moderador/dashboard.php'],
    'mod_resenas'   => ['icon' => '✎', 'label' => 'Reseñas',       'href' => '/specter/moderador/resenas.php'],
    'mod_usuarios'  => ['icon' => '⊟', 'label' => 'Usuarios',      'href' => '/specter/moderador/usuarios.php'],
    'mod_reportes'  => ['icon' => '◫', 'label' => 'Reportes',      'href' => '/specter/moderador/reportes.php'],
];
?>
<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Specter MOD — <?= htmlspecialchars($page_title ?? 'Panel') ?></title>
  <link rel="stylesheet" href="/specter/assets/css/style.css">
  <meta name="csrf-token" content="<?= csrf_token() ?>">
</head>
<body class="mod-body" data-loggedin="1">

<nav class="sidebar sidebar-mod" id="sidebar">
  <div class="sidebar-logo">
    <a href="/specter/moderador/dashboard.php" class="logo-link">
      <span class="logo-diamond" style="color:var(--amber)">◇</span>
      <span class="logo-text">SPECTER <span style="color:var(--amber);font-size:10px">MOD</span></span>
    </a>
  </div>

  <div class="sidebar-section-label">MODERACIÓN</div>
  <ul class="sidebar-nav">
    <?php foreach ($nav_mod as $key => $item): ?>
    <li>
      <a href="<?= $item['href'] ?>"
         class="nav-item <?= ($active_page ?? '') === $key ? 'active' : '' ?>">
        <span class="nav-icon"><?= $item['icon'] ?></span>
        <span class="nav-label"><?= $item['label'] ?></span>
      </a>
    </li>
    <?php endforeach; ?>
  </ul>

  <div class="sidebar-bottom">
    <a href="/specter/logout.php" class="nav-item sidebar-logout">
      <span class="nav-icon">⊗</span>
      <span class="nav-label">Cerrar sesión</span>
    </a>
    <div class="sidebar-user">
      <div class="user-avatar" style="background:var(--amber);color:#000">
        <?= strtoupper(substr($nombre, 0, 2)) ?>
      </div>
      <div class="user-info">
        <div class="user-name"><?= htmlspecialchars($nombre) ?></div>
        <div class="user-role" style="color:var(--amber)">Moderador</div>
      </div>
    </div>
  </div>
</nav>

<main class="main-content" id="mainContent">
  <div class="topbar">
    <div class="topbar-breadcrumb">
      <span style="color:var(--amber)">MOD</span> · <?= htmlspecialchars($page_title ?? 'Panel') ?>
    </div>
    <div class="topbar-actions">
      <span class="topbar-user"><?= htmlspecialchars($nombre) ?></span>
    </div>
  </div>
  <div class="page-content">
