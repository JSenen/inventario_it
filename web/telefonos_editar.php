<?php
require_once 'auth.php';
require_once 'config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die("ID de teléfono no válido.");
}

// Cargar departamentos
$deptStmt = $pdo->query("SELECT nombre FROM departamentos ORDER BY nombre ASC");
$departamentos = $deptStmt->fetchAll(PDO::FETCH_ASSOC);

// Cargar ubicaciones
$ubicStmt = $pdo->query("SELECT nombre FROM ubicaciones ORDER BY nombre ASC");
$ubicaciones = $ubicStmt->fetchAll(PDO::FETCH_ASSOC);

// Cargar secciones
$seccionesStmt = $pdo->query("SELECT nombre FROM secciones ORDER BY nombre ASC");
$secciones = $seccionesStmt->fetchAll(PDO::FETCH_ASSOC);

// 1) Cargar datos del teléfono
$stmtTel = $pdo->prepare("SELECT * FROM telefonos WHERE id = :id");
$stmtTel->execute([':id' => $id]);
$telefono = $stmtTel->fetch(PDO::FETCH_ASSOC);

if (!$telefono) {
    die("Teléfono no encontrado.");
}

// 2) Cargar SIM actual (si la hay): último registro sin fecha_liberacion
$sqlSimActual = "
    SELECT ts.id AS rel_id, s.*
    FROM telefono_sim ts
    JOIN sims s ON ts.sim_id = s.id
    WHERE ts.telefono_id = :id
      AND ts.fecha_liberacion IS NULL
    ORDER BY ts.fecha_asignacion DESC
    LIMIT 1
";
$stmtSimActual = $pdo->prepare($sqlSimActual);
$stmtSimActual->execute([':id' => $id]);
$simActual = $stmtSimActual->fetch(PDO::FETCH_ASSOC);

