<?php
// ── api/moderador.php ─────────────────────────────────────────
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/auth_guard.php';
header('Content-Type: application/json');
require_auth('moderador');
csrf_verify();

$pdo    = getDB();
$action = $_POST['action'] ?? '';

// ── APROBAR / BLOQUEAR / EN_REVISION ─────────────────────────
if (in_array($action, ['aprobar', 'bloquear', 'revision'])) {
    $idResena = (int)($_POST['id_resena'] ?? 0);
    if (!$idResena) { echo json_encode(['error' => 'ID inválido.']); exit; }

    $nuevoEstado = match($action) {
        'aprobar'  => 'LIMPIO',
        'bloquear' => 'BLOQUEADO',
        'revision' => 'EN_REVISION',
    };

    try {
        $pdo->beginTransaction();

        // Obtener estado anterior y jugador autor
        $prev = $pdo->prepare('SELECT estado_moderacion, id_jugador FROM resena WHERE id_resena = :id');
        $prev->execute([':id' => $idResena]);
        $resena = $prev->fetch();
        if (!$resena) { echo json_encode(['error' => 'Reseña no encontrada.']); exit; }

        // Actualizar estado
        $pdo->prepare(
            'UPDATE resena SET estado_moderacion = :estado WHERE id_resena = :id'
        )->execute([':estado' => $nuevoEstado, ':id' => $idResena]);

        // Actualizar reputación del autor
        $autoId = $resena['id_jugador'];
        if ($action === 'aprobar' && $resena['estado_moderacion'] !== 'LIMPIO') {
            $pdo->prepare(
                'UPDATE reputacion_usuario
                 SET resenas_aprobadas = resenas_aprobadas + 1,
                     indice_confianza  = LEAST(10.0, indice_confianza + 0.3)
                 WHERE id_jugador = :id'
            )->execute([':id' => $autoId]);
        } elseif ($action === 'bloquear' && $resena['estado_moderacion'] !== 'BLOQUEADO') {
            $pdo->prepare(
                'UPDATE reputacion_usuario
                 SET resenas_bloqueadas = resenas_bloqueadas + 1,
                     indice_confianza   = GREATEST(0.0, indice_confianza - 0.5)
                 WHERE id_jugador = :id'
            )->execute([':id' => $autoId]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'nuevo_estado' => $nuevoEstado]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Error al actualizar la reseña.']);
    }
    exit;
}

echo json_encode(['error' => 'Acción no reconocida.']);
