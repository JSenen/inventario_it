<?php
require_once 'auth.php';
require_once 'config.php';
require_once __DIR__ . '/includes/sims_schema.php';

ensureSimsSchema($pdo);

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=sims.xls");
header("Pragma: no-cache");
header("Expires: 0");

// Columnas

echo "ID\tEtiqueta\tNúmero\tNúmero corto\tICCID\tOperador\tPUK\tEstado\n";


// Filtros
$whereParts = [];
$params = [];

if (!empty($_GET['search'] ?? '')) {
    $whereParts[] = "(etiqueta LIKE :search 
              OR numero LIKE :search 
              OR numero_corto LIKE :search
              OR iccid LIKE :search 
              OR operador LIKE :search 
              OR puk LIKE :search)";
    $params[':search'] = "%" . $_GET['search'] . "%";
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

$sql = "SELECT id, etiqueta, numero, numero_corto, iccid, operador, puk, estado
        FROM sims
        $where
        ORDER BY id ASC";

$stmt = $pdo->prepare($sql);

foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}

$stmt->execute();

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

$iccid = $row['iccid'] ?? '';
$iccidExcel = '="' . str_replace('"', '""', $iccid) . '"';

    echo $row['id'] . "\t" .
     ($row['etiqueta'] ?? '') . "\t" .
     ($row['numero'] ?? '') . "\t" .
     ($row['numero_corto'] ?? '') . "\t" .
     $iccidExcel . "\t" .          // 👈 aquí
     ($row['operador'] ?? '') . "\t" .
     ($row['puk'] ?? '') . "\t" .
     ($row['estado'] ?? '') . "\n";

}
exit;
