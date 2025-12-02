<?php
// material_editar.php
require 'includes/db.php';

// 1) Validar ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: materiales.php');
    exit;
}

// 2) Cargar datos actuales del material
$stmt = $pdo->prepare("SELECT * FROM materiales WHERE id = :id");
$stmt->execute([':id' => $id]);
$mat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mat) {
    header('Location: materiales.php');
    exit;
}

// 3) Procesar envío del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $referencia    = trim($_POST['referencia'] ?? '');
    $descripcion   = trim($_POST['descripcion'] ?? '');
    $categoria     = trim($_POST['categoria'] ?? '');
    $unidad        = trim($_POST['unidad'] ?? 'ud');
    $stock_actual  = (int)($_POST['stock_actual'] ?? 0);
    $stock_minimo  = (int)($_POST['stock_minimo'] ?? 0);
    $ubicacion     = trim($_POST['ubicacion'] ?? '');
    $proveedor     = trim($_POST['proveedor'] ?? '');
    $coste         = $_POST['coste_unitario'] !== '' ? (float)$_POST['coste_unitario'] : null;
    $notas         = trim($_POST['notas'] ?? '');

    // Validación mínima
    if ($referencia === '' || $descripcion === '') {
        $error = "Referencia y descripción son obligatorias.";
    } else {
        $stmtUpd = $pdo->prepare("
            UPDATE materiales
            SET referencia     = :referencia,
                descripcion    = :descripcion,
                categoria      = :categoria,
                unidad         = :unidad,
                stock_actual   = :stock_actual,
                stock_minimo   = :stock_minimo,
                ubicacion      = :ubicacion,
                proveedor      = :proveedor,
                coste_unitario = :coste_unitario,
                notas          = :notas
            WHERE id = :id
        ");

        $stmtUpd->execute([
            ':referencia'     => $referencia,
            ':descripcion'    => $descripcion,
            ':categoria'      => $categoria,
            ':unidad'         => $unidad,
            ':stock_actual'   => $stock_actual,
            ':stock_minimo'   => $stock_minimo,
            ':ubicacion'      => $ubicacion,
            ':proveedor'      => $proveedor,
            ':coste_unitario' => $coste,
            ':notas'          => $notas,
            ':id'             => $id,
        ]);

        header('Location: material_ver.php?id=' . $id);
        exit;
    }

    // Si hay error, actualizamos $mat con los datos del POST para que el formulario no se pierda
    $mat['referencia']     = $referencia;
    $mat['descripcion']    = $descripcion;
    $mat['categoria']      = $categoria;
    $mat['unidad']         = $unidad;
    $mat['stock_actual']   = $stock_actual;
    $mat['stock_minimo']   = $stock_minimo;
    $mat['ubicacion']      = $ubicacion;
    $mat['proveedor']      = $proveedor;
    $mat['coste_unitario'] = $coste;
    $mat['notas']          = $notas;
}

include 'includes/header.php';
?>

<div class="container mt-4">
    <h2>Editar material / fungible</h2>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="row mb-3">
            <div class="col-md-4">
                <label class="form-label">Referencia</label>
                <input type="text" name="referencia" class="form-control"
                       value="<?= htmlspecialchars($mat['referencia']) ?>" required>
            </div>
            <div class="col-md-8">
                <label class="form-label">Descripción</label>
                <input type="text" name="descripcion" class="form-control"
                       value="<?= htmlspecialchars($mat['descripcion']) ?>" required>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-4">
                <label class="form-label">Categoría</label>
                <input type="text" name="categoria" class="form-control"
                       value="<?= htmlspecialchars($mat['categoria']) ?>" placeholder="Toner, Cable, SSD...">
            </div>
            <div class="col-md-2">
                <label class="form-label">Unidad</label>
                <input type="text" name="unidad" class="form-control"
                       value="<?= htmlspecialchars($mat['unidad']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Stock actual</label>
                <input type="number" name="stock_actual" class="form-control"
                       value="<?= (int)$mat['stock_actual'] ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Stock mínimo</label>
                <input type="number" name="stock_minimo" class="form-control"
                       value="<?= (int)$mat['stock_minimo'] ?>">
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-4">
                <label class="form-label">Ubicación</label>
                <input type="text" name="ubicacion" class="form-control"
                       value="<?= htmlspecialchars($mat['ubicacion']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Proveedor</label>
                <input type="text" name="proveedor" class="form-control"
                       value="<?= htmlspecialchars($mat['proveedor']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Coste unitario (€)</label>
                <input type="number" step="0.01" name="coste_unitario" class="form-control"
                       value="<?= $mat['coste_unitario'] !== null ? htmlspecialchars($mat['coste_unitario']) : '' ?>">
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Notas</label>
            <textarea name="notas" class="form-control" rows="3"><?= htmlspecialchars($mat['notas']) ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Guardar cambios</button>
        <a href="material_ver.php?id=<?= (int)$mat['id'] ?>" class="btn btn-secondary">Cancelar</a>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
