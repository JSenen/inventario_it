<?php
require_once 'auth.php';
require_once 'config.php';

// Opcional: para ver errores si algo falla
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

// Parámetros DataTables
$draw   = isset($_GET['draw']) ? (int)$_GET['draw'] : 1;
$start  = isset($_GET['start']) ? (int)$_GET['start'] : 0;
$length = isset($_GET['length']) ? (int)$_GET['length'] : 25;
$search = $_GET['search']['value'] ?? '';

// Columnas válidas para ordenar
$columns = ['id', 'marca', 'modelo', 'imei', 'usuario_asignado', 'departamento', 'estado'];

$orderColIndex = isset($_GET['order'][0]['column']) ? (int)$_GET['order'][0]['column'] : 0;
$orderDir      = $_GET['order'][0]['dir'] ?? 'asc';
$orderDir      = $orderDir === 'desc' ? 'DESC' : 'ASC';
$orderColumn   = $columns[$orderColIndex] ?? 'id';

// Filtro
$where = '';
$params = [];

if (!empty($search)) {
    $where = "WHERE marca LIKE :search
           OR modelo LIKE :search
           OR imei   LIKE :search
           OR usuario_asignado LIKE :search
           OR departamento LIKE :search";
    $params[':search'] = "%$search%";
}

// Total registros (sin filtro)
$totalStmt = $pdo->query("SELECT COUNT(*) FROM telefonos");
$recordsTotal = (int)$totalStmt->fetchColumn();

// Total filtrados
if ($where) {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM telefonos $where");
    $countStmt->execute($params);
    $recordsFiltered = (int)$countStmt->fetchColumn();
} else {
    $recordsFiltered = $recordsTotal;
}

// Datos
$sql = "SELECT id, marca, modelo, imei, usuario_asignado, departamento, estado
        FROM telefonos
        $where
        ORDER BY $orderColumn $orderDir
        LIMIT :start, :length";

$stmt = $pdo->prepare($sql);

// Bind de filtro si hay
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, PDO::PARAM_STR);
}

$stmt->bindValue(':start', $start, PDO::PARAM_INT);
$stmt->bindValue(':length', $length, PDO::PARAM_INT);

$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Construir array de salida
$data = [];
foreach ($rows as $r) {
    $acciones = '
        <a href="telefonos_ver.php?id=' . (int)$r['id'] . '" class="btn btn-sm btn-info">Ver</a>
        <a href="telefonos_editar.php?id=' . (int)$r['id'] . '" class="btn btn-sm btn-warning">Editar</a>
        <a href="telefono_parte.php?id=' . (int)$r['id'] . '" class="btn btn-sm btn-secondary" target="_blank">Parte</a>
    ';

    $data[] = [
        'id'               => (int)$r['id'],
        'marca'            => htmlspecialchars($r['marca']),
        'modelo'           => htmlspecialchars($r['modelo']),
        'imei'             => htmlspecialchars($r['imei']),
        'usuario_asignado' => htmlspecialchars($r['usuario_asignado']),
        'departamento'     => htmlspecialchars($r['departamento']),
        'estado'           => htmlspecialchars($r['estado']),
        'acciones'         => $acciones
    ];
}

// Respuesta JSON
echo json_encode([
    'draw'            => $draw,
    'recordsTotal'    => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data'            => $data
]);
