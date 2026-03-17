<?php
require_once 'auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . "/includes/logger.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $referencia    = trim($_POST['referencia'] ?? '');
    $descripcion   = trim($_POST['descripcion'] ?? '');
    $categoria     = trim($_POST['categoria'] ?? '');
    $unidad        = trim($_POST['unidad'] ?? 'ud');
    $stock_actual  = (int)($_POST['stock_actual'] ?? 0);
    $stock_minimo  = (int)($_POST['stock_minimo'] ?? 0);
    $ubicacion     = trim($_POST['ubicacion'] ?? html_entity_decode('Almac&eacute;n', ENT_QUOTES, 'UTF-8'));
    if ($ubicacion === '') {
        $ubicacion = html_entity_decode('Almac&eacute;n', ENT_QUOTES, 'UTF-8');
    }
    $proveedor     = trim($_POST['proveedor'] ?? '');
    $coste         = $_POST['coste_unitario'] !== '' ? (float)$_POST['coste_unitario'] : null;
    $notas         = trim($_POST['notas'] ?? '');
    $imagenRuta    = null;

    if (!empty($_FILES['imagen_nueva']['name'])) {
        $uploadDir = __DIR__ . '/uploads/materiales/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $nombreOriginal = basename($_FILES['imagen_nueva']['name']);
        $nombreLimpio   = preg_replace('/[^A-Za-z0-9_\.-]/', '_', $nombreOriginal);
        $nombreFinal    = time() . '_' . $nombreLimpio;
        $rutaRelativa   = 'uploads/materiales/' . $nombreFinal;
        $rutaFisica     = $uploadDir . $nombreFinal;

        if (move_uploaded_file($_FILES['imagen_nueva']['tmp_name'], $rutaFisica)) {
            $imagenRuta = $rutaRelativa;
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO materiales
            (referencia, descripcion, categoria, unidad,
             stock_actual, stock_minimo, ubicacion,
             proveedor, coste_unitario, notas, imagen)
        VALUES
            (:referencia, :descripcion, :categoria, :unidad,
             :stock_actual, :stock_minimo, :ubicacion,
             :proveedor, :coste_unitario, :notas, :imagen)
    ");
    $stmt->execute([
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
        ':imagen'         => $imagenRuta,
    ]);

    logActividad($pdo, 'NUEVO_MATERIAL', 'Nuevo material creado: Referencia=' . $referencia . ', Descripción=' . $descripcion );
    header('Location: materiales.php');
    exit;
}

include 'includes/header.php';
?>

<div class="container mt-4">
    <h2>Nuevo material / fungible</h2>
    <form method="post" enctype="multipart/form-data">
        <div class="row mb-3">
            <div class="col-md-4">
                <label class="form-label">Referencia</label>
                <input type="text" name="referencia" class="form-control" required>
            </div>
            <div class="col-md-8">
                <label class="form-label">Descripción</label>
                <input type="text" name="descripcion" class="form-control" required>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-4">
                <label class="form-label">Categoría</label>
                <input type="text" name="categoria" class="form-control" placeholder="Toner, Cable, SSD...">
            </div>
            <div class="col-md-2">
                <label class="form-label">Unidad</label>
                <input type="text" name="unidad" class="form-control" value="ud">
            </div>
            <div class="col-md-3">
                <label class="form-label">Stock actual</label>
                <input type="number" name="stock_actual" class="form-control" value="0">
            </div>
            <div class="col-md-3">
                <label class="form-label">Stock mínimo</label>
                <input type="number" name="stock_minimo" class="form-control" value="0">
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-4">
                <label class="form-label">Ubicación</label>
                <input type="text" name="ubicacion" class="form-control" value="Almac&eacute;n">
            </div>
            <div class="col-md-4">
                <label class="form-label">Proveedor</label>
                <input type="text" name="proveedor" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label">Coste unitario (€)</label>
                <input type="number" step="0.01" name="coste_unitario" class="form-control">
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Notas</label>
            <textarea name="notas" class="form-control" rows="3"></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Imagen (opcional)</label>
            <input type="file" name="imagen_nueva" accept="image/*" class="form-control">
        </div>

        <button type="submit" class="btn btn-primary">Guardar</button>
        <a href="materiales.php" class="btn btn-secondary">Cancelar</a>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
