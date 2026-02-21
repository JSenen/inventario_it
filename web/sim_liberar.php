<?php
require_once 'auth.php';
require_once 'config.php';

function safe_return_url(string $fallback = 'sims.php'): string
{
    $return = trim((string)($_GET['return'] ?? ''));
    if ($return === '') {
        return $fallback;
    }

    // Evita redirecciones externas
    $parts = parse_url($return);
    if ($parts === false) {
        return $fallback;
    }
    if (!empty($parts['scheme']) || !empty($parts['host'])) {
        return $fallback;
    }
    if (str_starts_with($return, '//')) {
        return $fallback;
    }

    return $return;
}

$simId = isset($_GET['sim_id']) ? (int)$_GET['sim_id'] : 0;
$telId = isset($_GET['telefono_id']) ? (int)$_GET['telefono_id'] : 0;

if ($simId <= 0 && $telId <= 0) {
    die('Debe indicar sim_id o telefono_id.');
}

try {
    $pdo->beginTransaction();

    if ($simId > 0) {
        $stmt = $pdo->prepare("
            SELECT id, sim_id, telefono_id
            FROM telefono_sim
            WHERE sim_id = :sim
              AND fecha_liberacion IS NULL
            ORDER BY fecha_asignacion DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute([':sim' => $simId]);
    } else {
        $stmt = $pdo->prepare("
            SELECT id, sim_id, telefono_id
            FROM telefono_sim
            WHERE telefono_id = :tel
              AND fecha_liberacion IS NULL
            ORDER BY fecha_asignacion DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute([':tel' => $telId]);
    }

    $rel = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$rel) {
        $pdo->rollBack();
        header('Location: ' . safe_return_url($simId > 0 ? 'sims_ver.php?id=' . $simId : 'telefonos_ver.php?id=' . $telId));
        exit;
    }

    $stmtClose = $pdo->prepare("
        UPDATE telefono_sim
        SET fecha_liberacion = NOW()
        WHERE id = :id
          AND fecha_liberacion IS NULL
    ");
    $stmtClose->execute([':id' => (int)$rel['id']]);

    $stmtSim = $pdo->prepare("
        UPDATE sims
        SET estado = 'Disponible'
        WHERE id = :sim
    ");
    $stmtSim->execute([':sim' => (int)$rel['sim_id']]);

    $pdo->commit();

    header('Location: ' . safe_return_url($simId > 0 ? 'sims_ver.php?id=' . $simId : 'telefonos_ver.php?id=' . $telId));
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die('Error al liberar SIM: ' . htmlspecialchars($e->getMessage()));
}

