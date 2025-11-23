<?php
require_once __DIR__ . '/config.php';

if (!isset($_GET['id'])) {
    die('ID de avería no indicado');
}

$id_averia = (int)$_GET['id'];

$sql = "UPDATE averias
        SET estado = 'CERRADA',
            fecha_cierre = NOW()
        WHERE id = :id
          AND estado = 'ABIERTA'";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id_averia]);

logActividad($pdo, 'CERRAR_AVERIA', 'Avería cerrada: ID=' . $id_averia);

header('Location: averias_list.php');
exit;
