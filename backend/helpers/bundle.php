<?php
// ── includes/bundle.php ──────────────────────────────────────
// Lógica de descuento por bundle (ReglaBundle CU-04).

/**
 * Calcula subtotal, porcentaje de descuento, monto de descuento y total
 * a partir de los ítems del carrito.
 *
 * @param  array $items  Filas de carrito_item con campos precio_item y es_bundle.
 * @return array{subtotal:float, pct:int, descuento:float, total:float}
 */
function calcular_bundle(array $items): array {
    $subtotal    = (float)array_sum(array_column($items, 'precio_item'));
    $bundleCount = count(array_filter($items, fn($i) => (bool)$i['es_bundle']));

    $pct = match(true) {
        $bundleCount >= 4 => 30,
        $bundleCount >= 3 => 20,
        $bundleCount >= 2 => 10,
        default           => 0,
    };

    $descuento = round($subtotal * $pct / 100, 2);
    $total     = round($subtotal - $descuento, 2);

    return compact('subtotal', 'pct', 'descuento', 'total');
}
