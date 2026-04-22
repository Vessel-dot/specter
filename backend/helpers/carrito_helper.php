<?php
// ── includes/carrito_helper.php ──────────────────────────────
// Helper para obtener o crear el carrito activo del jugador.

/**
 * Devuelve el id_carrito del jugador.
 * Si no tiene carrito, lo crea y devuelve el nuevo id.
 */
function get_or_create_carrito(PDO $pdo, int $jugadorId): int {
    $s = $pdo->prepare('SELECT id_carrito FROM carrito WHERE id_jugador = :jid');
    $s->execute([':jid' => $jugadorId]);
    $row = $s->fetch();
    if ($row) return (int)$row['id_carrito'];

    $ins = $pdo->prepare(
        'INSERT INTO carrito (id_jugador) VALUES (:jid) RETURNING id_carrito'
    );
    $ins->execute([':jid' => $jugadorId]);
    return (int)$ins->fetchColumn();
}
