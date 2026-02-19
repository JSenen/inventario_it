<?php
require_once 'auth.php';
require_once 'config.php';
require_once __DIR__ . '/includes/sims_schema.php';

ensureSimsSchema($pdo);

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $etiqueta  = trim($_POST['etiqueta'] ?? '');
    $numero    = trim($_POST['numero'] ?? '');
    $numeroCorto = trim($_POST['numero_corto'] ?? '');
    $iccid     = trim($_POST['iccid'] ?? '');
    $operador  = trim($_POST['operador'] ?? '');
    $tarifa    = trim($_POST['tarifa'] ?? '');
    $pin       = trim($_POST['pin'] ?? '');
    $puk       = trim($_POST['puk'] ?? '');
    $estado    = trim($_POST['estado'] ?? 'Disponible');
    $obs       = trim($_POST['observaciones'] ?? '');

    // if ($numero === '' || $iccid === '' || $operador === '') {
    //     $errores[] = "Número, ICCID y Operador son obligatorios.";
    // }

    if (!$errores) {
        $stmt = $pdo->prepare("
            INSERT INTO sims (etiqueta, numero, numero_corto, iccid, operador, tarifa, pin, puk, estado, observaciones)
            VALUES (:etiqueta, :numero, :numero_corto, :iccid, :operador, :tarifa, :pin, :puk, :estado, :obs)
        ");
        $stmt->execute([
            ':etiqueta' => $etiqueta ?: null,
            ':numero'   => $numero,
            ':numero_corto' => $numeroCorto !== '' ? $numeroCorto : null,
            ':iccid'    => $iccid,
            ':operador' => $operador,
            ':tarifa'   => $tarifa,
            ':pin'      => $pin,
            ':puk'      => $puk,
            ':estado'   => $estado,
            ':obs'      => $obs
        ]);

        header("Location: sims.php");
        exit;
    }
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <h2>Nueva SIM</h2>

    <?php if ($errores): ?>
        <div class="alert alert-danger">
            <?php foreach ($errores as $e): ?>
                <div><?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <div class="mb-3">
            <label class="form-label">Etiqueta</label>
            <input type="text" name="etiqueta" class="form-control campo-etiqueta" value="<?= htmlspecialchars($_POST['etiqueta'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Número</label>
            <input type="text" name="numero" class="form-control" >
        </div>
        <div class="mb-3">
            <label class="form-label">Número corto</label>
            <input type="text" name="numero_corto" class="form-control" value="<?= htmlspecialchars($_POST['numero_corto'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">ICCID</label>
            <input type="text" name="iccid" class="form-control" >
        </div>

        <div class="mb-3">
            <label class="form-label">Operador</label>
            <input type="text" name="operador" class="form-control" >
        </div>

        <!-- <div class="mb-3">
            <label class="form-label">Tarifa</label>
            <input type="text" name="tarifa" class="form-control">
        </div> -->

        <div class="mb-3">
            <label class="form-label">PIN</label>
            <input type="text" name="pin" class="form-control">
        </div>

        <div class="mb-3">
            <label class="form-label">PUK</label>
            <input type="text" name="puk" class="form-control">
        </div>

        <div class="mb-3">
            <label class="form-label">Estado</label>
            <select name="estado" class="form-select">
                <option value="Disponible">Disponible</option>
                <option value="Asignada">Asignada</option>
                <option value="Baja">Baja</option>
                <option value="Averiada">Averiada</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Observaciones</label>
            <textarea name="observaciones" class="form-control"></textarea>
        </div>

        <button type="submit" class="btn btn-success">Guardar</button>
        <a href="sims.php" class="btn btn-secondary">Cancelar</a>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
