<?php
require_once __DIR__ . '/config.php';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="equipos_export.csv"');

// Abrimos “salida” como si fuera un fichero
$fp = fopen('php://output', 'w');

// Cabecera CSV
fputcsv($fp, [
    'ID',
    'Numero serie',
    'Tipo',
    'Marca',
    'Modelo',
    'Usuario asignado',
    'Departamento',
    'Hostname / Servicio',
    'Ubicacion',
    'IP principal',
    'Red',
    'Fecha compra',
    'Fecha baja',
    'Proveedor',
    'Coste',
    'Estado',
    'Notas',
]);

// Recuperamos los mismos parámetros que envía DataTables
$req = $_GET;

// Búsqueda global
$searchValue = trim($req['search']['value'] ?? '');

// Filtros extra (si los usas en el DataTable)
$estado = $req['estado'] ?? '';
$tipo   = $req['tipo']   ?? '';

// WHERE dinámico
$where  = [];
$params = [];

if ($estado !== '') {
    $where[] = 'e.estado = :estado';
    $params[':estado'] = $estado;
}
if ($tipo !== '') {
    $where[] = 'e.tipo = :tipo';
    $params[':tipo'] = $tipo;
}

// Mismas columnas de búsqueda que en equipos_data.php
if ($searchValue !== '') {
    $params[':search'] = '%' . strtoupper($searchValue) . '%';

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
        'r.nombre',
    ];

    $orParts = [];
    foreach ($searchCols as $col) {
        $orParts[] = "UPPER(CAST($col AS CHAR)) LIKE :search";
    }
    $where[] = '(' . implode(' OR ', $orParts) . ')';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Consulta SIN LIMIT (exporta todo lo que cumpla filtros/búsqueda)
$sql = "
    SELECT
        e.*,
        ip.ip     AS ip_principal,
        r.nombre  AS red_nombre
    FROM equipos e
    LEFT JOIN ips_equipos ip ON ip.equipo_id = e.id AND ip.es_principal = 1
    LEFT JOIN redes r        ON r.id = ip.red_id
    $whereSql
    ORDER BY e.id ASC
";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->execute();

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($fp, [
        $row['id'],
        $row['numero_serie'],
        $row['tipo'],
        $row['marca'],
        $row['modelo'],
        $row['usuario_asignado'],
        $row['departamento'],
        $row['hostname'],
        $row['ubicacion'],
        $row['ip_principal'] ?? '',
        // $row['red_nombre'] ?? '',
        // $row['fecha_compra'],
        // $row['fecha_baja'],
        // $row['proveedor'],
        // $row['coste'],
        $row['estado'],
        $row['notas'],
    ]);
}

fclose($fp);
exit;
