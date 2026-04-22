<?php
// ── api/catalogo.php ──────────────────────────────────────────
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/auth_guard.php';
require_once __DIR__ . '/../helpers/carrito_helper.php';
header('Content-Type: application/json');

$pdo       = getDB();
$jugadorId = current_id();
$action    = $_GET['action'] ?? $_POST['action'] ?? '';

// ── DETALLE ───────────────────────────────────────────────────
if ($action === 'detalle') {
    $idJuego = (int)($_GET['id'] ?? 0);
    if (!$idJuego) { echo json_encode(['error' => 'ID inválido.']); exit; }

    $s = $pdo->prepare('SELECT * FROM videojuego WHERE id_videojuego = :vid');
    $s->execute([':vid' => $idJuego]);
    $juego = $s->fetch();
    if (!$juego) { echo json_encode(['error' => 'Juego no encontrado.']); exit; }

    $juego['estado'] = null; $juego['progreso'] = 0;
    $juego['id_resena'] = $juego['mi_cal'] = $juego['mi_resena'] = null;

    if ($jugadorId) {
        $b = $pdo->prepare('SELECT estado, progreso FROM biblioteca WHERE id_videojuego = :vid AND id_jugador = :jid');
        $b->execute([':vid' => $idJuego, ':jid' => $jugadorId]);
        if ($bib = $b->fetch()) { $juego['estado'] = $bib['estado']; $juego['progreso'] = $bib['progreso']; }

        $r = $pdo->prepare('SELECT id_resena, calificacion AS mi_cal, texto AS mi_resena FROM resena WHERE id_videojuego = :vid AND id_jugador = :jid');
        $r->execute([':vid' => $idJuego, ':jid' => $jugadorId]);
        if ($res = $r->fetch()) { $juego = array_merge($juego, $res); }
    }

    $inCart = false;
    if ($jugadorId) {
        $c = $pdo->prepare(
            'SELECT ci.id_item FROM carrito_item ci
             JOIN carrito c ON c.id_carrito = ci.id_carrito
             WHERE c.id_jugador = :jid AND ci.id_videojuego = :vid AND ci.es_regalo = FALSE'
        );
        $c->execute([':jid' => $jugadorId, ':vid' => $idJuego]);
        $inCart = (bool)$c->fetch();
    }
    echo json_encode(['success' => true, 'juego' => $juego, 'in_cart' => $inCart]);
    exit;
}

// ── TOGGLE CARRITO ────────────────────────────────────────────
if ($action === 'toggle_carrito') {
    if (!is_logged()) { echo json_encode(['error' => 'Sesión requerida.']); exit; }
    csrf_verify();
    require_auth('jugador');

    $idJuego  = (int)($_POST['id_videojuego'] ?? 0);
    $esRegalo = !empty($_POST['es_regalo']) && $_POST['es_regalo'] !== '0';
    $carritoId = get_or_create_carrito($pdo, $jugadorId);

    $dup = $pdo->prepare(
        'SELECT id_item FROM carrito_item WHERE id_carrito = :cid AND id_videojuego = :vid AND es_regalo = :regalo'
    );
    $dup->execute([':cid' => $carritoId, ':vid' => $idJuego, ':regalo' => db_bool($esRegalo)]);
    $existing = $dup->fetch();

    if ($existing) {
        $pdo->prepare('DELETE FROM carrito_item WHERE id_item = :iid')->execute([':iid' => $existing['id_item']]);
        $action_done = 'removed';
    } else {
        $p = $pdo->prepare('SELECT precio FROM videojuego WHERE id_videojuego = :vid');
        $p->execute([':vid' => $idJuego]);
        $precio = $p->fetchColumn();
        if (!$precio) { echo json_encode(['error' => 'Juego no encontrado.']); exit; }
        $pdo->prepare(
            'INSERT INTO carrito_item (id_carrito, id_videojuego, precio_item, es_regalo)
             VALUES (:cid, :vid, :precio, :regalo)'
        )->execute([':cid' => $carritoId, ':vid' => $idJuego, ':precio' => $precio, ':regalo' => db_bool($esRegalo)]);
        $action_done = 'added';
    }
    $cntStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM carrito_item ci
         JOIN carrito c ON c.id_carrito = ci.id_carrito
         WHERE c.id_jugador = :jid'
    );
    $cntStmt->execute([':jid' => $jugadorId]);
    echo json_encode(['success' => true, 'action' => $action_done, 'cart_count' => (int)$cntStmt->fetchColumn()]);
    exit;
}

// ── UPDATE BIBLIOTECA ─────────────────────────────────────────
if ($action === 'update_bib' && is_logged()) {
    csrf_verify();
    $idJuego  = (int)($_POST['id_videojuego'] ?? 0);
    $progreso = isset($_POST['progreso']) ? (int)$_POST['progreso'] : null;
    $estado   = $_POST['estado'] ?? null;

    $sets = []; $prms = [':jid' => $jugadorId, ':vid' => $idJuego];
    if ($progreso !== null) { $sets[] = 'progreso = :prog'; $prms[':prog'] = max(0, min(100, $progreso)); }
    if ($estado !== null && in_array($estado, ['Jugando','Completado','Pendiente'])) {
        $sets[] = 'estado = :est'; $prms[':est'] = $estado;
    }
    if (empty($sets)) { echo json_encode(['error' => 'Sin cambios.']); exit; }
    $pdo->prepare('UPDATE biblioteca SET ' . implode(', ', $sets) . ' WHERE id_jugador = :jid AND id_videojuego = :vid')->execute($prms);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['error' => 'Acción no reconocida.']);
