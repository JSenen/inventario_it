<?php
// monitor_desvincular.php
require_once 'auth.php';
require_once __DIR__. '/config.php';
require_once __DIR__ . '/includes/logger.php';

// id del monitor (obligatorio)
$idMonitor = isset($_GET['id']) ? (int)$_GET['id'] : 0;
// id del PC (para volver luego a su pantalla)
$idPc      = isset($_GET['pc']) ? (int)$_GET['pc'] : 0;

if ($idMonitor <= 0) {
    die("Monitor no especificado.");
}

try {
    // 1) Verificar que el monitor existe y es de tipo MONITOR
    $stmt = $pdo->prepare("
        SELECT * 
        FROM equipos 
        WHERE id = :id 
          AND UPPER(tipo) = 'MONITOR'
    ");
    $stmt->execute([':id' => $idMonitor]);
    $monitor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$monitor) {
        die("Monitor no encontrado o no es de tipo MONITOR.");
    }

    // 2) Eliminar la relación en pc_monitores
    $stmtDel = $pdo->prepare("
        DELETE FROM pc_monitores 
        WHERE id_monitor = :id_monitor
          AND (:id_pc = 0 OR id_pc = :id_pc)
    ");
    $stmtDel->execute([
        ':id_monitor' => $idMonitor,
        ':id_pc'      => $idPc
    ]);

    // 3) Pasar el monitor a Almacén (y limpiar asignación)
    $stmtUpd = $pdo->prepare("
        UPDATE equipos
        SET estado = 'Almacén',
            usuario_asignado = NULL,
            departamento     = NULL,
            ubicacion        = NULL,
            seccion_id       = NULL
        WHERE id = :id_monitor
    ");
    $stmtUpd->execute([':id_monitor' => $idMonitor]);

    // 4) Log
    logActividad($pdo, 'DESVINCULAR_MONITOR', "Monitor ID {$idMonitor} desvinculado de PC {$idPc} y pasado a Almacén");

    // 5) Redirección
    if ($idPc > 0) {
        header("Location: equipo_editar.php?id=" . $idPc);
    } else {
        header("Location: equipos.php");
    }
    exit;

} catch (Exception $e) {
    // En desarrollo puedes mostrar el error, en producción mejor un mensaje genérico
    die("Error al desvincular el monitor: " . $e->getMessage());
}
