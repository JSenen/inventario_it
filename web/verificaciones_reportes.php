<?php
require_once 'auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/verificaciones.php';
require_once __DIR__ . '/includes/logger.php';

ensureTablaVerificaciones($pdo);

$f_ubicacion    = strtoupper(trim($_GET['ubicacion'] ?? ''));
$f_departamento = strtoupper(trim($_GET['departamento'] ?? ''));
$f_seccion      = trim($_GET['seccion'] ?? '');
$f_estado       = trim($_GET['estado'] ?? '');
$f_buscar       = trim($_GET['buscar'] ?? '');
$f_fecha_desde  = trim($_GET['fecha_desde'] ?? '');
$f_fecha_hasta  = trim($_GET['fecha_hasta'] ?? '');
$agrupar        = trim($_GET['agrupar'] ?? 'ninguno');
$exportFormato  = trim($_GET['export'] ?? '');

$agrupaciones = [
    'ninguno'      => ['label' => 'Sin agrupar (detalle)', 'expr' => null],
    'fecha_dia'    => ['label' => 'Fecha (día)',            'expr' => 'DATE(ev.fecha)'],
    'equipo'       => ['label' => 'Equipo',                 'expr' => "CONCAT(COALESCE(NULLIF(e.etiqueta, ''), CONCAT('EQ-', e.id)), ' | SN: ', COALESCE(NULLIF(e.numero_serie, ''), '-'))"],
    'ubicacion'    => ['label' => 'Ubicación',              'expr' => "COALESCE(NULLIF(ev.ubicacion, ''), COALESCE(NULLIF(e.ubicacion, ''), 'SIN_UBICACION'))"],
    'departamento' => ['label' => 'Departamento',           'expr' => "COALESCE(NULLIF(ev.departamento, ''), COALESCE(NULLIF(e.departamento, ''), 'SIN_DEPTO'))"],
    'seccion'      => ['label' => 'Sección',                'expr' => "COALESCE(NULLIF(ev.seccion, ''), COALESCE(NULLIF(s.nombre, ''), 'SIN_SECCION'))"],
    'estado'       => ['label' => 'Estado',                 'expr' => "COALESCE(NULLIF(ev.estado_equipo, ''), 'SIN_ESTADO')"],
    'verificador'  => ['label' => 'Verificador',            'expr' => "COALESCE(NULLIF(ev.usuario_verificador, ''), 'SIN_USUARIO')"],
];

if (!isset($agrupaciones[$agrupar])) {
    $agrupar = 'ninguno';
}

$where = [];
$params = [];

if ($f_ubicacion !== '') {
    $where[] = 'UPPER(COALESCE(NULLIF(ev.ubicacion, \'\'), e.ubicacion)) = :ubicacion';
    $params[':ubicacion'] = $f_ubicacion;
}
if ($f_departamento !== '') {
    $where[] = 'UPPER(COALESCE(NULLIF(ev.departamento, \'\'), e.departamento)) = :departamento';
    $params[':departamento'] = $f_departamento;
}
if ($f_seccion !== '') {
    $where[] = 'COALESCE(NULLIF(ev.seccion, \'\'), s.nombre) = :seccion';
    $params[':seccion'] = $f_seccion;
}
if ($f_estado !== '') {
    $where[] = 'ev.estado_equipo = :estado';
    $params[':estado'] = $f_estado;
}
if ($f_fecha_desde !== '') {
    $where[] = 'DATE(ev.fecha) >= :fecha_desde';
    $params[':fecha_desde'] = $f_fecha_desde;
}
if ($f_fecha_hasta !== '') {
    $where[] = 'DATE(ev.fecha) <= :fecha_hasta';
    $params[':fecha_hasta'] = $f_fecha_hasta;
}
if ($f_buscar !== '') {
    $where[] = "(
        CAST(ev.equipo_id AS CHAR) LIKE :buscar
        OR COALESCE(e.etiqueta, '') LIKE :buscar
        OR COALESCE(e.numero_serie, '') LIKE :buscar
        OR COALESCE(e.marca, '') LIKE :buscar
        OR COALESCE(e.modelo, '') LIKE :buscar
        OR COALESCE(e.usuario_asignado, '') LIKE :buscar
        OR COALESCE(ev.ip, '') LIKE :buscar
        OR COALESCE(ev.usuario_verificador, '') LIKE :buscar
        OR COALESCE(ev.notas, '') LIKE :buscar
    )";
    $params[':buscar'] = '%' . $f_buscar . '%';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$baseFrom = "
    FROM equipos_verificaciones ev
    JOIN equipos e ON e.id = ev.equipo_id
    LEFT JOIN secciones s ON s.id = e.seccion_id
