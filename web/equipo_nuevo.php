<?php
require_once 'auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . "/includes/logger.php";
require_once __DIR__ . "/includes/movimientos_helper.php";


// Cargar tipos de equipos para los select
$tiposStmt = $pdo->query("SELECT nombre FROM tipos_equipo ORDER BY nombre ASC");
$tipos = $tiposStmt->fetchAll(PDO::FETCH_ASSOC);

// Cargar tipos de servicio para los select
$serviciosStmt = $pdo->query("SELECT nombre FROM tipos_servicio ORDER BY nombre ASC");
$servicios = $serviciosStmt->fetchAll(PDO::FETCH_ASSOC);


// Cargar ubicaciones desde la tabla ubicaciones
$ubicacionesStmt = $pdo->query("SELECT nombre FROM ubicaciones ORDER BY nombre ASC");
$ubicaciones = $ubicacionesStmt->fetchAll(PDO::FETCH_ASSOC);

// Cargar departamentos desde la tabla departamentos
$departamentosStmt = $pdo->query("SELECT nombre FROM departamentos ORDER BY nombre ASC");
$departamentos = $departamentosStmt->fetchAll(PDO::FETCH_ASSOC);

// Cargar redes para el select
$redesStmt = $pdo->query("SELECT id, nombre, direccion_red FROM redes ORDER BY id ASC");
$redes = $redesStmt->fetchAll(PDO::FETCH_ASSOC);

// Cargar secciones para el select
$seccionesStmt = $pdo->query("SELECT id, nombre FROM secciones ORDER BY nombre ASC");   
$secciones = $seccionesStmt->fetchAll(PDO::FETCH_ASSOC);

