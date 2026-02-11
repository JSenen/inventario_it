<?php
require_once 'auth.php';
require_once 'config.php';

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=telefonos.xls");
header("Pragma: no-cache");
header("Expires: 0");

// Columnas
echo "ID\tEtiqueta\tMarca\tModelo\tNúmero de Serie\tIMEI\tUsuario Asignado\tDepartamento\tSIM Número\tSIM Operador\tEstado\n";


// ---- Filtros (mismos que en telefonos_data.php) ----
$whereParts = [];
$params = [];

$search = $_GET['search'] ?? '';
if ($search !== '') {
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

$sql = "SELECT
            t.id, t.etiqueta, t.marca, t.modelo, t.numero_serie, t.imei,
            t.usuario_asignado, t.departamento, t.estado,
            s.numero AS sim_numero, s.operador AS sim_operador
        $fromClause
        $where
        ORDER BY t.id ASC";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->execute();

while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

    $imei = $r['imei'] ?? '';
    $imeiExcel = $imei !== '' ? '="' . str_replace('"', '""', $imei) . '"' : '';

    $simNumero = $r['sim_numero'] ?? '';
    $simNumeroExcel = $simNumero !== '' ? '="' . str_replace('"', '""', $simNumero) . '"' : '-';
    echo
  $r['id'] . "\t" .
  ($r['etiqueta'] ?? '') . "\t" .
  ($r['marca'] ?? '') . "\t" .
  ($r['modelo'] ?? '') . "\t" .
  ($r['numero_serie'] ?? '-') . "\t" .
  $imeiExcel . "\t" .            // 👈 IMEI como texto
  ($r['usuario_asignado'] ?? '') . "\t" .
  ($r['departamento'] ?? '') . "\t" .
  $simNumeroExcel . "\t" .       // 👈 (opcional) SIM como texto
  ($r['sim_operador'] ?? '-') . "\t" .
  ($r['estado'] ?? '') . "\n";

}
exit;
