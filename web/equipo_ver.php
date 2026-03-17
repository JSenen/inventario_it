<?php
require_once 'auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . "/includes/logger.php";
require_once __DIR__ . '/includes/verificaciones.php';
require_once __DIR__ . '/includes/equipos_schema.php';
require_once __DIR__ . '/includes/asset_files.php';

ensureEquiposSchema($pdo);
ensureAssetFilesSchema($pdo);

// 👇 AÑADE ESTO
$id_equipo = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_equipo <= 0) {
    die('ID de equipo no válido');
}

// Si quieres compatibilidad rápida con código antiguo que usa $id:
$id = $id_equipo;

// Obtener datos del equipo (incluye seccion)
$stmt = $pdo->prepare("
    SELECT e.*, s.nombre AS seccion_nombre
    FROM equipos e
    LEFT JOIN secciones s ON s.id = e.seccion_id
    WHERE e.id = :id
");
$stmt->execute([':id' => $id_equipo]);
$equipo = $stmt->fetch(PDO::FETCH_ASSOC);
$adjuntosEquipo = listEntityAttachments($pdo, 'equipo', $id_equipo);

if (!$equipo) {
    die('Equipo no encontrado');
}

$estadoRaw = trim((string)($equipo['estado'] ?? ''));
$estadoLower = strtolower($estadoRaw);
$estadoNorm = strtr($estadoLower, [
    'á' => 'a',
    'é' => 'e',
    'í' => 'i',
    'ó' => 'o',
    'ú' => 'u',
]);
$estadoClass = 'bg-secondary';
$estadoIcon  = 'bi-info-circle-fill';

switch ($estadoNorm) {
    case 'activo':
        $estadoClass = 'bg-success';
        $estadoIcon  = 'bi-check-circle-fill';
        break;
    case 'averiado':
        $estadoClass = 'bg-warning text-dark';
        $estadoIcon  = 'bi-exclamation-triangle-fill';
        break;
    case 'baja':
        $estadoClass = 'bg-danger';
        $estadoIcon  = 'bi-x-circle-fill';
        break;
    case 'baja definitiva':
        $estadoClass = 'bg-dark';
        $estadoIcon  = 'bi-x-octagon-fill';
        break;
    case 'almacen':
    case 'almacén':
        $estadoClass = 'bg-secondary';
        $estadoIcon  = 'bi-archive-fill';
        break;
    case 'prestado':
        $estadoClass = 'bg-info text-dark';
        $estadoIcon  = 'bi-clock-history';
        break;
    case 'privado':
        $estadoClass = 'bg-dark';
        $estadoIcon  = 'bi-shield-lock-fill';
        break;
}

// Asegurar tabla de relación SIM↔equipo para PTI
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

// Asegurar tabla de relación DOCK↔equipo PTI
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

$mensajeVerifError = null;

// Registrar verificación de inventario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_verificacion'])) {
    $ubicacionVerificada = strtoupper(trim($_POST['ubicacion_verificada'] ?? $equipo['ubicacion']));
    $estadoVerificado    = trim($_POST['estado_verificado'] ?? $equipo['estado']);
    $notasVerificacion   = trim($_POST['notas_verificacion'] ?? '');
    $usuarioVerificador  = $_SESSION['tip'] ?? ($_SESSION['usuario'] ?? 'SIN_USUARIO');

    $ipVerificada        = $ip_principal['ip'] ?? null;
    $seccionVerificada   = $equipo['seccion_nombre'] ?? null;
    $departamentoVerif   = $equipo['departamento'] ?? null;

    try {
        registrarVerificacionEquipo(
            $pdo,
            $id_equipo,
            $estadoVerificado,
            $ubicacionVerificada,
            $usuarioVerificador,
            $ipVerificada,
            $seccionVerificada,
            $departamentoVerif,
            $notasVerificacion !== '' ? $notasVerificacion : null
        );

        if (!empty($_POST['actualizar_equipo'])) {
            $stmtUpdateEq = $pdo->prepare("
                UPDATE equipos
                SET ubicacion = :ubicacion,
                    estado = :estado,
                    fecha_baja = CASE
                        WHEN :estado_baja = 1 AND fecha_baja IS NULL THEN NOW()
                        ELSE fecha_baja
                    END
                WHERE id = :id
            ");
            $stmtUpdateEq->execute([
                ':ubicacion' => $ubicacionVerificada,
                ':estado'    => $estadoVerificado,
                ':estado_baja' => (strcasecmp((string)$estadoVerificado, 'Baja') === 0 || strcasecmp((string)$estadoVerificado, 'Baja definitiva') === 0) ? 1 : 0,
                ':id'        => $id_equipo,
            ]);
        }

        logActividad(
            $pdo,
            'VERIFICACION_EQUIPO',
            "Verificación registrada para equipo ID={$id_equipo} por {$usuarioVerificador}"
        );

        $_SESSION['msg_verificacion'] = 'Verificación registrada correctamente.';
        header('Location: equipo_ver.php?id=' . $id_equipo);
        exit;
    } catch (Throwable $e) {
        $mensajeVerifError = 'No se pudo registrar la verificación: ' . $e->getMessage();
    }
}

// Inicializar variables para monitores o PC asociado
$monitores_pc = [];
$pc_asociado  = null;
$tipoEquipoUpper = strtoupper($equipo['tipo'] ?? '');
$esEquipoConMonitores = ($tipoEquipoUpper === 'PC')
    || (strpos($tipoEquipoUpper, 'PORTATIL') !== false)
    || (strpos($tipoEquipoUpper, 'PORTÁTIL') !== false)
    || ($tipoEquipoUpper === 'PTI')
    || ($tipoEquipoUpper === 'SITEL');

$simActual = null;
$dockActual = null;

// SIM actual si es PTI
if (strpos($tipoEquipoUpper, 'PTI') !== false) {
    $stmtSim = $pdo->prepare("
        SELECT s.*
        FROM equipo_sim es
        JOIN sims s ON s.id = es.sim_id
        WHERE es.equipo_id = :id
          AND es.fecha_liberacion IS NULL
        ORDER BY es.fecha_asignacion DESC
        LIMIT 1
    ");
    $stmtSim->execute([':id' => $id_equipo]);
    $simActual = $stmtSim->fetch(PDO::FETCH_ASSOC);

    $stmtDock = $pdo->prepare("
        SELECT d.id, d.etiqueta, d.marca, d.modelo, d.numero_serie
        FROM equipo_dock ed
        JOIN equipos d ON d.id = ed.dock_equipo_id
        WHERE ed.equipo_id = :id
          AND ed.fecha_liberacion IS NULL
        ORDER BY ed.fecha_asignacion DESC
        LIMIT 1
    ");
    $stmtDock->execute([':id' => $id_equipo]);
    $dockActual = $stmtDock->fetch(PDO::FETCH_ASSOC);
}

// Si es PC - SITEL o PORTÁTIL → listar monitores
if ($esEquipoConMonitores) {
    $sqlMon = "SELECT e.*
               FROM pc_monitores pm
               JOIN equipos e ON e.id = pm.id_monitor
               WHERE pm.id_pc = :id_pc
               ORDER BY e.marca, e.modelo, e.numero_serie";
    $stmtMon = $pdo->prepare($sqlMon);
    $stmtMon->execute([':id_pc' => $equipo['id']]);
    $monitores_pc = $stmtMon->fetchAll(PDO::FETCH_ASSOC);
}

// Si es MONITOR → ver a qué PC está asignado
if ($equipo['tipo'] === 'MONITOR') {
    $sqlPc = "SELECT e.*
              FROM pc_monitores pm
              JOIN equipos e ON e.id = pm.id_pc
              WHERE pm.id_monitor = :id_monitor";
    $stmtPc = $pdo->prepare($sqlPc);
    $stmtPc->execute([':id_monitor' => $equipo['id']]);
    $pc_asociado = $stmtPc->fetch(PDO::FETCH_ASSOC);
}

// Obtener IP principal
$sql_ip_principal = "SELECT ip, mac FROM ips_equipos WHERE equipo_id = :id AND es_principal = 1 LIMIT 1";
$stmt_ip = $pdo->prepare($sql_ip_principal);
$stmt_ip->execute(['id' => $id]);
$ip_principal = $stmt_ip->fetch(PDO::FETCH_ASSOC);

// Obtener todas las IPs
$sql_ips = "SELECT ie.ip, ie.mac, r.nombre AS red_nombre, r.direccion_red
            FROM ips_equipos ie
            JOIN redes r ON r.id = ie.red_id
            WHERE ie.equipo_id = :id
            ORDER BY ie.es_principal DESC, ie.id ASC";

$stmt_ips = $pdo->prepare($sql_ips);
$stmt_ips->execute(['id' => $id]);


// Materiales instalados / consumidos en este equipo (SALIDAS)
$sqlMat = "
    SELECT 
        mm.*,
        m.referencia,
        m.descripcion,
        m.unidad
    FROM materiales_movimientos mm
    JOIN materiales m ON m.id = mm.material_id
    WHERE mm.equipo_id = :equipo_id
      AND mm.tipo = 'SALIDA'
    ORDER BY mm.fecha DESC, mm.id DESC
";
$stmtMat = $pdo->prepare($sqlMat);
$stmtMat->execute([':equipo_id' => $equipo['id']]);
$materiales_instalados = $stmtMat->fetchAll(PDO::FETCH_ASSOC);

$ultimoMov = null;
if (isset($_GET['mov']) && $_GET['mov'] === 'last') {
    $stmtMov = $pdo->prepare("
        SELECT * FROM equipos_movimientos
        WHERE id_equipo = :id
        ORDER BY fecha DESC
        LIMIT 1
    ");
    $stmtMov->execute([':id' => $id_equipo]);
    $ultimoMov = $stmtMov->fetch(PDO::FETCH_ASSOC);
}
$movBajaId = isset($_GET['mov_baja']) ? (int)$_GET['mov_baja'] : 0;
$movAltaId = isset($_GET['mov_alta']) ? (int)$_GET['mov_alta'] : 0;
$movimientosTraslado = [];
if ($movBajaId > 0 && $movAltaId > 0) {
    $stmtMovTraslado = $pdo->prepare("
        SELECT id, tipo, fecha, firma_token
        FROM equipos_movimientos
        WHERE id_equipo = :id_equipo
          AND id IN (:id_baja, :id_alta)
        ORDER BY fecha DESC, id DESC
    ");
    $stmtMovTraslado->bindValue(':id_equipo', $id_equipo, PDO::PARAM_INT);
    $stmtMovTraslado->bindValue(':id_baja', $movBajaId, PDO::PARAM_INT);
    $stmtMovTraslado->bindValue(':id_alta', $movAltaId, PDO::PARAM_INT);
    $stmtMovTraslado->execute();
    $movimientosTraslado = $stmtMovTraslado->fetchAll(PDO::FETCH_ASSOC);
}

$ultimaVerificacion = obtenerUltimaVerificacionEquipo($pdo, $id_equipo);

logActividad($pdo, 'VER_EQUIPO', 'Detalle del equipo visualizado: ID=' . $id);

$ips = $stmt_ips->fetchAll(PDO::FETCH_ASSOC);

// Última renovación asociada (como equipo renovado o nuevo)
$stmtRenLast = $pdo->prepare("
    SELECT id
    FROM renovaciones
    WHERE equipo_old_id = :id OR equipo_new_id = :id
    ORDER BY fecha DESC
    LIMIT 1
");
$stmtRenLast->execute([':id' => $id_equipo]);
$ultimaRenov = $stmtRenLast->fetch(PDO::FETCH_ASSOC);
require_once __DIR__ . '/includes/header.php';
?>

<div class="mt-4">
    <h2>Detalle del Equipo</h2>
    <hr>
<?php if (!empty($movimientosTraslado)): ?>
    <div class="alert alert-info d-flex justify-content-between align-items-center">
        <div>
            Se han generado recibos de <strong>baja</strong> y <strong>alta</strong> por traslado de ubicación/departamento/sección.
        </div>
        <div class="btn-group btn-group-sm">
            <?php foreach ($movimientosTraslado as $movTras): ?>
                <a href="recibo_movimiento.php?id=<?= (int)$movTras['id'] ?>"
                   target="_blank"
                   class="btn btn-outline-secondary">
                    <?= htmlspecialchars(ucfirst($movTras['tipo'])) ?> #<?= (int)$movTras['id'] ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
<?php if ($ultimoMov): ?>
    <div class="alert alert-info d-flex justify-content-between align-items-center">
        <div>
            Se ha generado un recibo de <strong><?= htmlspecialchars($ultimoMov['tipo']) ?></strong>
            para este equipo (<?= htmlspecialchars($ultimoMov['fecha']) ?>).
        </div>
        <div class="btn-group btn-group-sm">
            <a href="recibo_movimiento.php?id=<?= (int)$ultimoMov['id'] ?>"
            target="_blank"
            class="btn btn-outline-secondary">
                Ver recibo
            </a>

            <?php if (!empty($ultimoMov['firma_token'])): ?>
                <a href="firma.php?token=<?= urlencode($ultimoMov['firma_token']) ?>"
                   class="btn btn-primary">
                    Firmar digitalmente
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>


    <div class="mb-3">
        <a href="index.php" class="btn btn-secondary">Volver al listado</a>
        <a href="averias_list.php?equipo_id=<?= $id ?>" class="btn btn-warning">Ver averías de este equipo</a>
        <a href="equipo_renovar.php?id=<?= $id ?>" class="btn btn-primary">Renovar equipo</a>
        <?php if (!empty($ultimaRenov['id'])): ?>
            <a href="recibo_renovacion.php?id=<?= (int)$ultimaRenov['id'] ?>" class="btn btn-outline-secondary">Último recibo de renovación</a>
        <?php endif; ?>
    </div>

    <?php if (!empty($_SESSION['msg_verificacion'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['msg_verificacion']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
        <?php unset($_SESSION['msg_verificacion']); ?>
    <?php endif; ?>
    <?php if ($mensajeVerifError): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($mensajeVerifError) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Control de inventario</strong>
            <?php if ($ultimaVerificacion): ?>
                <?php
                    $fechaVerifObj = new DateTime($ultimaVerificacion['fecha']);
                    $diasDiff = (int)(new DateTime())->diff($fechaVerifObj)->format('%a');
                    $badgeClass = 'bg-danger';
                    $badgeText  = 'Fuera de plazo';
                    if ($diasDiff <= 60) {
                        $badgeClass = 'bg-success';
                        $badgeText  = 'Al día';
                    } elseif ($diasDiff <= 90) {
                        $badgeClass = 'bg-warning text-dark';
                        $badgeText  = 'Revisar pronto';
                    }
                ?>
                <span class="badge rounded-pill <?= $badgeClass ?>">
                    <?= htmlspecialchars($badgeText) ?> · <?= htmlspecialchars($diasDiff) ?> días
                </span>
            <?php else: ?>
                <span class="badge bg-secondary">Sin controles previos</span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($ultimaVerificacion): ?>
                <p class="small mb-3">
                    Última verificación: <strong><?= htmlspecialchars($fechaVerifObj->format('Y-m-d H:i')) ?></strong>
                    · Ubicación: <?= htmlspecialchars($ultimaVerificacion['ubicacion'] ?? '-') ?>
                    · Estado: <?= htmlspecialchars($ultimaVerificacion['estado_equipo'] ?? '-') ?>
                    · IP: <?= htmlspecialchars($ultimaVerificacion['ip'] ?? '-') ?>
                    · Sección: <?= htmlspecialchars($ultimaVerificacion['seccion'] ?? '-') ?>
                    · Depto: <?= htmlspecialchars($ultimaVerificacion['departamento'] ?? '-') ?>
                    · Verificado por: <?= htmlspecialchars($ultimaVerificacion['usuario_verificador'] ?? '-') ?>
                    <?php if (!empty($ultimaVerificacion['notas'])): ?>
                        <br><span class="text-muted">Notas: <?= nl2br(htmlspecialchars($ultimaVerificacion['notas'])) ?></span>
                    <?php endif; ?>
                </p>
            <?php else: ?>
                <p class="text-muted small mb-3">Aún no se ha registrado ninguna verificación para este equipo.</p>
            <?php endif; ?>

            <form method="post" class="row g-3">
                <input type="hidden" name="registrar_verificacion" value="1">
                <div class="col-md-4">
                    <label class="form-label small mb-1">Ubicación verificada</label>
                    <input
                        type="text"
                        name="ubicacion_verificada"
                        class="form-control"
                        value="<?= htmlspecialchars($equipo['ubicacion']) ?>"
                        required
                    >
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Estado verificado</label>
                    <select name="estado_verificado" class="form-select">
                        <?php foreach (['Activo','Almacén','Averiado','Baja','Baja definitiva','Prestado','Privado'] as $estadoOpt): ?>
                            <option value="<?= $estadoOpt ?>" <?= $equipo['estado'] === $estadoOpt ? 'selected' : '' ?>>
                                <?= $estadoOpt ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="form-label small mb-1">Notas de verificación (opcional)</label>
                    <textarea name="notas_verificacion" rows="2" class="form-control" placeholder="Condición física, etiquetas, incidencias..."></textarea>
                </div>
                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="actualizar_equipo" name="actualizar_equipo" checked>
                        <label class="form-check-label" for="actualizar_equipo">
                            Actualizar ficha del equipo con esta ubicación y estado
                        </label>
                    </div>
                </div>
                <div class="col-md-6 text-end">
                    <button type="submit" class="btn btn-primary">Registrar verificación</button>
                </div>
            </form>
        </div>
    </div>

    <h4>Información del equipo</h4>
    <table class="table table-bordered">
        <tr>
            <th>ID</th>
             <td> <span class="<?= !empty($equipo['etiqueta']) ? 'etiqueta-ok' : 'etiqueta-missing' ?>"><?= htmlspecialchars(!empty($equipo['etiqueta']) ? $equipo['etiqueta'] : '(sin etiqueta)') ?></span></td></tr>
        <?php if (!empty($equipo['imagen']) && is_file(__DIR__ . '/' . ltrim((string)$equipo['imagen'], '/'))): ?>
        <tr>
            <th>Imagen</th>
            <td>
                <img src="<?= htmlspecialchars($equipo['imagen']) ?>"
                     alt="Imagen del equipo"
                     style="max-width: 250px; height: auto;">
            </td>
        </tr>
    <?php endif; ?>
        <tr><th>Tipo</th> <td><?= htmlspecialchars($equipo['tipo']) ?></td></tr>
        <tr><th>Marca</th> <td><?= htmlspecialchars($equipo['marca']) ?></td></tr>
        <tr><th>Modelo</th> <td><?= htmlspecialchars($equipo['modelo']) ?></td></tr>
        <tr><th>Número de serie</th> <td><?= htmlspecialchars($equipo['numero_serie']) ?></td></tr>
        <?php if (strpos($tipoEquipoUpper, 'PTI') !== false || !empty($equipo['imei'])): ?>
            <tr><th>IMEI</th> <td><?= htmlspecialchars($equipo['imei'] ?: '-') ?></td></tr>
        <?php endif; ?>
        <tr><th>Servicio</th> <td><?= htmlspecialchars($equipo['hostname']) ?></td></tr>
        <tr>
            <th>Usuario asignado</th>
            <td>
                <?php $usuarioAsignado = trim((string)($equipo['usuario_asignado'] ?? '')); ?>
                <span class="dato-contacto-destacado<?= $usuarioAsignado === '' ? ' dato-contacto-destacado-vacio' : '' ?>">
                    <?= htmlspecialchars($usuarioAsignado !== '' ? $usuarioAsignado : '-') ?>
                </span>
                <?php if ($usuarioAsignado !== ''): ?>
                    <a class="btn btn-sm btn-outline-primary ms-2" href="usuario_asociado.php?tip=<?= urlencode($usuarioAsignado) ?>">
                        Ver todo por TIP
                    </a>
                <?php endif; ?>
            </td>
        </tr>
        <tr><th>Departamento</th> <td><?= htmlspecialchars($equipo['departamento']) ?></td></tr>
        <tr><th>Ubicación</th> <td><?= htmlspecialchars($equipo['ubicacion']) ?></td></tr>
        <?php if ($simActual): ?>
            <tr>
                <th>SIM (PTI)</th>
                <td>
                    <?php if (!empty($simActual['etiqueta'])): ?>
                        <a href="sims_ver.php?id=<?= (int)$simActual['id'] ?>">
                            <span class="etiqueta-numero"><?= htmlspecialchars($simActual['etiqueta']) ?></span>
                        </a>
                    <?php endif; ?>
                    Nº <?= htmlspecialchars($simActual['numero']) ?> · <br>
                    ICCID: <?= htmlspecialchars($simActual['iccid']) ?>
                </td>
            </tr>
        <?php elseif (strpos($tipoEquipoUpper, 'PTI') !== false): ?>
            <tr>
                <th>SIM (PTI)</th>
                <td><span class="text-muted">Sin SIM asignada</span></td>
            </tr>
        <?php endif; ?>
        <?php if ($dockActual): ?>
            <tr>
                <th>DOCK (PTI)</th>
                <td>
                    <a href="equipo_ver.php?id=<?= (int)$dockActual['id'] ?>">
                        <?= htmlspecialchars($dockActual['etiqueta'] ?: ('EQ-' . $dockActual['id'])) ?>
                    </a><br>
                    <?= htmlspecialchars(trim(($dockActual['marca'] ?? '') . ' ' . ($dockActual['modelo'] ?? ''))) ?>
                    <?php if (!empty($dockActual['numero_serie'])): ?>
                        <br>SN: <?= htmlspecialchars($dockActual['numero_serie']) ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php elseif (strpos($tipoEquipoUpper, 'PTI') !== false): ?>
            <tr>
                <th>DOCK (PTI)</th>
                <td><span class="text-muted">Sin DOCK asignado</span></td>
            </tr>
        <?php endif; ?>
        <?php if ($esEquipoConMonitores): ?>
<tr>
    <th>Monitores asociados</th>
    <td>
        <?php if (!empty($monitores_pc)): ?>
            <ul class="mb-0">
                <?php foreach ($monitores_pc as $m): ?>
                    <li>
                        <a href="equipo_ver.php?id=<?= (int)$m['id'] ?>">
                            <?= htmlspecialchars(($m['marca'] ?? '').' '.($m['modelo'] ?? '')) ?>
                            <?php if (!empty($m['numero_serie'])): ?>
                                (SN: <?= htmlspecialchars($m['numero_serie']) ?>)
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <span class="text-muted">Sin monitores asociados</span>
        <?php endif; ?>
    </td>
</tr>
<?php endif; ?>

<?php if ($equipo['tipo'] === 'MONITOR'): ?>
<tr>
    <th>Asignado a PC</th>
    <td>
        <?php if ($pc_asociado): ?>
            <a href="equipo_ver.php?id=<?= (int)$pc_asociado['id'] ?>">
                <?= htmlspecialchars(
                    $pc_asociado['hostname']
                    ?: (($pc_asociado['marca'] ?? '').' '.($pc_asociado['modelo'] ?? ''))
                ) ?>
            </a>
        <?php else: ?>
            <span class="text-muted">No asignado</span>
        <?php endif; ?>
    </td>
</tr>
<?php endif; ?>

        <tr>
    <th>Fecha Alta</th>
    <td><?= htmlspecialchars($equipo['fecha_compra'] ?? '') ?></td>
</tr>
<tr><th>Fecha de baja</th> 
    <td>
        <?= $equipo['fecha_baja'] ? htmlspecialchars($equipo['fecha_baja']) : '<span class="text-muted">-</span>' ?>
    </td>
</tr>
<!-- <tr>
    <th>Proveedor</th>
    <td><?= htmlspecialchars($equipo['proveedor'] ?? '') ?></td>
</tr>
<tr>
    <th>Coste</th>
    <td>
        <?php if ($equipo['coste'] !== null && $equipo['coste'] !== ''): ?>
            <?= htmlspecialchars($equipo['coste']) ?> €
        <?php else: ?>
            -
        <?php endif; ?>
    </td>
</tr> -->
        <tr>
            <th>Estado</th>
            <td>
                <span class="badge rounded-pill <?= $estadoClass ?> d-inline-flex align-items-center gap-1">
                    <i class="bi <?= $estadoIcon ?>"></i>
                    <?= htmlspecialchars($estadoRaw !== '' ? $estadoRaw : '-') ?>
                </span>
            </td>
        </tr>
        <tr><th>Creado en</th> <td><?= htmlspecialchars($equipo['creado_en']) ?></td></tr>
    </table>
    <?php if (!empty($adjuntosEquipo)): ?>
        <div class="card mb-4">
            <div class="card-header"><strong>Adjuntos</strong></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Archivo</th>
                                <th>Tamaño</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($adjuntosEquipo as $adjunto): ?>
                                <tr>
                                    <td>
                                        <a href="<?= htmlspecialchars($adjunto['file_path']) ?>" target="_blank" rel="noopener">
                                            <?= htmlspecialchars($adjunto['original_name']) ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars(formatAttachmentSize((int)($adjunto['file_size'] ?? 0))) ?></td>
                                    <td><?= htmlspecialchars($adjunto['created_at'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
<h4 class="mt-4">Material instalado / consumido</h4>

<?php if (!empty($materiales_instalados)): ?>
    <table class="table table-sm table-striped">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Referencia</th>
                <th>Descripción</th>
                <th>Cantidad</th>
                <th>Usuario</th>
                <th>Motivo</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($materiales_instalados as $mi): ?>
            <tr>
                <td><?= htmlspecialchars($mi['fecha']) ?></td>
                <td><?= htmlspecialchars($mi['referencia']) ?></td>
                <td><?= htmlspecialchars($mi['descripcion']) ?></td>
                <td><?= (int)$mi['cantidad'] . ' ' . htmlspecialchars($mi['unidad']) ?></td>
                <td><?= htmlspecialchars($mi['usuario']) ?></td>
                <td><?= htmlspecialchars($mi['motivo']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p class="text-muted">No hay registros de material asociado a este equipo.</p>
<?php endif; ?>

    <h4>Notas del equipo</h4>
    <div class="border p-2 mb-4" style="white-space: pre-wrap;">
        <?= !empty($equipo['notas']) ? nl2br(htmlspecialchars($equipo['notas'])) : '<span class="text-muted">Sin notas.</span>' ?>
    </div>

    <h4>IP principal</h4>
    <?php if ($ip_principal): ?>
        <table class="table table-bordered">
            <tr><th>IP</th><td><?= htmlspecialchars($ip_principal['ip']) ?></td></tr>
            <tr><th>MAC</th><td><?= htmlspecialchars($ip_principal['mac']) ?></td></tr>
        </table>
    <?php else: ?>
        <p class="text-muted">Este equipo no tiene IP principal asignada.</p>
    <?php endif; ?>

    <h4>Todas las IPs del equipo</h4>
    <?php if ($ips): ?>
        <div style="overflow-x:auto;">
            <table class="table table-striped table-sm" style="min-width: 900px;">
                <thead>
                <tr>
                    <th>IP</th>
                    <th>MAC</th>
                    <th>Red</th>
                    <th>Dirección red</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($ips as $ip): ?>
                    <tr>
                        <td><?= htmlspecialchars($ip['ip']) ?></td>
                        <td><?= htmlspecialchars($ip['mac']) ?></td>
                        <td><?= htmlspecialchars($ip['red_nombre']) ?></td>
                        <td><?= htmlspecialchars($ip['direccion_red']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted">No hay IPs registradas para este equipo.</p>
    <?php endif; ?>

</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
