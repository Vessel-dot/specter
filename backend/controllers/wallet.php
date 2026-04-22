<?php
// ── api/wallet.php ────────────────────────────────────────────
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/auth_guard.php';
header('Content-Type: application/json');
require_auth('jugador');

$pdo       = getDB();
$jugadorId = current_id();
$action    = $_GET['action'] ?? $_POST['action'] ?? '';

// ── VER SALDO ─────────────────────────────────────────────────
if ($action === 'saldo') {
    $s = $pdo->prepare('SELECT saldo_specter FROM jugador WHERE id_jugador = :jid');
    $s->execute([':jid' => $jugadorId]);
    $saldo = (float)$s->fetchColumn();
    echo json_encode(['success' => true, 'saldo' => number_format($saldo, 2)]);
    exit;
}

// ── VER MOVIMIENTOS ───────────────────────────────────────────
if ($action === 'movimientos') {
    $stmt = $pdo->prepare(
        'SELECT tipo, monto, descripcion, fecha
         FROM movimiento_saldo
         WHERE id_jugador = :jid
         ORDER BY fecha DESC LIMIT 50'
    );
    $stmt->execute([':jid' => $jugadorId]);
    echo json_encode(['success' => true, 'movimientos' => $stmt->fetchAll()]);
    exit;
}

// ── RECARGAR (simula recarga con tarjeta) ─────────────────────
if ($action === 'recargar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_once __DIR__ . '/../helpers/luhn.php';
    $monto   = (float)($_POST['monto'] ?? 0);
    $numero  = preg_replace('/\D/', '', $_POST['numero_tarjeta'] ?? '');

    if ($monto <= 0)           { echo json_encode(['error' => 'Monto inválido.']); exit; }
    if (!luhn_check($numero))  { echo json_encode(['error' => 'Tarjeta inválida (falla Luhn).']); exit; }

    try {
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE jugador SET saldo_specter = saldo_specter + :monto WHERE id_jugador = :jid')
            ->execute([':monto' => $monto, ':jid' => $jugadorId]);
        $pdo->prepare(
            "INSERT INTO movimiento_saldo (id_jugador, tipo, monto, descripcion)
             VALUES (:jid, 'recarga', :monto, :desc)"
        )->execute([
            ':jid'   => $jugadorId,
            ':monto' => $monto,
            ':desc'  => 'Recarga con tarjeta *' . substr($numero, -4),
        ]);
        $pdo->commit();

        $s = $pdo->prepare('SELECT saldo_specter FROM jugador WHERE id_jugador = :jid');
        $s->execute([':jid' => $jugadorId]);
        echo json_encode(['success' => true, 'nuevo_saldo' => number_format((float)$s->fetchColumn(), 2)]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Error al recargar.']);
    }
    exit;
}

echo json_encode(['error' => 'Acción no reconocida.']);
