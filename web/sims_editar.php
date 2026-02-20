<?php
require_once 'auth.php';
require_once 'config.php';
require_once __DIR__ . '/includes/sims_schema.php';

ensureSimsSchema($pdo);

$errores = [];

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die('ID de SIM no valido.');
}

$stmt = $pdo->prepare('SELECT * FROM sims WHERE id = :id');
$stmt->execute([':id' => $id]);
$sim = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sim) {
    die('Tarjeta SIM no encontrada.');
}

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

$form = [
    'etiqueta'      => (string)($sim['etiqueta'] ?? ''),
    'numero'        => (string)($sim['numero'] ?? ''),
    'numero_corto'  => (string)($sim['numero_corto'] ?? ''),
    'iccid'         => (string)($sim['iccid'] ?? ''),
    'operador'      => (string)($sim['operador'] ?? ''),
    'tarifa'        => (string)($sim['tarifa'] ?? ''),
    'pin'           => (string)($sim['pin'] ?? ''),
    'puk'           => (string)($sim['puk'] ?? ''),
    'estado'        => (string)($sim['estado'] ?? 'Disponible'),
    'fecha_alta'    => (string)($sim['fecha_alta'] ?? ''),
    'fecha_baja'    => (string)($sim['fecha_baja'] ?? ''),
    'observaciones' => (string)($sim['observaciones'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $etiqueta    = trim($_POST['etiqueta'] ?? '');
    $numero      = trim($_POST['numero'] ?? '');
    $numeroCorto = trim($_POST['numero_corto'] ?? '');
    $iccid       = trim($_POST['iccid'] ?? '');
    $operador    = trim($_POST['operador'] ?? '');
    $tarifa      = trim($_POST['tarifa'] ?? '');
    $pin         = trim($_POST['pin'] ?? '');
    $puk         = trim($_POST['puk'] ?? '');
    $estado      = trim($_POST['estado'] ?? 'Disponible');
    $fechaAlta   = trim($_POST['fecha_alta'] ?? '');
    $fechaBaja   = trim($_POST['fecha_baja'] ?? '');
    $obs         = trim($_POST['observaciones'] ?? '');

    if (strcasecmp($estado, 'Baja') === 0 && $fechaBaja === '') {
        $fechaBaja = date('Y-m-d');
    }

    $form = [
        'etiqueta'      => $etiqueta,
        'numero'        => $numero,
        'numero_corto'  => $numeroCorto,
        'iccid'         => $iccid,
        'operador'      => $operador,
        'tarifa'        => $tarifa,
        'pin'           => $pin,
        'puk'           => $puk,
        'estado'        => $estado,
        'fecha_alta'    => $fechaAlta,
        'fecha_baja'    => $fechaBaja,
        'observaciones' => $obs,
    ];

    // if ($numero === '' || $iccid === '' || $operador === '') {
    //     $errores[] = 'Numero, ICCID y Operador son obligatorios.';
    // }

    if (!$errores) {
        try {
            $stmtUpd = $pdo->prepare("
                UPDATE sims
                   SET etiqueta      = :etiqueta,
                       numero        = :numero,
                       numero_corto  = :numero_corto,
                       iccid         = :iccid,
                       operador      = :operador,
                       tarifa        = :tarifa,
                       pin           = :pin,
                       puk           = :puk,
                       estado        = :estado,
                       fecha_alta    = :fecha_alta,
                       fecha_baja    = :fecha_baja,
                       observaciones = :obs
                 WHERE id = :id
            ");

            $stmtUpd->execute([
                ':etiqueta'     => $etiqueta ?: null,
                ':numero'       => $numero,
                ':numero_corto' => $numeroCorto !== '' ? $numeroCorto : null,
                ':iccid'        => $iccid,
                ':operador'     => $operador,
                ':tarifa'       => $tarifa !== '' ? $tarifa : null,
                ':pin'          => $pin,
                ':puk'          => $puk,
                ':estado'       => $estado,
                ':fecha_alta'   => $fechaAlta !== '' ? $fechaAlta : null,
                ':fecha_baja'   => $fechaBaja !== '' ? $fechaBaja : null,
                ':obs'          => $obs,
                ':id'           => $id,
            ]);

            header('Location: sims_ver.php?id=' . $id);
            exit;
        } catch (Exception $e) {
            $errores[] = 'Error al actualizar la SIM: ' . $e->getMessage();
        }
    }
}

$operadores = $pdo->query("
    SELECT DISTINCT operador
    FROM sims
    WHERE operador IS NOT NULL AND operador <> ''
    ORDER BY operador ASC
")->fetchAll(PDO::FETCH_COLUMN);

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h2 class="mb-0">Editar SIM #<?= (int)$id ?></h2>
            <div class="text-muted small">Actualiza los datos de la tarjeta SIM y su estado operativo.</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="sims_ver.php?id=<?= (int)$id ?>" class="btn btn-outline-primary">
                <i class="bi bi-eye"></i> Ver ficha
            </a>
            <a href="sims.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver al listado
            </a>
        </div>
    </div>

    <div class="mb-3">
        <?php if ($telefonoActual): ?>
            <span class="badge text-bg-success">
                Asignada a:
                <a href="telefonos_ver.php?id=<?= (int)$telefonoActual['id'] ?>" class="text-white text-decoration-underline">
                    <?= htmlspecialchars(trim(($telefonoActual['marca'] ?? '') . ' ' . ($telefonoActual['modelo'] ?? ''))) ?>
                </a>
            </span>
        <?php else: ?>
            <span class="badge text-bg-secondary">No asignada actualmente</span>
        <?php endif; ?>
    </div>

    <?php if (!empty($errores)): ?>
        <div class="alert alert-danger mb-3">
            <div class="fw-semibold mb-1">No se ha podido actualizar la SIM:</div>
            <?php foreach ($errores as $e): ?>
                <div>&bull; <?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
        <div class="card mb-3">
            <div class="card-header fw-semibold">Identificacion y estado</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Etiqueta</label>
                        <input
                            type="text"
                            name="etiqueta"
                            class="form-control campo-etiqueta"
                            value="<?= htmlspecialchars($form['etiqueta']) ?>"
                            placeholder="Ej: SIM-PTI-001"
                        >
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Estado</label>
                        <select name="estado" class="form-select">
                            <?php
                            $estados = ['Disponible', 'Asignada', 'Baja', 'Averiada'];
                            foreach ($estados as $est):
                                $sel = ($form['estado'] === $est) ? 'selected' : '';
                            ?>
                                <option value="<?= htmlspecialchars($est) ?>" <?= $sel ?>><?= htmlspecialchars($est) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Fecha alta</label>
                        <input type="date" name="fecha_alta" class="form-control" value="<?= htmlspecialchars($form['fecha_alta']) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Fecha baja</label>
                        <input type="date" name="fecha_baja" class="form-control" value="<?= htmlspecialchars($form['fecha_baja']) ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header fw-semibold">Linea y operador</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Numero</label>
                        <input
                            type="text"
                            name="numero"
                            class="form-control sim-numero-destacado"
                            value="<?= htmlspecialchars($form['numero']) ?>"
                            placeholder="Ej: 600123456"
                        >
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Numero corto</label>
                        <input
                            type="text"
                            name="numero_corto"
                            class="form-control"
                            value="<?= htmlspecialchars($form['numero_corto']) ?>"
                            placeholder="Ej: 312345"
                        >
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">ICCID</label>
                        <input
                            type="text"
                            name="iccid"
                            class="form-control"
                            value="<?= htmlspecialchars($form['iccid']) ?>"
                            placeholder="Ej: 8934..."
                        >
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Operador</label>
                        <input
                            type="text"
                            name="operador"
                            class="form-control"
                            list="operadores_sugeridos"
                            value="<?= htmlspecialchars($form['operador']) ?>"
                            placeholder="Ej: Movistar"
                        >
                        <datalist id="operadores_sugeridos">
                            <?php foreach ($operadores as $op): ?>
                                <option value="<?= htmlspecialchars($op) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tarifa</label>
                        <input
                            type="text"
                            name="tarifa"
                            class="form-control"
                            value="<?= htmlspecialchars($form['tarifa']) ?>"
                            placeholder="Opcional"
                        >
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header fw-semibold">Seguridad y notas</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">PIN</label>
                        <input
                            type="text"
                            name="pin"
                            class="form-control"
                            value="<?= htmlspecialchars($form['pin']) ?>"
                            placeholder="PIN"
                        >
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">PUK</label>
                        <input
                            type="text"
                            name="puk"
                            class="form-control"
                            value="<?= htmlspecialchars($form['puk']) ?>"
                            placeholder="PUK"
                        >
                    </div>
                    <div class="col-12">
                        <label class="form-label">Observaciones</label>
                        <textarea
                            name="observaciones"
                            class="form-control"
                            rows="3"
                            placeholder="Notas internas, incidencias, historico..."
                        ><?= htmlspecialchars($form['observaciones']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-success">
                <i class="bi bi-save"></i> Guardar cambios
            </button>
            <a href="sims_ver.php?id=<?= (int)$id ?>" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
