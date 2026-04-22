<?php
// ── api/pago.php ─────────────────────────────────────────────
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/auth_guard.php';
require_once __DIR__ . '/../helpers/bundle.php';
require_once __DIR__ . '/../helpers/carrito_helper.php';
require_once __DIR__ . '/../helpers/luhn.php';
header('Content-Type: application/json');
require_auth('jugador');
csrf_verify();

$pdo       = getDB();
$jugadorId = current_id();
$action    = $_POST['action'] ?? '';

// ── VALIDAR TARJETA ───────────────────────────────────────────
if ($action === 'validar_tarjeta') {
    $numero = preg_replace('/\D/', '', $_POST['numero'] ?? '');
    if (!luhn_check($numero)) {
        echo json_encode(['error' => 'Número de tarjeta inválido (falla Luhn).']); exit;
    }
    echo json_encode([
        'success' => true,
        'marca'   => detectar_marca($numero),
        'mascara' => mascara_tarjeta($numero),
        'ultimos4'=> substr($numero, -4),
    ]);
    exit;
}

// ── GUARDAR TARJETA ───────────────────────────────────────────
if ($action === 'guardar_tarjeta') {
    $numero  = preg_replace('/\D/', '', $_POST['numero'] ?? '');
    $nombre  = trim($_POST['nombre_titular'] ?? '');
    $mes     = trim($_POST['mes_exp'] ?? '');
    $anio    = trim($_POST['anio_exp'] ?? '');

    if (!luhn_check($numero)) { echo json_encode(['error' => 'Tarjeta inválida.']); exit; }
    if (!$nombre || !$mes || !$anio) { echo json_encode(['error' => 'Datos incompletos.']); exit; }

    // Verificar expiración
    $expY = (int)$anio; $expM = (int)$mes;
    $nowY = (int)date('Y'); $nowM = (int)date('m');
    if ($expY < $nowY || ($expY === $nowY && $expM < $nowM)) {
        echo json_encode(['error' => 'La tarjeta está expirada.']); exit;
    }

    $marca = detectar_marca($numero);
    // ¿Ya existe esa tarjeta (mismos últimos 4 + mismo mes/año)?
    $chk = $pdo->prepare(
        'SELECT id_tarjeta FROM tarjeta_pago
         WHERE id_jugador = :jid AND ultimos4 = :u4 AND mes_exp = :mes AND anio_exp = :anio'
    );
    $chk->execute([':jid' => $jugadorId, ':u4' => substr($numero,-4), ':mes' => $mes, ':anio' => $anio]);
    if ($chk->fetch()) {
        echo json_encode(['error' => 'Esta tarjeta ya está guardada.']); exit;
    }

    $ins = $pdo->prepare(
        'INSERT INTO tarjeta_pago (id_jugador, ultimos4, marca, nombre_titular, mes_exp, anio_exp)
         VALUES (:jid, :u4, :marca, :nombre, :mes, :anio) RETURNING id_tarjeta'
    );
    $ins->execute([
        ':jid'    => $jugadorId,
        ':u4'     => substr($numero, -4),
        ':marca'  => $marca,
        ':nombre' => $nombre,
        ':mes'    => $mes,
        ':anio'   => $anio,
    ]);
    echo json_encode(['success' => true, 'id_tarjeta' => (int)$ins->fetchColumn(), 'marca' => $marca]);
    exit;
}