// Tabla de relación SIM↔equipo (para PTI) por si aún no existe
$pdo->exec("
    CREATE TABLE IF NOT EXISTS equipo_sim (
        id INT AUTO_INCREMENT PRIMARY KEY,
        equipo_id INT NOT NULL,
        sim_id INT NOT NULL,
        fecha_asignacion DATETIME DEFAULT CURRENT_TIMESTAMP,
        fecha_liberacion DATETIME DEFAULT NULL,
        observaciones TEXT,
        INDEX idx_equipo (equipo_id),
        INDEX idx_sim (sim_id),
        FOREIGN KEY (equipo_id) REFERENCES equipos(id),
        FOREIGN KEY (sim_id) REFERENCES sims(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// SIMs disponibles para asignar (solo se muestran si el tipo es PTI)
$simsDisponibles = $pdo->query("SELECT id, numero, operador, iccid, etiqueta FROM sims WHERE estado = 'Disponible' ORDER BY numero ASC")->fetchAll(PDO::FETCH_ASSOC);

// Monitores libres (no asignados todavía)
$monitoresStmt = $pdo->query("
    SELECT e.id, e.marca, e.modelo, e.numero_serie
    FROM equipos e
    LEFT JOIN pc_monitores pm ON pm.id_monitor = e.id
    WHERE e.tipo = 'MONITOR'
      AND pm.id_monitor IS NULL
    ORDER BY e.marca, e.modelo, e.numero_serie
");
$monitores = $monitoresStmt->fetchAll(PDO::FETCH_ASSOC);


$errores = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $estado           = $_POST['estado'] ?? '';
    $usuario_asignado = trim($_POST['usuario_asignado'] ?? '');

    $estadosRequierenUsuario = ['Activo', 'Prestado'];

    if (in_array($estado, $estadosRequierenUsuario, true) && $usuario_asignado === '') {
        $_SESSION['error'] = "Para crear un equipo en estado '{$estado}' debes indicar un usuario asignado.";
        header('Location: equipo_nuevo.php');
        exit;
    }


    $tipo            = trim($_POST['tipo'] ?? '');
    $marca           = strtoupper(trim($_POST['marca'] ?? ''));
    $modelo          = strtoupper(trim($_POST['modelo'] ?? ''));
    $numero_serie    = strtoupper(trim($_POST['numero_serie'] ?? ''));
    $hostname        = strtoupper(trim($_POST['hostname'] ?? ''));
    $usuario_asignado= strtoupper(trim($_POST['usuario_asignado'] ?? ''));
    $departamento    = strtoupper(trim($_POST['departamento'] ?? ''));
    $ubicacion       = strtoupper(trim($_POST['ubicacion'] ?? ''));
    $fecha_compra    = $_POST['fecha_compra'] ?? null;
    $proveedor       = strtoupper(trim($_POST['proveedor'] ?? 'GC'));
    $coste           = $_POST['coste'] ?? null;
    $estado          = trim($_POST['estado'] ?? 'Activo');
    $notas           = trim($_POST['notas'] ?? '');
    $etiqueta        = strtoupper(trim($_POST['etiqueta'] ?? ''));

    $ip              = trim($_POST['ip'] ?? '');
    $mac             = strtoupper(trim($_POST['mac'] ?? ''));
    $red_id          = $_POST['red_id'] ?? '';
    $monitoresSeleccionados = isset($_POST['monitores']) && is_array($_POST['monitores'])
    ? array_map('intval', $_POST['monitores'])
    : [];
    $sim_id_pti      = isset($_POST['sim_id_pti']) && $_POST['sim_id_pti'] !== '' ? (int)$_POST['sim_id_pti'] : null;

    $tipoUpper       = strtoupper($tipo);
    $esPTI           = (strpos($tipoUpper, 'PTI') !== false);

    // Validar SIM solo si es PTI y se seleccionó una
    if ($esPTI && $sim_id_pti !== null) {
        $stmtCheckSim = $pdo->prepare("
            SELECT COUNT(*) 
            FROM equipo_sim 
            WHERE sim_id = :sim AND fecha_liberacion IS NULL
        ");
        $stmtCheckSim->execute([':sim' => $sim_id_pti]);
        $yaAsignada = (int)$stmtCheckSim->fetchColumn() > 0;
        if ($yaAsignada) {
            $errores[] = "La SIM seleccionada ya está asignada a otro equipo.";
        }

        $stmtEstadoSim = $pdo->prepare("SELECT estado FROM sims WHERE id = :id");
        $stmtEstadoSim->execute([':id' => $sim_id_pti]);
        $estadoSim = $stmtEstadoSim->fetchColumn();
        if (!$estadoSim) {
            $errores[] = "La SIM seleccionada no existe.";
        } elseif ($estadoSim !== 'Disponible') {
            $errores[] = "La SIM seleccionada no está disponible.";
        }
    }


    if ($tipo === '') {
        $errores[] = "El campo Tipo es obligatorio.";
    }
    $seccion_id = isset($_POST['seccion_id']) && $_POST['seccion_id'] !== ''
    ? (int) $_POST['seccion_id']
    : null;

    // Manejo de la imagen del equipo
$imagenRuta = null;

// 1) Si ha seleccionado una imagen ya existente
if (!empty($_POST['imagen_existente'])) {
    $file = basename($_POST['imagen_existente']);
    $imagenRuta = 'uploads/equipos/' . $file;

// 2) Si no hay existente pero sube una nueva
} elseif (!empty($_FILES['imagen']['name'])) {
    $uploadDir = __DIR__ . '/uploads/equipos/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $nombreOriginal = basename($_FILES['imagen']['name']);
    $nombreLimpio   = preg_replace('/[^A-Za-z0-9_\.-]/', '_', $nombreOriginal);
    $nombreFinal    = time() . '_' . $nombreLimpio;

    $rutaRelativa = 'uploads/equipos/' . $nombreFinal;
    $rutaFisica   = $uploadDir . $nombreFinal;

    if (move_uploaded_file($_FILES['imagen']['tmp_name'], $rutaFisica)) {
        $imagenRuta = $rutaRelativa;
    } else {
        $errores[] = "No se pudo guardar la imagen del equipo.";
    }
}



// Comprobar si el número de serie ya existe
if ($numero_serie !== '') {
    $stmtCheck = $pdo->prepare("SELECT id FROM equipos WHERE numero_serie = :ns");
    $stmtCheck->execute([':ns' => $numero_serie]);

    if ($stmtCheck->fetch()) {
        $errores[] = "El número de serie $numero_serie ya existe en otro equipo.";
    }
}
    // Si no hay errores, proceder a guardar
    
    // Justo arriba del if (empty($errores))
    $seccion_id = isset($_POST['seccion_id']) && $_POST['seccion_id'] !== ''
    ? (int) $_POST['seccion_id']
    : null;

    if (empty($errores)) {
        try {
            $pdo->beginTransaction();

            $sqlEquipo = "INSERT INTO equipos 
                (tipo, marca, modelo, numero_serie, hostname, usuario_asignado, departamento, ubicacion, fecha_compra, proveedor, coste, estado, notas, etiqueta, imagen, seccion_id)
                VALUES 
                (:tipo, :marca, :modelo, :numero_serie, :hostname, :usuario_asignado, :departamento, :ubicacion, :fecha_compra, :proveedor, :coste, :estado, :notas, :etiqueta, :imagen, :seccion_id)";
            $stmtEq = $pdo->prepare($sqlEquipo);
            $stmtEq->execute([
                ':tipo'            => $tipo,
                ':marca'           => $marca,
                ':modelo'          => $modelo,
                ':numero_serie'    => $numero_serie,
                ':hostname'        => $hostname,
                ':usuario_asignado'=> $usuario_asignado,
                ':departamento'    => $departamento,
                ':ubicacion'       => $ubicacion,
                ':fecha_compra'    => $fecha_compra ?: null,
                ':proveedor'       => $proveedor,
                ':coste'           => $coste !== '' ? $coste : null,
                ':estado'          => $estado,
                ':notas'           => $notas,
                ':etiqueta'        => $etiqueta,
                ':imagen'          => $imagenRuta,
                ':seccion_id'      => $seccion_id,
            ]);

            $equipoId = (int)$pdo->lastInsertId();

            // Si es un equipo con monitores (PC, portátil o variante PTI), guardar asociaciones
        $tipoConMonitores = strtoupper($tipo);
        $tiposMonitores = ['PC','PORTATIL','PORTÁTIL','PTI','PORTATIL (PTI)','PORTÁTIL (PTI)'];

        if (in_array($tipoConMonitores, $tiposMonitores, true) && !empty($monitoresSeleccionados)) {
            $sqlRel = "INSERT INTO pc_monitores (id_pc, id_monitor)
                    VALUES (:id_pc, :id_monitor)";
            $stmtRel = $pdo->prepare($sqlRel);

            foreach ($monitoresSeleccionados as $idMon) {
                if ($idMon > 0) {
                    $stmtRel->execute([
                        ':id_pc'     => $equipoId,
                        ':id_monitor'=> $idMon
                    ]);
                }
            }
        }

            // Si es PTI y se seleccionó SIM, crear relación
            if ($esPTI && $sim_id_pti !== null) {
                $stmtRelSim = $pdo->prepare("
                    INSERT INTO equipo_sim (equipo_id, sim_id)
                    VALUES (:equipo, :sim)
                ");
                $stmtRelSim->execute([
                    ':equipo' => $equipoId,
                    ':sim'    => $sim_id_pti,
                ]);

                $stmtUpdSim = $pdo->prepare("UPDATE sims SET estado = 'Asignada' WHERE id = :sim");
                $stmtUpdSim->execute([':sim' => $sim_id_pti]);
            }

            // Si se proporcionó IP y red, guardarla
            if ($ip !== '' && $red_id !== '') {
                $sqlIp = "INSERT INTO ips_equipos (equipo_id, red_id, ip, mac, es_principal, notas)
                          VALUES (:equipo_id, :red_id, :ip, :mac, 1, NULL)";
                $stmtIp = $pdo->prepare($sqlIp);
                $stmtIp->execute([
                    ':equipo_id' => $equipoId,
                    ':red_id'    => $red_id,
                    ':ip'        => $ip,
                    ':mac'       => $mac,
                ]);
            }

                        logActividad($pdo, 'CREAR_EQUIPO', 'Nuevo equipo creado: ID=' . $equipoId);

            $pdo->commit();

            // 👇 Si se crea ya como Activo/Prestado con usuario, consideramos salida desde almacén
            try {
                $idMov = registrarMovimientoEquipo(
                    $pdo,
                    $equipoId,
                    'Almacén',        // origen lógico
                    null,             // sin usuario anterior
                    $estado,
                    $usuario_asignado
                );
            } catch (Exception $eMov) {
                $idMov = null;
                // logActividad($pdo, 'ERROR_MOVIMIENTO', $eMov->getMessage());
            }

            if (!empty($idMov)) {
                header('Location: equipo_ver.php?id=' . $equipoId . '&mov=last');
                exit;
            }

            // 🔴 Si no hay movimiento (por ejemplo se crea en Almacén), volvemos al index
            header('Location: index.php?msg=ok');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errores[] = "Error al guardar el equipo: " . $e->getMessage();
        }

        
        

    }
 
}
   // SOLO si NO hubo redirección, cargamos el HTML
require_once __DIR__ . '/includes/header.php';
?>

<h1 class="h3 mb-3">Nuevo equipo</h1>

<?php if ($errores): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errores as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php
// Carpeta FÍSICA donde guarda las imágenes de equipos
$uploadDirFs = __DIR__ . '/uploads/equipos/';

$imagenes_existentes = [];
if (is_dir($uploadDirFs)) {
    $imagenes_existentes = glob(
        $uploadDirFs . '*.{jpg,jpeg,png,gif,webp,JPG,JPEG,PNG,GIF,WEBP}',
        GLOB_BRACE
    );
}

if (!is_array($imagenes_existentes)) {
    $imagenes_existentes = [];
}

// Ordenar alfabéticamente por nombre de archivo SIN el timestamp inicial
usort($imagenes_existentes, function ($a, $b) {
    $na = preg_replace('/^\d+_/', '', basename($a));
    $nb = preg_replace('/^\d+_/', '', basename($b));
    return strcasecmp($na, $nb);
});

?>


<form method="post" enctype="multipart/form-data">
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3 shadow-sm">
                <div class="card-header py-2">
                    <strong>Identificación</strong>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Etiqueta</label>
                            <input type="text" name="etiqueta" class="form-control campo-etiqueta" value="<?= htmlspecialchars($_POST['etiqueta'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tipo de equipo</label>
                            <select name="tipo" class="form-control campo-destacado" required>
                                <option value="">-- Selecciona Tipo --</option>
                                <?php foreach ($tipos as $t): ?>
                                    <?php
                                        $nnombreTipos = $t['nombre'];
                                        $valorPost  = $_POST['tipo'] ?? '';
                                        $selected   = (strtoupper($valorPost) === strtoupper($nnombreTipos)) ? 'selected' : '';
                                    ?>
                                    <option value="<?= htmlspecialchars(strtoupper($nnombreTipos)) ?>" <?= $selected ?>>
                                        <?= htmlspecialchars($nnombreTipos) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Número de serie</label>
                            <input type="text" name="numero_serie" class="form-control campo-destacado" value="<?= htmlspecialchars($_POST['numero_serie'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Marca</label>
                            <input type="text" name="marca" class="form-control campo-destacado" value="<?= htmlspecialchars($_POST['marca'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Modelo</label>
                            <input type="text" name="modelo" class="form-control campo-destacado" value="<?= htmlspecialchars($_POST['modelo'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Servicio</label>
                            <select name="hostname" class="form-select campo-destacado ">
                                <option value="">-- Selecciona Servicio --</option>
                                <?php foreach ($servicios as $s): ?>
                                    <?php
                                        $nombreServicio = $s['nombre'];
                                        $valorPost  = $_POST['hostname'] ?? '';
                                        $selected   = (strtoupper($valorPost) === strtoupper($nombreServicio)) ? 'selected' : '';
                                    ?>
                                    <option value="<?= htmlspecialchars(strtoupper($nombreServicio)) ?>" <?= $selected ?>>
                                        <?= htmlspecialchars($nombreServicio) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12" id="bloque-monitores" style="display:none;">
                            <label class="form-label">Monitores libres para asociar</label>
                            <select name="monitores[]" class="form-select" multiple size="4">
                                <?php
                                $postMonitores = isset($_POST['monitores']) && is_array($_POST['monitores'])
                                    ? array_map('intval', $_POST['monitores'])
                                    : [];
                                foreach ($monitores as $m):
                                    $idMon = (int)$m['id'];
                                    $selected = in_array($idMon, $postMonitores) ? 'selected' : '';
                                ?>
                                    <option class="campo-destacado" value="<?= $idMon ?>" <?= $selected ?>>
                                        <?= htmlspecialchars(trim(
                                            ($m['marca'] ?? '') . ' ' .
                                            ($m['modelo'] ?? '') .
                                            ( $m['numero_serie'] ? ' [SN: '.$m['numero_serie'].']' : '' )
                                        )) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">
                                Mantén Ctrl (o Cmd en Mac) para seleccionar varios monitores.
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3 shadow-sm">
                <div class="card-header py-2">
                    <strong>Asignación y ubicación</strong>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Usuario asignado</label>
                            <input type="text" name="usuario_asignado" class="form-control campo-destacado" value="<?= htmlspecialchars($_POST['usuario_asignado'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label bi-geo-alt-fill">Ubicación</label>
                            <select name="ubicacion" class="form-select campo-destacado">
                                <option value="">-- Selecciona ubicación --</option>
                                <?php foreach ($ubicaciones as $u): ?>
                                    <?php
                                        $nombreUbic = $u['nombre'];
                                        $valorPost  = $_POST['ubicacion'] ?? '';
                                        $selected   = (strtoupper($valorPost) === strtoupper($nombreUbic)) ? 'selected' : '';
                                    ?>
                                    <option value="<?= htmlspecialchars(strtoupper($nombreUbic)) ?>" <?= $selected ?>>
                                        <?= htmlspecialchars($nombreUbic) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label bi-diagram-3">Departamento</label>
                            <select name="departamento" class="form-select campo-destacado">
                                <option value="">-- Selecciona departamento --</option>
                                <?php foreach ($departamentos as $d): ?>
                                    <?php
                                        $nombreDep  = $d['nombre'];
                                        $valorPost  = $_POST['departamento'] ?? '';
                                        $selected   = (strtoupper($valorPost) === strtoupper($nombreDep)) ? 'selected' : '';
                                    ?>
                                    <option value="<?= htmlspecialchars(strtoupper($nombreDep)) ?>" <?= $selected ?>>
                                        <?= htmlspecialchars($nombreDep) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>   
                        </div>
                        <div class="col-md-6">
                            <label class="form-label bi-grid-3x3-gap">Sección</label>
                            <select name="seccion_id" class="form-control campo-destacado" required>
                                <option value="">-- Selecciona sección --</option>
                                <?php
                                $seccionPost = $_POST['seccion_id'] ?? '';
                                foreach ($secciones as $sec):
                                    $selected = ($seccionPost !== '' && (int)$seccionPost === (int)$sec['id']) ? 'selected' : '';
                                ?>
                                    <option value="<?= $sec['id'] ?>" <?= $selected ?>>
                                        <?= htmlspecialchars($sec['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fecha Alta</label>
                            <input type="date" name="fecha_compra" class="form-control campo-destacado" value="<?= htmlspecialchars($_POST['fecha_compra'] ?? '') ?>">
                        </div>
                        <div class="col-md-6" id="bloque-sim-pti" style="display:none;">
                            <label class="form-label"><b>Asignar SIM (solo PTI, opcional)</b></label>
                            <select name="sim_id_pti" class="form-select">
                                <option value="">-- Sin SIM --</option>
                                <?php foreach ($simsDisponibles as $sim): ?>
                                    <?php
                                        $selected = (isset($_POST['sim_id_pti']) && (int)$_POST['sim_id_pti'] === (int)$sim['id']) ? 'selected' : '';
                                    ?>
                                    <?php
                                        // La clase en <option> no siempre aplica en todos los navegadores; añadimos color inline si tiene etiqueta
                                        $styleEtiqueta = $sim['etiqueta'] ? 'style="color:#0d6efd;font-weight:600;"' : '';
                                    ?>
                                    <option value="<?= (int)$sim['id'] ?>" <?= $selected ?> class="<?= $sim['etiqueta'] ? 'etiqueta-ok' : '' ?>" <?= $styleEtiqueta ?>>
                                        <?= $sim['etiqueta'] ? '[' . htmlspecialchars($sim['etiqueta']) . '] ' : '' ?><?= htmlspecialchars($sim['numero']) ?> · <?= htmlspecialchars($sim['operador']) ?> (ICCID: <?= htmlspecialchars($sim['iccid']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Visible solo para equipos de tipo PTI. No es obligatorio.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3 shadow-sm">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <strong>Imagen</strong>
                    <button type="button" class="btn btn-link btn-sm text-decoration-none p-0" id="limpiarImagen">Quitar selección</button>
                </div>
                <div class="card-body">
                    <input type="hidden" name="imagen_existente" id="imagen_existente" value="<?= htmlspecialchars($_POST['imagen_existente'] ?? '') ?>">
                    <div class="thumb-grid-wrapper">
                        <div class="row row-cols-2 row-cols-md-3 g-2 thumb-grid mb-3">
                        <?php
                        $imagenPost = $_POST['imagen_existente'] ?? '';
                        if (!empty($imagenes_existentes)):
                            foreach ($imagenes_existentes as $rutaFs):
                                $file  = basename($rutaFs);
                                $label = preg_replace('/^\d+_/', '', $file);
                                $isSel = ($imagenPost !== '' && $imagenPost === $file);
                        ?>
                            <div class="col">
                                <button type="button"
                                        class="btn btn-light w-100 h-100 seleccionar-imagen <?= $isSel ? 'active-selection' : '' ?>"
                                        data-file="<?= htmlspecialchars($file) ?>"
                                        data-label="<?= htmlspecialchars($label) ?>">
                                    <img src="uploads/equipos/<?= htmlspecialchars($file) ?>" class="img-fluid" alt="<?= htmlspecialchars($label) ?>">
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
                    <label for="imagen" class="form-label mb-1"><strong>Subir imagen nueva</strong> (si no hay ninguna disponible)</label>
                    <input type="file" class="form-control campo-destacado" id="imagen_nueva_input" name="imagen" accept="image/*">
                </div>
            </div>

            <div class="card mb-3 shadow-sm">
                <div class="card-header py-2">
                    <strong>Notas</strong>
                </div>
                <div class="card-body">
                    <textarea name="notas" class="form-control campo-destacado" rows="3"><?= htmlspecialchars($_POST['notas'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="card mb-3 shadow-sm">
                <div class="card-header py-2">
                    <strong>Datos de red (opcional)</strong>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Red</label>
                            <select name="red_id" class="form-select campo-destacado" id="redSelect">
                                <option value="">-- Sin red --</option>
                                <?php foreach ($redes as $r): ?>
                                    <option value="<?= $r['id'] ?>" <?= (($_POST['red_id'] ?? '') == $r['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($r['nombre']) ?> (<?= htmlspecialchars($r['direccion_red']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">IP principal</label>
                            <select name="ip" id="ipSelect" class="form-select campo-destacado">
                                <option value="">Selecciona una red primero</option>
                                <?php
                                $ipSelected = $_POST['ip'] ?? '';
                                if ($ipSelected !== ''): ?>
                                    <option value="<?= htmlspecialchars($ipSelected) ?>" selected>
                                        <?= htmlspecialchars($ipSelected) ?>
                                    </option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">MAC</label>
                            <input type="text" name="mac" class="form-control campo-destacado" placeholder="AA:BB:CC:DD:EE:FF" value="<?= htmlspecialchars($_POST['mac'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm sticky-summary mb-3">
                <div class="card-header py-2">
                    <strong>Estado y seguimiento</strong>
                </div>
                <div class="card-body">
                    <?php
                    $estados = ['Activo', 'Almacén', 'Averiado', 'Baja', 'Prestado','Privado'];
                    $estadoSel = $_POST['estado'] ?? 'Activo';
                    $colores = [
                        'Activo'   => '#198754',
                        'Almacén'  => '#0d6efd',
                        'Averiado' => '#ffc107',
                        'Baja'     => '#dc3545',
                        'Prestado' => '#6c757d',
                        'Privado'  => '#6f42c1',
                    ];

                    foreach ($estados as $est):
                        $idRadio = "estado_" . strtolower(str_replace(' ', '_', $est));
                        $color   = $colores[$est] ?? '#fff';
                    ?>
                        <div class="form-check">
                            <input 
                                class="form-check-input"
                                type="radio"
                                name="estado"
                                id="<?= $idRadio ?>"
                                value="<?= $est ?>"
                                <?= ($estadoSel === $est) ? 'checked' : '' ?>
                            >
                            <label class="form-check-label" for="<?= $idRadio ?>" style="color: <?= $color ?>;">
                                <?= $est ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                    <div class="alert alert-light border mt-3 mb-0 py-2 small">
                        Activo o Prestado requieren <strong>usuario asignado</strong>.
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header py-2">
                    <strong>Previsualización</strong>
                </div>
                <div class="card-body text-center">
                    <img id="preview_img_nuevo" class="img-fluid rounded" style="display:none;max-height:220px;border:1px solid #e5e7eb;">
                    <div class="text-muted small mt-2">Se mostrará la imagen seleccionada o la subida.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="form-actions-fixed mt-3">
        <div class="container d-flex justify-content-end gap-2">
            <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-success">Guardar equipo</button>
        </div>
    </div>
</form>

<!-- JavaScript para mostrar/ocultar bloque de monitores -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tipoSelect       = document.querySelector('select[name="tipo"]');
    const bloqueMonitores  = document.getElementById('bloque-monitores');
    const bloqueSim        = document.getElementById('bloque-sim-pti');

    function actualizarBloqueMonitores() {
        if (!tipoSelect) return;
        const valor = (tipoSelect.value || '').toUpperCase();
        const esPortatil = valor.includes('PORTATIL') || valor.includes('PORTÁTIL') || valor === 'PTI';
        const esPTI = valor.includes('PTI');
        if (valor === 'PC' || esPortatil) {
            bloqueMonitores.style.display = 'block';
        } else {
            bloqueMonitores.style.display = 'none';
        }
        if (bloqueSim) {
            bloqueSim.style.display = esPTI ? 'block' : 'none';
            if (!esPTI) {
                const selectSim = bloqueSim.querySelector('select[name="sim_id_pti"]');
                if (selectSim) selectSim.value = '';
            }
        }
    }

    if (tipoSelect) {
        tipoSelect.addEventListener('change', actualizarBloqueMonitores);
        actualizarBloqueMonitores();
    }
});
</script>
<!-- JavaScript para cargar IPs libres según red seleccionada -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const redSelect = document.getElementById('redSelect');
    const ipSelect  = document.getElementById('ipSelect');
    const currentIp = <?= json_encode($_POST['ip'] ?? '') ?>;
    const imagenInput = document.getElementById('imagen_nueva_input');
    const imagenHidden = document.getElementById('imagen_existente');
    const preview = document.getElementById('preview_img_nuevo');
    const limpiarBtn = document.getElementById('limpiarImagen');
    const imagenButtons = document.querySelectorAll('.seleccionar-imagen');

    function setPreview(src) {
        if (preview) {
            preview.src = src || '';
            preview.style.display = src ? 'block' : 'none';
        }
    }

    async function cargarIpsLibres(redId) {
        ipSelect.innerHTML = '<option value=\"\">Cargando IPs...</option>';

        if (!redId) {
            ipSelect.innerHTML = '<option value=\"\">Selecciona una red primero</option>';
            return;
        }

        try {
            const resp = await fetch('get_ips_libres.php?red_id=' + encodeURIComponent(redId));
            const data = await resp.json();

            ipSelect.innerHTML = '<option value=\"\">-- Selecciona IP --</option>';

            if (!Array.isArray(data) || data.length === 0) {
                const opt = document.createElement('option');
                opt.value = '';
                opt.textContent = 'No hay IPs libres en esta red';
                ipSelect.appendChild(opt);
                return;
            }

            data.forEach(ip => {
                const opt = document.createElement('option');
                opt.value = ip;
                opt.textContent = ip;
                if (ip === currentIp) {
                    opt.selected = true;
                }
                ipSelect.appendChild(opt);
            });
        } catch (e) {
            console.error(e);
            ipSelect.innerHTML = '<option value=\"\">Error cargando IPs</option>';
        }
    }

    if (redSelect) {
        redSelect.addEventListener('change', function () {
            cargarIpsLibres(this.value);
        });

        if (redSelect.value) {
            cargarIpsLibres(redSelect.value);
        }
    }

    if (imagenButtons.length) {
        imagenButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                imagenButtons.forEach(b => b.classList.remove('active-selection'));
                btn.classList.add('active-selection');
                const file = btn.dataset.file || '';
                imagenHidden.value = file;
                if (file) {
                    setPreview('uploads/equipos/' + file);
                }
                if (imagenInput) {
                    imagenInput.value = '';
                }
            });
        });
    }

    if (imagenInput) {
        imagenInput.addEventListener('change', (e) => {
            if (e.target.files && e.target.files[0]) {
                imagenButtons.forEach(b => b.classList.remove('active-selection'));
                imagenHidden.value = '';
                const reader = new FileReader();
                reader.onload = function (ev) {
                    setPreview(ev.target.result);
                };
                reader.readAsDataURL(e.target.files[0]);
            }
        });
    }

    if (limpiarBtn) {
        limpiarBtn.addEventListener('click', () => {
            imagenButtons.forEach(b => b.classList.remove('active-selection'));
            imagenHidden.value = '';
            if (imagenInput) {
                imagenInput.value = '';
            }
            setPreview('');
        });
    }

    // Mostrar preview si venimos de un POST con imagen seleccionada
    if (imagenHidden && imagenHidden.value) {
        setPreview('uploads/equipos/' + imagenHidden.value);
    }
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
