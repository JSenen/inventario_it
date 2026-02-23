<?php
require_once 'auth.php';
require_once 'config.php';

$errores = [];

// Cargar departamentos
$deptStmt = $pdo->query("SELECT nombre FROM departamentos ORDER BY nombre ASC");
$departamentos = $deptStmt->fetchAll(PDO::FETCH_ASSOC);

// Cargar ubicaciones
$ubicStmt = $pdo->query("SELECT nombre FROM ubicaciones ORDER BY nombre ASC");
$ubicaciones = $ubicStmt->fetchAll(PDO::FETCH_ASSOC);

// Cargar secciones
$seccionesStmt = $pdo->query("SELECT nombre FROM secciones ORDER BY nombre ASC");
$secciones = $seccionesStmt->fetchAll(PDO::FETCH_ASSOC);

// Cargar SIMs disponibles para el select
$simStmt = $pdo->query("SELECT id, numero, operador FROM sims WHERE estado = 'Disponible' ORDER BY numero ASC");
$simsDisponibles = $simStmt->fetchAll(PDO::FETCH_ASSOC);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $etiqueta   = trim($_POST['etiqueta'] ?? '');
    $marca      = trim($_POST['marca'] ?? '');
    $modelo     = trim($_POST['modelo'] ?? '');
    $imei       = trim($_POST['imei'] ?? '');
    $num_serie  = trim($_POST['numero_serie'] ?? '');
    $usuario    = trim($_POST['usuario_asignado'] ?? '');
    $depart     = trim($_POST['departamento'] ?? '');
    $ubicacion  = trim($_POST['ubicacion'] ?? '');
    $seccion    = trim($_POST['seccion'] ?? '');
    $estado     = trim($_POST['estado'] ?? 'Activo');
    $fecha_alta = $_POST['fecha_alta'] ?? date('Y-m-d');
    $proveedor  = trim($_POST['proveedor'] ?? '');
    $coste      = $_POST['coste'] ?? null;
    $obs        = trim($_POST['observaciones'] ?? '');
    $sim_id     = $_POST['sim_id'] ?? '';

    if ($marca === '' || $modelo === '' || $imei === '') {
        $errores[] = "Marca, Modelo e IMEI son obligatorios.";
    }

    if (!$errores) {

        try {
            $pdo->beginTransaction();

            // Insertar teléfono
            $stmtTel = $pdo->prepare("
                INSERT INTO telefonos
                (etiqueta, marca, modelo, imei, numero_serie, usuario_asignado, departamento, ubicacion, seccion,
                 estado, fecha_alta, proveedor, coste, observaciones)
                VALUES
                (:etiqueta, :marca, :modelo, :imei, :num_serie, :usuario, :depart, :ubicacion, :seccion,
                 :estado, :fecha_alta, :proveedor, :coste, :obs)
            ");

            $stmtTel->execute([
                ':etiqueta'   => $etiqueta ?: null,
                ':marca'      => $marca,
                ':modelo'     => $modelo,
                ':imei'       => $imei,
                ':num_serie'  => $num_serie,
                ':usuario'    => $usuario,
                ':depart'     => $depart,
                ':ubicacion'  => $ubicacion,
                ':seccion'    => $seccion,
                ':estado'     => $estado,
                ':fecha_alta' => $fecha_alta,
                ':proveedor'  => $proveedor,
                ':coste'      => $coste ?: null,
                ':obs'        => $obs
            ]);

            $telefono_id = $pdo->lastInsertId();

            // Si se ha seleccionado una SIM → crear relación y marcarla como Asignada
            if (!empty($sim_id)) {
                // Insertar en histórico
                $stmtRel = $pdo->prepare("
                    INSERT INTO telefono_sim (telefono_id, sim_id)
                    VALUES (:tel, :sim)
                ");
                $stmtRel->execute([
                    ':tel' => $telefono_id,
                    ':sim' => $sim_id
                ]);

                // Marcar SIM como asignada
                $stmtUpdSim = $pdo->prepare("
                    UPDATE sims SET estado = 'Asignada' WHERE id = :sim
                ");
                $stmtUpdSim->execute([':sim' => $sim_id]);
            }

            $pdo->commit();
            header("Location: telefonos.php");
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errores[] = "Error al guardar el teléfono: " . $e->getMessage();
        }
    }
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <h2>Nuevo teléfono móvil</h2>

    <?php if ($errores): ?>
        <div class="alert alert-danger">
            <?php foreach ($errores as $e): ?>
                <div><?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Etiqueta</label>
                <input type="text" name="etiqueta" class="form-control campo-etiqueta" value="<?= htmlspecialchars($_POST['etiqueta'] ?? '') ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Marca</label>
                <input type="text" name="marca" class="form-control" required>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Modelo</label>
                <input type="text" name="modelo" class="form-control" required>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">IMEI</label>
                <input type="text" name="imei" class="form-control" required>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Número de serie</label>
                <input type="text" name="numero_serie" class="form-control">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Usuario asignado</label>
                <input type="text" name="usuario_asignado" class="form-control">
            </div>
            <div class="row">

        <div class="col-md-4 mb-3">
        <label class="form-label">Ubicación</label>
        <select name="ubicacion" class="form-select">
            <option value="">-- Seleccione ubicación --</option>
            <?php foreach ($ubicaciones as $u): 
                $nombre = $u['nombre'];
                $selected = (isset($_POST['ubicacion']) && $_POST['ubicacion'] === $nombre) ? 'selected' : '';
            ?>
                <option value="<?= htmlspecialchars($nombre) ?>" <?= $selected ?>>
                    <?= htmlspecialchars($nombre) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div class="col-md-4 mb-3">
        <label class="form-label">Departamento</label>
        <select name="departamento" class="form-select">
            <option value="">-- Seleccione departamento --</option>
            <?php foreach ($departamentos as $d): 
                $nombre = $d['nombre'];
                $selected = (isset($_POST['departamento']) && $_POST['departamento'] === $nombre) ? 'selected' : '';
            ?>
                <option value="<?= htmlspecialchars($nombre) ?>" <?= $selected ?>>
                    <?= htmlspecialchars($nombre) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

   

    <div class="col-md-4 mb-3">
        <label class="form-label">Sección</label>
        <select name="seccion" class="form-select">
            <option value="">-- Seleccione sección --</option>
            <?php foreach ($secciones as $s): 
                $nombre = $s['nombre'];
                $selected = (isset($_POST['seccion']) && $_POST['seccion'] === $nombre) ? 'selected' : '';
            ?>
                <option value="<?= htmlspecialchars($nombre) ?>" <?= $selected ?>>
                    <?= htmlspecialchars($nombre) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

            <div class="col-md-4 mb-3">
                <label class="form-label">Estado</label>
                <select name="estado" class="form-select">
                    <option value="Activo">Activo</option>
                    <option value="Almacén" selected>Almacén</option>
                    <option value="Prestado">Prestado</option>
                    <option value="Averiado">Averiado</option>
                    <option value="Baja">Baja</option>
                </select>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Fecha alta</label>
                <input type="date" name="fecha_alta" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            <!-- <div class="col-md-4 mb-3">
                <label class="form-label">Proveedor</label>
                <input type="text" name="proveedor" class="form-control">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Coste (€)</label>
                <input type="number" step="0.01" name="coste" class="form-control">
            </div> -->
        </div>

        <!-- Selección de SIM -->
        <div class="mb-3">
            <label class="form-label"><b>Asignar SIM (opcional)</b></label>
            <select name="sim_id" class="form-select">
                <option value="">-- Sin SIM --</option>
                <?php foreach ($simsDisponibles as $sim): ?>
                    <option value="<?= (int)$sim['id'] ?>">
                        <?= htmlspecialchars($sim['numero']) ?> (<?= htmlspecialchars($sim['operador']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Observaciones</label>
            <textarea name="observaciones" class="form-control"></textarea>
        </div>

        <button type="submit" class="btn btn-success">Guardar</button>
        <a href="telefonos.php" class="btn btn-secondary">Cancelar</a>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
