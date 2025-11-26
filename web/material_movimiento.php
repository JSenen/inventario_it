<?php
require_once 'auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . "/includes/logger.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: materiales.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM materiales WHERE id = :id");
$stmt->execute([':id' => $id]);
$mat = $stmt->fetch(PDO::FETCH_ASSOC);

// Equipos para vincular (PC, PORTÁTIL, MONITOR, IMPRESORA, etc.)
$equiposStmt = $pdo->query("
    SELECT id, tipo, hostname, numero_serie, usuario_asignado
    FROM equipos
    WHERE tipo IN ('PC','PORTÁTIL','MONITOR','IMPRESORA')
    ORDER BY tipo, hostname, numero_serie
");
$equipos = $equiposStmt->fetchAll(PDO::FETCH_ASSOC);


if (!$mat) {
    header('Location: materiales.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo     = $_POST['tipo'] === 'SALIDA' ? 'SALIDA' : 'ENTRADA';
    $cantidad = max(1, (int)($_POST['cantidad'] ?? 1));
    $motivo   = trim($_POST['motivo'] ?? '');
    $usuario  = trim($_POST['usuario'] ?? '');
    $notas    = trim($_POST['notas'] ?? '');
    $equipoId = isset($_POST['equipo_id']) ? (int)$_POST['equipo_id'] : 0;

    if ($equipoId <= 0) {
        $equipoId = null;
    }


    $pdo->beginTransaction();

    // Insertar movimiento
  $stmtMov = $pdo->prepare("
    INSERT INTO materiales_movimientos
        (material_id, tipo, cantidad, motivo, usuario, notas, equipo_id)
    VALUES
        (:material_id, :tipo, :cantidad, :motivo, :usuario, :notas, :equipo_id)
    ");
    $stmtMov->execute([
        ':material_id' => $id,
        ':tipo'        => $tipo,
        ':cantidad'    => $cantidad,
        ':motivo'      => $motivo,
        ':usuario'     => $usuario,
        ':notas'       => $notas,
        ':equipo_id'   => $equipoId,
    ]);



    // Actualizar stock
    $delta = ($tipo === 'ENTRADA') ? $cantidad : -$cantidad;
    $stmtUpd = $pdo->prepare("
        UPDATE materiales
        SET stock_actual = stock_actual + :delta
        WHERE id = :id
    ");
    $stmtUpd->execute([
        ':delta' => $delta,
        ':id'    => $id,
    ]);

    $pdo->commit();

    header('Location: material_ver.php?id=' . $id);
    exit;
}

include 'includes/header.php';
?>

<div class="container mt-4">
    <h2>Nuevo movimiento de stock</h2>
    <p><strong><?= htmlspecialchars($mat['descripcion']) ?></strong> (stock actual: <?= (int)$mat['stock_actual'] ?> <?= htmlspecialchars($mat['unidad']) ?>)</p>

    <form method="post">
        <div class="row mb-3">
            <div class="col-md-3">
                <label class="form-label">Tipo</label>
                <select name="tipo" class="form-select">
                    <option value="ENTRADA">Entrada</option>
                    <option value="SALIDA">Salida</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Cantidad</label>
                <input type="number" name="cantidad" class="form-control" value="1" min="1" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Usuario</label>
                <input type="text" name="usuario" class="form-control" placeholder="Tu nombre o usuario">
            </div>
        </div>

        <div class="row mb-3">
        <!-- Asociar a equipo -->
        <div class="col-md-6">
            <label class="form-label">Asociar a equipo (opcional)</label>
            <select name="equipo_id" class="form-select">
                <option value="0">-- Sin asociar --</option>
                <?php foreach ($equipos as $eq): ?>
                    <?php
                        $label = trim(
                            $eq['tipo'] . ' - ' .
                            ($eq['hostname'] ?: 'sin hostname') .
                            ($eq['numero_serie'] ? ' [SN: '.$eq['numero_serie'].']' : '') .
                            ($eq['usuario_asignado'] ? ' ('.$eq['usuario_asignado'].')' : '')
                        );
                    ?>
                    <option value="<?= (int)$eq['id'] ?>">
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>


        <div class="mb-3">
            <label class="form-label">Motivo</label>
            <input type="text" name="motivo" class="form-control" placeholder="Ej: Instalado en PC123, compra proveedor X">
        </div>

        <div class="mb-3">
            <label class="form-label">Notas</label>
            <textarea name="notas" class="form-control" rows="3"></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Guardar movimiento</button>
        <a href="material_ver.php?id=<?= (int)$mat['id'] ?>" class="btn btn-secondary">Cancelar</a>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
