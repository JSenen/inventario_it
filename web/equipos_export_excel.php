<?php
require_once __DIR__ . '/config.php';

// Recuperamos los mismos parámetros que DataTables
$req = $_GET;

$searchValue = trim($req['search']['value'] ?? '');
$estado = $req['estado'] ?? '';
$tipo   = $req['tipo']   ?? '';

// WHERE dinámico (igual que en equipos_export.php)
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

// Consulta SIN LIMIT → exporta TODO lo que cumpla filtros/búsqueda
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

// Cabeceras para que el navegador lo trate como Excel
$filename = "equipos_export_" . date("Ymd_His") . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Cache-Control: max-age=0");

// Importante para caracteres especiales
echo "\xEF\xBB\xBF";

// Comenzamos una tabla HTML que Excel entiende perfectamente
?>
<table border="1">
    <thead>
        <tr>
            <th>ID</th>
            <th>Número serie</th>
            <th>Tipo</th>
            <th>Marca</th>
            <th>Modelo</th>
            <th>Usuario asignado</th>
            <th>Departamento</th>
            <th>Hostname / Servicio</th>
            <th>Ubicación</th>
            <th>IP principal</th>
            <th>Red</th>
            <th>Fecha compra</th>
            <th>Fecha baja</th>
            <th>Proveedor</th>
            <th>Coste</th>
            <th>Estado</th>
            <th>Notas</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
            <tr>
                <td><?= htmlspecialchars($row['id']) ?></td>
                <td><?= htmlspecialchars($row['numero_serie'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['tipo'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['marca'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['modelo'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['usuario_asignado'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['departamento'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['hostname'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['ubicacion'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['ip_principal'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['red_nombre'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['fecha_compra'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['fecha_baja'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['proveedor'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['coste'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['estado'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['notas'] ?? '') ?></td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>
<?php
exit;