// ── PROCESAR PAGO (checkout) ──────────────────────────────────
if ($action === 'procesar_pago') {
    $carritoId     = get_or_create_carrito($pdo, $jugadorId);
    $metodoPago    = $_POST['metodo_pago'] ?? 'tarjeta'; // tarjeta | saldo | mixto
    $idTarjeta     = (int)($_POST['id_tarjeta'] ?? 0);
    $usarSaldo     = in_array($metodoPago, ['saldo','mixto']);
    $usarTarjeta   = in_array($metodoPago, ['tarjeta','mixto']);

    // Si método incluye tarjeta, validar número de tarjeta en POST (simulado)
    $numeroCrudo = '';
    if ($usarTarjeta && !$idTarjeta) {
        $numeroCrudo = preg_replace('/\D/', '', $_POST['numero_tarjeta'] ?? '');
        if (!luhn_check($numeroCrudo)) {
            echo json_encode(['error' => 'Número de tarjeta inválido (falla Luhn).']); exit;
        }
    }

    // Obtener ítems
    $stmt = $pdo->prepare(
        'SELECT ci.id_item, ci.id_videojuego, ci.precio_item, ci.es_bundle, ci.es_regalo,
                v.titulo, v.steam_id
         FROM carrito_item ci JOIN videojuego v ON v.id_videojuego = ci.id_videojuego
         WHERE ci.id_carrito = :cid'
    );
    $stmt->execute([':cid' => $carritoId]);
    $items = $stmt->fetchAll();
    if (empty($items)) { echo json_encode(['error' => 'El carrito está vacío.']); exit; }

    $bundle    = calcular_bundle($items);
    $total     = $bundle['total'];

    // Calcular distribución saldo/tarjeta
    $saldoActual = 0;
    if ($usarSaldo) {
        $s = $pdo->prepare('SELECT saldo_specter FROM jugador WHERE id_jugador = :jid');
        $s->execute([':jid' => $jugadorId]);
        $saldoActual = (float)$s->fetchColumn();
    }

    $montoSaldo   = 0;
    $montoTarjeta = $total;
    if ($usarSaldo) {
        $montoSaldo   = min($saldoActual, $total);
        $montoTarjeta = round($total - $montoSaldo, 2);
    }
    if ($metodoPago === 'saldo' && $montoSaldo < $total) {
        echo json_encode(['error' => 'Saldo insuficiente. Necesitas $' . number_format($total, 2)]); exit;
    }

    try {
        $pdo->beginTransaction();

        // Crear compra
        $ins = $pdo->prepare(
            'INSERT INTO compra (id_jugador, total, descuento, metodo_pago, monto_saldo, monto_tarjeta, id_tarjeta_pago)
             VALUES (:jid, :total, :desc, :metodo, :msaldo, :mtarjeta, :itarjeta)
             RETURNING id_compra'
        );
        $ins->execute([
            ':jid'      => $jugadorId,
            ':total'    => $total,
            ':desc'     => $bundle['descuento'],
            ':metodo'   => $metodoPago,
            ':msaldo'   => $montoSaldo,
            ':mtarjeta' => $montoTarjeta,
            ':itarjeta' => $idTarjeta ?: null,
        ]);
        $idCompra = (int)$ins->fetchColumn();

        // Detalles + biblioteca
        $detStmt = $pdo->prepare(
            'INSERT INTO compra_detalle (id_compra, id_videojuego, precio_unitario, es_regalo)
             VALUES (:cid, :vid, :precio, :regalo)'
        );
        $bibStmt = $pdo->prepare(
            "INSERT INTO biblioteca (id_jugador, id_videojuego, estado)
             VALUES (:jid, :vid, 'Pendiente')
             ON CONFLICT (id_jugador, id_videojuego) DO NOTHING"
        );
        foreach ($items as $it) {
            $detStmt->execute([':cid' => $idCompra, ':vid' => $it['id_videojuego'],
                               ':precio' => $it['precio_item'], ':regalo' => db_bool((bool)$it['es_regalo'])]);
            if (!$it['es_regalo'])
                $bibStmt->execute([':jid' => $jugadorId, ':vid' => $it['id_videojuego']]);
        }

        // Descontar saldo si aplica
        if ($montoSaldo > 0) {
            $pdo->prepare('UPDATE jugador SET saldo_specter = saldo_specter - :monto WHERE id_jugador = :jid')
                ->execute([':monto' => $montoSaldo, ':jid' => $jugadorId]);
            $pdo->prepare(
                "INSERT INTO movimiento_saldo (id_jugador, tipo, monto, descripcion, id_compra)
                 VALUES (:jid, 'gasto', :monto, :desc, :cid)"
            )->execute([':jid' => $jugadorId, ':monto' => $montoSaldo,
                        ':desc' => 'Compra #' . str_pad($idCompra, 8, '0', STR_PAD_LEFT),
                        ':cid'  => $idCompra]);
        }

        // Guardar tarjeta usada en este pago (si fue nueva)
        if ($usarTarjeta && !$idTarjeta && $numeroCrudo) {
            $marca = detectar_marca($numeroCrudo);
            $nomT  = trim($_POST['nombre_titular'] ?? 'Titular');
            $mesT  = trim($_POST['mes_exp'] ?? '12');
            $anioT = trim($_POST['anio_exp'] ?? '2030');
            $insT  = $pdo->prepare(
                'INSERT INTO tarjeta_pago (id_jugador, ultimos4, marca, nombre_titular, mes_exp, anio_exp)
                 VALUES (:jid, :u4, :marca, :nombre, :mes, :anio)
                 ON CONFLICT DO NOTHING RETURNING id_tarjeta'
            );
            $insT->execute([':jid' => $jugadorId, ':u4' => substr($numeroCrudo,-4),
                            ':marca' => $marca, ':nombre' => $nomT, ':mes' => $mesT, ':anio' => $anioT]);
        }

        // Vaciar carrito + actividad
        $pdo->prepare('DELETE FROM carrito_item WHERE id_carrito = :cid')->execute([':cid' => $carritoId]);
        $pdo->prepare("INSERT INTO actividad (id_jugador, texto, tipo) VALUES (:jid, :txt, 'compra')")
            ->execute([':jid' => $jugadorId,
                       ':txt' => 'Compra completada — ' . count($items) . ' juego(s)']);

        $pdo->commit();
        echo json_encode([
            'success'   => true,
            'id_compra' => $idCompra,
            'total'     => number_format($total, 2),
            'juegos'    => array_map(fn($i) => ['titulo' => $i['titulo'], 'steam_id' => $i['steam_id']], $items),
        ]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Error al procesar el pago: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['error' => 'Acción no reconocida.']);
