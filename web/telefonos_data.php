<?php
require_once 'auth.php';
require_once 'config.php';

function jsonError(string $msg): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $msg]);
    exit;
}

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
$columns = [
    't.id',
    't.etiqueta',
    't.marca',
    't.modelo',
    't.numero_serie',
    't.imei',
    't.usuario_asignado',
    't.departamento',
    's.numero',
    's.operador',
    't.estado'
];

$orderColIndex = isset($_GET['order'][0]['column']) ? (int)$_GET['order'][0]['column'] : 0;
$orderDir      = $_GET['order'][0]['dir'] ?? 'asc';
$orderDir      = $orderDir === 'desc' ? 'DESC' : 'ASC';
$orderColumn   = $columns[$orderColIndex] ?? 'id';

// Filtro
$whereParts = [];
$params     = [];

if (!empty($search)) {
    $whereParts[] = "(t.etiqueta LIKE :search
        OR t.marca LIKE :search
        OR t.modelo LIKE :search
        OR t.numero_serie LIKE :search
        OR t.imei LIKE :search
        OR t.usuario_asignado LIKE :search
        OR t.departamento LIKE :search
        OR s.numero LIKE :search
        OR s.operador LIKE :search
        OR s.iccid LIKE :search)";
    $params[':search'] = "%$search%";
}

$estado = $_GET['estado'] ?? '';
if ($estado !== '') {
    $whereParts[] = "t.estado = :estado";
    $params[':estado'] = $estado;
}

$departamento = $_GET['departamento'] ?? '';
if ($departamento !== '') {
    $whereParts[] = "t.departamento = :departamento";
    $params[':departamento'] = $departamento;
}

$operador = $_GET['operador'] ?? '';
if ($operador !== '') {
    $whereParts[] = "s.operador = :operador";
    $params[':operador'] = $operador;
}

$simAsignada = $_GET['sim_asignada'] ?? '';
if ($simAsignada === 'con') {
    $whereParts[] = "ts.sim_id IS NOT NULL";
} elseif ($simAsignada === 'sin') {
    $whereParts[] = "ts.sim_id IS NULL";
}

$where = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';

$fromClause = "
    FROM telefonos t
    LEFT JOIN telefono_sim ts
        ON ts.telefono_id = t.id
       AND ts.fecha_liberacion IS NULL
    LEFT JOIN sims s
        ON s.id = ts.sim_id
";

try {
    // Total registros (sin filtro)
    $totalStmt = $pdo->query("SELECT COUNT(*) FROM telefonos");
    $recordsTotal = (int)$totalStmt->fetchColumn();

    // Total filtrados
    if ($where) {
        $countStmt = $pdo->prepare("SELECT COUNT(DISTINCT t.id) $fromClause $where");
        $countStmt->execute($params);
        $recordsFiltered = (int)$countStmt->fetchColumn();
    } else {
        $recordsFiltered = $recordsTotal;
    }

// Datos
$sql = "SELECT
            t.id,
            t.etiqueta,
            t.marca,
            t.modelo,
            t.numero_serie,
            t.imei,
            t.usuario_asignado,
            t.departamento,
            t.estado,
            s.numero   AS sim_numero,
            s.operador AS sim_operador
        $fromClause
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
} catch (Exception $e) {
    jsonError('Error en consulta: ' . $e->getMessage());
}

// Construir array de salida
$data = [];
foreach ($rows as $r) {
    $acciones = '
        <a href="telefonos_ver.php?id=' . (int)$r['id'] . '" class="btn btn-sm btn-info">Ver</a>
        <a href="telefonos_editar.php?id=' . (int)$r['id'] . '" class="btn btn-sm btn-warning">Editar</a>
        <a href="telefono_parte.php?id=' . (int)$r['id'] . '" class="btn btn-sm btn-secondary" target="_blank">Parte</a>
    ';

    $simNumero   = trim((string)($r['sim_numero'] ?? ''));
    $simOperador = $r['sim_operador'] ?? '';
    $numeroSerie = $r['numero_serie'] ?? '';
    $usuarioAsignado = trim((string)($r['usuario_asignado'] ?? ''));
    $etiquetaTel = trim((string)($r['etiqueta'] ?? ''));

    $data[] = [
        'id'               => (int)$r['id'],
        'etiqueta'         => $etiquetaTel !== '' ? '<span class="etiqueta-ok">' . htmlspecialchars($etiquetaTel) . '</span>' : '<span class="etiqueta-missing">(sin etiqueta)</span>',
        'marca'            => htmlspecialchars($r['marca']),
        'modelo'           => htmlspecialchars($r['modelo']),
        'numero_serie'     => $numeroSerie !== '' ? htmlspecialchars($numeroSerie) : '-',
        'imei'             => htmlspecialchars($r['imei']),
        'usuario_asignado' => '<span class="dato-contacto-destacado' . ($usuarioAsignado === '' ? ' dato-contacto-destacado-vacio' : '') . '">' . htmlspecialchars($usuarioAsignado !== '' ? $usuarioAsignado : '-') . '</span>',
        'departamento'     => htmlspecialchars($r['departamento']),
        'sim_numero'       => '<span class="sim-numero-destacado' . ($simNumero === '' ? ' sim-numero-destacado-vacio' : '') . '">' . htmlspecialchars($simNumero !== '' ? $simNumero : '-') . '</span>',
        'sim_operador'     => $simOperador !== '' ? htmlspecialchars($simOperador) : '-',
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
