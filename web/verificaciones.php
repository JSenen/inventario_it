<?php
require_once 'auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/verificaciones.php';

ensureTablaVerificaciones($pdo);

// Registrar verificación rápida desde el listado de equipos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verificar_equipo'])) {
    $equipoId = (int)$_POST['verificar_equipo'];
    $nota     = trim($_POST['nota'] ?? '');
    $usuario  = $_SESSION['tip'] ?? ($_SESSION['usuario'] ?? 'SIN_USUARIO');

    $stmtEq = $pdo->prepare("
        SELECT e.*, s.nombre AS seccion_nombre,
            (SELECT ip FROM ips_equipos ie WHERE ie.equipo_id = e.id AND ie.es_principal = 1 LIMIT 1) AS ip_principal
        FROM equipos e
        LEFT JOIN secciones s ON s.id = e.seccion_id
        WHERE e.id = :id
    ");
    $stmtEq->execute([':id' => $equipoId]);
    $eq = $stmtEq->fetch(PDO::FETCH_ASSOC);

    if ($eq) {
        registrarVerificacionEquipo(
            $pdo,
            $equipoId,
            $eq['estado'],
            $eq['ubicacion'],
            $usuario,
            $eq['ip_principal'] ?? null,
            $eq['seccion_nombre'] ?? null,
            $eq['departamento'] ?? null,
            $nota !== '' ? $nota : null
        );
        $_SESSION['msg_verif_list'] = "Verificación registrada para el equipo ID {$equipoId}.";
    } else {
        $_SESSION['msg_verif_list_error'] = "No se encontró el equipo solicitado.";
    }

    header('Location: verificaciones.php');
    exit;
}

// Filtros
$f_ubicacion   = strtoupper(trim($_GET['ubicacion'] ?? ''));
$f_departamento= strtoupper(trim($_GET['departamento'] ?? ''));
$f_seccion     = $_GET['seccion'] ?? '';
$f_estado      = trim($_GET['estado'] ?? '');
$f_dias        = isset($_GET['dias']) ? max(0, (int)$_GET['dias']) : 0;

$where = [];
$params = [];

