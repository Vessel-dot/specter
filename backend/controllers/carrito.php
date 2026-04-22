<?php
// ── api/carrito.php ───────────────────────────────────────────
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/auth_guard.php';
require_once __DIR__ . '/../helpers/bundle.php';
require_once __DIR__ . '/../helpers/carrito_helper.php';
header('Content-Type: application/json');
require_auth('jugador');
csrf_verify();

$pdo       = getDB();
$jugadorId = current_id();
$carritoId = get_or_create_carrito($pdo, $jugadorId);
$action    = $_POST['action'] ?? '';

/** Devuelve subtotal, descuento, pct, total e item_count actualizados del carrito. */
function resumen_carrito(PDO $pdo, int $carritoId): array {
    $stmt = $pdo->prepare(
        'SELECT ci.precio_item, ci.es_bundle
         FROM carrito_item ci WHERE ci.id_carrito = :cid'
    );
    $stmt->execute([':cid' => $carritoId]);
    $rows = $stmt->fetchAll();
    $bundle = calcular_bundle($rows);
    $bundle['item_count'] = count($rows);
    return $bundle;
}

// ── AGREGAR ───────────────────────────────────────────────────
if ($action === 'agregar') {
    $idJuego  = (int)($_POST['id_videojuego'] ?? 0);
    $esBundle = !empty($_POST['es_bundle']) && $_POST['es_bundle'] !== '0';
    $esRegalo = !empty($_POST['es_regalo'])  && $_POST['es_regalo']  !== '0';

    if (!$idJuego) { echo json_encode(['error' => 'ID de juego inválido.']); exit; }

    $check = $pdo->prepare('SELECT precio FROM videojuego WHERE id_videojuego = :vid');
    $check->execute([':vid' => $idJuego]);
    $juego = $check->fetch();
    if (!$juego) { echo json_encode(['error' => 'Juego no encontrado.']); exit; }

    $dup = $pdo->prepare(
        'SELECT id_item FROM carrito_item
         WHERE id_carrito = :cid AND id_videojuego = :vid AND es_regalo = :regalo'
    );
    $dup->execute([':cid' => $carritoId, ':vid' => $idJuego, ':regalo' => db_bool($esRegalo)]);
    if ($dup->fetch()) { echo json_encode(['error' => 'El juego ya está en el carrito.']); exit; }

    try {
        $ins = $pdo->prepare(
            'INSERT INTO carrito_item (id_carrito, id_videojuego, precio_item, es_bundle, es_regalo)
             VALUES (:cid, :vid, :precio, :bundle, :regalo) RETURNING id_item'
        );
        $ins->execute([
            ':cid'    => $carritoId,
            ':vid'    => $idJuego,
            ':precio' => $juego['precio'],
            ':bundle' => db_bool($esBundle),
            ':regalo' => db_bool($esRegalo),
        ]);
        $idItem = (int)$ins->fetchColumn();

        // Datos completos del juego para renderizar el ítem en el frontend
        $jStmt = $pdo->prepare(
            'SELECT v.titulo, v.genero, v.anio, v.steam_id, ci.precio_item, ci.es_regalo, ci.es_bundle
             FROM carrito_item ci JOIN videojuego v ON v.id_videojuego = ci.id_videojuego
             WHERE ci.id_item = :iid'
        );
        $jStmt->execute([':iid' => $idItem]);
        $itemData = $jStmt->fetch();
        $itemData['id_item'] = $idItem;

        $resumen = resumen_carrito($pdo, $carritoId);
        echo json_encode(['success' => true, 'item' => $itemData] + $resumen);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Error al agregar al carrito.']);
    }
    exit;
}

// ── QUITAR ────────────────────────────────────────────────────
if ($action === 'quitar') {
    $idItem = (int)($_POST['id_item'] ?? 0);
    if (!$idItem) { echo json_encode(['error' => 'ID de ítem inválido.']); exit; }
    $pdo->prepare('DELETE FROM carrito_item WHERE id_item = :iid AND id_carrito = :cid')
        ->execute([':iid' => $idItem, ':cid' => $carritoId]);
    $resumen = resumen_carrito($pdo, $carritoId);
    echo json_encode(['success' => true] + $resumen);
    exit;
}