// 3) Cargar SIMs disponibles para el select
//    (puede que quieras excluir la SIM actual de la lista, aquí la incluimos aparte)
$simStmt = $pdo->query("SELECT id, numero, operador FROM sims WHERE estado = 'Disponible' ORDER BY numero ASC");
$simsDisponibles = $simStmt->fetchAll(PDO::FETCH_ASSOC);

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $marca      = trim($_POST['marca'] ?? '');
    $modelo     = trim($_POST['modelo'] ?? '');
    $imei       = trim($_POST['imei'] ?? '');
    $num_serie  = trim($_POST['numero_serie'] ?? '');
    $usuario    = trim($_POST['usuario_asignado'] ?? '');
    $depart     = trim($_POST['departamento'] ?? '');
    $ubicacion  = trim($_POST['ubicacion'] ?? '');
    $seccion    = trim($_POST['seccion'] ?? '');
    $estado     = trim($_POST['estado'] ?? 'Activo');
    $fecha_alta = $_POST['fecha_alta'] ?? $telefono['fecha_alta'];
    $fecha_baja = $_POST['fecha_baja'] ?? $telefono['fecha_baja'];
    $proveedor  = trim($_POST['proveedor'] ?? '');
    $coste      = $_POST['coste'] ?? null;
    $obs        = trim($_POST['observaciones'] ?? '');

    // SIM seleccionada en el formulario
    $sim_id_nueva = $_POST['sim_id'] ?? '';          // puede venir '' (sin SIM)
    $sim_id_actual = $simActual['id'] ?? null;       // id de la tabla sims de la SIM en uso actualmente

    if ($marca === '' || $modelo === '' || $imei === '') {
        $errores[] = "Marca, Modelo e IMEI son obligatorios.";
    }

    if (!$errores) {

        try {
            $pdo->beginTransaction();

            // 1) Actualizar datos del teléfono
            $stmtUpd = $pdo->prepare("
                UPDATE telefonos
                   SET marca = :marca,
                       modelo = :modelo,
                       imei = :imei,
                       numero_serie = :num_serie,
                       usuario_asignado = :usuario,
                       departamento = :depart,
                       ubicacion = :ubicacion,
                       seccion = :seccion,
                       estado = :estado,
                       fecha_alta = :fecha_alta,
                       fecha_baja = :fecha_baja,
                       proveedor = :proveedor,
                       coste = :coste,
                       observaciones = :obs
                 WHERE id = :id
            ");
            $stmtUpd->execute([
                ':marca'      => $marca,
                ':modelo'     => $modelo,
                ':imei'       => $imei,
                ':num_serie'  => $num_serie,
                ':usuario'    => $usuario,
                ':depart'     => $depart,
                ':ubicacion'  => $ubicacion,
                ':seccion'    => $seccion,
                ':estado'     => $estado,
                ':fecha_alta' => $fecha_alta ?: null,
                ':fecha_baja' => $fecha_baja ?: null,
                ':proveedor'  => $proveedor,
                ':coste'      => $coste ?: null,
                ':obs'        => $obs,
                ':id'         => $id
            ]);

            // 2) Gestionar SIM (cambios / liberación / nueva asignación)

            // Normalizamos valor de la nueva SIM: null si viene vacío
            $sim_id_nueva = $sim_id_nueva !== '' ? (int)$sim_id_nueva : null;

            // Caso A: había SIM y ahora NO hay -> liberar
            if ($simActual && $sim_id_nueva === null) {

                // Cerrar relación actual
                $stmtClose = $pdo->prepare("
                    UPDATE telefono_sim
                       SET fecha_liberacion = NOW()
                     WHERE telefono_id = :tel
                       AND sim_id = :sim
                       AND fecha_liberacion IS NULL
                ");
                $stmtClose->execute([
                    ':tel' => $id,
                    ':sim' => $simActual['id']
                ]);

                // Poner SIM como Disponible
                $stmtUpdSim = $pdo->prepare("
                    UPDATE sims SET estado = 'Disponible' WHERE id = :sim
                ");
                $stmtUpdSim->execute([':sim' => $simActual['id']]);
            }

            // Caso B: no había SIM y ahora sí hay -> asignar
            if (!$simActual && $sim_id_nueva !== null) {

                // Crear relación
                $stmtRel = $pdo->prepare("
                    INSERT INTO telefono_sim (telefono_id, sim_id)
                    VALUES (:tel, :sim)
                ");
                $stmtRel->execute([
                    ':tel' => $id,
                    ':sim' => $sim_id_nueva
                ]);

                // Marcar SIM como Asignada
                $stmtUpdSim = $pdo->prepare("
                    UPDATE sims SET estado = 'Asignada' WHERE id = :sim
                ");
                $stmtUpdSim->execute([':sim' => $sim_id_nueva]);
            }

            // Caso C: había SIM y ahora hay otra distinta -> cambio de SIM
            if ($simActual && $sim_id_nueva !== null && $sim_id_nueva !== (int)$simActual['id']) {

                // Cerrar relación actual
                $stmtClose = $pdo->prepare("
                    UPDATE telefono_sim
                       SET fecha_liberacion = NOW()
                     WHERE telefono_id = :tel
                       AND sim_id = :sim
                       AND fecha_liberacion IS NULL
                ");
                $stmtClose->execute([
                    ':tel' => $id,
                    ':sim' => $simActual['id']
                ]);

                // Poner SIM vieja como Disponible
                $stmtUpdSimOld = $pdo->prepare("
                    UPDATE sims SET estado = 'Disponible' WHERE id = :sim
                ");
                $stmtUpdSimOld->execute([':sim' => $simActual['id']]);

                // Crear relación con la nueva SIM
                $stmtRelNew = $pdo->prepare("
                    INSERT INTO telefono_sim (telefono_id, sim_id)
                    VALUES (:tel, :sim)
                ");
                $stmtRelNew->execute([
                    ':tel' => $id,
                    ':sim' => $sim_id_nueva
                ]);

                // Marcar nueva SIM como Asignada
                $stmtUpdSimNew = $pdo->prepare("
                    UPDATE sims SET estado = 'Asignada' WHERE id = :sim
                ");
                $stmtUpdSimNew->execute([':sim' => $sim_id_nueva]);
            }

            // Caso D: había SIM y se deja la misma -> no hacemos nada en relaciones
            // (ya cubierto: no entra en ninguna de las condiciones anteriores)

            $pdo->commit();
            header("Location: telefonos_ver.php?id=" . $id);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errores[] = "Error al actualizar el teléfono: " . $e->getMessage();
        }
    }
}

// Recargar datos actualizados del teléfono (por si venimos de un error)
$stmtTel->execute([':id' => $id]);
$telefono = $stmtTel->fetch(PDO::FETCH_ASSOC);

// Recalcular simActual (por simplicidad)
$stmtSimActual->execute([':id' => $id]);
$simActual = $stmtSimActual->fetch(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <h2>Editar teléfono móvil</h2>

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
                <label class="form-label">Marca</label>
                <input type="text" name="marca" class="form-control"
                       value="<?= htmlspecialchars($telefono['marca']) ?>" required>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Modelo</label>
                <input type="text" name="modelo" class="form-control"
                       value="<?= htmlspecialchars($telefono['modelo']) ?>" required>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">IMEI</label>
                <input type="text" name="imei" class="form-control"
                       value="<?= htmlspecialchars($telefono['imei']) ?>" required>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Número de serie</label>
                <input type="text" name="numero_serie" class="form-control"
                       value="<?= htmlspecialchars($telefono['numero_serie']) ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Usuario asignado</label>
                <input type="text" name="usuario_asignado" class="form-control"
                       value="<?= htmlspecialchars($telefono['usuario_asignado']) ?>">
            </div>
            <div class="row">
    <div class="col-md-4 mb-3">
        <label class="form-label">Departamento</label>
        <select name="departamento" class="form-select">
            <option value="">-- Seleccione departamento --</option>
            <?php 
            $valorDept = isset($_POST['departamento']) 
                ? $_POST['departamento'] 
                : $telefono['departamento'];
            foreach ($departamentos as $d): 
                $nombre = $d['nombre'];
                $selected = ($valorDept === $nombre) ? 'selected' : '';
            ?>
                <option value="<?= htmlspecialchars($nombre) ?>" <?= $selected ?>>
                    <?= htmlspecialchars($nombre) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-4 mb-3">
        <label class="form-label">Ubicación</label>
        <select name="ubicacion" class="form-select">
            <option value="">-- Seleccione ubicación --</option>
            <?php 
            $valorUbic = isset($_POST['ubicacion']) 
                ? $_POST['ubicacion'] 
                : $telefono['ubicacion'];
            foreach ($ubicaciones as $u): 
                $nombre = $u['nombre'];
                $selected = ($valorUbic === $nombre) ? 'selected' : '';
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
            <?php 
            $valorSec = isset($_POST['seccion']) 
                ? $_POST['seccion'] 
                : $telefono['seccion'];
            foreach ($secciones as $s): 
                $nombre = $s['nombre'];
                $selected = ($valorSec === $nombre) ? 'selected' : '';
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
                    <?php
                    $estados = ['Activo', 'Almacén', 'Prestado', 'Averiado', 'Baja'];
                    foreach ($estados as $est) {
                        $sel = ($telefono['estado'] === $est) ? 'selected' : '';
                        echo "<option value=\"$est\" $sel>$est</option>";
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3 mb-3">
                <label class="form-label">Fecha alta</label>
                <input type="date" name="fecha_alta" class="form-control"
                       value="<?= htmlspecialchars($telefono['fecha_alta']) ?>">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Fecha baja</label>
                <input type="date" name="fecha_baja" class="form-control"
                       value="<?= htmlspecialchars($telefono['fecha_baja']) ?>">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Proveedor</label>
                <input type="text" name="proveedor" class="form-control"
                       value="<?= htmlspecialchars($telefono['proveedor']) ?>">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Coste (€)</label>
                <input type="number" step="0.01" name="coste" class="form-control"
                       value="<?= htmlspecialchars($telefono['coste']) ?>">
            </div>
        </div>

        <!-- Select de SIM -->
        <div class="mb-3">
            <label class="form-label"><b>SIM asignada</b></label>
            <select name="sim_id" class="form-select">
                <option value="">-- Sin SIM --</option>

                <?php if ($simActual): ?>
                    <option value="<?= (int)$simActual['id'] ?>" selected>
                        <?= htmlspecialchars($simActual['numero']) ?> (<?= htmlspecialchars($simActual['operador']) ?>) [ACTUAL]
                    </option>
                <?php endif; ?>

                <?php foreach ($simsDisponibles as $sim): ?>
                    <option value="<?= (int)$sim['id'] ?>">
                        <?= htmlspecialchars($sim['numero']) ?> (<?= htmlspecialchars($sim['operador']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="text-muted">
                Si eliges una SIM distinta, se cambiará.
                Si seleccionas "-- Sin SIM --", se liberará la SIM actual (si la hubiera).
            </small>
        </div>

        <div class="mb-3">
            <label class="form-label">Observaciones</label>
            <textarea name="observaciones" class="form-control"><?= htmlspecialchars($telefono['observaciones']) ?></textarea>
        </div>

        <button type="submit" class="btn btn-success">Guardar cambios</button>
        <a href="telefonos_ver.php?id=<?= (int)$id ?>" class="btn btn-secondary">Cancelar</a>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
