<?php
require_once 'auth.php';
require_once 'config.php';

$errores = [];

// Obtener ID de la SIM
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die("ID de SIM no válido.");
}

// Cargar datos actuales de la SIM
$stmt = $pdo->prepare("SELECT * FROM sims WHERE id = :id");
$stmt->execute([':id' => $id]);
$sim = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sim) {
    die("Tarjeta SIM no encontrada.");
}

// Comprobar si está asignada actualmente a un teléfono (solo informativo)
$sqlTel = "
    SELECT t.id, t.marca, t.modelo, t.imei
    FROM telefono_sim ts
    JOIN telefonos t ON t.id = ts.telefono_id
    WHERE ts.sim_id = :id
      AND ts.fecha_liberacion IS NULL
    LIMIT 1
";
$stmtTel = $pdo->prepare($sqlTel);
$stmtTel->execute([':id' => $id]);
$telefonoActual = $stmtTel->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $etiqueta  = trim($_POST['etiqueta'] ?? '');
    $numero    = trim($_POST['numero'] ?? '');
    $iccid     = trim($_POST['iccid'] ?? '');
    $operador  = trim($_POST['operador'] ?? '');
    $tarifa    = trim($_POST['tarifa'] ?? '');
    $pin       = trim($_POST['pin'] ?? '');
    $puk       = trim($_POST['puk'] ?? '');
    $estado    = trim($_POST['estado'] ?? 'Disponible');
    $fechaAlta = $_POST['fecha_alta'] ?? $sim['fecha_alta'];
    $fechaBaja = $_POST['fecha_baja'] ?? $sim['fecha_baja'];
    $obs       = trim($_POST['observaciones'] ?? '');

    if ($numero === '' || $iccid === '' || $operador === '') {
        $errores[] = "Número, ICCID y Operador son obligatorios.";
    }

    if (!$errores) {
        try {
            $stmtUpd = $pdo->prepare("
                UPDATE sims
                   SET etiqueta    = :etiqueta,
                       numero      = :numero,
                       iccid       = :iccid,
                       operador    = :operador,
                       tarifa      = :tarifa,
                       pin         = :pin,
                       puk         = :puk,
                       estado      = :estado,
                       fecha_alta  = :fecha_alta,
                       fecha_baja  = :fecha_baja,
                       observaciones = :obs
                 WHERE id = :id
            ");

            $stmtUpd->execute([
                ':etiqueta'   => $etiqueta ?: null,
                ':numero'     => $numero,
                ':iccid'      => $iccid,
                ':operador'   => $operador,
                ':tarifa'     => $tarifa,
                ':pin'        => $pin,
                ':puk'        => $puk,
                ':estado'     => $estado,
                ':fecha_alta' => $fechaAlta ?: null,
                ':fecha_baja' => $fechaBaja ?: null,
                ':obs'        => $obs,
                ':id'         => $id
            ]);

            header("Location: sims_ver.php?id=" . $id);
            exit;

        } catch (Exception $e) {
            $errores[] = "Error al actualizar la SIM: " . $e->getMessage();
        }
    }

    // Recargar datos en $sim con lo del POST para mantener valores del formulario
    $sim['numero']        = $numero;
    $sim['etiqueta']      = $etiqueta;
    $sim['iccid']         = $iccid;
    $sim['operador']      = $operador;
    $sim['tarifa']        = $tarifa;
    $sim['pin']           = $pin;
    $sim['puk']           = $puk;
    $sim['estado']        = $estado;
    $sim['fecha_alta']    = $fechaAlta;
    $sim['fecha_baja']    = $fechaBaja;
    $sim['observaciones'] = $obs;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Editar tarjeta SIM</h2>
        <div>
            <?php if ($telefonoActual): ?>
                <span class="badge bg-success">
                    Asignada a:
                    <a href="telefonos_ver.php?id=<?= (int)$telefonoActual['id'] ?>" class="text-white text-decoration-underline">
                        <?= htmlspecialchars($telefonoActual['marca'] . ' ' . $telefonoActual['modelo']) ?>
                    </a>
                </span>
            <?php else: ?>
                <span class="badge bg-secondary">No asignada actualmente</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($errores): ?>
        <div class="alert alert-danger">
            <?php foreach ($errores as $e): ?>
                <div><?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <div class="row">
            <div class="col-md-3 mb-3">
                <label class="form-label">Etiqueta</label>
                <input type="text" name="etiqueta" class="form-control"
                       value="<?= htmlspecialchars($sim['etiqueta']) ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Número</label>
                <input type="text" name="numero" class="form-control"
                       value="<?= htmlspecialchars($sim['numero']) ?>" required>
            </div>

            <div class="col-md-4 mb-3">
                <label class="form-label">ICCID</label>
                <input type="text" name="iccid" class="form-control"
                       value="<?= htmlspecialchars($sim['iccid']) ?>" required>
            </div>

            <div class="col-md-4 mb-3">
                <label class="form-label">Operador</label>
                <input type="text" name="operador" class="form-control"
                       value="<?= htmlspecialchars($sim['operador']) ?>" required>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Tarifa</label>
                <input type="text" name="tarifa" class="form-control"
                       value="<?= htmlspecialchars($sim['tarifa']) ?>">
            </div>

            <div class="col-md-4 mb-3">
                <label class="form-label">PIN</label>
                <input type="text" name="pin" class="form-control"
                       value="<?= htmlspecialchars($sim['pin']) ?>">
            </div>

            <div class="col-md-4 mb-3">
                <label class="form-label">PUK</label>
                <input type="text" name="puk" class="form-control"
                       value="<?= htmlspecialchars($sim['puk']) ?>">
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Estado</label>
                <select name="estado" class="form-select">
                    <?php
                    $estados = ['Disponible', 'Asignada', 'Baja', 'Averiada'];
                    foreach ($estados as $est) {
                        $sel = ($sim['estado'] === $est) ? 'selected' : '';
                        echo "<option value=\"$est\" $sel>$est</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="col-md-4 mb-3">
                <label class="form-label">Fecha alta</label>
                <input type="date" name="fecha_alta" class="form-control"
                       value="<?= htmlspecialchars($sim['fecha_alta']) ?>">
            </div>

            <div class="col-md-4 mb-3">
                <label class="form-label">Fecha baja</label>
                <input type="date" name="fecha_baja" class="form-control"
                       value="<?= htmlspecialchars($sim['fecha_baja']) ?>">
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Observaciones</label>
            <textarea name="observaciones" class="form-control" rows="3"><?= htmlspecialchars($sim['observaciones']) ?></textarea>
        </div>

        <button type="submit" class="btn btn-success">Guardar cambios</button>
        <a href="sims_ver.php?id=<?= (int)$id ?>" class="btn btn-secondary">Cancelar</a>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
