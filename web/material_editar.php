<?php
// material_editar.php
require_once 'auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . "/includes/logger.php";

// 1) Validar ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: materiales.php');
    exit;
}

// 2) Cargar datos actuales del material
$stmt = $pdo->prepare("
    SELECT m.*, GROUP_CONCAT(mc.modelo_impresora ORDER BY mc.modelo_impresora SEPARATOR '\n') as modelos_compatibles
    FROM materiales m
    LEFT JOIN materiales_compatibilidad mc ON m.id = mc.material_id
    WHERE m.id = :id
    GROUP BY m.id");
$stmt->execute([':id' => $id]);
$mat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mat) {
    header('Location: materiales.php');
    exit;
}

// Imagen actual del material
$imagenActual = $mat['imagen'] ?? '';


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
    $modelos_compatibles_str = trim($_POST['modelos_compatibles'] ?? '');

    $errores = [];

    // Mantener la imagen actual por defecto
    $imagenRuta = $mat['imagen'] ?? null;
    $imagenAnterior = $imagenRuta;
    $imagenCambiada = false;

    // 1) Si ha elegido una imagen existente
    if (!empty($_POST['imagen_existente'])) {
        $file = basename($_POST['imagen_existente']);
        $nuevaRuta = 'uploads/materiales/' . $file;
        if ($nuevaRuta !== $imagenRuta) {
            $imagenCambiada = true;
        }
        $imagenRuta = $nuevaRuta;

    // 2) Si no, pero ha subido una nueva
    } elseif (!empty($_FILES['imagen_nueva']['name'])) {
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
            $imagenCambiada = true;
        } else {
            $errores[] = "No se pudo guardar la nueva imagen.";
        }
    }

    // Eliminar imagen anterior si no la usa otro material
    if ($imagenCambiada && !empty($imagenAnterior) && $imagenAnterior !== $imagenRuta) {
        $stmtUsoImg = $pdo->prepare("SELECT COUNT(*) FROM materiales WHERE imagen = :img AND id <> :id");
        $stmtUsoImg->execute([':img' => $imagenAnterior, ':id' => $id]);
        if ((int)$stmtUsoImg->fetchColumn() === 0) {
            $rutaAnteriorFs = __DIR__ . '/' . $imagenAnterior;
            if (is_file($rutaAnteriorFs)) {
                @unlink($rutaAnteriorFs);
            }
        }
    }


    // Validación mínima
    if ($referencia === '' || $descripcion === '') {
        $errores[] = "Referencia y descripción son obligatorias.";
    }

    if (empty($errores)) {
        try {
            $pdo->beginTransaction();
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
                notas          = :notas,
                imagen         = :imagen
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
            ':imagen'         => $imagenRuta,
            ':id'             => $id,
        ]);

            // Gestionar modelos compatibles
            $modelos_nuevos = [];
            if ($modelos_compatibles_str !== '') {
                $modelos_nuevos = array_filter(array_map('trim', explode("\n", $modelos_compatibles_str)));
            }

            // Borrar compatibilidades antiguas
            $stmtDelCompat = $pdo->prepare("DELETE FROM materiales_compatibilidad WHERE material_id = :id");
            $stmtDelCompat->execute([':id' => $id]);

            // Insertar las nuevas
            if (!empty($modelos_nuevos)) {
                $stmtInsCompat = $pdo->prepare("INSERT INTO materiales_compatibilidad (material_id, modelo_impresora) VALUES (:id, :modelo)");
                foreach ($modelos_nuevos as $modelo) {
                    $stmtInsCompat->execute([':id' => $id, ':modelo' => $modelo]);
                }
            }

            $pdo->commit();
            logActividad($pdo, 'EDITAR_MATERIAL', 'Material editado: ID=' . $id);
            header('Location: material_ver.php?id=' . $id);
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errores[] = "Error al guardar: " . $e->getMessage();
        }
    } // fin if empty(errores)

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
    $mat['modelos_compatibles'] = $modelos_compatibles_str;
}

include 'includes/header.php';

$imagenes_existentes = glob(
    __DIR__ . '/uploads/materiales/*.{jpg,jpeg,png,gif,webp,JPG,JPEG,PNG,GIF,WEBP}',
    GLOB_BRACE
);
if (!is_array($imagenes_existentes)) $imagenes_existentes = [];

usort($imagenes_existentes, function ($a, $b) {
    $na = preg_replace('/^\d+_/', '', basename($a));
    $nb = preg_replace('/^\d+_/', '', basename($b));
    return strcasecmp($na, $nb);
});

$imagenSeleccionada = $_POST['imagen_existente']
    ?? (!empty($mat['imagen']) ? basename($mat['imagen']) : '');

?>

