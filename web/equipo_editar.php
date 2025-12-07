<?php
// equipo_editar.php
require_once 'auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . "/includes/logger.php";
require_once __DIR__ . "/includes/movimientos_helper.php";


$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: index.php');
    exit;
}
$id_equipo = $id;
// Cargar equipo
$stmtEq = $pdo->prepare("SELECT * FROM equipos WHERE id = :id");
$stmtEq->execute([':id' => $id]);
$equipo = $stmtEq->fetch(PDO::FETCH_ASSOC);

// Guardamos el estado original para saber si sale / entra de almacén
$estadoOriginal   = $equipo['estado'] ?? null;
$usuarioOriginal  = $equipo['usuario_asignado'] ?? null;

// Cargar listas tipos equipo para los selects
$tiposStmt = $pdo->query("SELECT nombre FROM tipos_equipo ORDER BY nombre ASC");
$tipos = $tiposStmt->fetchAll(PDO::FETCH_ASSOC);

// Cargar listas de servicios para los selects
$serviciosStmt = $pdo->query("SELECT nombre FROM tipos_servicio ORDER BY nombre ASC");
$servicios = $serviciosStmt->fetchAll(PDO::FETCH_ASSOC);

// Cargar ubicaciones desde la tabla ubicaciones
$ubicacionesStmt = $pdo->query("SELECT nombre FROM ubicaciones ORDER BY nombre ASC");
$ubicaciones = $ubicacionesStmt->fetchAll(PDO::FETCH_ASSOC);

// Cargar secciones desde la tabla secciones
$seccionesStmt = $pdo->query("SELECT id, nombre FROM secciones ORDER BY nombre ASC");
$secciones = $seccionesStmt->fetchAll(PDO::FETCH_ASSOC);
// Cargar departamentos desde la tabla departamentos
$departamentosStmt = $pdo->query("SELECT nombre FROM departamentos ORDER BY nombre ASC");       
$departamentos = $departamentosStmt->fetchAll(PDO::FETCH_ASSOC);

// Imagen actual del equipo
$imagenActual = isset($equipo['imagen']) ? $equipo['imagen'] : '';


