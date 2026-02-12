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

// Tabla de relación SIM↔equipo (PTI) por si aún no existe
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

// Tabla de relación DOCK↔equipo PTI por si aún no existe
$pdo->exec("
    CREATE TABLE IF NOT EXISTS equipo_dock (
        id INT AUTO_INCREMENT PRIMARY KEY,
        equipo_id INT NOT NULL,
        dock_equipo_id INT NOT NULL,
        fecha_asignacion DATETIME DEFAULT CURRENT_TIMESTAMP,
        fecha_liberacion DATETIME DEFAULT NULL,
        observaciones TEXT,
        INDEX idx_equipo (equipo_id),
        INDEX idx_dock (dock_equipo_id),
        FOREIGN KEY (equipo_id) REFERENCES equipos(id),
        FOREIGN KEY (dock_equipo_id) REFERENCES equipos(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// SIM actual (si es PTI) y SIMs disponibles
$stmtSimActual = $pdo->prepare("
    SELECT es.id AS rel_id, s.*
    FROM equipo_sim es
    JOIN sims s ON s.id = es.sim_id
    WHERE es.equipo_id = :id
      AND es.fecha_liberacion IS NULL
    ORDER BY es.fecha_asignacion DESC
    LIMIT 1
");
$stmtSimActual->execute([':id' => $id_equipo]);
$simActual = $stmtSimActual->fetch(PDO::FETCH_ASSOC);

$simsDisponiblesStmt = $pdo->prepare("
    SELECT id, numero, operador, iccid, etiqueta
    FROM sims
    WHERE estado = 'Disponible' OR id = :simActual
    ORDER BY numero ASC
");
$simsDisponiblesStmt->execute([':simActual' => $simActual['id'] ?? 0]);
$simsDisponibles = $simsDisponiblesStmt->fetchAll(PDO::FETCH_ASSOC);

// DOCK actual (si es PTI) y DOCKs disponibles
$stmtDockActual = $pdo->prepare("
    SELECT ed.id AS rel_id, d.id, d.etiqueta, d.marca, d.modelo, d.numero_serie
    FROM equipo_dock ed
    JOIN equipos d ON d.id = ed.dock_equipo_id
    WHERE ed.equipo_id = :id
      AND ed.fecha_liberacion IS NULL
    ORDER BY ed.fecha_asignacion DESC
    LIMIT 1
");
$stmtDockActual->execute([':id' => $id_equipo]);
$dockActual = $stmtDockActual->fetch(PDO::FETCH_ASSOC);

$docksDisponiblesStmt = $pdo->prepare("
    SELECT d.id, d.etiqueta, d.marca, d.modelo, d.numero_serie
    FROM equipos d
    LEFT JOIN equipo_dock ed
      ON ed.dock_equipo_id = d.id
     AND ed.fecha_liberacion IS NULL
    WHERE UPPER(d.tipo) = 'DOCK'
      AND (ed.id IS NULL OR d.id = :dockActual)
    ORDER BY d.etiqueta ASC, d.numero_serie ASC, d.id ASC
");
$docksDisponiblesStmt->execute([':dockActual' => $dockActual['id'] ?? 0]);
$docksDisponibles = $docksDisponiblesStmt->fetchAll(PDO::FETCH_ASSOC);

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

    // Mantener la imagen actual por defecto y registrar su valor anterior
    $imagenRuta     = $equipo['imagen'] ?? null;
    $imagenAnterior = $imagenRuta;
    $imagenCambiada = false;
    // Monitores seleccionados en el formulario
    $monitoresSeleccionadosPost = isset($_POST['monitores']) && is_array($_POST['monitores'])
    ? array_map('intval', $_POST['monitores'])
    : [];
    // SIM seleccionada para PTI (opcional)
    $sim_id_pti_nueva = isset($_POST['sim_id_pti']) && $_POST['sim_id_pti'] !== '' ? (int)$_POST['sim_id_pti'] : null;
    // DOCK seleccionado para PTI (opcional)
    $dock_id_pti_nuevo = isset($_POST['dock_id_pti']) && $_POST['dock_id_pti'] !== '' ? (int)$_POST['dock_id_pti'] : null;

    // 1) Si ha elegido una imagen existente en el desplegable
    if (!empty($_POST['imagen_existente'])) {
        $file = basename($_POST['imagen_existente']); // seguridad básica
        $nuevaRuta = 'uploads/equipos/' . $file;
        if ($nuevaRuta !== $imagenRuta) {
            $imagenCambiada = true;
        }
        $imagenRuta = $nuevaRuta;

    // 2) Si no ha elegido existente, pero ha subido una nueva imagen
    } elseif (!empty($_FILES['imagen_nueva']['name'])) {

        $uploadDir = __DIR__ . '/uploads/equipos/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $nombreOriginal = basename($_FILES['imagen_nueva']['name']);
        $nombreLimpio   = preg_replace('/[^A-Za-z0-9_\.-]/', '_', $nombreOriginal);
        $nombreFinal    = time() . '_' . $nombreLimpio;

        $rutaRelativa = 'uploads/equipos/' . $nombreFinal;
        $rutaFisica   = $uploadDir . $nombreFinal;

        if (move_uploaded_file($_FILES['imagen_nueva']['tmp_name'], $rutaFisica)) {
            $imagenRuta     = $rutaRelativa;
            $imagenCambiada = true;
        } else {
            $errores[] = "No se pudo guardar la nueva imagen del equipo.";
        }
    }

    // 3) Si no hay ni imagen_existente ni archivo nuevo
    //    -> $imagenRuta se queda igual que venía de la BD
    // Si ha cambiado la imagen, eliminar la anterior solo si no la usa otro equipo
    if ($imagenCambiada && !empty($imagenAnterior) && $imagenAnterior !== $imagenRuta) {
        $stmtUsoImg = $pdo->prepare("
            SELECT COUNT(*) FROM equipos WHERE imagen = :img AND id <> :id
        ");
        $stmtUsoImg->execute([
            ':img' => $imagenAnterior,
            ':id'  => $id_equipo,
        ]);
        $estaCompartida = (int)$stmtUsoImg->fetchColumn() > 0;

        if (!$estaCompartida) {
            $rutaAnteriorFs = __DIR__ . '/' . $imagenAnterior;
            if (is_file($rutaAnteriorFs)) {
                @unlink($rutaAnteriorFs);
            }
        }
    }


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

            $tipoUpper        = strtoupper($tipo);
            $esPTI            = (strpos($tipoUpper, 'PTI') !== false);

            // Validación SIM para PTI
            if ($esPTI && $sim_id_pti_nueva !== null && (!$simActual || $sim_id_pti_nueva !== (int)$simActual['id'])) {
                $stmtCheckSim = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM equipo_sim 
                    WHERE sim_id = :sim AND fecha_liberacion IS NULL
                ");
                $stmtCheckSim->execute([':sim' => $sim_id_pti_nueva]);
                $yaAsignada = (int)$stmtCheckSim->fetchColumn() > 0;
                if ($yaAsignada) {
                    $errores[] = "La SIM seleccionada ya está asignada a otro equipo.";
                }

                $stmtEstadoSim = $pdo->prepare("SELECT estado FROM sims WHERE id = :id");
                $stmtEstadoSim->execute([':id' => $sim_id_pti_nueva]);
                $estadoSim = $stmtEstadoSim->fetchColumn();
                if (!$estadoSim) {
                    $errores[] = "La SIM seleccionada no existe.";
                } elseif ($estadoSim !== 'Disponible') {
                    $errores[] = "La SIM seleccionada no está disponible.";
                }
            }

            // Validación DOCK para PTI
            if ($esPTI && $dock_id_pti_nuevo !== null && (!$dockActual || $dock_id_pti_nuevo !== (int)$dockActual['id'])) {
                $stmtCheckDock = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM equipo_dock
                    WHERE dock_equipo_id = :dock AND fecha_liberacion IS NULL
                ");
                $stmtCheckDock->execute([':dock' => $dock_id_pti_nuevo]);
                if ((int)$stmtCheckDock->fetchColumn() > 0) {
                    $errores[] = "El DOCK seleccionado ya está asignado a otro equipo.";
                }

                $stmtDockExiste = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM equipos
                    WHERE id = :id AND UPPER(tipo) = 'DOCK'
                ");
                $stmtDockExiste->execute([':id' => $dock_id_pti_nuevo]);
                if ((int)$stmtDockExiste->fetchColumn() === 0) {
                    $errores[] = "El DOCK seleccionado no existe o no es válido.";
                }
            }
    

            // Red / IP
            $ip     = strtoupper(trim($_POST['ip'] ?? ''));
            $mac    = strtoupper(trim($_POST['mac'] ?? ''));
            $red_id = $_POST['red_id'] ?? '';

            // Avería
            $gestion_averia = strtoupper(trim($_POST['gestion_averia'] ?? 'EXTERNA')); // EXTERNA | INTERNA (GATI)
            $tipo_averia    = strtoupper(trim($_POST['tipo_averia'] ?? ''));
            $num_asunto     = strtoupper(trim($_POST['num_asunto'] ?? ''));
            $desc_averia    = trim($_POST['desc_averia'] ?? '');
            $empresa_ext    = strtoupper(trim($_POST['empresa_ext'] ?? ''));

            $estadoEsAveriado = (strcasecmp($estado, 'Averiado') === 0);

            if ($tipo === '') {
                $errores[] = "El campo Tipo es obligatorio.";
            }

            if ($estadoEsAveriado) {
                if ($tipo_averia === '') {
                    $errores[] = "El tipo de avería es obligatorio cuando el equipo está en estado AVERIADO.";
                }
                if ($gestion_averia === 'INTERNA') {
                    // Reparación interna: limpiar campos externos para evitar datos residuales
                    $num_asunto  = null;
                    $empresa_ext = $empresa_ext !== '' ? $empresa_ext : 'GATI (INTERNA)';
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

            // Normalizamos campos de avería según quién gestiona
            $esGestionInterna = ($gestion_averia === 'INTERNA');
            $numAsuntoDb      = $esGestionInterna ? null : ($num_asunto !== '' ? $num_asunto : null);
            $empresaExtDb     = $esGestionInterna
                ? ($empresa_ext !== '' ? $empresa_ext : 'GATI (INTERNA)')
                : ($empresa_ext !== '' ? $empresa_ext : 'PENDIENTE RAU');

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
            
            $tipoConMonitores = strtoupper($tipo);
            $tiposMonitores = ['PC','PORTATIL','PORTÁTIL','PTI','PORTATIL (PTI)','PORTÁTIL (PTI)'];
            $esEquipoConMonitor = in_array($tipoConMonitores, $tiposMonitores, true);

            // Gestionar monitores asociados
            if ($esEquipoConMonitor) {
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

            // Gestionar SIM para PTI
            if (!$esPTI && $simActual) {
                // Ya no es PTI: liberar SIM actual
                $stmtClose = $pdo->prepare("
                    UPDATE equipo_sim
                    SET fecha_liberacion = NOW()
                    WHERE equipo_id = :eq AND fecha_liberacion IS NULL
                ");
                $stmtClose->execute([':eq' => $id_equipo]);

                $stmtUpdSim = $pdo->prepare("UPDATE sims SET estado = 'Disponible' WHERE id = :sim");
                $stmtUpdSim->execute([':sim' => $simActual['id']]);
            } elseif ($esPTI) {
                // A) tenía SIM y se quita
                if ($simActual && $sim_id_pti_nueva === null) {
                    $stmtClose = $pdo->prepare("
                        UPDATE equipo_sim
                        SET fecha_liberacion = NOW()
                        WHERE equipo_id = :eq AND sim_id = :sim AND fecha_liberacion IS NULL
                    ");
                    $stmtClose->execute([':eq' => $id_equipo, ':sim' => $simActual['id']]);

                    $stmtUpdSim = $pdo->prepare("UPDATE sims SET estado = 'Disponible' WHERE id = :sim");
                    $stmtUpdSim->execute([':sim' => $simActual['id']]);
                }

                // B) no tenía SIM y ahora sí
                if (!$simActual && $sim_id_pti_nueva !== null) {
                    $stmtRel = $pdo->prepare("
                        INSERT INTO equipo_sim (equipo_id, sim_id)
                        VALUES (:eq, :sim)
                    ");
                    $stmtRel->execute([':eq' => $id_equipo, ':sim' => $sim_id_pti_nueva]);

                    $stmtUpdSim = $pdo->prepare("UPDATE sims SET estado = 'Asignada' WHERE id = :sim");
                    $stmtUpdSim->execute([':sim' => $sim_id_pti_nueva]);
                }

                // C) tenía SIM y se cambia por otra
                if ($simActual && $sim_id_pti_nueva !== null && $sim_id_pti_nueva !== (int)$simActual['id']) {
                    // cerrar relación actual
                    $stmtClose = $pdo->prepare("
                        UPDATE equipo_sim
                        SET fecha_liberacion = NOW()
                        WHERE equipo_id = :eq AND sim_id = :sim AND fecha_liberacion IS NULL
                    ");
                    $stmtClose->execute([':eq' => $id_equipo, ':sim' => $simActual['id']]);

                    $stmtUpdSimOld = $pdo->prepare("UPDATE sims SET estado = 'Disponible' WHERE id = :sim");
                    $stmtUpdSimOld->execute([':sim' => $simActual['id']]);

                    // nueva relación
                    $stmtRelNew = $pdo->prepare("
                        INSERT INTO equipo_sim (equipo_id, sim_id)
                        VALUES (:eq, :sim)
                    ");
                    $stmtRelNew->execute([':eq' => $id_equipo, ':sim' => $sim_id_pti_nueva]);

                    $stmtUpdSimNew = $pdo->prepare("UPDATE sims SET estado = 'Asignada' WHERE id = :sim");
                    $stmtUpdSimNew->execute([':sim' => $sim_id_pti_nueva]);
                }
            }

            // Gestionar DOCK para PTI
            if (!$esPTI && $dockActual) {
                $stmtCloseDock = $pdo->prepare("
                    UPDATE equipo_dock
                    SET fecha_liberacion = NOW()
                    WHERE equipo_id = :eq AND fecha_liberacion IS NULL
                ");
                $stmtCloseDock->execute([':eq' => $id_equipo]);
            } elseif ($esPTI) {
                // A) tenía DOCK y se quita
                if ($dockActual && $dock_id_pti_nuevo === null) {
                    $stmtCloseDock = $pdo->prepare("
                        UPDATE equipo_dock
                        SET fecha_liberacion = NOW()
                        WHERE equipo_id = :eq AND dock_equipo_id = :dock AND fecha_liberacion IS NULL
                    ");
                    $stmtCloseDock->execute([':eq' => $id_equipo, ':dock' => $dockActual['id']]);
                }

                // B) no tenía DOCK y ahora sí
                if (!$dockActual && $dock_id_pti_nuevo !== null) {
                    $stmtDockRel = $pdo->prepare("
                        INSERT INTO equipo_dock (equipo_id, dock_equipo_id)
                        VALUES (:eq, :dock)
                    ");
                    $stmtDockRel->execute([':eq' => $id_equipo, ':dock' => $dock_id_pti_nuevo]);
                }

                // C) tenía DOCK y se cambia por otro
                if ($dockActual && $dock_id_pti_nuevo !== null && $dock_id_pti_nuevo !== (int)$dockActual['id']) {
                    $stmtCloseDock = $pdo->prepare("
                        UPDATE equipo_dock
                        SET fecha_liberacion = NOW()
                        WHERE equipo_id = :eq AND dock_equipo_id = :dock AND fecha_liberacion IS NULL
                    ");
                    $stmtCloseDock->execute([':eq' => $id_equipo, ':dock' => $dockActual['id']]);

                    $stmtDockRel = $pdo->prepare("
                        INSERT INTO equipo_dock (equipo_id, dock_equipo_id)
                        VALUES (:eq, :dock)
                    ");
                    $stmtDockRel->execute([':eq' => $id_equipo, ':dock' => $dock_id_pti_nuevo]);
                }
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
                        ':num_asunto'  => $numAsuntoDb,
                        ':descripcion' => $desc_averia,
                        ':empresa_ext' => $empresaExtDb,
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
                        ':num_asunto'  => $numAsuntoDb,
                        ':descripcion' => $desc_averia,
                        ':empresa_ext' => $empresaExtDb,
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
                'tipo_averia'    => $tipo_averia,
                'num_asunto'     => $numAsuntoDb ?? $num_asunto,
                'descripcion'    => $desc_averia,
                'empresa_ext'    => $empresaExtDb,
                'gestion_averia' => $gestion_averia,
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
                    ($m['modelo'] ?? '')
                )) ?>

                <?php if (!empty($m['etiqueta'])): ?>
                    <span class="etiqueta-ok">
                        <?= htmlspecialchars($m['etiqueta']) ?>
                    </span>
                <?php endif; ?>

                <?= !empty($m['numero_serie']) ? ' [SN: '.$m['numero_serie'].']' : '' ?>
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
                    ($m['marca'] ?? '') . ' modelo: ' .
                    ($m['modelo'] ?? '') . ' etiqueta: ' . 
                    ($m['etiqueta'] ?? '')  . ' ' .
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

<div class="col-md-6" id="bloque-sim-pti" style="display:none;">
    <label class="form-label"><b>SIM (solo PTI, opcional)</b></label>
    <select name="sim_id_pti" class="form-select">
        <option value="">-- Sin SIM --</option>
        <?php
        $simPost = $_POST['sim_id_pti'] ?? null;
        foreach ($simsDisponibles as $sim):
            $selected =
                ($simPost !== null && $simPost !== '') ? ((int)$simPost === (int)$sim['id'] ? 'selected' : '') :
                ($simActual && (int)$simActual['id'] === (int)$sim['id'] ? 'selected' : '');
        ?>
            <?php
                $styleEtiqueta = $sim['etiqueta'] ? 'style="color:#0d6efd;font-weight:600;"' : '';
            ?>
            <option value="<?= (int)$sim['id'] ?>" <?= $selected ?> class="<?= $sim['etiqueta'] ? 'etiqueta-ok' : '' ?>" <?= $styleEtiqueta ?>>
                <?= $sim['etiqueta'] ? '[' . htmlspecialchars($sim['etiqueta']) . '] ' : '' ?><?= htmlspecialchars($sim['numero']) ?> · <?= htmlspecialchars($sim['operador']) ?> (ICCID: <?= htmlspecialchars($sim['iccid']) ?>)
            </option>
        <?php endforeach; ?>
    </select>
    <?php if ($simActual): ?>
        <small class="text-muted">SIM actual: <?= htmlspecialchars($simActual['numero']) ?> (<?= htmlspecialchars($simActual['operador']) ?>)</small>
    <?php else: ?>
        <small class="text-muted">Visible solo si el tipo es PTI. No es obligatoria.</small>
    <?php endif; ?>
</div>

<div class="col-md-6" id="bloque-dock-pti" style="display:none;">
    <label class="form-label"><b>DOCK (solo PTI, opcional)</b></label>
    <select name="dock_id_pti" class="form-select">
        <option value="">-- Sin DOCK --</option>
        <?php
        $dockPost = $_POST['dock_id_pti'] ?? null;
        foreach ($docksDisponibles as $dock):
            $selected =
                ($dockPost !== null && $dockPost !== '') ? ((int)$dockPost === (int)$dock['id'] ? 'selected' : '') :
                ($dockActual && (int)$dockActual['id'] === (int)$dock['id'] ? 'selected' : '');
        ?>
            <option value="<?= (int)$dock['id'] ?>" <?= $selected ?>>
                <?= !empty($dock['etiqueta']) ? '[' . htmlspecialchars($dock['etiqueta']) . '] ' : '' ?>
                <?= htmlspecialchars(trim(($dock['marca'] ?? '') . ' ' . ($dock['modelo'] ?? ''))) ?>
                <?= !empty($dock['numero_serie']) ? ' (SN: ' . htmlspecialchars($dock['numero_serie']) . ')' : '' ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php if ($dockActual): ?>
        <small class="text-muted">DOCK actual: <?= htmlspecialchars($dockActual['etiqueta'] ?: ('EQ-' . $dockActual['id'])) ?></small>
    <?php else: ?>
        <small class="text-muted">Visible solo si el tipo es PTI. No es obligatorio.</small>
    <?php endif; ?>
</div>



    <div class="col-md-2">
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
    $estados = ['Activo', 'Almacén', 'Averiado', 'Baja', 'Prestado', 'Privado'];

    // Estado actual del equipo
    $estadoSel = $equipo['estado'] ?? 'Activo';

    // Colores por estado
    $colores = [
        'Activo'   => '#28a745', 
        'Almacén'  => '#0d6efd', 
        'Averiado' => '#ffc107', 
        'Baja'     => '#dc3545', 
        'Prestado' => '#6c757d', 
        'Privado'  => '#f90dfdff',
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

$imagenSeleccionada = $_POST['imagen_existente']
    ?? (!empty($equipo['imagen']) ? basename($equipo['imagen']) : '');
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
<input type="hidden" name="imagen_actual" value="<?= htmlspecialchars($equipo['imagen'] ?? '') ?>">
<input type="hidden" name="imagen_existente" id="imagen_existente" value="<?= htmlspecialchars($imagenSeleccionada) ?>">

<div class="card mb-3 shadow-sm">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <strong>Imagen del equipo</strong>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-link btn-sm text-decoration-none p-0" data-bs-toggle="collapse" data-bs-target="#galeriaImagenes">Cambiar imagen</button>
            <button type="button" class="btn btn-link btn-sm text-decoration-none p-0" id="limpiarImagen">Quitar selección</button>
        </div>
    </div>
    <div class="card-body">
        <div class="text-center mb-3">
            <img
                id="preview_img_editar"
                <?php if (!empty($equipo['imagen'])): ?>
                    src="<?= htmlspecialchars($equipo['imagen']) ?>"
                    style="max-width:140px;border:1px solid #ccc;"
                <?php else: ?>
                    style="display:none;max-width:140px;border:1px solid #ccc;"
                <?php endif; ?>
            >
            <img id="preview_img_nueva"
                style="display:none;max-width:140px;border:1px solid #ccc;margin-top:8px;">
            <div class="text-muted small mt-1">Vista previa actual</div>
        </div>

        <div id="galeriaImagenes" class="collapse">
            <div class="thumb-grid-wrapper">
                <div class="row row-cols-2 row-cols-md-3 g-2 thumb-grid mb-3 mt-2">
                <?php
                $imagenPost = $imagenSeleccionada;
                if (!empty($imagenes_existentes)):
                    foreach ($imagenes_existentes as $img):
                        $file  = basename($img);
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
        </div>

        <div class="mt-3">
            <label class="form-label mb-1"><strong>Subir imagen nueva</strong></label>
            <input type="file" name="imagen_nueva" id="imagen_nueva" accept="image/*" class="form-control campo-destacado">
            <small class="form-text">Si subes una nueva, tendrá prioridad sobre la seleccionada.</small>
        </div>
    </div>
</div>



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
$inferGestion    = ($averia && ($averia['num_asunto'] ?? '') === '') ? 'INTERNA' : 'EXTERNA';
$gestion_averia_val = strtoupper($_POST['gestion_averia'] ?? ($averia['gestion_averia'] ?? $inferGestion));
if (!in_array($gestion_averia_val, ['EXTERNA', 'INTERNA'], true)) {
    $gestion_averia_val = 'EXTERNA';
}
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
            <label class="form-label">Quién gestiona la avería *</label>
            <select name="gestion_averia" id="gestionAveria" class="form-select campo-averia">
                <option value="EXTERNA" <?= $gestion_averia_val === 'EXTERNA' ? 'selected' : '' ?>>
                    RAU / Empresa externa (requiere nº de asunto)
                </option>
                <option value="INTERNA" <?= $gestion_averia_val === 'INTERNA' ? 'selected' : '' ?>>
                    GATI (reparación interna, sin nº de asunto)
                </option>
            </select>
            <p class="form-text mb-0">
                Si interviene una empresa, se solicitará el nº de asunto a RAU. Para reparaciones internas, no se generará número.
            </p>
        </div>
        <div class="col-md-4">
            <label class="form-label">Tipo de avería *</label>
            <input type="text" name="tipo_averia" class="form-control campo-averia"
                   value="<?= htmlspecialchars($tipo_averia_val) ?>"
                   placeholder="Ej: No enciende, Pantalla rota, Disco defectuoso...">
        </div>
        <div class="col-md-4" id="grupoNumAsunto">
            <label class="form-label">Nº de asunto (empresa externa) *</label>
            <input type="text" name="num_asunto" class="form-control campo-averia"
                   value="<?= htmlspecialchars($num_asunto_val) ?>"
                   placeholder="Referencia del ticket de la empresa externa">
                           <p class="form-text mb-3">
    Este número lo asigna RAU una vez el GATI envia incidencia si no se repara por el propio GATI.
</p>
        </div>

        <div class="col-md-4" id="grupoEmpresaExt">
            <label class="form-label">Reparación realizada por</label>
            <input type="text" name="empresa_ext" class="form-control campo-averia"
                   value="<?= htmlspecialchars($empresa_ext_val) ?>"
                   placeholder="Nombre de la empresa asignada por RAU">
            <p class="form-text mb-3">
                Completar cuando lo gestiona una empresa externa; para GATI puede dejarlo en blanco.
            </p>
        </div>
        <div class="col-12">
            <label class="form-label">Descripción de la avería</label>
            <textarea name="desc_averia" class="form-control campo-averia" rows="3"
                      placeholder="Describe brevemente el problema, pruebas realizadas, etc."><?= htmlspecialchars($desc_averia_val) ?></textarea>
        </div>
    </div>

    <div id="rauEmailBox" class="alert alert-info mt-3" style="display:none;">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong class="mb-0">Texto para correo a RAU</strong>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="copyRauEmail">Copiar</button>
        </div>
        <textarea class="form-control" id="rauEmailBody" rows="7" readonly></textarea>
        <div class="form-text">
            Copia y pega este texto en GroupWise para solicitar nº de asunto y empresa.
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
</div>

    <div class="form-actions-fixed mt-3">
        <div class="container d-flex justify-content-end gap-2">
            <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-success">Guardar cambios</button>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const redSelect = document.getElementById('redSelect');
    const ipSelect  = document.getElementById('ipSelect');
    const currentIp = <?= json_encode($ip_val) ?>;
    const equipoId  = <?= (int)$equipo['id'] ?>;
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

    if (imagenButtons.length) {
        imagenButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                imagenButtons.forEach(b => b.classList.remove('active-selection'));
                btn.classList.add('active-selection');
                const file = btn.dataset.file || '';
                if (imagenHidden) imagenHidden.value = file;
                if (imagenNueva) imagenNueva.value = '';
                if (previewNueva) previewNueva.style.display = 'none';
                if (file) {
                    setPreview('uploads/equipos/' + file);
                }
            });
        });
    }

    if (imagenNueva) {
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
                    if (previewImg) {
                        previewImg.style.display = 'none';
                    }
                };
                reader.readAsDataURL(e.target.files[0]);
            }
        });
    }

    if (limpiarBtn) {
        limpiarBtn.addEventListener('click', () => {
            imagenButtons.forEach(b => b.classList.remove('active-selection'));
            if (imagenHidden) imagenHidden.value = '';
            if (imagenNueva) imagenNueva.value = '';
            if (previewNueva) previewNueva.style.display = 'none';
            if (previewImg) previewImg.style.display = 'none';
        });
    }

    if (imagenHidden && imagenHidden.value) {
        setPreview('uploads/equipos/' + imagenHidden.value);
    }
});
</script>

<script>
    // Mostrar/ocultar bloque de avería según estado seleccionado
document.addEventListener('DOMContentLoaded', function () {
    const radiosEstado  = document.querySelectorAll('input[name="estado"]');
    const bloqueAveria  = document.getElementById('bloqueAveria');
    const selectGestion = document.getElementById('gestionAveria');
    const grupoNumAsunto = document.getElementById('grupoNumAsunto');
    const grupoEmpresaExt = document.getElementById('grupoEmpresaExt');
    const inputNumAsunto = document.querySelector('input[name="num_asunto"]');
    const inputEmpresaExt = document.querySelector('input[name="empresa_ext"]');
    const rauEmailBox   = document.getElementById('rauEmailBox');
    const rauEmailBody  = document.getElementById('rauEmailBody');
    const copyRauEmail  = document.getElementById('copyRauEmail');

    const inputsRau = [
        'input[name="etiqueta"]',
        'select[name="tipo"]',
        'input[name="marca"]',
        'input[name="modelo"]',
        'input[name="numero_serie"]',
        'select[name="hostname"]',
        'input[name="usuario_asignado"]',
        'select[name="departamento"]',
        'select[name="seccion_id"]',
        'select[name="ubicacion"]',
        'select[name="ip"]',
        'input[name="tipo_averia"]',
        'textarea[name="desc_averia"]'
    ].map(sel => document.querySelector(sel)).filter(Boolean);

    function toggleCamposGestion() {
        const esInterna = (selectGestion?.value || '').toUpperCase() === 'INTERNA';

        if (grupoNumAsunto) {
            grupoNumAsunto.style.display = esInterna ? 'none' : '';
        }
        if (grupoEmpresaExt) {
            grupoEmpresaExt.style.display = esInterna ? 'none' : '';
        }
        if (inputNumAsunto) {
            inputNumAsunto.required = false;
        }
        if (inputEmpresaExt) {
            inputEmpresaExt.required = false;
        }
        buildRauEmail();
    }

    function toggleAveria() {
        if (!bloqueAveria) return;
        const seleccionado = document.querySelector('input[name="estado"]:checked');
        if (!seleccionado) {
            bloqueAveria.style.display = 'none';
            toggleCamposGestion();
            buildRauEmail();
            return;
        }

        const val = seleccionado.value.toLowerCase(); // 'Activo', 'Averiado', etc.
        if (val === 'averiado') {
            bloqueAveria.style.display = '';   // se muestra
        } else {
            bloqueAveria.style.display = 'none'; // se oculta
        }
        toggleCamposGestion();
    }

    function buildRauEmail() {
        const esInterna = (selectGestion?.value || '').toUpperCase() === 'INTERNA';
        const bloqueVisible = bloqueAveria && bloqueAveria.style.display !== 'none';
        if (!rauEmailBox || !rauEmailBody) return;

        if (!bloqueVisible || esInterna) {
            rauEmailBox.style.display = 'none';
            rauEmailBody.value = '';
            return;
        }

        const getVal = (el) => (el?.value || '').trim();
        const getText = (el) => {
            if (!el) return '';
            if (el.tagName === 'SELECT') {
                const opt = el.selectedOptions && el.selectedOptions[0];
                return (opt?.textContent || '').trim();
            }
            return getVal(el);
        };

        const etiqueta    = getVal(inputsRau[0]) || ('EQ-' + <?= (int)$equipo['id'] ?>);
        const tipo        = getText(inputsRau[1]);
        const marca       = getVal(inputsRau[2]);
        const modelo      = getVal(inputsRau[3]);
        const serie       = getVal(inputsRau[4]);
        const servicio    = getText(inputsRau[5]);
        const usuario     = getVal(inputsRau[6]);
        const depto       = getText(inputsRau[7]);
        const seccion     = getText(inputsRau[8]);
        const ubicacion   = getText(inputsRau[9]);
        const ip          = getText(inputsRau[10]);
        const tipoAv      = getVal(inputsRau[11]);
        const descAv      = getVal(inputsRau[12]);

        const asunto = `Solicitud RAU - Avería equipo ${etiqueta}`;
        const cuerpo = [
            asunto,
            '',
            `Equipo: ${etiqueta} (ID ${<?= (int)$equipo['id'] ?>})`,
            `Tipo/Marca/Modelo: ${[tipo, marca, modelo].filter(Boolean).join(' ')}`,
            `Nº serie: ${serie || 'N/D'}`,
            `Servicio: ${servicio || 'N/D'}`,
            `Usuario asignado: ${usuario || 'N/D'}`,
            `Departamento: ${depto || 'N/D'}`,
            `Sección: ${seccion || 'N/D'}`,
            `Ubicación: ${ubicacion || 'N/D'}`,
            `IP principal: ${ip || 'N/D'}`,
            '',
            `Tipo de avería: ${tipoAv || 'N/D'}`,
            `Descripción: ${descAv || 'N/D'}`,
            '',
            'Solicito nº de asunto y empresa que atenderá la incidencia.'
        ].join('\\n');

        rauEmailBody.value = cuerpo;
        rauEmailBox.style.display = '';
    }

    // Escuchar cambios en todos los radios
    radiosEstado.forEach(radio => {
        radio.addEventListener('change', toggleAveria);
    });
    if (selectGestion) {
        selectGestion.addEventListener('change', toggleCamposGestion);
    }
    inputsRau.forEach(el => {
        el.addEventListener('input', buildRauEmail);
        el.addEventListener('change', buildRauEmail);
    });
    if (selectGestion) {
        selectGestion.addEventListener('change', buildRauEmail);
    }
    if (copyRauEmail && rauEmailBody) {
        copyRauEmail.addEventListener('click', () => {
            rauEmailBody.select();
            document.execCommand('copy');
        });
    }

    // Estado inicial al cargar la página
    toggleAveria();
    buildRauEmail();
});
</script>


<!-- Mostrar/ocultar bloque de monitores según tipo de equipo -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tipoSelect       = document.querySelector('select[name="tipo"]');
    const bloqueMonitores  = document.getElementById('bloque-monitores');
    const bloqueSim        = document.getElementById('bloque-sim-pti');
    const bloqueDock       = document.getElementById('bloque-dock-pti');

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
        if (bloqueDock) {
            bloqueDock.style.display = esPTI ? 'block' : 'none';
            if (!esPTI) {
                const selectDock = bloqueDock.querySelector('select[name="dock_id_pti"]');
                if (selectDock) selectDock.value = '';
            }
        }
    }

    if (tipoSelect) {
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
