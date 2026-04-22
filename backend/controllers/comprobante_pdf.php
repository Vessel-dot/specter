<?php
// ── api/comprobante_pdf.php ───────────────────────────────────
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/auth_guard.php';
require_once __DIR__ . '/../helpers/pdf_helper.php';
require_auth('jugador');
// GET endpoint - protegido por sesión autenticada

$idCompra  = (int)($_GET['id'] ?? 0);
$jugadorId = current_id();

if (!$idCompra) { http_response_code(400); echo 'ID inválido.'; exit; }

$pdo = getDB();

// Verificar que la compra pertenece al jugador
$stmt = $pdo->prepare('SELECT * FROM compra WHERE id_compra = :id AND id_jugador = :jid');
$stmt->execute([':id' => $idCompra, ':jid' => $jugadorId]);
$compra = $stmt->fetch();
if (!$compra) { http_response_code(403); echo 'Compra no encontrada.'; exit; }

// Obtener ítems
$items = $pdo->prepare(
    'SELECT cd.precio_unitario, cd.es_regalo, v.titulo
     FROM compra_detalle cd JOIN videojuego v ON v.id_videojuego = cd.id_videojuego
     WHERE cd.id_compra = :id'
);
$items->execute([':id' => $idCompra]);
$items = $items->fetchAll();

// Datos del jugador
$jStmt = $pdo->prepare('SELECT nombre FROM jugador WHERE id_jugador = :jid');
$jStmt->execute([':jid' => $jugadorId]);
$jugador = $jStmt->fetch();

// Generar PDF con Dompdf
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    // Fallback: devolver HTML si Dompdf no está instalado
    header('Content-Type: text/html; charset=UTF-8');
    echo generar_html_comprobante($compra, $items, $jugador);
    exit;
}

require_once $autoload;
use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('defaultFont', 'Helvetica');
$options->set('isRemoteEnabled', false); // sin fuentes remotas en producción
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);
$html   = generar_html_comprobante($compra, $items, $jugador);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$ticket = str_pad((string)$idCompra, 8, '0', STR_PAD_LEFT);
$dompdf->stream("specter_comprobante_{$ticket}.pdf", [
    'Attachment' => isset($_GET['download']) ? 1 : 0,
]);