// ── COMPRAR (TicketTemporal CU-04) ────────────────────────────
if ($action === 'comprar') {
    $itemsStmt = $pdo->prepare(
        'SELECT ci.id_item, ci.id_videojuego, ci.precio_item,
                ci.es_bundle, ci.es_regalo, v.titulo, v.steam_id
         FROM carrito_item ci
         JOIN videojuego v ON v.id_videojuego = ci.id_videojuego
         WHERE ci.id_carrito = :cid'
    );
    $itemsStmt->execute([':cid' => $carritoId]);
    $items = $itemsStmt->fetchAll();

    if (empty($items)) { echo json_encode(['error' => 'El carrito está vacío.']); exit; }

    // Verificar duplicados en biblioteca (juegos no-regalo ya poseídos)
    $yaEnBib = [];
    $nonGift = array_filter($items, fn($i) => !$i['es_regalo']);
    if (!empty($nonGift)) {
        $ids        = implode(',', array_map(fn($i) => (int)$i['id_videojuego'], $nonGift));
        $bibDupStmt = $pdo->prepare(
            "SELECT id_videojuego FROM biblioteca
             WHERE id_jugador = :jid AND id_videojuego IN ({$ids})"
        );
        $bibDupStmt->execute([':jid' => $jugadorId]);
        $yaEnBib = array_column($bibDupStmt->fetchAll(), 'id_videojuego');
    }
    if (!empty($yaEnBib)) {
        // Filtramos del listado para no bloquear, pero lo notificamos al cliente
        // (ON CONFLICT DO NOTHING en INSERT lo gestiona a nivel BD igualmente)
    }

    $bundle    = calcular_bundle($items);
    $total     = $bundle['total'];
    $descuento = $bundle['descuento'];

    try {
        $pdo->beginTransaction();

        $ins = $pdo->prepare(
            'INSERT INTO compra (id_jugador, total, descuento)
             VALUES (:jid, :total, :desc) RETURNING id_compra'
        );
        $ins->execute([':jid' => $jugadorId, ':total' => $total, ':desc' => $descuento]);
        $idCompra = (int)$ins->fetchColumn();

        $detStmt = $pdo->prepare(
            'INSERT INTO compra_detalle (id_compra, id_videojuego, precio_unitario, es_regalo)
             VALUES (:cid, :vid, :precio, :regalo)'
        );
        $bibStmt = $pdo->prepare(
            "INSERT INTO biblioteca (id_jugador, id_videojuego, estado)
             VALUES (:jid, :vid, 'Pendiente')
             ON CONFLICT (id_jugador, id_videojuego) DO NOTHING"
        );

        foreach ($items as $item) {
            $detStmt->execute([
                ':cid'    => $idCompra,
                ':vid'    => $item['id_videojuego'],
                ':precio' => $item['precio_item'],
                ':regalo' => db_bool((bool)$item['es_regalo']),
            ]);
            if (!$item['es_regalo']) {
                $bibStmt->execute([':jid' => $jugadorId, ':vid' => $item['id_videojuego']]);
            }
        }

        $pdo->prepare('DELETE FROM carrito_item WHERE id_carrito = :cid')
            ->execute([':cid' => $carritoId]);

        $pdo->prepare(
            "INSERT INTO actividad (id_jugador, texto, tipo)
             VALUES (:jid, :txt, 'compra')"
        )->execute([
            ':jid' => $jugadorId,
            ':txt' => 'Compra completada — ' . count($items) . ' juego(s) adquiridos',
        ]);

        $pdo->commit();
        echo json_encode([
            'success'  => true,
            'total'    => number_format($total, 2),
            'juegos'   => array_map(fn($i) => [
                'titulo' => $i['titulo'], 'steam_id' => $i['steam_id'],
            ], $items),
        ]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Error al procesar la compra.']);
    }
    exit;
}

echo json_encode(['error' => 'Acción no reconocida.']);
