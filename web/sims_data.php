<?php
require_once 'auth.php';
require_once 'config.php';
require_once __DIR__ . '/includes/sims_schema.php';

ensureSimsSchema($pdo);

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
$columns = ['s.id', 's.etiqueta', 's.numero', 's.numero_corto', 's.iccid', 's.operador', 's.puk', 's.estado'];
$orderColIndex = $_GET['order'][0]['column'] ?? 0;
$orderDir      = $_GET['order'][0]['dir'] ?? 'asc';
$orderColumn   = $columns[$orderColIndex] ?? 'id';

// Filtro básico
$where = '';
$params = [];

$whereParts = [];
$params     = [];

if (!empty($search)) {
    $whereParts[] = "(s.etiqueta LIKE :search 
              OR s.numero LIKE :search 
              OR s.numero_corto LIKE :search
              OR s.iccid LIKE :search 
              OR s.operador LIKE :search 
              OR s.puk LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($_GET['estado'] ?? '')) {
    $whereParts[] = "s.estado = :estado";
    $params[':estado'] = $_GET['estado'];
}

if (!empty($_GET['operador'] ?? '')) {
    $whereParts[] = "s.operador = :operador";
    $params[':operador'] = $_GET['operador'];
}

$where = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';

try {
    $fromClause = "
        FROM sims s
        LEFT JOIN telefono_sim ts
            ON ts.id = (
                SELECT ts2.id
                FROM telefono_sim ts2
                WHERE ts2.sim_id = s.id
                  AND ts2.fecha_liberacion IS NULL
                ORDER BY ts2.fecha_asignacion DESC, ts2.id DESC
                LIMIT 1
            )
        LEFT JOIN telefonos t ON t.id = ts.telefono_id
    ";

    // Total registros
    $totalStmt = $pdo->query("SELECT COUNT(*) FROM sims");
    $recordsTotal = (int)$totalStmt->fetchColumn();

    // Total filtrados
    if ($where) {
        $countStmt = $pdo->prepare("SELECT COUNT(*) $fromClause $where");
        $countStmt->execute($params);
        $recordsFiltered = (int)$countStmt->fetchColumn();
    } else {
        $recordsFiltered = $recordsTotal;
    }

    // Datos
    $sql = "SELECT s.id, s.etiqueta, s.numero, s.numero_corto, s.iccid, s.operador, s.puk, s.estado, t.id AS telefono_id
            $fromClause
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

    $telefonoId = isset($r['telefono_id']) ? (int)$r['telefono_id'] : 0;
    if ($telefonoId > 0) {
        $acciones .= '
            <a
                href="sim_liberar.php?sim_id=' . (int)$r['id'] . '&return=' . rawurlencode('sims.php') . '"
                class="btn btn-sm btn-outline-danger"
                data-confirm-message="Se liberara esta SIM del telefono actual. Continuar?"
            >Liberar</a>
        ';
    }

    $etiquetaSim = trim((string)($r['etiqueta'] ?? ''));
    $data[] = [
        'id'       => $r['id'],
        'etiqueta' => $etiquetaSim !== '' ? '<span class="etiqueta-ok">' . htmlspecialchars($etiquetaSim) . '</span>' : '<span class="etiqueta-missing">(sin etiqueta)</span>',
        'numero'   => '<span class="sim-numero-destacado">' . htmlspecialchars((string)($r['numero'])) . '</span>',
        'numero_corto' => '<span class="sim-numero-destacado">' . htmlspecialchars((string)($r['numero_corto'] ?? '')). '</span>',
        'iccid'    => htmlspecialchars((string)($r['iccid'])),
        'operador' => htmlspecialchars((string)($r['operador'])),
        'puk'      => htmlspecialchars((string)($r['puk'])),
        'estado'   => htmlspecialchars((string)($r['estado'])),
        'acciones' => $acciones
    ];
}

echo json_encode([
    'draw'            => (int)$draw,
    'recordsTotal'    => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data'            => $data
]);
