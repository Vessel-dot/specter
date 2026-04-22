<?php
// ── logout.php ───────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
session_destroy();
setcookie('tema', '', time() - 3600, '/');
header('Location: /specter/login.php');
exit;