<div class="container mt-4">
    <h2>Editar material / fungible</h2>

    <?php if (!empty($errores)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errores as $err) echo htmlspecialchars($err) . '<br>'; ?>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
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

        <div class="row">
            <div class="col-md-6">
                <div class="card mb-3 shadow-sm">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                        <strong>Imagen del material</strong>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-link btn-sm text-decoration-none p-0" data-bs-toggle="collapse" data-bs-target="#galeriaImagenes">Cambiar</button>
                            <button type="button" class="btn btn-link btn-sm text-decoration-none p-0" id="limpiarImagen">Quitar</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <input type="hidden" name="imagen_existente" id="imagen_existente" value="<?= htmlspecialchars($imagenSeleccionada) ?>">
                        <div class="text-center mb-3">
                            <img id="preview_img_editar"
                                 src="<?= !empty($mat['imagen']) ? htmlspecialchars($mat['imagen']) : '' ?>"
                                 style="max-width:140px; border:1px solid #ccc; <?= !empty($mat['imagen']) ? '' : 'display:none;' ?>">
                            <img id="preview_img_nueva" style="display:none;max-width:140px;border:1px solid #ccc;margin-top:8px;">
                        </div>

                        <div id="galeriaImagenes" class="collapse">
                            <div class="thumb-grid-wrapper">
                                <div class="row row-cols-2 row-cols-md-3 g-2 thumb-grid mb-3 mt-2">
                                    <?php foreach ($imagenes_existentes as $img):
                                        $file = basename($img);
                                        $label = preg_replace('/^\d+_/', '', $file);
                                        $isSel = ($imagenSeleccionada === $file);
                                    ?>
                                        <div class="col">
                                            <button type="button"
                                                    class="btn btn-light w-100 h-100 seleccionar-imagen <?= $isSel ? 'active-selection' : '' ?>"
                                                    data-file="<?= htmlspecialchars($file) ?>">
                                                <img src="uploads/materiales/<?= htmlspecialchars($file) ?>" class="img-fluid" alt="<?= htmlspecialchars($label) ?>">
                                                <div class="small text-truncate mt-1"><?= htmlspecialchars($label) ?></div>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label mb-1"><strong>Subir imagen nueva</strong></label>
                            <input type="file" name="imagen_nueva" id="imagen_nueva" accept="image/*" class="form-control">
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">Modelos de impresora compatibles (uno por línea)</label>
                    <textarea name="modelos_compatibles" class="form-control" rows="10"
                              placeholder="HP LaserJet Pro M404dn&#10;Brother HL-L2350DW..."><?= htmlspecialchars($mat['modelos_compatibles'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Guardar cambios</button>
        <a href="material_ver.php?id=<?= (int)$mat['id'] ?>" class="btn btn-secondary">Cancelar</a>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const imagenButtons = document.querySelectorAll('.seleccionar-imagen');
    const imagenHidden  = document.getElementById('imagen_existente');
    const imagenNueva   = document.getElementById('imagen_nueva');
    const previewImg    = document.getElementById('preview_img_editar');
    const previewNueva  = document.getElementById('preview_img_nueva');
    const limpiarBtn    = document.getElementById('limpiarImagen');

    function setPreview(src) {
        if (previewImg) {
            previewImg.src = src || '';
            previewImg.style.display = src ? 'block' : 'none';
        }
    }

    imagenButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            imagenButtons.forEach(b => b.classList.remove('active-selection'));
            btn.classList.add('active-selection');
            const file = btn.dataset.file || '';
            if (imagenHidden) imagenHidden.value = file;
            if (imagenNueva) imagenNueva.value = '';
            if (previewNueva) previewNueva.style.display = 'none';
            if (file) setPreview('uploads/materiales/' + file);
        });
    });

    imagenNueva.addEventListener('change', (e) => {
        if (e.target.files && e.target.files[0]) {
            imagenButtons.forEach(b => b.classList.remove('active-selection'));
            if (imagenHidden) imagenHidden.value = '';
            const reader = new FileReader();
            reader.onload = function (ev) {
                if (previewNueva) {
                    previewNueva.src = ev.target.result;
                    previewNueva.style.display = 'block';
                }
                if (previewImg) previewImg.style.display = 'none';
            };
            reader.readAsDataURL(e.target.files[0]);
        }
    });

    limpiarBtn.addEventListener('click', () => {
        imagenButtons.forEach(b => b.classList.remove('active-selection'));
        if (imagenHidden) imagenHidden.value = '';
        if (imagenNueva) imagenNueva.value = '';
        if (previewNueva) previewNueva.style.display = 'none';
        if (previewImg) previewImg.style.display = 'none';
    });
});
</script>

<?php include 'includes/footer.php'; ?>