";

$resultados = [];
$totales = ['registros' => 0];

if ($agrupar === 'ninguno') {
    $sql = "
        SELECT
            ev.fecha,
            ev.equipo_id,
            e.etiqueta,
            e.numero_serie,
            e.tipo,
            e.marca,
            e.modelo,
            e.usuario_asignado,
            COALESCE(NULLIF(ev.departamento, ''), e.departamento) AS departamento,
            COALESCE(NULLIF(ev.seccion, ''), s.nombre) AS seccion,
            COALESCE(NULLIF(ev.ubicacion, ''), e.ubicacion) AS ubicacion,
            ev.estado_equipo,
            ev.ip,
            ev.usuario_verificador,
            ev.notas
        $baseFrom
        $whereSql
        ORDER BY ev.fecha DESC
        LIMIT 2000
    ";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    $stmt->execute();
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totales['registros'] = count($resultados);
} else {
    $expr = $agrupaciones[$agrupar]['expr'];
    $sql = "
        SELECT
            $expr AS grupo,
            COUNT(*) AS total,
            MIN(ev.fecha) AS primera_fecha,
            MAX(ev.fecha) AS ultima_fecha
        $baseFrom
        $whereSql
        GROUP BY $expr
        ORDER BY total DESC, ultima_fecha DESC
        LIMIT 2000
    ";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    $stmt->execute();
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $countSql = "
        SELECT COUNT(*) AS total_registros
        $baseFrom
        $whereSql
    ";
    $stmtCount = $pdo->prepare($countSql);
    foreach ($params as $k => $v) {
        $stmtCount->bindValue($k, $v, PDO::PARAM_STR);
    }
    $stmtCount->execute();
    $totales['registros'] = (int)$stmtCount->fetchColumn();
    $totales['grupos'] = count($resultados);
}

