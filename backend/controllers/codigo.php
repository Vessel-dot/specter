<?php
// ── api/codigo.php ────────────────────────────────────────────
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/auth_guard.php';
header('Content-Type: application/json');
require_auth('jugador');

$pdo       = getDB();
$jugadorId = current_id();
$action    = $_POST['action'] ?? $_GET['action'] ?? '';

/**
 * Genera un código formato XXXX-XXXX-XXXX con caracteres alfanuméricos
 * sin ambigüedades (sin 0, O, I, 1).
 */
function generar_codigo_unico(PDO $pdo): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $partes = [];
        for ($i = 0; $i < 3; $i++) {
            $part = '';
            for ($j = 0; $j < 4; $j++)
                $part .= $chars[random_int(0, strlen($chars) - 1)];
            $partes[] = $part;
        }
        $codigo = implode('-', $partes);
        $chk = $pdo->prepare('SELECT id_codigo FROM codigo_canje WHERE codigo = :c');
        $chk->execute([':c' => $codigo]);
    } while ($chk->fetch());
    return $codigo;
}

// ── CANJEAR CÓDIGO ────────────────────────────────────────────
if ($action === 'canjear') {
    csrf_verify();
    $input  = strtoupper(trim($_POST['codigo'] ?? ''));
    // Normalizar: quitar guiones extra, validar formato XXXX-XXXX-XXXX
    $codigo = preg_replace('/[^A-Z0-9]/', '', $input);
    if (strlen($codigo) === 12)
        $codigo = substr($codigo,0,4) . '-' . substr($codigo,4,4) . '-' . substr($codigo,8,4);

    if (!preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $codigo)) {
        echo json_encode(['error' => 'Formato de código inválido. Usa XXXX-XXXX-XXXX.']); exit;
    }

    $stmt = $pdo->prepare(
        "SELECT id_codigo, valor, estado, fecha_expiracion, id_jugador_comprador
         FROM codigo_canje WHERE codigo = :c"
    );
    $stmt->execute([':c' => $codigo]);
    $cod = $stmt->fetch();

    if (!$cod)                      { echo json_encode(['error' => 'Código no encontrado.']); exit; }
    if ($cod['estado'] !== 'activo'){ echo json_encode(['error' => 'Este código ya fue canjeado o expiró.']); exit; }
    if (strtotime($cod['fecha_expiracion']) < time()) {
        $pdo->prepare("UPDATE codigo_canje SET estado='expirado' WHERE id_codigo=:id")->execute([':id'=>$cod['id_codigo']]);
        echo json_encode(['error' => 'Este código ha expirado.']); exit;
    }
    $valor = (float)$cod['valor'];
    try {
        $pdo->beginTransaction();
        // Marcar código como canjeado
        $pdo->prepare(
            "UPDATE codigo_canje
             SET estado = 'canjeado', id_jugador_canje = :jid, fecha_uso = NOW()
             WHERE id_codigo = :id"
        )->execute([':jid' => $jugadorId, ':id' => $cod['id_codigo']]);
        // Acreditar saldo
        $pdo->prepare('UPDATE jugador SET saldo_specter = saldo_specter + :val WHERE id_jugador = :jid')
            ->execute([':val' => $valor, ':jid' => $jugadorId]);
        // Registrar movimiento
        $pdo->prepare(
            "INSERT INTO movimiento_saldo (id_jugador, tipo, monto, descripcion, id_canje)
             VALUES (:jid, 'canje', :val, :desc, :cid)"
        )->execute([
            ':jid'  => $jugadorId,
            ':val'  => $valor,
            ':desc' => 'Canje de código Specter',
            ':cid'  => $cod['id_codigo'],
        ]);
        $pdo->commit();

        $s = $pdo->prepare('SELECT saldo_specter FROM jugador WHERE id_jugador = :jid');
        $s->execute([':jid' => $jugadorId]);
        echo json_encode([
            'success'     => true,
            'valor'       => number_format($valor, 2),
            'nuevo_saldo' => number_format((float)$s->fetchColumn(), 2),
        ]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Error al canjear el código.']);
    }
    exit;
}

// ── COMPRAR TARJETA (genera código) ──────────────────────────
if ($action === 'comprar_tarjeta') {
    csrf_verify();
    require_once __DIR__ . '/../helpers/luhn.php';
    $idProducto  = (int)($_POST['id_producto'] ?? 0);
    $numeroCrudo = preg_replace('/\D/', '', $_POST['numero_tarjeta'] ?? '');

    if (!luhn_check($numeroCrudo)) { echo json_encode(['error' => 'Tarjeta inválida.']); exit; }

    $prod = $pdo->prepare('SELECT * FROM tarjeta_specter_producto WHERE id_producto = :id AND activo = TRUE');
    $prod->execute([':id' => $idProducto]);
    $producto = $prod->fetch();
    if (!$producto) { echo json_encode(['error' => 'Producto no encontrado.']); exit; }

    $codigo = generar_codigo_unico($pdo);
    try {
        $pdo->beginTransaction();
        $ins = $pdo->prepare(
            'INSERT INTO codigo_canje (codigo, valor, id_jugador_comprador, id_producto)
             VALUES (:c, :val, :jid, :pid) RETURNING id_codigo'
        );
        $ins->execute([':c' => $codigo, ':val' => $producto['denominacion'],
                       ':jid' => $jugadorId, ':pid' => $idProducto]);
        $pdo->commit();
        echo json_encode([
            'success' => true,
            'codigo'  => $codigo,
            'valor'   => number_format((float)$producto['denominacion'], 2),
            'nombre'  => $producto['nombre'],
        ]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Error al generar el código.']);
    }
    exit;
}

echo json_encode(['error' => 'Acción no reconocida.']);