if ($f_ubicacion !== '') {
    $where[] = 'UPPER(ev.ubicacion) = :ubicacion';
    $params[':ubicacion'] = $f_ubicacion;
}
if ($f_departamento !== '') {
    $where[] = 'UPPER(ev.departamento) = :departamento';
    $params[':departamento'] = $f_departamento;
}
if ($f_seccion !== '') {
    $where[] = 'ev.seccion = :seccion';
    $params[':seccion'] = $f_seccion;
}
if ($f_estado !== '') {
    $where[] = 'ev.estado_equipo = :estado';
    $params[':estado'] = $f_estado;
}
if ($f_dias > 0) {
    $where[] = 'ev.fecha >= DATE_SUB(NOW(), INTERVAL :dias DAY)';
    $params[':dias'] = $f_dias;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT
        ev.*,
        e.etiqueta,
        e.usuario_asignado,
        e.tipo,
        e.marca,
        e.modelo,
        e.departamento AS departamento_ficha,
        e.ubicacion    AS ubicacion_ficha,
        s.nombre AS seccion_nombre
    FROM equipos_verificaciones ev
    JOIN equipos e ON e.id = ev.equipo_id
    LEFT JOIN secciones s ON s.id = e.seccion_id
    $whereSql
    ORDER BY ev.fecha DESC
    LIMIT 500
";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$verificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Equipos según filtros (para marcar controles rápidamente)
$whereEquipos = [];
$paramsEquipos = [];
if ($f_ubicacion !== '') {
    $whereEquipos[] = 'UPPER(e.ubicacion) = :ubi_eq';
    $paramsEquipos[':ubi_eq'] = $f_ubicacion;
}
if ($f_departamento !== '') {
    $whereEquipos[] = 'UPPER(e.departamento) = :dep_eq';
    $paramsEquipos[':dep_eq'] = $f_departamento;
}
if ($f_seccion !== '') {
    $whereEquipos[] = 's.nombre = :sec_eq';
    $paramsEquipos[':sec_eq'] = $f_seccion;
}
if ($f_estado !== '') {
    $whereEquipos[] = 'e.estado = :estado_eq';
    $paramsEquipos[':estado_eq'] = $f_estado;
}
$whereEquiposSql = $whereEquipos ? 'WHERE ' . implode(' AND ', $whereEquipos) : '';

$sqlEquipos = "
    SELECT
        e.id,
        e.etiqueta,
        e.tipo,
        e.marca,
        e.modelo,
        e.usuario_asignado,
        e.departamento,
        e.ubicacion,
        e.estado,
        s.nombre AS seccion_nombre,
        ip.ip AS ip_principal,
        (
            SELECT ev.fecha
            FROM equipos_verificaciones ev
            WHERE ev.equipo_id = e.id
            ORDER BY ev.fecha DESC
            LIMIT 1
        ) AS ultima_verificacion_fecha
    FROM equipos e
    LEFT JOIN secciones s ON s.id = e.seccion_id
    LEFT JOIN ips_equipos ip ON ip.equipo_id = e.id AND ip.es_principal = 1
    $whereEquiposSql
    ORDER BY e.id DESC
    LIMIT 500
";
$stmtEqList = $pdo->prepare($sqlEquipos);
foreach ($paramsEquipos as $k => $v) {
    $stmtEqList->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmtEqList->execute();
$equiposListado = $stmtEqList->fetchAll(PDO::FETCH_ASSOC);

// Listas para filtros
$ubicaciones = $pdo->query("SELECT nombre FROM ubicaciones ORDER BY nombre ASC")->fetchAll(PDO::FETCH_COLUMN);
$departamentos = $pdo->query("SELECT nombre FROM departamentos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_COLUMN);
$secciones = $pdo->query("SELECT id, nombre FROM secciones ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

?>
<div class="container mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Controles de inventario</h1>
        <div class="text-muted small">Máx. 500 registros recientes</div>
    </div>

    <?php if (!empty($_SESSION['msg_verif_list'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['msg_verif_list']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
        <?php unset($_SESSION['msg_verif_list']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['msg_verif_list_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['msg_verif_list_error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
        <?php unset($_SESSION['msg_verif_list_error']); ?>
    <?php endif; ?>

    <div class="card mb-3 shadow-sm">
        <div class="card-body">
            <form class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1">Ubicación verificada</label>
                    <select name="ubicacion" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <?php foreach ($ubicaciones as $ubi): ?>
                            <option value="<?= htmlspecialchars(strtoupper($ubi)) ?>" <?= $f_ubicacion === strtoupper($ubi) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ubi) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Departamento</label>
                    <select name="departamento" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($departamentos as $dep): ?>
                            <option value="<?= htmlspecialchars(strtoupper($dep)) ?>" <?= $f_departamento === strtoupper($dep) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dep) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Sección</label>
                    <select name="seccion" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <?php foreach ($secciones as $sec): ?>
                            <option value="<?= htmlspecialchars($sec['nombre']) ?>" <?= $f_seccion === $sec['nombre'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sec['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Estado verificado</label>
                    <select name="estado" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach (['Activo','Almacén','Averiado','Baja','Prestado','Privado'] as $estadoOpt): ?>
                            <option value="<?= $estadoOpt ?>" <?= $f_estado === $estadoOpt ? 'selected' : '' ?>><?= $estadoOpt ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label small mb-1">Últimos días</label>
                    <input type="number" min="0" name="dias" class="form-control form-control-sm" value="<?= htmlspecialchars((string)$f_dias) ?>" placeholder="0">
                </div>
                <div class="col-md-12 d-flex justify-content-end gap-2">
                    <a href="verificaciones.php" class="btn btn-outline-secondary btn-sm">Limpiar</a>
                    <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4 shadow-sm">
        <div class="card-header">
            <strong>Equipos según filtros (para marcar verificación rápida)</strong>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID / Etiqueta</th>
                            <th>Tipo / Modelo</th>
                            <th>Usuario</th>
                            <th>Depto</th>
                            <th>Sección</th>
                            <th>Ubicación</th>
                            <th>IP</th>
                            <th>Estado</th>
                            <th>Últ. verificación</th>
                            <th class="text-center">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($equiposListado): ?>
                            <?php foreach ($equiposListado as $eq): ?>
                                <tr>
                                    <td>
                                        <a href="equipo_ver.php?id=<?= (int)$eq['id'] ?>">
                                            <?= htmlspecialchars($eq['etiqueta'] ?: ('EQ-' . $eq['id'])) ?>
                                        </a>
                                        <div class="text-muted small">ID: <?= (int)$eq['id'] ?></div>
                                    </td>
                                    <td><?= htmlspecialchars(trim(($eq['tipo'] ?? '') . ' ' . ($eq['marca'] ?? '') . ' ' . ($eq['modelo'] ?? ''))) ?></td>
                                    <td><?= htmlspecialchars($eq['usuario_asignado'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($eq['departamento'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($eq['seccion_nombre'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($eq['ubicacion'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($eq['ip_principal'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($eq['estado'] ?? '') ?></td>
                                    <td>
                                        <?php if (!empty($eq['ultima_verificacion_fecha'])): ?>
                                            <span class="badge bg-success-subtle border text-success">
                                                <?= htmlspecialchars($eq['ultima_verificacion_fecha']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">Sin verificación</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <form method="post" class="d-flex align-items-center gap-2">
                                            <input type="hidden" name="verificar_equipo" value="<?= (int)$eq['id'] ?>">
                                            <input type="text" name="nota" class="form-control form-control-sm" placeholder="Nota opcional" style="max-width: 160px;">
                                            <button type="submit" class="btn btn-sm btn-primary">Marcar</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="10" class="text-center text-muted">No hay equipos para estos filtros.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha</th>
                            <th>Equipo</th>
                            <th>Tipo / Modelo</th>
                            <th>Usuario</th>
                            <th>Depto</th>
                            <th>Sección</th>
                            <th>Ubicación</th>
                            <th>Estado</th>
                            <th>IP</th>
                            <th>Verificador</th>
                            <th>Notas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($verificaciones): ?>
                            <?php foreach ($verificaciones as $v): ?>
                                <tr>
                                    <td><?= htmlspecialchars($v['fecha']) ?></td>
                                    <td>
                                        <a href="equipo_ver.php?id=<?= (int)$v['equipo_id'] ?>">
                                            <?= htmlspecialchars($v['etiqueta'] ?: ('EQ-' . $v['equipo_id'])) ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars(trim(($v['tipo'] ?? '') . ' ' . ($v['marca'] ?? '') . ' ' . ($v['modelo'] ?? ''))) ?></td>
                                    <td><?= htmlspecialchars($v['usuario_asignado'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($v['departamento'] ?? $v['departamento_ficha'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($v['seccion'] ?? $v['seccion_nombre'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($v['ubicacion'] ?? $v['ubicacion_ficha'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($v['estado_equipo'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($v['ip'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($v['usuario_verificador'] ?? '') ?></td>
                                    <td class="text-muted"><?= nl2br(htmlspecialchars($v['notas'] ?? '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="11" class="text-center text-muted">Sin verificaciones para los filtros seleccionados.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
