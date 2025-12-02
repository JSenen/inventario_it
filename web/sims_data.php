<?php
require_once 'auth.php';
require_once 'config.php';

// Parámetros DataTables
$draw   = $_GET['draw'] ?? 1;
$start  = $_GET['start'] ?? 0;
$length = $_GET['length'] ?? 25;
$search = $_GET['search']['value'] ?? '';

// Columnas permitidas para ordenar
$columns = ['id', 'numero', 'iccid', 'operador', 'tarifa', 'estado'];
$orderColIndex = $_GET['order'][0]['column'] ?? 0;
$orderDir      = $_GET['order'][0]['dir'] ?? 'asc';
$orderColumn   = $columns[$orderColIndex] ?? 'id';

// Filtro básico
$where = '';
$params = [];

if (!empty($search)) {
    $where = "WHERE numero LIKE :search 
              OR iccid LIKE :search 
              OR operador LIKE :search 
              OR tarifa LIKE :search";
    $params[':search'] = "%$search%";
}

// Total registros
$totalStmt = $pdo->query("SELECT COUNT(*) FROM sims");
$recordsTotal = (int)$totalStmt->fetchColumn();

// Total filtrados
if ($where) {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM sims $where");
    $countStmt->execute($params);
    $recordsFiltered = (int)$countStmt->fetchColumn();
} else {
    $recordsFiltered = $recordsTotal;
}

// Datos
$sql = "SELECT id, numero, iccid, operador, tarifa, estado 
        FROM sims
        $where
        ORDER BY $orderColumn $orderDir
        LIMIT :start, :length";

$stmt = $pdo->prepare($sql);

foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}

$stmt->bindValue(':start', (int)$start, PDO::PARAM_INT);
$stmt->bindValue(':length', (int)$length, PDO::PARAM_INT);

$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Formatear filas
$data = [];
foreach ($rows as $r) {
    $acciones = '
        <a href="sims_ver.php?id=' . (int)$r['id'] . '" class="btn btn-sm btn-info">Ver</a>
        <a href="sims_editar.php?id=' . (int)$r['id'] . '" class="btn btn-sm btn-warning">Editar</a>
    ';

    $data[] = [
        'id'       => $r['id'],
        'numero'   => htmlspecialchars($r['numero']),
        'iccid'    => htmlspecialchars($r['iccid']),
        'operador' => htmlspecialchars($r['operador']),
        'tarifa'   => htmlspecialchars($r['tarifa']),
        'estado'   => htmlspecialchars($r['estado']),
        'acciones' => $acciones
    ];
}

echo json_encode([
    'draw'            => (int)$draw,
    'recordsTotal'    => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data'            => $data
]);