if (!$equipo) {
    echo '<div class="alert alert-danger">Equipo no encontrado.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}
// Cargar posible avería abierta del equipo
$stmtAv = $pdo->prepare("SELECT * FROM averias WHERE equipo_id = :id AND estado = 'ABIERTA' ORDER BY fecha_creacion DESC LIMIT 1");
$stmtAv->execute([':id' => $id]);
$averia = $stmtAv->fetch(PDO::FETCH_ASSOC);

// Cargar IP principal (si existe)
$stmtIp = $pdo->prepare("SELECT * FROM ips_equipos WHERE equipo_id = :id AND es_principal = 1 LIMIT 1");
$stmtIp->execute([':id' => $id]);
$ipRow = $stmtIp->fetch(PDO::FETCH_ASSOC);

// Cargar redes
$redesStmt = $pdo->query("SELECT id, nombre, direccion_red FROM redes ORDER BY id ASC");
$redes = $redesStmt->fetchAll(PDO::FETCH_ASSOC);

// Monitores que se pueden elegir (libres o ya asignados a este equipo)
$sqlMonitores = "
    SELECT e.id, e.marca, e.modelo, e.numero_serie, e.etiqueta,
           CASE WHEN pm_actual.id_pc IS NULL THEN 0 ELSE 1 END AS asignado_a_este
    FROM equipos e
    LEFT JOIN pc_monitores pm_actual
        ON pm_actual.id_monitor = e.id
       AND pm_actual.id_pc = :id_pc
    LEFT JOIN pc_monitores pm_otro
        ON pm_otro.id_monitor = e.id
       AND pm_otro.id_pc <> :id_pc
    WHERE e.tipo = 'MONITOR'
      AND pm_otro.id_monitor IS NULL
    ORDER BY asignado_a_este DESC, e.marca, e.modelo, e.numero_serie
";
$stmtMon = $pdo->prepare($sqlMonitores);
$stmtMon->execute([':id_pc' => $id_equipo]);
$monitores = $stmtMon->fetchAll(PDO::FETCH_ASSOC);

// Monitores ya asociados a este equipo
$stmtMonSel = $pdo->prepare("SELECT id_monitor FROM pc_monitores WHERE id_pc = :id_pc");
$stmtMonSel->execute([':id_pc' => $id_equipo]);
$monitoresSeleccionados = $stmtMonSel->fetchAll(PDO::FETCH_COLUMN);


$errores = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ID del equipo: primero miro POST, si no, cojo el de GET
    $id_equipo = isset($_POST['id_equipo'])
        ? (int)$_POST['id_equipo']
        : (int)($_GET['id'] ?? 0);
    $estado           = $_POST['estado'] ?? '';
    $usuario_asignado = trim($_POST['usuario_asignado'] ?? '');

    // ESTADOS que requieren usuario sí o sí
    $estadosRequierenUsuario = ['Activo', 'Prestado'];

    if (in_array($estado, $estadosRequierenUsuario, true) && $usuario_asignado === '') {
        // Mensaje de error muy simple. Puedes usar tu sistema de flasheo si ya tienes.
        $_SESSION['error'] = "Para poner el equipo en estado '{$estado}' debes indicar un usuario asignado.";
        header('Location: equipo_editar.php?id=' . $id_equipo);
        exit;
    }

    // Mantener la imagen actual por defecto
    $imagenRuta = $equipo['imagen'] ?? null;
    // Monitores seleccionados en el formulario
    $monitoresSeleccionadosPost = isset($_POST['monitores']) && is_array($_POST['monitores'])
    ? array_map('intval', $_POST['monitores'])
    : [];

    // Imagen actual desde la BD (puede ser null o cadena vacía)
    $imagenRuta = $equipo['imagen'] ?? null;
    // 1) Si ha elegido una imagen existente en el desplegable
    if (!empty($_POST['imagen_existente'])) {

        // Borrar la imagen anterior si existe en disco
        if (!empty($imagenRuta) && file_exists(__DIR__ . '/' . $imagenRuta)) {
            @unlink(__DIR__ . '/' . $imagenRuta);
        }

        $file = basename($_POST['imagen_existente']); // seguridad básica
        $imagenRuta = 'uploads/equipos/' . $file;

        // 2) Si no ha elegido existente, pero ha subido una nueva imagen
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

                // Borrar la imagen anterior si existe
                if (!empty($imagenRuta) && file_exists(__DIR__ . '/' . $imagenRuta)) {
                    @unlink(__DIR__ . '/' . $imagenRuta);
                }

                $imagenRuta = $rutaRelativa;
            } else {
                $errores[] = "No se pudo guardar la nueva imagen del equipo.";
            }
        }

        // 3) Si no hay ni imagen_existente ni archivo nuevo
        //    -> $imagenRuta se queda igual que venía de la BD


            // Campos principales
            $tipo             = strtoupper(trim($_POST['tipo'] ?? ''));
            $marca            = strtoupper(trim($_POST['marca'] ?? ''));
            $modelo           = strtoupper(trim($_POST['modelo'] ?? ''));
            $numero_serie     = strtoupper(trim($_POST['numero_serie'] ?? ''));
            $hostname         = strtoupper(trim($_POST['hostname'] ?? ''));
            $usuario_asignado = strtoupper(trim($_POST['usuario_asignado'] ?? ''));
            $departamento     = strtoupper(trim($_POST['departamento'] ?? ''));
            $ubicacion        = strtoupper(trim($_POST['ubicacion'] ?? ''));
            $fecha_compra     = $_POST['fecha_compra'] ?? null;
            $proveedor        = strtoupper(trim($_POST['proveedor'] ?? ''));
            $coste            = $_POST['coste'] ?? null;
            $estado           = trim($_POST['estado'] ?? 'En uso');
            $notas            = strtoupper(trim($_POST['notas'] ?? ''));
            $etiqueta        = strtoupper(trim($_POST['etiqueta'] ?? ''));
    

            // Red / IP
            $ip     = strtoupper(trim($_POST['ip'] ?? ''));
            $mac    = strtoupper(trim($_POST['mac'] ?? ''));
            $red_id = $_POST['red_id'] ?? '';

            // Avería
            $tipo_averia  = strtoupper(trim($_POST['tipo_averia'] ?? ''));
            $num_asunto   = strtoupper(trim($_POST['num_asunto'] ?? ''));
            $desc_averia  = trim($_POST['desc_averia'] ?? '');
            $empresa_ext  = strtoupper(trim($_POST['empresa_ext'] ?? ''));

            $estadoEsAveriado = (strcasecmp($estado, 'Averiado') === 0);

            if ($tipo === '') {
                $errores[] = "El campo Tipo es obligatorio.";
            }

            if ($estadoEsAveriado) {
                if ($tipo_averia === '') {
                    $errores[] = "El tipo de avería es obligatorio cuando el equipo está en estado AVERIADO.";
                }
                if ($num_asunto === '') {
                    $errores[] = "El número de asunto es obligatorio cuando el equipo está en estado AVERIADO.";
                }
            }

            // Si el estado pasa a BAJA por primera vez → guardar fecha_baja
            if ($estado === 'Baja') {

                $sql_check = "SELECT fecha_baja FROM equipos WHERE id = :id";
                $stmt_check = $pdo->prepare($sql_check);
                $stmt_check->execute([':id' => $id_equipo]);
                $check = $stmt_check->fetch(PDO::FETCH_ASSOC);

                // Solo insertar fecha_baja si está vacía (evita sobrescritura)
                if (empty($check['fecha_baja'])) {
                    $sql_baja = "UPDATE equipos SET fecha_baja = NOW() WHERE id = :id";
                    $stmt_baja = $pdo->prepare($sql_baja);
                    $stmt_baja->execute([':id' => $id_equipo]);
                }
            }

            // Verificar número de serie único si se ha proporcionado
            // Comprobar duplicado de número de serie (excluyendo el propio)
            if ($numero_serie !== '') {
                $stmtCheck = $pdo->prepare("
                    SELECT id 
                    FROM equipos 
                    WHERE numero_serie = :ns 
                    AND id <> :id
                    LIMIT 1
                ");
                $stmtCheck->execute([
                    ':ns' => $numero_serie,
                    ':id' => $id_equipo
                ]);

                if ($stmtCheck->fetch()) {
                    $errores[] = "El número de serie $numero_serie ya está asignado a otro equipo.";
                }
            }
            // Obtener seccion_id
            $seccion_id = isset($_POST['seccion_id']) && $_POST['seccion_id'] !== ''
            ? (int) $_POST['seccion_id']
            : null;

            // Si no hay errores, proceder a actualizar
            if (empty($errores)) {
                try {
                    $pdo->beginTransaction();

                    // Actualizar equipo
                    $sqlEquipo = "UPDATE equipos SET
                        tipo = :tipo,
                        marca = :marca,
                        modelo = :modelo,
                        numero_serie = :numero_serie,
                        hostname = :hostname,
                        usuario_asignado = :usuario_asignado,
                        departamento = :departamento,
                        ubicacion = :ubicacion,
                        fecha_compra = :fecha_compra,
                        proveedor = :proveedor,
                        coste = :coste,
                        estado = :estado,
                        notas = :notas,
                        imagen = :imagen,
                        seccion_id = :seccion_id,
                        etiqueta = :etiqueta
                    WHERE id = :id";

                    $stmtUp = $pdo->prepare($sqlEquipo);
                    $stmtUp->execute([
                        ':tipo'             => $tipo,
                        ':marca'            => $marca,
                        ':modelo'           => $modelo,
                        ':numero_serie'     => $numero_serie,
                        ':hostname'         => $hostname,
                        ':usuario_asignado' => $usuario_asignado,
                        ':departamento'     => $departamento,
                        ':ubicacion'        => $ubicacion,
                        ':fecha_compra'     => $fecha_compra ?: null,
                        ':proveedor'        => $proveedor,
                        ':coste'            => $coste !== '' ? $coste : null,
                        ':estado'           => $estado,
                        ':notas'            => $notas,
                        ':imagen'           => $imagenRuta,   
                        ':id'               => $id,
                        ':seccion_id'       => $seccion_id,
                        ':etiqueta'         => $etiqueta,
                    ]);

                    // Gestionar IP principal
                    if ($ip === '' || $red_id === '') {
                        if ($ipRow) {
                            $delIp = $pdo->prepare("DELETE FROM ips_equipos WHERE id = :id");
                            $delIp->execute([':id' => $ipRow['id']]);
                        }
                    } else {
                        if ($ipRow) {
                            $updIp = $pdo->prepare("UPDATE ips_equipos
                                SET ip = :ip, mac = :mac, red_id = :red_id
                                WHERE id = :id");
                            $updIp->execute([
                                ':ip'     => $ip,
                                ':mac'    => $mac,
                                ':red_id' => $red_id,
                                ':id'     => $ipRow['id'],
                            ]);
                        } else {
                            $insIp = $pdo->prepare("INSERT INTO ips_equipos
                                (equipo_id, red_id, ip, mac, es_principal, notas)
                                VALUES (:equipo_id, :red_id, :ip, :mac, 1, NULL)");
                            $insIp->execute([
                                ':equipo_id' => $id,
                                ':red_id'    => $red_id,
                                ':ip'        => $ip,
                                ':mac'       => $mac,
                            ]);
                        }
                    }
            
            // Gestionar monitores asociados
            if (in_array($tipo, ['PC','PORTATIL'])) {
                // Borrar relaciones actuales y crear nuevas
                $stmtDel = $pdo->prepare("DELETE FROM pc_monitores WHERE id_pc = :id_pc");
                $stmtDel->execute([':id_pc' => $id_equipo]);

                if (!empty($monitoresSeleccionadosPost)) {
                    $stmtIns = $pdo->prepare("INSERT INTO pc_monitores (id_pc, id_monitor)
                                            VALUES (:id_pc, :id_monitor)");
                    foreach ($monitoresSeleccionadosPost as $idMon) {
                        if ($idMon > 0) {
                            $stmtIns->execute([
                                ':id_pc'     => $id_equipo,
                                ':id_monitor'=> $idMon
                            ]);
                        }
                    }
                }
            } else {
                // Si deja de ser PC/PORTÁTIL, se eliminan sus monitores
                $stmtDel = $pdo->prepare("DELETE FROM pc_monitores WHERE id_pc = :id_pc");
                $stmtDel->execute([':id_pc' => $id_equipo]);
            }

            // Gestionar AVERÍA
            $averiaId = null;

            if ($estadoEsAveriado) {
                if ($averia) {
                    // Actualizar avería existente
                    $stmtAvUp = $pdo->prepare("
                        UPDATE averias
                        SET tipo_averia = :tipo_averia,
                            num_asunto  = :num_asunto,
                            descripcion = :descripcion,
                            empresa_ext = :empresa_ext
                        WHERE id = :id
                    ");
                    $stmtAvUp->execute([
                        ':tipo_averia' => $tipo_averia,
                        ':num_asunto'  => $num_asunto,
                        ':descripcion' => $desc_averia,
                        ':empresa_ext' => $empresa_ext,
                        ':id'          => $averia['id'],
                    ]);
                    $averiaId = (int)$averia['id'];
                } else {
                    // Crear nueva avería
                    $stmtAvIns = $pdo->prepare("
                        INSERT INTO averias (equipo_id, tipo_averia, num_asunto, descripcion, empresa_ext)
                        VALUES (:equipo_id, :tipo_averia, :num_asunto, :descripcion, :empresa_ext)
                    ");
                    $stmtAvIns->execute([
                        ':equipo_id'   => $id,
                        ':tipo_averia' => $tipo_averia,
                        ':num_asunto'  => $num_asunto,
                        ':descripcion' => $desc_averia,
                        ':empresa_ext' => $empresa_ext,
                    ]);
                    $averiaId = (int)$pdo->lastInsertId();
                }
            } else {
                // Opcional: cerrar avería si ya no está averiado
                /*
                if ($averia) {
                    $stmtCerrar = $pdo->prepare("UPDATE averias SET estado = 'CERRADA' WHERE id = :id");
                    $stmtCerrar->execute([':id' => $averia['id']]);
                }
                */
            }
                        logActividad($pdo, 'EDITAR_EQUIPO', 'Equipo editado: ID=' . $id);

            $pdo->commit();

            // 👇 Registrar movimiento (si procede: Almacén <-> Activo/Prestado)
            try {
                $idMov = registrarMovimientoEquipo(
                    $pdo,
                    $id_equipo,      // o $id según cómo lo llames
                    $estadoOriginal,
                    $usuarioOriginal,
                    $estado,
                    $usuario_asignado
                );
            } catch (Exception $eMov) {
                $idMov = null;
                // logActividad($pdo, 'ERROR_MOVIMIENTO', $eMov->getMessage());
            }

            // 🔁 Si se ha generado un movimiento, vamos a la ficha del equipo con aviso
            if (!empty($idMov)) {
                header('Location: equipo_ver.php?id=' . $id_equipo . '&mov=last');
                exit;
            }

            // 🔴 Si no hay movimiento, seguimos con tu lógica normal
            if ($averiaId) {
                header('Location: averia_parte.php?id=' . $averiaId);
            } else {
                header('Location: index.php?msg=ok');
            }
            exit;



        } catch (Exception $e) {
            $pdo->rollBack();
            $errores[] = "Error al actualizar el equipo: " . $e->getMessage();
        }
    }

            // Si hay errores, mantener lo que ha puesto el usuario
        if (!empty($errores)) {
            $equipo = array_merge($equipo, [
                'tipo'             => $tipo,
                'marca'            => $marca,
                'modelo'           => $modelo,
                'numero_serie'     => $numero_serie,
                'hostname'         => $hostname,
                'usuario_asignado' => $usuario_asignado,
                'departamento'     => $departamento,
                'ubicacion'        => $ubicacion,
                'fecha_compra'     => $fecha_compra,
                'proveedor'        => $proveedor,
                'coste'            => $coste,
                'estado'           => $estado,
                'notas'            => $notas,
                'seccion_id'       => $seccion_id,
                'etiqueta'         => $etiqueta,
            ]);

            $ipRow = [
                'ip'     => $ip,
                'mac'    => $mac,
                'red_id' => $red_id,
            ];

            $averia = [
                'tipo_averia' => $tipo_averia,
                'num_asunto'  => $num_asunto,
                'descripcion' => $desc_averia,
                'empresa_ext' => $empresa_ext,
            ];
        }

}


// Valores actuales para el formulario
$ip_val  = $ipRow['ip']  ?? '';
$mac_val = $ipRow['mac'] ?? '';
$red_sel = $ipRow['red_id'] ?? '';

require_once __DIR__ . '/includes/header.php'; 
?>


<h1 class="h3 mb-3">Editar equipo <?= htmlspecialchars($equipo['etiqueta'] ?? $equipo['id']) ?></h1>

<?php if ($errores): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errores as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
 

<form method="post" class="row g-3" enctype="multipart/form-data">

  <div class="col-md-3">
        <label class="form-label">Etiqueta</label>
        <input type="text" name="etiqueta" class="form-control campo-etiqueta" value="<?= htmlspecialchars($equipo['etiqueta'] ?? '') ?>">
    </div>

    <div class="col-md-3">
    <label class="form-label"><b>Tipo de equipo</b></label>
    <select name="tipo" class="form-control campo-destacado" required>
        <option value="">-- Selecciona tipo --</option>
        <?php foreach ($tipos as $t): ?>
            <?php
                // Nombre tal y como está en la tabla ubicaciones
                $nombreTipo = $t['nombre'];
                // Lo que hay ahora guardado en el equipo
                $tipoEquipo = $equipo['tipo'] ?? '';
                // Comparamos en mayúsculas porque tú guardas con strtoupper()
                $selected = (strtoupper($tipoEquipo) === strtoupper($nombreTipo)) ? 'selected' : '';
            ?>
            <option value="<?= htmlspecialchars(strtoupper($nombreTipo)) ?>" <?= $selected ?>>
                <?= htmlspecialchars($nombreTipo) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>


    <!-- <div class="col-md-8" id="bloque-monitores" style="display:none;">
    <label class="form-label">Monitores asociados</label>
    <select name="monitores[]" class="form-select campo-destacado" multiple size="5">
        <?php
        $seleccionActual = $_POST['monitores'] ?? $monitoresSeleccionados;
        if (!is_array($seleccionActual)) {
            $seleccionActual = [];
        }
        $seleccionActual = array_map('intval', $seleccionActual);

        foreach ($monitores as $m):
            $idMon    = (int)$m['id'];
            $selected = in_array($idMon, $seleccionActual) ? 'selected' : '';
        ?>
            <option value="<?= $idMon ?>" <?= $selected ?>>
                <?= htmlspecialchars(trim(
                    ($m['marca'] ?? '') . ' ' .
                    ($m['modelo'] ?? '') .
                    ( $m['numero_serie'] ? ' [SN: '.$m['numero_serie'].']' : '' )
                )) ?>
                <?= $m['asignado_a_este'] ? ' (actual)' : '' ?>
            </option>
        <?php endforeach; ?>
    </select>
</div> -->

<div class="col-md-8" id="bloque-monitores" style="display:none;">
    <label class="form-label">Monitores asociados</label>

    <select name="monitores[]" class="form-select campo-destacado" multiple size="5">
        <?php
        $seleccionActual = $_POST['monitores'] ?? $monitoresSeleccionados;
        if (!is_array($seleccionActual)) {
            $seleccionActual = [];
        }
        $seleccionActual = array_map('intval', $seleccionActual);

        foreach ($monitores as $m):
            $idMon    = (int)$m['id'];
            $selected = in_array($idMon, $seleccionActual) ? 'selected' : '';
        ?>
            <option value="<?= $idMon ?>" <?= $selected ?>
                    <?= $m['asignado_a_este'] ? ' data-actual="1"' : '' ?>>
                <?= htmlspecialchars(trim(
                    ($m['marca'] ?? '') . ' ' .
                    ($m['modelo'] ?? '') .
                    ( $m['numero_serie'] ? ' [SN: '.$m['numero_serie'].']' : '' )
                )) ?>
                <?= $m['asignado_a_este'] ? ' (actual)' : '' ?>
            </option>
        <?php endforeach; ?>
    </select>

    <!-- AQUI APARECEN LOS BOTONES -->
    <?php if (!empty($monitoresSeleccionados)): ?>
        <div class="mt-2">
            <label><b>Acciones sobre monitores asociados:</b></label>

            <?php foreach ($monitores as $m): ?>
                <?php if ($m['asignado_a_este']): ?>
                    <div class="d-flex align-items-center mb-1">
                        <span>
                            <?= htmlspecialchars(trim(
                                ($m['marca'] ?? '') . ' ' .
                                ($m['modelo'] ?? '') .
                                ($m['numero_serie'] ? ' [SN: '.$m['numero_serie'].']' : '')
                            )) ?>
                        </span>

                        <a href="monitor_desvincular.php?id=<?= $m['id'] ?>&pc=<?= $equipo['id'] ?>"
                           class="btn btn-warning btn-sm ms-3"
                           onclick="return confirm('¿Desvincular este monitor y enviarlo a Almacén?');">
                            Desvincular y pasar a Almacén
                        </a>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>



    <div class="col-md-2">
        <label class="form-label">Marca</label>
        <input type="text" name="marca" class="form-control campo-destacado" value="<?= htmlspecialchars($equipo['marca'] ?? '') ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label">Modelo</label>
        <input type="text" name="modelo" class="form-control campo-destacado" value="<?= htmlspecialchars($equipo['modelo'] ?? '') ?>">
    </div>

    <div class="col-md-3">
        <label class="form-label">Número de serie</label>
        <input type="text" name="numero_serie" class="form-control campo-destacado" value="<?= htmlspecialchars($equipo['numero_serie'] ?? '') ?>">
    </div>
    <div class="col-md-3">
    <label class="form-label">Servicio</label>
        <select name="hostname" class="form-select campo-destacado">
            <option value="">-- Selecciona Servicio --</option>
            <?php foreach ($servicios as $s): ?>
                <?php
                    // Nombre tal y como está en la tabla ubicaciones
                    $nombreServicio = $s['nombre'];
                    // Lo que hay ahora guardado en el equipo
                    $tipoEquipo = $equipo['hostname'] ?? '';
                    // Comparamos en mayúsculas porque tú guardas con strtoupper()
                    $selected = (strtoupper($tipoEquipo) === strtoupper($nombreServicio)) ? 'selected' : '';
                ?>
                <option value="<?= htmlspecialchars(strtoupper($nombreServicio)) ?>" <?= $selected ?>>
                    <?= htmlspecialchars($nombreServicio) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-3">
        <label class="form-label">Usuario asignado</label>
        <input type="text" name="usuario_asignado" class="form-control campo-destacado" value="<?= htmlspecialchars($equipo['usuario_asignado'] ?? '') ?>">
    </div>
 <div class="col-md-2">
    <label class="form-label bi-geo-alt-fill"> Ubicación</label>
    <select name="ubicacion" class="form-select campo-destacado">
        <option value="">-- Selecciona ubicación --</option>
        <?php foreach ($ubicaciones as $u): ?>
            <?php
                $nombreUbic = $u['nombre'];
                $ubicEquipo = $equipo['ubicacion'] ?? '';
                $selected   = (strtoupper($ubicEquipo) === strtoupper($nombreUbic)) ? 'selected' : '';
            ?>
            <option value="<?= htmlspecialchars(strtoupper($nombreUbic)) ?>" <?= $selected ?>>
                <?= htmlspecialchars($nombreUbic) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<div class="col-md-3">
    <label class="form-label bi-diagram-3"> Departamento</label>
    <select name="departamento" class="form-select campo-destacado">
        <option value="">-- Selecciona departamento --</option>
        <?php foreach ($departamentos as $d): ?>
            <?php
                $nombreDep = $d['nombre'];
                $depEquipo = $equipo['departamento'] ?? '';
                $selected  = (strtoupper($depEquipo) === strtoupper($nombreDep)) ? 'selected' : '';
            ?>
            <option value="<?= htmlspecialchars(strtoupper($nombreDep)) ?>" <?= $selected ?>>
                <?= htmlspecialchars($nombreDep) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<div class="col-md-3">
    <label class="form-label bi-grid-3x3-gap"> Sección</label>
    <select name="seccion_id" class="form-select campo-destacado">
        <option value="">-- Selecciona sección --</option>
        <?php foreach ($secciones as $sec): ?>
            <?php
                $idSec         = (int)$sec['id'];
                $nombreSec     = $sec['nombre'];
                $seccionEquipo = isset($equipo['seccion_id']) ? (int)$equipo['seccion_id'] : null;
                $selected      = ($seccionEquipo === $idSec) ? 'selected' : '';
            ?>
            <option value="<?= htmlspecialchars($idSec) ?>" <?= $selected ?>>
                <?= htmlspecialchars($nombreSec) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>



    <div class="col-md-4">
        <label class="form-label">Fecha Alta</label>
        <input type="date" name="fecha_compra" class="form-control campo-destacado" value="<?= htmlspecialchars($equipo['fecha_compra'] ?? '') ?>">
    </div>

    <!-- <div class="col-md-4">
        <label class="form-label">Proveedor</label>
        <input type="text" name="proveedor" class="form-control campo-destacado" value="<?= htmlspecialchars($equipo['proveedor'] ?? '') ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label">Coste (€)</label>
        <input type="number" step="0.01" name="coste" class="form-control campo-destacado" value="<?= htmlspecialchars($equipo['coste'] ?? '') ?>">
    </div> -->
    <div class="col-md-4">
    <label class="form-label d-block campo-destacado" style="color: #414141; font-weight: bold;">
        Estado
    </label>

    <!-- ID oculto del equipo -->
    <input type="hidden" name="id_equipo" value="<?= (int)$equipo['id'] ?>">

    <?php
    // Lista de estados
    $estados = ['Activo', 'Almacén', 'Averiado', 'Baja', 'Prestado'];

    // Estado actual del equipo
    $estadoSel = $equipo['estado'] ?? 'Activo';

    // Colores por estado
    $colores = [
        'Activo'   => '#28a745', 
        'Almacén'  => '#0d6efd', 
        'Averiado' => '#ffc107', 
        'Baja'     => '#dc3545', 
        'Prestado' => '#6c757d', 
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
</div>

<?php
$imagenes_existentes = glob(
    __DIR__ . '/uploads/equipos/*.{jpg,jpeg,png,gif,webp,JPG,JPEG,PNG,GIF,WEBP}',
    GLOB_BRACE
);

if (!is_array($imagenes_existentes)) {
    $imagenes_existentes = [];
}

// Ordenar alfabéticamente por nombre SIN timestamp
usort($imagenes_existentes, function ($a, $b) {
    $na = preg_replace('/^\d+_/', '', basename($a));
    $nb = preg_replace('/^\d+_/', '', basename($b));
    return strcasecmp($na, $nb);
});
?>


    <!-- <div class="mb-3">
        <label for="imagen" class="form-label">Imagen del equipo</label>
            <?php if (!empty($equipo['imagen'])): ?>
                <div class="mb-3">
                    <label class="form-label">Imagen actual:</label><br>
                    <img src="<?= htmlspecialchars($equipo['imagen']) ?>" 
                        alt="Imagen del equipo"
                        style="max-width:180px; height:auto; border:1px solid #ccc; padding:4px;">
                </div>
            <?php endif; ?>

        <input type="file" class="form-control" name="imagen" accept="image/*">
    </div> -->

    <!-- Imagen actual (oculta para el POST) -->
<input type="hidden" name="imagen_actual" 
       value="<?= htmlspecialchars($equipo['imagen'] ?? '') ?>">
<div class="mb-3">
    <label class="form-label"><b>Banco de imágenes en Base de Datos</b></label>

    <div class="d-flex align-items-center gap-3">

        <select name="imagen_existente" id="imagen_existente" class="form-control campo-destacado" style="max-width: 350px;">
            <option value="">-- Mantener / elegir otra imagen --</option>

            <?php foreach ($imagenes_existentes as $img): ?>
                <?php 
                    $file  = basename($img);
                    $label = preg_replace('/^\d+_/', '', $file);
                ?>
                <option value="<?= htmlspecialchars($file) ?>">
                    <?= htmlspecialchars($label) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <!-- Miniatura al cargar -->
        <!-- Miniatura de la imagen elegida -->
<img
    id="preview_img_editar"
    <?php if (!empty($equipo['imagen'])): ?>
        src="<?= htmlspecialchars($equipo['imagen']) ?>"
        style="max-width:120px;border:1px solid #ccc;margin-left:12px;"
    <?php else: ?>
        style="display:none;max-width:120px;border:1px solid #ccc;margin-left:12px;"
    <?php endif; ?>
>


    </div>

    <small class="form-text">
        Si eliges una imagen, se usará esa.
        Si subes una nueva, tendrá prioridad.
        Si no haces nada, se mantiene la actual.
    </small>
</div>

<div class="mb-3">
    <label class="form-label"><b>Subir imagen nueva</b></label>
    <input type="file" name="imagen_nueva" id="imagen_nueva" accept="image/*" class="form-control campo-destacado">

    <img id="preview_img_nueva"
         style="display:none;max-width:120px;border:1px solid #ccc;margin-top:8px;">

    <small class="form-text">
        Si seleccionas una imagen nueva, tendrá prioridad sobre la imagen existente.
    </small>
</div>


<script>
document.addEventListener('DOMContentLoaded', () => {
    const select  = document.getElementById('imagen_existente');
    const preview = document.getElementById('preview_img_editar');

    if (select) {
        select.addEventListener('change', function () {
            if (this.value) {
                preview.src = 'uploads/equipos/' + this.value;
                preview.style.display = 'block';
            } else {
                preview.src = '';
                preview.style.display = 'none';
            }
        });
    }
});
</script>



    <div class="col-12">
        <label class="form-label">Notas</label>
        <textarea name="notas" class="form-control campo-destacado" rows="3"><?= htmlspecialchars($equipo['notas'] ?? '') ?></textarea>
    </div>



    
    <hr class="mt-4">

    <!-- Sección de averías -->
     <?php
$tipo_averia_val = $_POST['tipo_averia'] ?? ($averia['tipo_averia'] ?? '');
$num_asunto_val  = $_POST['num_asunto']  ?? ($averia['num_asunto']  ?? '');
$desc_averia_val = $_POST['desc_averia'] ?? ($averia['descripcion'] ?? '');
$empresa_ext_val = $_POST['empresa_ext'] ?? ($averia['empresa_ext'] ?? '');
$mostrarAveria   = (strcasecmp($equipo['estado'] ?? '', 'Averiado') === 0);
?>

<hr class="mt-4">
<h2 class="h5">Datos de avería</h2>
<p class="form-text mb-3">
    Rellenar estos campos si el estado del equipo pasa a  <strong>Averiado</strong>.
</p>

<div id="bloqueAveria" style="<?= $mostrarAveria ? '' : 'display:none;' ?>">
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Tipo de avería *</label>
            <input type="text" name="tipo_averia" class="form-control campo-averia"
                   value="<?= htmlspecialchars($tipo_averia_val) ?>"
                   placeholder="Ej: No enciende, Pantalla rota, Disco defectuoso...">
        </div>
        <div class="col-md-4">
            <label class="form-label">Nº de asunto (empresa externa) *</label>
            <input type="text" name="num_asunto" class="form-control campo-averia"
                   value="<?= htmlspecialchars($num_asunto_val) ?>"
                   placeholder="Referencia del ticket de la empresa externa">
                           <p class="form-text mb-3">
    Este número lo asigna RAU una vez el GATI envia incidencia si no se repara por el propio GATI.
</p>
        </div>

        <div class="col-md-4">
            <label class="form-label">Reparación realizada por: </label>
            <input type="text" name="empresa_ext" class="form-control campo-averia"
                   value="<?= htmlspecialchars($empresa_ext_val) ?>"
                   placeholder="Nombre de la empresa de soporte o 'Interna'">
                               <p class="form-text mb-3">
    La empresa externa notifcada por RAU con el número de asunto o indicar 'Interna'.
</p>
        </div>
        <div class="col-12">
            <label class="form-label">Descripción de la avería</label>
            <textarea name="desc_averia" class="form-control campo-averia" rows="3"
                      placeholder="Describe brevemente el problema, pruebas realizadas, etc."><?= htmlspecialchars($desc_averia_val) ?></textarea>
        </div>
    </div>
</div>


    <h2 class="h5">Datos de red (opcional)</h2>

<div class="col-md-4">
    <label class="form-label">Red</label>
    <select name="red_id" class="form-select campo-destacado" id="redSelect">
        <option value="">-- Sin red --</option>
        <?php foreach ($redes as $r): ?>
            <option value="<?= $r['id'] ?>" <?= ($red_sel == $r['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($r['nombre']) ?> (<?= htmlspecialchars($r['direccion_red']) ?>)
            </option>
        <?php endforeach; ?>
    </select>
</div>

<div class="col-md-4">
    <label class="form-label">IP principal</label>
    <select name="ip" id="ipSelect" class="form-select campo-destacado">
        <?php if ($ip_val): ?>
            <option value="<?= htmlspecialchars($ip_val) ?>" selected>
                <?= htmlspecialchars($ip_val) ?>
            </option>
        <?php else: ?>
            <option value="">Selecciona una red primero</option>
        <?php endif; ?>
    </select>
</div>

<div class="col-md-4">
    <label class="form-label">MAC</label>
    <input type="text" name="mac" class="form-control campo-destacado" placeholder="AA:BB:CC:DD:EE:FF"value="<?= htmlspecialchars($mac_val) ?>">
</div>


    <div class="col-12 mt-4">
        <button type="submit" class="btn btn-success">Guardar cambios</button>
        <a href="index.php" class="btn btn-secondary">Cancelar</a>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const redSelect = document.getElementById('redSelect');
    const ipSelect  = document.getElementById('ipSelect');
    const currentIp = <?= json_encode($ip_val) ?>;
    const equipoId  = <?= (int)$equipo['id'] ?>;

    async function cargarIpsLibres(redId) {
        if (!redId) {
            ipSelect.innerHTML = '<option value="">Selecciona una red primero</option>';
            return;
        }

        ipSelect.innerHTML = '<option value="">Cargando IPs...</option>';

        try {
            const url = 'get_ips_libres.php?red_id=' + encodeURIComponent(redId)
                      + '&equipo_id=' + encodeURIComponent(equipoId);

            const resp = await fetch(url);
            const data = await resp.json();

            ipSelect.innerHTML = '<option value="">-- Selecciona IP --</option>';

            if (!Array.isArray(data) || data.length === 0) {
                const opt = document.createElement('option');
                opt.value = currentIp || '';
                opt.textContent = currentIp ? currentIp + ' (actual, única disponible)' : 'No hay IPs libres en esta red';
                opt.selected = !!currentIp;
                ipSelect.appendChild(opt);
                return;
            }

            // Añadir todas las IPs libres
            data.forEach(ip => {
                const opt = document.createElement('option');
                opt.value = ip;
                opt.textContent = ip;
                // si coincide con la IP actual, la dejamos seleccionada
                if (ip === currentIp) {
                    opt.selected = true;
                }
                ipSelect.appendChild(opt);
            });

            // Si la IP actual no está en la lista (por cualquier motivo), la añadimos al final
            if (currentIp && !data.includes(currentIp)) {
                const opt = document.createElement('option');
                opt.value = currentIp;
                opt.textContent = currentIp + ' (actual)';
                opt.selected = true;
                ipSelect.appendChild(opt);
            }

        } catch (e) {
            console.error(e);
            ipSelect.innerHTML = '<option value="">Error cargando IPs</option>';
        }
    }

    if (redSelect) {
        redSelect.addEventListener('change', function () {
            cargarIpsLibres(this.value);
        });

        // Si ya hay red seleccionada al entrar (editar), cargamos IPs de esa red
        if (redSelect.value) {
            cargarIpsLibres(redSelect.value);
        }
    }
});
</script>

<script>
    // Mostrar/ocultar bloque de avería según estado seleccionado
document.addEventListener('DOMContentLoaded', function () {
    const radiosEstado  = document.querySelectorAll('input[name="estado"]');
    const bloqueAveria  = document.getElementById('bloqueAveria');

    if (radiosEstado.length > 0 && bloqueAveria) {

        function toggleAveria() {
            const seleccionado = document.querySelector('input[name="estado"]:checked');
            if (!seleccionado) {
                bloqueAveria.style.display = 'none';
                return;
            }

            const val = seleccionado.value.toLowerCase(); // 'Activo', 'Averiado', etc.
            if (val === 'averiado') {
                bloqueAveria.style.display = '';   // se muestra
            } else {
                bloqueAveria.style.display = 'none'; // se oculta
            }
        }

        // Escuchar cambios en todos los radios
        radiosEstado.forEach(radio => {
            radio.addEventListener('change', toggleAveria);
        });

        // Estado inicial al cargar la página
        toggleAveria();
    }
});
</script>


<!-- Mostrar/ocultar bloque de monitores según tipo de equipo -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tipoSelect       = document.querySelector('select[name="tipo"]');
    const bloqueMonitores  = document.getElementById('bloque-monitores');

    function actualizarBloqueMonitores() {
        if (!tipoSelect) return;
        const valor = (tipoSelect.value || '').toUpperCase();
        if (valor === 'PC' || valor === 'PORTATIL' || valor === 'PORTÁTIL') {
            bloqueMonitores.style.display = 'block';
        } else {
            bloqueMonitores.style.display = 'none';
        }
    }

    if (tipoSelect && bloqueMonitores) {
        tipoSelect.addEventListener('change', actualizarBloqueMonitores);
        actualizarBloqueMonitores();
    }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const select  = document.getElementById('imagen_existente');
    const preview = document.getElementById('preview_img_editar');
    const fileInput = document.getElementById('imagen_nueva');
    const previewNueva = document.getElementById('preview_img_nueva');

    if (select) {
        select.addEventListener('change', function () {
            if (this.value) {
                preview.src = 'uploads/equipos/' + this.value;
                preview.style.display = 'block';
            } else {
                // Si quitas la selección, podrías volver a la imagen actual
                // o esconder la miniatura; de momento la escondemos:
                preview.src = '';
                preview.style.display = 'none';
            }
        });
    }

    if (fileInput) {
        fileInput.addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = e => {
                    previewNueva.src = e.target.result;
                    previewNueva.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                previewNueva.src = '';
                previewNueva.style.display = 'none';
            }
        });
    }
});
</script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll('select[name="monitores[]"] option[data-actual="1"]')
        .forEach(function(opt) {
            opt.style.color = "#0d6efd";      // azul Bootstrap
            opt.style.fontWeight = "bold";
        });
});
</script>


<?php
require_once __DIR__ . '/includes/footer.php';
