<?php
require_once 'auth.php';
require_once 'config.php';
require_once __DIR__ . '/includes/asset_files.php';

ensureAssetFilesSchema($pdo);

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

$imagenes_existentes = glob(
    __DIR__ . '/uploads/telefonos/*.{jpg,jpeg,png,gif,webp,JPG,JPEG,PNG,GIF,WEBP}',
    GLOB_BRACE
);
if (!is_array($imagenes_existentes)) {
    $imagenes_existentes = [];
}
usort($imagenes_existentes, function ($a, $b) {
    $na = preg_replace('/^\d+_/', '', basename($a));
    $nb = preg_replace('/^\d+_/', '', basename($b));
    return strcasecmp($na, $nb);
});

$errores = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $marca      = trim($_POST['marca'] ?? '');
    $modelo     = trim($_POST['modelo'] ?? '');
    $imei       = trim($_POST['imei'] ?? '');
    $etiqueta   = trim($_POST['etiqueta'] ?? '');
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
    $sim_id_nueva = $_POST['sim_id'] ?? '';

    if ($marca === '' || $modelo === '' || $imei === '') {
        $errores[] = "Marca, Modelo e IMEI son obligatorios.";
    }

    // --- Gestión de imagen ---
    $imagenRuta = null;
    if (!empty($_POST['imagen_existente'])) {
        $file = basename($_POST['imagen_existente']);
        $imagenRuta = 'uploads/telefonos/' . $file;
    } elseif (!empty($_FILES['imagen_nueva']['name'])) {
        $uploadDir = __DIR__ . '/uploads/telefonos/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $nombreOriginal = basename($_FILES['imagen_nueva']['name']);
        $nombreLimpio   = preg_replace('/[^A-Za-z0-9_\.-]/', '_', $nombreOriginal);
        $nombreFinal    = time() . '_' . $nombreLimpio;
        $rutaRelativa   = 'uploads/telefonos/' . $nombreFinal;
        $rutaFisica     = $uploadDir . $nombreFinal;

        if (move_uploaded_file($_FILES['imagen_nueva']['tmp_name'], $rutaFisica)) {
            $imagenRuta = $rutaRelativa;
        } else {
            $errores[] = "No se pudo guardar la nueva imagen del teléfono.";
        }
    }
    // --- Fin gestión de imagen ---

    if (!$errores) {
        $adjuntosSubidos = [];
        try {
            $pdo->beginTransaction();

            $stmtIns = $pdo->prepare("
                INSERT INTO telefonos (marca, modelo, imei, etiqueta, numero_serie, usuario_asignado, departamento, ubicacion, seccion, estado, fecha_alta, proveedor, coste, observaciones, imagen)
                VALUES (:marca, :modelo, :imei, :etiqueta, :num_serie, :usuario, :depart, :ubicacion, :seccion, :estado, :fecha_alta, :proveedor, :coste, :obs, :imagen)
            ");
            $stmtIns->execute([
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
                ':fecha_alta' => $fecha_alta ?: null,
                ':proveedor'  => $proveedor,
                ':coste'      => $coste ?: null,
                ':obs'        => $obs,
                ':imagen'     => $imagenRuta
            ]);

            $telefono_id = $pdo->lastInsertId();
            $adjuntosSubidos = uploadEntityAttachments(
                $pdo,
                'telefono',
                (int)$telefono_id,
                $_FILES['adjuntos'] ?? [],
                $_SESSION['tip'] ?? null
            );
            if ($sim_id_nueva !== '') {
                $stmtRel = $pdo->prepare("
                    INSERT INTO telefono_sim (telefono_id, sim_id)
                    VALUES (:tel, :sim)
                ");
                $stmtRel->execute([
                    ':tel' => $telefono_id,
                    ':sim' => $sim_id_nueva
                ]);

                $stmtUpdSim = $pdo->prepare("
                    UPDATE sims SET estado = 'Asignada' WHERE id = :sim
                ");
                $stmtUpdSim->execute([':sim' => $sim_id_nueva]);
            }

            $pdo->commit();
            header("Location: telefonos.php");
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            cleanupUploadedAttachments($adjuntosSubidos ?? []);
            $errores[] = "Error al guardar el teléfono: " . $e->getMessage();
        }
    }
}

//$imagenSeleccionada = $_POST['imagen_existente'] ?? '';

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

    <form method="post" enctype="multipart/form-data">
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
                    <option value="Baja definitiva">Baja definitiva</option>
                    <option value="Extraviado">Extraviado</option>
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

        <div class="card mb-3 shadow-sm">
            <div class="card-header py-2">
                <strong>Adjuntos</strong>
            </div>
            <div class="card-body">
                <label class="form-label">Subir uno o varios archivos</label>
                <input type="file" name="adjuntos[]" class="form-control" multiple>
                <small class="form-text">Puedes adjuntar facturas, contratos, actas o cualquier otro documento.</small>
            </div>
        </div>

        <div class="card mb-3 shadow-sm">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <strong>Imagen del teléfono</strong>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-link btn-sm text-decoration-none p-0" data-bs-toggle="collapse" data-bs-target="#galeriaImagenes">Elegir existente</button>
                    <button type="button" class="btn btn-link btn-sm text-decoration-none p-0" id="limpiarImagen">Quitar selección</button>
                </div>
            </div>
            <div class="card-body">
                <input type="hidden" name="imagen_existente" id="imagen_existente" value="<?= htmlspecialchars($imagenSeleccionada ?? '') ?>">
                <div class="text-center mb-3">
                    <img id="preview_img_editar" style="display:none;max-width:140px;border:1px solid #ccc;">
                    <img id="preview_img_nueva" style="display:none;max-width:140px;border:1px solid #ccc;margin-top:8px;">
                    <div class="text-muted small mt-1">Vista previa</div>
                </div>

                <div id="galeriaImagenes" class="collapse">
                    <div class="thumb-grid-wrapper">
                        <div class="row row-cols-2 row-cols-md-3 g-2 thumb-grid mb-3 mt-2">
                            <?php
                            if (!empty($imagenes_existentes)):
                                foreach ($imagenes_existentes as $img):
                                    $file  = basename($img);
                                    $label = preg_replace('/^\d+_/', '', $file);
                                    $isSel = (($imagenSeleccionada ?? '') === $file);
                            ?>
                                <div class="col">
                                    <button type="button"
                                            class="btn btn-light w-100 h-100 seleccionar-imagen <?= $isSel ? 'active-selection' : '' ?>"
                                            data-file="<?= htmlspecialchars($file) ?>">
                                        <img src="uploads/telefonos/<?= htmlspecialchars($file) ?>" class="img-fluid" alt="<?= htmlspecialchars($label) ?>">
                                        <div class="small text-truncate mt-1"><?= htmlspecialchars($label) ?></div>
                                    </button>
                                </div>
                            <?php
                                endforeach;
                            else:
                            ?>
                                <div class="col">
                                    <span class="text-muted small">No hay imágenes guardadas.</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="mt-3">
                    <label class="form-label mb-1"><strong>O subir imagen nueva</strong></label>
                    <input type="file" name="imagen_nueva" id="imagen_nueva" accept="image/*" class="form-control">
                    <small class="form-text">Si subes una nueva, tendrá prioridad sobre la seleccionada.</small>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-success">Crear teléfono</button>
        <a href="telefonos.php" class="btn btn-secondary">Cancelar</a>
    </form>
</div>

<script src="assets/js/gestion_imagenes.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    initImageManager({
        previewEditar: 'preview_img_editar',
        previewNueva: 'preview_img_nueva',
        inputExistente: 'imagen_existente',
        inputNuevo: 'imagen_nueva',
        btnLimpiar: 'limpiarImagen',
        btnSelector: '.seleccionar-imagen',
        uploadPath: 'uploads/telefonos/'
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
