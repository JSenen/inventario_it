<?php
require_once __DIR__ . '/config.php';
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Recibir parámetros como DataTables
$req = $_GET;

$searchValue = trim($req['search']['value'] ?? '');

$estado = $req['estado'] ?? '';
$tipo   = $req['tipo']   ?? '';

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

// ----------------------
// CONSULTA SIN LIMIT
// ----------------------
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


// ----------------------
// GENERAR EXCEL
// ----------------------
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Encabezados
$headers = [
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
];

$col = 1;
foreach ($headers as $header) {
    $sheet->setCellValueByColumnAndRow($col, 1, $header);
    $col++;
}

// Filas
$rowNum = 2;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

    $dataRow = [
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
        $row['red_nombre'] ?? '',
        $row['fecha_compra'],
        $row['fecha_baja'],
        $row['proveedor'],
        $row['coste'],
        $row['estado'],
        $row['notas'],
    ];

    $col = 1;
    foreach ($dataRow as $cellValue) {
        $sheet->setCellValueByColumnAndRow($col, $rowNum, $cellValue);
        $col++;
    }

    $rowNum++;
}

// Auto-width
foreach (range('A', $sheet->getHighestColumn()) as $colName) {
    $sheet->getColumnDimension($colName)->setAutoSize(true);
}

// Descargar
$filename = "equipos_export_" . date("Ymd_His") . ".xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
