<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '0');

try {
    // DataTables params
    $req = $_GET ?: $_POST;

    $draw        = (int)($req['draw']   ?? 0);
    $start       = (int)($req['start']  ?? 0);
    $length      = (int)($req['length'] ?? 10);
    $searchValue = trim($req['search']['value'] ?? '');

    // (Opcional) filtros adicionales si los usas en el index
    $estado = $req['estado'] ?? '';
    $tipo   = $req['tipo']   ?? '';

    // Columnas en el mismo orden de la tabla HTML
    $columns = [
        0  => 'e.id',
        1  => 'e.id',            // imagen → no ordenamos
        2  => 'e.numero_serie',
        3  => 'e.tipo',
        4  => 'e.marca',
        5  => 'e.usuario_asignado',
        6  => 'e.hostname',
        7  => 'e.ubicacion',
        8  => 'ip_principal',    // alias
        //9  => 'r.nombre',        // red
        9 => 'e.estado',
        10 => 'e.id',            // acciones
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

    // BÚSQUEDA GLOBAL: en todos los campos relevantes
    if ($searchValue !== '') {
        $params[':search'] = '%' . strtoupper($searchValue) . '%';

        // Lista de columnas en las que queremos buscar
        $searchCols = [
            'e.id',
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
        r.nombre  AS red_nombre,
        s.nombre AS seccion_nombre,

        (
            SELECT COUNT(*)
            FROM pc_monitores pm
            WHERE pm.id_pc = e.id
        ) AS num_monitores
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
        $colImagen   = !empty($row['imagen'])
            ? '<img src="' . htmlspecialchars($row['imagen']) . '" style="width:50px;height:auto;">'
            : '<span class="text-muted">Sin imagen</span>';
        $colNumSerie = htmlspecialchars($row['numero_serie'] ?? '');
        $colTipo     = htmlspecialchars($row['tipo'] ?? '');

        $colMarcaModelo =
            '<strong>' . htmlspecialchars($row['marca'] ?? '') . '</strong><br>' .
            '<small class="text-muted">' . htmlspecialchars($row['modelo'] ?? '') . '</small>';

        $colUsuarioDepto =
            htmlspecialchars($row['usuario_asignado'] ?? '') . '<br>' .
            '<small class="text-muted">' . htmlspecialchars($row['departamento'] ?? '') . '</small>'. '<br>' .
            '<small class="text-muted">' . htmlspecialchars($row['seccion_nombre'] ?? '') . '</small>';

        $colServicio  = htmlspecialchars($row['hostname'] ?? '');
        $colUbicacion = htmlspecialchars($row['ubicacion'] ?? '');
        $colIp        = htmlspecialchars($row['ip_principal'] ?? '');
        //$colRed       = htmlspecialchars($row['red_nombre'] ?? '');
        $colMonitores = '';
$numMon = (int)($row['num_monitores'] ?? 0);

if (in_array($row['tipo'], ['PC', 'PORTÁTIL', 'PORTATIL'])) {
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

        $colEstado    = htmlspecialchars($row['estado'] ?? '');

        $colAcciones =
            '<div class="btn-group btn-group-sm">
                <a href="equipo_ver.php?id=' . $row['id'] . '" class="btn btn-outline-primary">Ver</a>
                <a href="equipo_editar.php?id=' . $row['id'] . '" class="btn btn-outline-secondary">Editar</a>
                <a href="equipo_borrar.php?id=' . $row['id'] . '" class="btn btn-outline-danger"
                   onclick="return confirm(\'¿Seguro que quieres eliminar este equipo?\');">
                   Borrar
                </a>
            </div>';

        $data[] = [
            $colId,
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
