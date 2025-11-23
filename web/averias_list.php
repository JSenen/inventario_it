<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/header.php';

$estado_filtro = $_GET['estado'] ?? 'TODAS';

$sql = "SELECT a.*,
               e.hostname      AS nombre_equipo,
               e.numero_serie  AS numero_serie
        FROM averias a
        JOIN equipos e ON e.id = a.equipo_id";

$params = [];

if ($estado_filtro === 'ABIERTA' || $estado_filtro === 'CERRADA') {
    $sql .= " WHERE a.estado = :estado";
    $params[':estado'] = $estado_filtro;
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
<div class="container mt-4">
    <h1>Gestión de averías</h1>

    <form method="get" class="mb-3">
        <label for="estado">Filtrar por estado:</label>
        <select name="estado" id="estado" onchange="this.form.submit()">
            <option value="TODAS"   <?= $estado_filtro === 'TODAS' ? 'selected' : '' ?>>Todas</option>
            <option value="ABIERTA" <?= $estado_filtro === 'ABIERTA' ? 'selected' : '' ?>>Abiertas</option>
            <option value="CERRADA" <?= $estado_filtro === 'CERRADA' ? 'selected' : '' ?>>Cerradas</option>
        </select>
    </form>

    <table class="table table-striped table-sm">
        <thead>
        <tr>
            <th>ID</th>
            <th>Equipo</th>
            <th>IP</th>
            <th>Nº Serie</th>
            <th>Tipo avería</th>
            <th>Nº asunto</th>
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
                <td><?= htmlspecialchars($av['id']) ?></td>
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
</body>
</html>
