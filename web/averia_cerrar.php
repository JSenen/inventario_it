<?php
require_once 'auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/logger.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Procesar cierre con solución ---
    $id_averia = isset($_POST['id_averia']) ? (int)$_POST['id_averia'] : 0;
    $solucion  = trim($_POST['solucion_aplicada'] ?? '');

    if ($id_averia <= 0) {
        die('ID de avería no válido');
    }

    // Puedes forzar que haya texto:
    if ($solucion === '') {
        die('Debes indicar la solución aplicada antes de cerrar la avería.');
    }

    $sql = "UPDATE averias
            SET estado = 'CERRADA',
                fecha_cierre = NOW(),
                solucion_aplicada = :solucion
            WHERE id = :id
              AND estado = 'ABIERTA'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':solucion' => $solucion,
        ':id'       => $id_averia
    ]);

    logActividad($pdo, 'CERRAR_AVERIA', 'Avería cerrada: ID=' . $id_averia);

    header('Location: averias_list.php');
    exit;
}

// --- Si no es POST, es GET: mostrar formulario de cierre ---
if (!isset($_GET['id'])) {
    die('ID de avería no indicado');
}

$id_averia = (int)$_GET['id'];

// Cargamos datos básicos de la avería + equipo para mostrar algo de contexto
$sql = "SELECT a.*,
               e.hostname     AS nombre_equipo,
               e.numero_serie AS numero_serie
        FROM averias a
        JOIN equipos e ON e.id = a.equipo_id
        WHERE a.id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id_averia]);
$averia = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$averia) {
    die('Avería no encontrada');
}

if ($averia['estado'] !== 'ABIERTA') {
    die('Esta avería ya no está ABIERTA.');
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cerrar avería GATI-<?= htmlspecialchars($averia['id']) ?></title>
    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">
    <h1>Cerrar avería GATI-<?= htmlspecialchars($averia['id']) ?></h1>

    <div class="mb-3">
        <p><strong>Equipo:</strong> <?= htmlspecialchars($averia['nombre_equipo']) ?></p>
        <p><strong>Nº Serie:</strong> <?= htmlspecialchars($averia['numero_serie']) ?></p>
        <p><strong>Tipo de avería:</strong> <?= htmlspecialchars($averia['tipo_averia']) ?></p>
    </div>

    <form method="post">
        <input type="hidden" name="id_averia" value="<?= (int)$averia['id'] ?>">

        <div class="mb-3">
            <label for="solucion_aplicada" class="form-label">
                Solución aplicada (obligatorio):
            </label>
            <textarea
                name="solucion_aplicada"
                id="solucion_aplicada"
                rows="4"
                class="form-control"
                required
            ></textarea>
        </div>

        <button type="submit" class="btn btn-success">
            Guardar solución y cerrar avería
        </button>
        <a href="averias_list.php" class="btn btn-secondary">
            Cancelar
        </a>
    </form>
</div>
</body>
</html>
