<?php
// equipo_borrar.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . "/includes/logger.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

try {
    // 1) Comprobar si el equipo tiene movimientos asociados
    $stmtCheck = $pdo->prepare("
        SELECT COUNT(*) 
        FROM equipos_movimientos 
        WHERE id_equipo = :id
    ");
    $stmtCheck->execute([':id' => $id]);
    $tieneMovimientos = (int)$stmtCheck->fetchColumn();

    if ($tieneMovimientos > 0) {
        // ❌ No dejamos borrar porque se rompería la integridad referencial
        $_SESSION['error'] = "No se puede borrar el equipo porque tiene $tieneMovimientos movimientos en el historial. "
                            . "Si quieres darlo de baja, usa el estado BAJA en lugar de borrarlo.";
        header('Location: equipo_editar.php?id=' . $id);
        exit;
    }

    // 2) No tiene movimientos → ahora sí lo borramos
    $stmt = $pdo->prepare("DELETE FROM equipos WHERE id = :id");
    $stmt->execute([':id' => $id]);

    // Registrar actividad (manteniendo tu función)
    logActividad($pdo, 'BORRAR_EQUIPO', 'Equipo eliminado: ID=' . $id);

    $_SESSION['success'] = "Equipo eliminado correctamente.";
    header('Location: index.php?msg=ok');
    exit;

} catch (Exception $e) {
    $_SESSION['error'] = "Error al intentar borrar el equipo: " . $e->getMessage();
    header('Location: equipo_editar.php?id=' . $id);
    exit;
}
