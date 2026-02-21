<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . "/includes/logger.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: materiales.php');
    exit;
}

$materialId = isset($_POST['material_id']) ? (int)$_POST['material_id'] : 0;
if ($materialId <= 0) {
    header('Location: materiales.php');
    exit;
}

// Obtenemos el material y su stock
$stmt = $pdo->prepare("SELECT * FROM materiales WHERE id = :id");
$stmt->execute([':id' => $materialId]);
$mat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mat) {
    header('Location: materiales.php');
    exit;
}

// Si no hay stock, no dejamos bajar de 0
if ((int)$mat['stock_actual'] <= 0) {
    header('Location: material_ver.php?id=' . $materialId);
    exit;
}

$pdo->beginTransaction();

try {
    // Insertar movimiento SALIDA de 1 unidad
    $stmtMov = $pdo->prepare("
        INSERT INTO materiales_movimientos
            (material_id, tipo, cantidad, motivo, usuario, notas)
        VALUES
            (:material_id, 'SALIDA', 1, :motivo, :usuario, :notas)
    ");

    $stmtMov->execute([
        ':material_id' => $materialId,
        ':motivo'      => 'Salida rápida desde listado',
        ':usuario'     => '',   // si quieres, aquí puedes meter tu nombre
        ':notas'       => '',
    ]);

    // Actualizar stock
    $stmtUpd = $pdo->prepare("
        UPDATE materiales
        SET stock_actual = stock_actual - 1
        WHERE id = :id AND stock_actual > 0
    ");
    $stmtUpd->execute([':id' => $materialId]);

    $pdo->commit();
    logActividad($pdo, 'SALIDA_MATERIAL', 'Salida rápida de 1 unidad del material ID=' . $materialId);
} catch (Exception $e) {
    $pdo->rollBack();
}

header('Location: materiales.php');
exit;
