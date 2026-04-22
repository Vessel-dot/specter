<?php
// ── api/resenas.php ───────────────────────────────────────────
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/auth_guard.php';
header('Content-Type: application/json');
require_auth('jugador');
csrf_verify();

$pdo       = getDB();
$jugadorId = current_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idJuego = (int)($_POST['id_videojuego'] ?? 0);
    $cal     = (int)($_POST['calificacion']  ?? 0);
    $texto   = trim($_POST['texto']          ?? '');

    if (!$idJuego || $cal < 1 || $cal > 5 || !$texto) {
        echo json_encode(['error' => 'Datos incompletos.']); exit;
    }
    if (mb_strlen($texto) > 1000) {
        echo json_encode(['error' => 'El texto supera 1000 caracteres.']); exit;
    }

    // Verificar que el juego está en la biblioteca
    $check = $pdo->prepare(
        'SELECT id_biblioteca FROM biblioteca WHERE id_jugador = :jid AND id_videojuego = :vid'
    );
    $check->execute([':jid' => $jugadorId, ':vid' => $idJuego]);
    if (!$check->fetch()) {
        echo json_encode(['error' => 'Solo puedes reseñar juegos de tu biblioteca.']); exit;
    }

    // Verificar que no existe ya una reseña
    $dup = $pdo->prepare(
        'SELECT id_resena FROM resena WHERE id_jugador = :jid AND id_videojuego = :vid'
    );
    $dup->execute([':jid' => $jugadorId, ':vid' => $idJuego]);
    if ($dup->fetch()) {
        echo json_encode(['error' => 'Ya tienes una reseña para este juego.']); exit;
    }

    try {
        $pdo->beginTransaction();

        // Insertar reseña
        $pdo->prepare(
            'INSERT INTO resena (id_jugador, id_videojuego, calificacion, texto)
             VALUES (:jid, :vid, :cal, :txt)'
        )->execute([':jid' => $jugadorId, ':vid' => $idJuego, ':cal' => $cal, ':txt' => $texto]);

        // Actualizar reputación
        $pdo->prepare(
            'UPDATE reputacion_usuario
             SET resenas_aprobadas  = resenas_aprobadas + 1,
                 indice_confianza   = LEAST(10.0, indice_confianza + 0.2)
             WHERE id_jugador = :jid'
        )->execute([':jid' => $jugadorId]);

        // Registrar actividad
        $gameTitle = $pdo->prepare('SELECT titulo FROM videojuego WHERE id_videojuego = :vid');
        $gameTitle->execute([':vid' => $idJuego]);
        $titulo = $gameTitle->fetchColumn();
        $pdo->prepare(
            "INSERT INTO actividad (id_jugador, texto, tipo)
             VALUES (:jid, :txt, 'resena')"
        )->execute([':jid' => $jugadorId, ':txt' => "Nueva reseña escrita para {$titulo}"]);

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Error al guardar la reseña.']);
    }
    exit;
}

echo json_encode(['error' => 'Método no permitido.']);
