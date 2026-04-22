<?php
// ── includes/pdf_helper.php ───────────────────────────────────
// Genera el HTML del comprobante de compra para Dompdf.

function generar_html_comprobante(array $compra, array $items, array $jugador): string {
    $fecha   = date('d/m/Y H:i', strtotime($compra['fecha_compra']));
    $ticket  = str_pad((string)$compra['id_compra'], 8, '0', STR_PAD_LEFT);
    $metodo  = match($compra['metodo_pago'] ?? 'tarjeta') {
        'saldo'  => 'Saldo Specter',
        'mixto'  => 'Mixto (Saldo + Tarjeta)',
        default  => 'Tarjeta de pago',
    };

    $itemsHtml = '';
    foreach ($items as $it) {
        $precio = number_format((float)$it['precio_unitario'], 2);
        $regalo = $it['es_regalo'] ? ' <span style="color:#c084fc">(Regalo)</span>' : '';
        $itemsHtml .= "<tr>
            <td style='padding:8px 0;border-bottom:1px solid #2a2a32;'>{$it['titulo']}{$regalo}</td>
            <td style='padding:8px 0;border-bottom:1px solid #2a2a32;text-align:right;font-family:monospace'>\${$precio}</td>
        </tr>";
    }

    $subtotal  = number_format((float)$compra['total'] + (float)$compra['descuento'], 2);
    $descuento = number_format((float)$compra['descuento'], 2);
    $total     = number_format((float)$compra['total'], 2);
    $nombre    = htmlspecialchars($jugador['nombre']);

    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700&display=swap');
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'DM Sans', Arial, sans-serif; background: #111114; color: #e2e2ea; font-size: 13px; }
  .wrap { max-width: 520px; margin: 0 auto; padding: 32px 24px; }
  .logo { font-size: 28px; font-weight: 700; color: #4cc9f0; letter-spacing: 2px; margin-bottom: 4px; }
  .logo-sub { font-size: 11px; color: #52526a; letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 28px; }
  .ticket-box { background: #1c1c21; border: 1px solid #2a2a32; border-radius: 10px; padding: 22px; margin-bottom: 20px; }
  .ticket-header { display: flex; justify-content: space-between; margin-bottom: 18px; }
  .ticket-num { font-size: 11px; color: #52526a; font-family: monospace; }
  .ticket-date { font-size: 11px; color: #52526a; font-family: monospace; }
  .section-title { font-size: 10px; font-weight: 700; letter-spacing: 2px; color: #52526a; text-transform: uppercase; margin-bottom: 10px; }
  table { width: 100%; border-collapse: collapse; }
  .totals { margin-top: 14px; border-top: 1px solid #2a2a32; padding-top: 14px; }
  .total-row { display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 12px; color: #9898b0; }
  .total-final { display: flex; justify-content: space-between; margin-top: 8px; font-size: 16px; font-weight: 700; color: #e2e2ea; }
  .total-final span:last-child { font-family: monospace; color: #4cc9f0; }
  .badge { display: inline-block; background: #4cc9f010; color: #4cc9f0; border: 1px solid #4cc9f0; border-radius: 4px; padding: 2px 8px; font-size: 10px; font-family: monospace; font-weight: 700; }
  .badge-green { background: #6ee7b710; color: #6ee7b7; border-color: #6ee7b7; }
  .footer { text-align: center; color: #52526a; font-size: 11px; margin-top: 24px; line-height: 1.6; }
</style>
</head>
<body>
<div class="wrap">
  <div class="logo">◇ SPECTER</div>
  <div class="logo-sub">Comprobante de compra</div>

  <div class="ticket-box">
    <div class="ticket-header">
      <span class="ticket-num">Ticket #{$ticket}</span>
      <span class="ticket-date">{$fecha}</span>
    </div>

    <div class="section-title">Comprador</div>
    <p style="margin-bottom:16px;color:#e2e2ea">{$nombre}</p>

    <div class="section-title">Artículos</div>
    <table>{$itemsHtml}</table>

    <div class="totals">
      <div class="total-row"><span>Subtotal</span><span style="font-family:monospace">\${$subtotal}</span></div>
      <div class="total-row" style="color:#6ee7b7"><span>Descuento bundle</span><span style="font-family:monospace">-\${$descuento}</span></div>
      <div class="total-row"><span>Método de pago</span><span>{$metodo}</span></div>
      <div class="total-final"><span>Total pagado</span><span>\${$total}</span></div>
    </div>
  </div>

  <div style="text-align:center;margin-bottom:20px">
    <span class="badge badge-green">Pago confirmado</span>
  </div>

  <div class="footer">
    ◇ Specter Gaming Platform<br>
    Este comprobante es válido como constancia de pago digital.<br>
    Ticket #{$ticket} — {$fecha}
  </div>
</div>
</body>
</html>
HTML;
}