if (in_array($exportFormato, ['csv', 'xls', 'pdf'], true)) {
    $fechaArchivo = date('Ymd_His');
    $nombreBase = "verificaciones_{$agrupar}_{$fechaArchivo}";
    logActividad(
        $pdo,
        'EXPORTAR_VERIFICACIONES',
        "Formato={$exportFormato}; agrupar={$agrupar}; buscar={$f_buscar}; desde={$f_fecha_desde}; hasta={$f_fecha_hasta}",
        ['modulo' => 'VERIFICACIONES', 'nivel' => 'INFO']
    );

    if ($exportFormato === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombreBase . '.csv"');

        $out = fopen('php://output', 'w');
        if ($out === false) {
            exit;
        }

        if ($agrupar === 'ninguno') {
            fputcsv($out, ['Fecha', 'Equipo ID', 'Etiqueta', 'Numero Serie', 'Tipo', 'Marca', 'Modelo', 'Usuario', 'Departamento', 'Seccion', 'Ubicacion', 'Estado', 'IP', 'Verificador', 'Notas']);
            foreach ($resultados as $r) {
                fputcsv($out, [
                    $r['fecha'] ?? '',
                    $r['equipo_id'] ?? '',
                    $r['etiqueta'] ?? '',
                    $r['numero_serie'] ?? '',
                    $r['tipo'] ?? '',
                    $r['marca'] ?? '',
                    $r['modelo'] ?? '',
                    $r['usuario_asignado'] ?? '',
                    $r['departamento'] ?? '',
                    $r['seccion'] ?? '',
                    $r['ubicacion'] ?? '',
                    $r['estado_equipo'] ?? '',
                    $r['ip'] ?? '',
                    $r['usuario_verificador'] ?? '',
                    $r['notas'] ?? '',
                ]);
            }
        } else {
            fputcsv($out, ['Agrupacion', 'Valor', 'Total verificaciones', 'Primera fecha', 'Ultima fecha']);
            foreach ($resultados as $r) {
                fputcsv($out, [
                    $agrupaciones[$agrupar]['label'],
                    $r['grupo'] ?? '',
                    $r['total'] ?? 0,
                    $r['primera_fecha'] ?? '',
                    $r['ultima_fecha'] ?? '',
                ]);
            }
        }

        fclose($out);
        exit;
    }

    if ($exportFormato === 'xls') {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombreBase . '.xls"');
        header('Cache-Control: max-age=0');
        echo "\xEF\xBB\xBF";
        ?>
        <table border="1">
            <thead>
                <?php if ($agrupar === 'ninguno'): ?>
                    <tr>
                        <th>Fecha</th>
                        <th>Equipo ID</th>
                        <th>Etiqueta</th>
                        <th>Numero Serie</th>
                        <th>Tipo</th>
                        <th>Marca</th>
                        <th>Modelo</th>
                        <th>Usuario</th>
                        <th>Departamento</th>
                        <th>Seccion</th>
                        <th>Ubicacion</th>
                        <th>Estado</th>
                        <th>IP</th>
                        <th>Verificador</th>
                        <th>Notas</th>
                    </tr>
                <?php else: ?>
                    <tr>
                        <th>Agrupacion</th>
                        <th>Valor</th>
                        <th>Total verificaciones</th>
                        <th>Primera fecha</th>
                        <th>Ultima fecha</th>
                    </tr>
                <?php endif; ?>
            </thead>
            <tbody>
                <?php foreach ($resultados as $r): ?>
                    <?php if ($agrupar === 'ninguno'): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['fecha'] ?? '') ?></td>
                            <td><?= htmlspecialchars((string)($r['equipo_id'] ?? '')) ?></td>
                            <td><?= htmlspecialchars($r['etiqueta'] ?? '') ?></td>
                            <td><?= htmlspecialchars($r['numero_serie'] ?? '') ?></td>
                            <td><?= htmlspecialchars($r['tipo'] ?? '') ?></td>
                            <td><?= htmlspecialchars($r['marca'] ?? '') ?></td>
                            <td><?= htmlspecialchars($r['modelo'] ?? '') ?></td>
                            <td><?= htmlspecialchars($r['usuario_asignado'] ?? '') ?></td>
                            <td><?= htmlspecialchars($r['departamento'] ?? '') ?></td>
                            <td><?= htmlspecialchars($r['seccion'] ?? '') ?></td>
                            <td><?= htmlspecialchars($r['ubicacion'] ?? '') ?></td>
                            <td><?= htmlspecialchars($r['estado_equipo'] ?? '') ?></td>
                            <td><?= htmlspecialchars($r['ip'] ?? '') ?></td>
                            <td><?= htmlspecialchars($r['usuario_verificador'] ?? '') ?></td>
                            <td><?= htmlspecialchars($r['notas'] ?? '') ?></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td><?= htmlspecialchars($agrupaciones[$agrupar]['label']) ?></td>
                            <td><?= htmlspecialchars($r['grupo'] ?? '') ?></td>
                            <td><?= htmlspecialchars((string)($r['total'] ?? 0)) ?></td>
                            <td><?= htmlspecialchars($r['primera_fecha'] ?? '') ?></td>
                            <td><?= htmlspecialchars($r['ultima_fecha'] ?? '') ?></td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        exit;
    }

    if ($exportFormato === 'pdf') {
        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <title>Reporte de verificaciones</title>
            <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
            <style>
                body { padding: 18px; background: #f8f9fa; font-size: 12px; }
                .documento { max-width: 1200px; margin: 0 auto; background: #fff; border: 1px solid #ddd; border-radius: 6px; padding: 18px; }
                .table td, .table th { padding: 4px 6px; }
                .small-kv { font-size: 11px; color: #555; }
                @media print {
                    body { background: #fff; padding: 0; }
                    .no-print { display: none !important; }
                    .documento { border: none; border-radius: 0; padding: 0; max-width: none; }
                }
            </style>
        </head>
        <body>
            <div class="documento">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h2 class="h5 mb-1">Reporte de verificaciones</h2>
                        <div class="small-kv">Generado: <?= htmlspecialchars(date('Y-m-d H:i:s')) ?></div>
                        <div class="small-kv">Agrupación: <?= htmlspecialchars($agrupaciones[$agrupar]['label']) ?></div>
                        <div class="small-kv">Total verificaciones: <?= number_format((int)$totales['registros'], 0, ',', '.') ?></div>
                    </div>
                    <div class="no-print">
                        <button class="btn btn-primary btn-sm" onclick="window.print()">Imprimir / Guardar como PDF</button>
                    </div>
                </div>

                <div class="small-kv mb-2">
                    Filtros:
                    Buscar="<?= htmlspecialchars($f_buscar) ?>",
                    Desde="<?= htmlspecialchars($f_fecha_desde) ?>",
                    Hasta="<?= htmlspecialchars($f_fecha_hasta) ?>",
                    Ubicación="<?= htmlspecialchars($f_ubicacion) ?>",
                    Departamento="<?= htmlspecialchars($f_departamento) ?>",
                    Sección="<?= htmlspecialchars($f_seccion) ?>",
                    Estado="<?= htmlspecialchars($f_estado) ?>"
                </div>

                <?php if ($agrupar === 'ninguno'): ?>
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Equipo ID</th>
                                <th>Etiqueta</th>
                                <th>Nº serie</th>
                                <th>Tipo / Marca / Modelo</th>
                                <th>Usuario</th>
                                <th>Departamento</th>
                                <th>Sección</th>
                                <th>Ubicación</th>
                                <th>Estado</th>
                                <th>IP</th>
                                <th>Verificador</th>
                                <th>Notas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resultados as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['fecha'] ?? '') ?></td>
                                    <td><?= htmlspecialchars((string)($r['equipo_id'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars($r['etiqueta'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($r['numero_serie'] ?? '') ?></td>
                                    <td><?= htmlspecialchars(trim(($r['tipo'] ?? '') . ' ' . ($r['marca'] ?? '') . ' ' . ($r['modelo'] ?? ''))) ?></td>
                                    <td><?= htmlspecialchars($r['usuario_asignado'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($r['departamento'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($r['seccion'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($r['ubicacion'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($r['estado_equipo'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($r['ip'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($r['usuario_verificador'] ?? '') ?></td>
                                    <td><?= nl2br(htmlspecialchars($r['notas'] ?? '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th><?= htmlspecialchars($agrupaciones[$agrupar]['label']) ?></th>
                                <th>Total verificaciones</th>
                                <th>Primera fecha</th>
                                <th>Última fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resultados as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['grupo'] ?? '') ?></td>
                                    <td><?= number_format((int)($r['total'] ?? 0), 0, ',', '.') ?></td>
                                    <td><?= htmlspecialchars($r['primera_fecha'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($r['ultima_fecha'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

    exit;
}

$ubicaciones = $pdo->query("SELECT nombre FROM ubicaciones ORDER BY nombre ASC")->fetchAll(PDO::FETCH_COLUMN);
$departamentos = $pdo->query("SELECT nombre FROM departamentos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_COLUMN);
$secciones = $pdo->query("SELECT id, nombre FROM secciones ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>
<div class="container mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Reportes de verificaciones</h1>
        <div class="small text-muted">Máx. 2.000 filas por consulta</div>
    </div>

    <div class="card mb-3 shadow-sm">
        <div class="card-body">
            <form class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1">Buscar</label>
                    <input type="text" name="buscar" class="form-control form-control-sm" value="<?= htmlspecialchars($f_buscar) ?>" placeholder="Etiqueta, serie, IP, usuario...">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Desde</label>
                    <input type="date" name="fecha_desde" class="form-control form-control-sm" value="<?= htmlspecialchars($f_fecha_desde) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Hasta</label>
                    <input type="date" name="fecha_hasta" class="form-control form-control-sm" value="<?= htmlspecialchars($f_fecha_hasta) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Agrupar por</label>
                    <select name="agrupar" class="form-select form-select-sm">
                        <?php foreach ($agrupaciones as $key => $conf): ?>
                            <option value="<?= htmlspecialchars($key) ?>" <?= $agrupar === $key ? 'selected' : '' ?>>
                                <?= htmlspecialchars($conf['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Ubicación</label>
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
                    <label class="form-label small mb-1">Estado</label>
                    <select name="estado" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach (['Activo','Almacén','Averiado','Baja','Prestado','Privado'] as $estadoOpt): ?>
                            <option value="<?= htmlspecialchars($estadoOpt) ?>" <?= $f_estado === $estadoOpt ? 'selected' : '' ?>>
                                <?= htmlspecialchars($estadoOpt) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-12 d-flex justify-content-end gap-2">
                    <a href="verificaciones_reportes.php" class="btn btn-outline-secondary btn-sm">Limpiar</a>
                    <button type="submit" class="btn btn-primary btn-sm">Consultar</button>
                    <button type="submit" name="export" value="csv" class="btn btn-success btn-sm">Descargar CSV</button>
                    <button type="submit" name="export" value="xls" class="btn btn-success btn-sm">Descargar Excel</button>
                    <button type="submit" name="export" value="pdf" class="btn btn-danger btn-sm">Descargar PDF</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-3 shadow-sm">
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="small text-muted">Agrupación actual</div>
                    <div><strong><?= htmlspecialchars($agrupaciones[$agrupar]['label']) ?></strong></div>
                </div>
                <div class="col-md-4">
                    <div class="small text-muted">Total verificaciones</div>
                    <div><strong><?= number_format((int)$totales['registros'], 0, ',', '.') ?></strong></div>
                </div>
                <div class="col-md-4">
                    <div class="small text-muted">Total filas mostradas</div>
                    <div><strong><?= number_format((int)count($resultados), 0, ',', '.') ?></strong></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <?php if ($agrupar === 'ninguno'): ?>
                    <table class="table table-sm table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Equipo</th>
                                <th>Serie</th>
                                <th>Tipo / Modelo</th>
                                <th>Usuario</th>
                                <th>Departamento</th>
                                <th>Sección</th>
                                <th>Ubicación</th>
                                <th>Estado</th>
                                <th>IP</th>
                                <th>Verificador</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($resultados): ?>
                                <?php foreach ($resultados as $r): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($r['fecha'] ?? '') ?></td>
                                        <td>
                                            <a href="equipo_ver.php?id=<?= (int)($r['equipo_id'] ?? 0) ?>">
                                                <?= htmlspecialchars(($r['etiqueta'] ?? '') !== '' ? $r['etiqueta'] : ('EQ-' . (int)($r['equipo_id'] ?? 0))) ?>
                                            </a>
                                        </td>
                                        <td><?= htmlspecialchars($r['numero_serie'] ?? '') ?></td>
                                        <td><?= htmlspecialchars(trim(($r['tipo'] ?? '') . ' ' . ($r['marca'] ?? '') . ' ' . ($r['modelo'] ?? ''))) ?></td>
                                        <td><?= htmlspecialchars($r['usuario_asignado'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($r['departamento'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($r['seccion'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($r['ubicacion'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($r['estado_equipo'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($r['ip'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($r['usuario_verificador'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="11" class="text-center text-muted">Sin resultados para los filtros seleccionados.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <table class="table table-sm table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th><?= htmlspecialchars($agrupaciones[$agrupar]['label']) ?></th>
                                <th>Total verificaciones</th>
                                <th>Primera fecha</th>
                                <th>Última fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($resultados): ?>
                                <?php foreach ($resultados as $r): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($r['grupo'] ?? '') ?></td>
                                        <td><?= number_format((int)($r['total'] ?? 0), 0, ',', '.') ?></td>
                                        <td><?= htmlspecialchars($r['primera_fecha'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($r['ultima_fecha'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center text-muted">Sin resultados para los filtros seleccionados.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
