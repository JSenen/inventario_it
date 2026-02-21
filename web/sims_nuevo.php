<?php
require_once 'auth.php';
require_once 'config.php';
require_once __DIR__ . '/includes/sims_schema.php';

ensureSimsSchema($pdo);

$errores = [];
$old = [
    'etiqueta'      => '',
    'numero'        => '',
    'numero_corto'  => '',
    'iccid'         => '',
    'operador'      => '',
    'pin'           => '',
    'puk'           => '',
    'estado'        => 'Disponible',
    'observaciones' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $etiqueta    = trim($_POST['etiqueta'] ?? '');
    $numero      = trim($_POST['numero'] ?? '');
    $numeroCorto = trim($_POST['numero_corto'] ?? '');
    $iccid       = trim($_POST['iccid'] ?? '');
    $operador    = trim($_POST['operador'] ?? '');
    $pin         = trim($_POST['pin'] ?? '');
    $puk         = trim($_POST['puk'] ?? '');
    $estado      = trim($_POST['estado'] ?? 'Disponible');
    $obs         = trim($_POST['observaciones'] ?? '');

    $old = [
        'etiqueta'      => $etiqueta,
        'numero'        => $numero,
        'numero_corto'  => $numeroCorto,
        'iccid'         => $iccid,
        'operador'      => $operador,
        'pin'           => $pin,
        'puk'           => $puk,
        'estado'        => $estado,
        'observaciones' => $obs,
    ];

    // if ($numero === '' || $iccid === '' || $operador === '') {
    //     $errores[] = "Número, ICCID y Operador son obligatorios.";
    // }

    if (!$errores) {
        $stmt = $pdo->prepare("
            INSERT INTO sims (etiqueta, numero, numero_corto, iccid, operador, tarifa, pin, puk, estado, observaciones)
            VALUES (:etiqueta, :numero, :numero_corto, :iccid, :operador, :tarifa, :pin, :puk, :estado, :obs)
        ");
        $stmt->execute([
            ':etiqueta'     => $etiqueta ?: null,
            ':numero'       => $numero,
            ':numero_corto' => $numeroCorto !== '' ? $numeroCorto : null,
            ':iccid'        => $iccid,
            ':operador'     => $operador,
            ':tarifa'       => null,
            ':pin'          => $pin,
            ':puk'          => $puk,
            ':estado'       => $estado,
            ':obs'          => $obs,
        ]);

        header("Location: sims.php");
        exit;
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
            <h2 class="mb-0">Nueva SIM</h2>
            <div class="text-muted small">Alta de tarjeta SIM para inventario y asignación posterior.</div>
        </div>
        <a href="sims.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Volver al listado
        </a>
    </div>

    <?php if (!empty($errores)): ?>
        <div class="alert alert-danger mb-3">
            <div class="fw-semibold mb-1">No se ha podido guardar la SIM:</div>
            <?php foreach ($errores as $e): ?>
                <div>&bull; <?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
        <div class="card mb-3">
            <div class="card-header fw-semibold">Identificación y estado</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Etiqueta</label>
                        <input
                            type="text"
                            name="etiqueta"
                            class="form-control campo-etiqueta"
                            value="<?= htmlspecialchars($old['etiqueta']) ?>"
                            placeholder="Ej: SIM-PTI-001"
                            autofocus
                        >
                        <div class="form-text">Código interno visible en listados y búsquedas.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Estado</label>
                        <select name="estado" class="form-select">
                            <?php
                            $estados = ['Disponible', 'Asignada', 'Baja', 'Averiada'];
                            foreach ($estados as $est):
                                $sel = ($old['estado'] === $est) ? 'selected' : '';
                            ?>
                                <option value="<?= htmlspecialchars($est) ?>" <?= $sel ?>><?= htmlspecialchars($est) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header fw-semibold">Línea y operador</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Número</label>
                        <input
                            type="text"
                            name="numero"
                            class="form-control sim-numero-destacado"
                            value="<?= htmlspecialchars($old['numero']) ?>"
                            placeholder="Ej: 600123456"
                        >
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Número corto</label>
                        <input
                            type="text"
                            name="numero_corto"
                            class="form-control"
                            value="<?= htmlspecialchars($old['numero_corto']) ?>"
                            placeholder="Ej: 312345"
                        >
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">ICCID</label>
                        <input
                            type="text"
                            name="iccid"
                            class="form-control"
                            value="<?= htmlspecialchars($old['iccid']) ?>"
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
                            value="<?= htmlspecialchars($old['operador']) ?>"
                            placeholder="Ej: Movistar"
                        >
                        <datalist id="operadores_sugeridos">
                            <?php foreach ($operadores as $op): ?>
                                <option value="<?= htmlspecialchars($op) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
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
                            value="<?= htmlspecialchars($old['pin']) ?>"
                            placeholder="PIN"
                        >
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">PUK</label>
                        <input
                            type="text"
                            name="puk"
                            class="form-control"
                            value="<?= htmlspecialchars($old['puk']) ?>"
                            placeholder="PUK"
                        >
                    </div>
                    <div class="col-12">
                        <label class="form-label">Observaciones</label>
                        <textarea
                            name="observaciones"
                            class="form-control"
                            rows="3"
                            placeholder="Notas internas, incidencias, histórico..."
                        ><?= htmlspecialchars($old['observaciones']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-success">
                <i class="bi bi-save"></i> Guardar SIM
            </button>
            <a href="sims.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
