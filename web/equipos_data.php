<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/verificaciones.php';

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '0');

try {
    ensureTablaVerificaciones($pdo);

    // DataTables params
    $req = $_GET ?: $_POST;

    $draw        = (int)($req['draw']   ?? 0);
    $start       = (int)($req['start']  ?? 0);
    $length      = (int)($req['length'] ?? 10);
    $searchValue = trim($req['search']['value'] ?? '');

    // (Opcional) filtros adicionales si los usas en el index
    $estado     = $req['estado'] ?? '';
    $tipo       = $req['tipo']   ?? '';
    $ubicacion  = $req['ubicacion'] ?? '';
    $seccion_id = $req['seccion_id'] ?? '';
    $etiqueta_estado = $req['etiqueta_estado'] ?? '';

    // Columnas en el mismo orden de la tabla HTML
    $columns = [
        0  => 'e.id',
        1  => 'e.etiqueta',
        2  => 'e.id',            // imagen → no ordenamos
        3  => 'e.numero_serie',
        4  => 'e.tipo',
        5  => 'e.marca',
        6  => 'e.usuario_asignado',
        7  => 'e.hostname',
        8  => 'e.ubicacion',
        9  => 'ip_principal',    // alias
        //10  => 'r.nombre',        // red
        10 => 'num_monitores',
        11 => 'ultima_renovacion_fecha',
        12 => 'ultima_verificacion_fecha',
        13 => 'e.estado',
        14 => 'e.id',            // acciones
    ];

    // TOTAL SIN FILTROS (solo tabla equipos)
    $stmtTotal = $pdo->query("SELECT COUNT(*) FROM equipos");
    $recordsTotal = (int)$stmtTotal->fetchColumn();

    // WHERE dinámico
    $where  = [];
    $params = [];

    // Filtros de estado / tipo si existen
    if ($estado !== '') {
        $where[] = 'e.estado = :estado';
        $params[':estado'] = $estado;
    }
    if ($tipo !== '') {
        $where[] = 'e.tipo = :tipo';
        $params[':tipo'] = $tipo;
    }
    if ($ubicacion !== '') {
        $where[] = 'e.ubicacion = :ubicacion';
        $params[':ubicacion'] = $ubicacion;
    }
    if ($seccion_id !== '') {
        $where[] = 'e.seccion_id = :seccion_id';
        $params[':seccion_id'] = (int)$seccion_id;
    }
    if ($etiqueta_estado === 'con') {
        $where[] = "(e.etiqueta IS NOT NULL AND e.etiqueta <> '')";
    } elseif ($etiqueta_estado === 'sin') {
        $where[] = "(e.etiqueta IS NULL OR e.etiqueta = '')";
    }

    // BÚSQUEDA GLOBAL: en todos los campos relevantes
    if ($searchValue !== '') {
        $params[':search'] = '%' . strtoupper($searchValue) . '%';

        // Lista de columnas en las que queremos buscar
        $searchCols = [
            'e.id',
            'e.etiqueta',
            'e.tipo',
            'e.marca',
            'e.modelo',
            'e.numero_serie',
            'e.hostname',
            'e.usuario_asignado',
            'e.departamento',
            'e.ubicacion',
            'e.fecha_compra',
            'e.fecha_baja',
            'e.proveedor',
            'e.coste',
            'e.estado',
            'e.notas',
            'ip.ip',
            'ip.mac',
            //'r.nombre',
        ];

        $orParts = [];
        foreach ($searchCols as $col) {
            // CAST a CHAR y UPPER para que LIKE funcione bien con todo
            $orParts[] = "UPPER(CAST($col AS CHAR)) LIKE :search";
        }

        $where[] = '(' . implode(' OR ', $orParts) . ')';
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // ORDEN
    $orderSql = "ORDER BY e.id ASC";
    if (isset($req['order'][0]['column'])) {
        $colIndex = (int)$req['order'][0]['column'];
        $dir      = strtoupper($req['order'][0]['dir'] ?? 'ASC');
        if (isset($columns[$colIndex]) && in_array($dir, ['ASC', 'DESC'], true)) {
            $orderSql = "ORDER BY {$columns[$colIndex]} $dir";
        }
    }

    // TOTAL FILTRADO (con JOINs)
    $sqlCount = "
        SELECT COUNT(*)
        FROM equipos e
        LEFT JOIN ips_equipos ip ON ip.equipo_id = e.id AND ip.es_principal = 1
        LEFT JOIN redes r ON r.id = ip.red_id
        $whereSql
    ";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $recordsFiltered = (int)$stmtCount->fetchColumn();

    // CONSULTA PRINCIPAL
   // CONSULTA PRINCIPAL
$sqlData = "
    SELECT
        e.*,
        ip.ip     AS ip_principal,
        ip.mac    AS ip_mac,
        r.nombre  AS red_nombre,
        s.nombre AS seccion_nombre,

        (
            SELECT COUNT(*)
            FROM pc_monitores pm
            WHERE pm.id_pc = e.id
        ) AS num_monitores
        ,
        (
            SELECT rr.id
            FROM renovaciones rr
            WHERE rr.equipo_old_id = e.id OR rr.equipo_new_id = e.id
            ORDER BY rr.fecha DESC
            LIMIT 1
        ) AS ultima_renovacion_id,
        (
            SELECT rr.fecha
            FROM renovaciones rr
            WHERE rr.equipo_old_id = e.id OR rr.equipo_new_id = e.id
            ORDER BY rr.fecha DESC
            LIMIT 1
        ) AS ultima_renovacion_fecha,
        (
            SELECT rr.firmado
            FROM renovaciones rr
            WHERE rr.equipo_old_id = e.id OR rr.equipo_new_id = e.id
            ORDER BY rr.fecha DESC
            LIMIT 1
        ) AS ultima_renovacion_firmado,
        (
            SELECT ev.fecha
            FROM equipos_verificaciones ev
            WHERE ev.equipo_id = e.id
            ORDER BY ev.fecha DESC
            LIMIT 1
        ) AS ultima_verificacion_fecha,
        (
            SELECT ev.ubicacion
            FROM equipos_verificaciones ev
            WHERE ev.equipo_id = e.id
            ORDER BY ev.fecha DESC
            LIMIT 1
        ) AS ultima_verificacion_ubicacion,
        (
            SELECT ev.estado_equipo
            FROM equipos_verificaciones ev
            WHERE ev.equipo_id = e.id
            ORDER BY ev.fecha DESC
            LIMIT 1
        ) AS ultima_verificacion_estado
    FROM equipos e
    LEFT JOIN ips_equipos ip ON ip.equipo_id = e.id AND ip.es_principal = 1
    LEFT JOIN redes r        ON r.id = ip.red_id
    LEFT JOIN secciones s    ON s.id = e.seccion_id
    $whereSql
    $orderSql
    LIMIT :start, :length
";


    $stmt = $pdo->prepare($sqlData);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':start',  $start,  PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    $stmt->execute();



    $data = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

        $colId       = htmlspecialchars($row['id']);
        $rutaImagen = (string)($row['imagen'] ?? '');
        $tieneImagen = ($rutaImagen !== '') && is_file(__DIR__ . '/' . ltrim($rutaImagen, '/'));
        $colImagen   = $tieneImagen
            ? '<img src="' . htmlspecialchars($rutaImagen) . '" style="width:50px;height:auto;">'
            : '<span class="text-muted">Sin imagen</span>';
        $colNumSerie = htmlspecialchars($row['numero_serie'] ?? '');
        $colTipo     = htmlspecialchars($row['tipo'] ?? '');

        $infoTooltip = [];
        if (!empty($row['numero_serie'])) {
            $infoTooltip[] = 'SN: ' . $row['numero_serie'];
        }
        if (!empty($row['hostname'])) {
            $infoTooltip[] = 'Servicio: ' . $row['hostname'];
        }
        if (!empty($row['ubicacion'])) {
            $infoTooltip[] = 'Ubicación: ' . $row['ubicacion'];
        }
        $tooltipText = htmlspecialchars(implode(' | ', $infoTooltip));

        $colMarcaModelo =
            '<div data-bs-toggle="tooltip" title="' . $tooltipText . '">' .
            '<strong>' . htmlspecialchars($row['marca'] ?? '') . '</strong><br>' .
            '<small class="text-muted">' . htmlspecialchars($row['modelo'] ?? '') . '</small>' .
            '</div>';

        $usuarioAsignado = trim((string)($row['usuario_asignado'] ?? ''));
        $usuarioClass = $usuarioAsignado === '' ? 'dato-contacto-destacado dato-contacto-destacado-vacio' : 'dato-contacto-destacado';
        $colUsuarioDepto =
            '<span class="' . $usuarioClass . '">' . htmlspecialchars($usuarioAsignado !== '' ? $usuarioAsignado : '-') . '</span><br>' .
            '<small class="text-muted">' . htmlspecialchars($row['departamento'] ?? '') . '</small>'. '<br>' .
            '<small class="text-muted">' . htmlspecialchars($row['seccion_nombre'] ?? '') . '</small>';

        $colServicio  = htmlspecialchars($row['hostname'] ?? '');
        $colUbicacion = htmlspecialchars($row['ubicacion'] ?? '');
        $colIp        = '';
        if (!empty($row['ip_principal'])) {
            $ipTitle = !empty($row['ip_mac']) ? 'MAC: ' . htmlspecialchars($row['ip_mac']) : 'IP principal';
            $colIp = '<span class="badge rounded-pill bg-success d-inline-flex align-items-center gap-1" data-bs-toggle="tooltip" title="' . $ipTitle . '">' .
                     '<i class="bi bi-check-circle-fill"></i>' .
                     htmlspecialchars($row['ip_principal']) .
                     '</span>';
        } else {
            $colIp = '<span class="badge rounded-pill bg-warning text-dark d-inline-flex align-items-center gap-1" data-bs-toggle="tooltip" title="Sin IP principal">' .
                     '<i class="bi bi-exclamation-triangle-fill"></i>' .
                     'Sin IP' .
                     '</span>';
        }
        //$colRed       = htmlspecialchars($row['red_nombre'] ?? '');
        $colMonitores = '';
        $numMon = (int)($row['num_monitores'] ?? 0);
        $tipoUpper = strtoupper($row['tipo'] ?? '');
        $esEquipoConMonitores = ($tipoUpper === 'PC')
            || (strpos($tipoUpper, 'PORTATIL') !== false)
            || (strpos($tipoUpper, 'PORTÁTIL') !== false)
            || ($tipoUpper === 'PTI');

        if ($esEquipoConMonitores) {
            if ($numMon === 0) {
                $colMonitores = '<span class="badge bg-secondary">0</span>';
            } elseif ($numMon === 1) {
                $colMonitores = '<span class="badge bg-success">1</span>';
            } else {
                $colMonitores = '<span class="badge bg-primary">' . $numMon . '</span>';
            }
        } else {
            // Para monitores, impresoras, etc.
            $colMonitores = '<span class="text-muted">-</span>';
        }

        // Estado con badge e icono
        $estadoRaw = trim((string)($row['estado'] ?? ''));
        $estadoLower = strtolower($estadoRaw);
        $estadoClass = 'bg-secondary';
        $estadoIcon  = 'bi-info-circle-fill';

        switch ($estadoLower) {
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
            case 'almacen':
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

        $colEstado = '<span class="badge rounded-pill ' . $estadoClass . ' d-inline-flex align-items-center gap-1">' .
                     '<i class="bi ' . $estadoIcon . '"></i>' .
                     htmlspecialchars($estadoRaw ?: '-') .
                     '</span>';

        // Última verificación inventario
        $colUltVerif = '<span class="text-muted">—</span>';
        if (!empty($row['ultima_verificacion_fecha'])) {
            $fechaVerif   = new DateTime($row['ultima_verificacion_fecha']);
            $hoy          = new DateTime();
            $diasDiff     = (int)$hoy->diff($fechaVerif)->format('%a');
            $ubicacionVer = $row['ultima_verificacion_ubicacion'] ?? '';
            $estadoVer    = $row['ultima_verificacion_estado'] ?? '';

            $badgeClass = 'bg-danger';
            $badgeText  = 'Fuera de plazo';
            $icon       = 'bi-exclamation-octagon-fill';

            if ($diasDiff <= 60) {
                $badgeClass = 'bg-success';
                $badgeText  = 'Al día';
                $icon       = 'bi-check-circle-fill';
            } elseif ($diasDiff <= 90) {
                $badgeClass = 'bg-warning text-dark';
                $badgeText  = 'Revisar pronto';
                $icon       = 'bi-exclamation-triangle-fill';
            }

            $colUltVerif =
                '<div class="d-flex flex-column gap-1 small">' .
                    '<span class="badge rounded-pill ' . $badgeClass . ' d-inline-flex align-items-center gap-1">' .
                        '<i class="bi ' . $icon . '"></i>' .
                        $badgeText .
                    '</span>' .
                    '<small class="text-muted">' . htmlspecialchars($fechaVerif->format('Y-m-d H:i')) . '</small>';

            if (!empty($ubicacionVer)) {
                $colUltVerif .= '<small class="text-muted">Ubicación: ' . htmlspecialchars($ubicacionVer) . '</small>';
            }
            if (!empty($estadoVer)) {
                $colUltVerif .= '<small class="text-muted">Estado: ' . htmlspecialchars($estadoVer) . '</small>';
            }

            $colUltVerif .= '</div>';
        }

        // Última renovación
        $colUltRenov = '<span class="text-muted">—</span>';
        if (!empty($row['ultima_renovacion_id'])) {
            $fechaRen = $row['ultima_renovacion_fecha'] ? date('Y-m-d H:i', strtotime($row['ultima_renovacion_fecha'])) : '';
            $firmado = (int)($row['ultima_renovacion_firmado'] ?? 0) === 1;
            $badgeClass = $firmado ? 'bg-success' : 'bg-warning text-dark';
            $icon = $firmado ? 'bi-check-circle-fill' : 'bi-clock-fill';
            $badgeText  = $firmado ? 'Firmado' : 'Pendiente';
            $colUltRenov =
                '<div class="d-flex flex-column gap-1">' .
                    '<a href="recibo_renovacion.php?id=' . (int)$row['ultima_renovacion_id'] . '" class="text-decoration-none">Recibo</a>' .
                    '<span class="badge rounded-pill ' . $badgeClass . ' d-inline-flex align-items-center gap-1">' .
                        '<i class="bi ' . $icon . '"></i>' .
                        $badgeText .
                    '</span>' .
                    '<small class="text-muted">' . htmlspecialchars($fechaRen) . '</small>' .
                '</div>';
        }

        $colAcciones =
            '<div class="btn-group btn-group-sm">
                <a href="equipo_ver.php?id=' . $row['id'] . '" class="btn btn-outline-primary">Ver</a>
                <a href="equipo_editar.php?id=' . $row['id'] . '" class="btn btn-outline-secondary">Editar</a>
                <a href="equipo_borrar.php?id=' . $row['id'] . '" class="btn btn-outline-danger"
                   data-confirm-message="¿Seguro que quieres eliminar este equipo?">
                   Borrar
                </a>
            </div>';

        $data[] = [
            $colId,
            $colEtiqueta = !empty($row['etiqueta'])
                ? '<span class="etiqueta-ok">' . htmlspecialchars($row['etiqueta']) . '</span>'
                : '<span class="etiqueta-missing">(sin etiqueta)</span>',
            $colImagen,
            $colNumSerie,
            $colTipo,
            $colMarcaModelo,
            $colUsuarioDepto,
            $colServicio,
            $colUbicacion,
            $colIp,
            //$colRed,
            $colMonitores,
            $colUltRenov,
            $colUltVerif,
            $colEstado,
            $colAcciones,
        ];
    }

    echo json_encode([
        'draw'            => $draw,
        'recordsTotal'    => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data'            => $data,
    ]);

} catch (Throwable $e) {
    echo json_encode([
        'draw'            => $draw ?? 0,
        'recordsTotal'    => 0,
        'recordsFiltered' => 0,
        'data'            => [],
        'error'           => $e->getMessage(),
    ]);
}
