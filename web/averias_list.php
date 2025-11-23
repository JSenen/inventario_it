<?php
require_once 'auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/header.php';


$estado_filtro = $_GET['estado'] ?? 'TODAS';
$busqueda      = trim($_GET['busqueda'] ?? '');
$equipo_id     = isset($_GET['equipo_id']) ? (int)$_GET['equipo_id'] : 0;

// Consulta base
$sql = "SELECT a.*,
               e.hostname      AS nombre_equipo,
               e.numero_serie  AS numero_serie,
               ip.ip           AS ip_principal
        FROM averias a
        JOIN equipos e ON e.id = a.equipo_id
        LEFT JOIN ips_equipos ip 
            ON ip.equipo_id = e.id AND ip.es_principal = 1
        WHERE 1=1";

$params = [];

// 🔹 Filtrar por equipo si viene en la URL
if ($equipo_id > 0) {
    $sql .= " AND a.equipo_id = :equipo_id";
    $params[':equipo_id'] = $equipo_id;
}

// Filtro por estado
if ($estado_filtro === 'ABIERTA' || $estado_filtro === 'CERRADA') {
    $sql .= " AND a.estado = :estado";
    $params[':estado'] = $estado_filtro;
}

// Filtro por búsqueda
if ($busqueda !== '') {
    $sql .= " AND (
                a.id = :id_busqueda_exacto
                OR e.hostname      LIKE :busqueda
                OR e.numero_serie  LIKE :busqueda
                OR a.num_asunto    LIKE :busqueda
                OR a.tipo_averia   LIKE :busqueda
                OR a.empresa_ext   LIKE :busqueda
            )";

    // Si el texto es numérico, lo usamos como posible ID
    $id_busqueda = ctype_digit($busqueda) ? (int)$busqueda : 0;
    $params[':id_busqueda_exacto'] = $id_busqueda;

    $params[':busqueda'] = '%' . $busqueda . '%';
}

$sql .= " ORDER BY a.fecha_creacion DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$averias = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de averías</title>
    <link rel="stylesheet" href="css/bootstrap.min.css"><!-- o la que uses -->
</head>
<body>
<div class="container-fluid mt-4">
    <h1>Gestión de averías</h1>

    <form method="get" class="mb-3">
        <label for="estado">Filtrar por estado:</label>
        <select name="estado" id="estado" onchange="this.form.submit()">
            <option value="TODAS"   <?= $estado_filtro === 'TODAS' ? 'selected' : '' ?>>Todas</option>
            <option value="ABIERTA" <?= $estado_filtro === 'ABIERTA' ? 'selected' : '' ?>>Abiertas</option>
            <option value="CERRADA" <?= $estado_filtro === 'CERRADA' ? 'selected' : '' ?>>Cerradas</option>
        </select>
    </form>
    <div class="row mb-3">
    <div class="col-md-4 ms-auto">
        <label for="buscarAverias" class="form-label">Buscar en averías:</label>
        <input type="text"
               id="buscarAverias"
               class="form-control"
               placeholder="Escribe cualquier término (equipo, IP, serie, asunto...)"
               onkeyup="filtrarAverias()">
    </div>
</div>


   <table id="tablaAverias" class="table table-striped table-sm" style="width: 100% !important;">


        <thead>
<tr>
    <th>Asunto GATI</th>
    <th>Equipo</th>
    <th>IP</th>
    <th>Nº Serie</th>
    <th>Tipo avería</th>
    <th>Nº asunto externo</th>
    <th>Empresa externa</th>
    <th>Fecha apertura</th>
    <th>Fecha cierre</th>
    <th>Estado</th>
    <th>Acciones</th>
</tr>
</thead>

        <tbody>
        <?php foreach ($averias as $av): ?>
            <tr>
                <td>GATI-<?= htmlspecialchars($av['id']) ?></td>
                <td><?= htmlspecialchars($av['nombre_equipo'] ?? '') ?></td>
                <td><?= htmlspecialchars($av['ip_equipo'] ?? '') ?></td>
                <td><?= htmlspecialchars($av['numero_serie'] ?? '') ?></td>
                <td><?= htmlspecialchars($av['tipo_averia']) ?></td>
                <td><?= htmlspecialchars($av['num_asunto']) ?></td>
                <td><?= htmlspecialchars($av['empresa_ext']) ?></td>
                <td><?= htmlspecialchars($av['fecha_creacion']) ?></td>
                <td><?= htmlspecialchars($av['fecha_cierre'] ?? '-') ?></td>
                <td>
                    <?php if ($av['estado'] === 'ABIERTA'): ?>
                        <span class="badge bg-danger">ABIERTA</span>
                    <?php else: ?>
                        <span class="badge bg-success">CERRADA</span>
                    <?php endif; ?>
                </td>
                <td>
                    <!-- Ver parte PDF -->
                    <a class="btn btn-sm btn-primary"
                       href="averia_parte.php?id=<?= $av['id'] ?>"
                       target="_blank">
                        Parte PDF
                    </a>

                    <!-- Ver equipo -->
                    <a class="btn btn-sm btn-secondary"
                       href="equipo_ver.php?id=<?= $av['equipo_id'] ?>">
                        Ver equipo
                    </a>

                    <!-- Cerrar avería (solo si está abierta) -->
                    <?php if ($av['estado'] === 'ABIERTA'): ?>
                        <a class="btn btn-sm btn-success"
                           href="averia_cerrar.php?id=<?= $av['id'] ?>"
                           onclick="return confirm('¿Cerrar esta avería?');">
                            Cerrar
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
function filtrarAverias() {
    const input  = document.getElementById('buscarAverias');
    const filtro = input.value.toLowerCase();
    const tabla  = document.getElementById('tablaAverias');
    const filas  = tabla.getElementsByTagName('tr');

    // Empieza en 1 para saltarse la fila de cabecera
    for (let i = 1; i < filas.length; i++) {
        const fila   = filas[i];
        const texto  = fila.textContent.toLowerCase();

        if (texto.indexOf(filtro) > -1) {
            fila.style.display = '';
        } else {
            fila.style.display = 'none';
        }
    }
}
</script>

</body>
</html>
