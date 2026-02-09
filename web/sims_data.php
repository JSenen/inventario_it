<?php
require_once 'auth.php';
require_once 'config.php';

function jsonError(string $msg): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $msg]);
    exit;
}

// Parámetros DataTables
$draw   = $_GET['draw'] ?? 1;
$start  = $_GET['start'] ?? 0;
$length = $_GET['length'] ?? 25;
$search = $_GET['search']['value'] ?? '';

// Columnas permitidas para ordenar
$columns = ['id', 'etiqueta', 'numero', 'iccid', 'operador', 'puk', 'estado'];
$orderColIndex = $_GET['order'][0]['column'] ?? 0;
$orderDir      = $_GET['order'][0]['dir'] ?? 'asc';
$orderColumn   = $columns[$orderColIndex] ?? 'id';

// Filtro básico
$where = '';
$params = [];

$whereParts = [];
$params     = [];

if (!empty($search)) {
    $whereParts[] = "(etiqueta LIKE :search 
              OR numero LIKE :search 
              OR iccid LIKE :search 
              OR operador LIKE :search 
              OR puk LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($_GET['estado'] ?? '')) {
    $whereParts[] = "estado = :estado";
    $params[':estado'] = $_GET['estado'];
}

if (!empty($_GET['operador'] ?? '')) {
    $whereParts[] = "operador = :operador";
    $params[':operador'] = $_GET['operador'];
}

$where = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';

try {
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
    $sql = "SELECT id, etiqueta, numero, iccid, operador, puk, estado 
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
} catch (Exception $e) {
    jsonError('Error en consulta: ' . $e->getMessage());
}

// Formatear filas
$data = [];
foreach ($rows as $r) {
    $acciones = '
        <a href="sims_ver.php?id=' . (int)$r['id'] . '" class="btn btn-sm btn-info">Ver</a>
        <a href="sims_editar.php?id=' . (int)$r['id'] . '" class="btn btn-sm btn-warning">Editar</a>
    ';

    $data[] = [
        'id'       => $r['id'],
        'etiqueta' => htmlspecialchars($r['etiqueta'] ?? ''),
        'numero'   => htmlspecialchars($r['numero']),
        'iccid'    => htmlspecialchars($r['iccid']),
        'operador' => htmlspecialchars($r['operador']),
        'puk'      => htmlspecialchars($r['puk']),
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
