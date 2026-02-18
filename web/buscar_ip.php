<?php
require_once 'auth.php';
require_once __DIR__ . '/config.php';

$ipBuscada = trim($_GET['ip'] ?? '');
$resultados = [];

if ($ipBuscada !== '') {
    // Permitimos buscar exacto o parcial (ej: "10.52.2.")
    $sql = "
        SELECT 
            i.id,
            i.ip,
            i.estado,
            r.nombre        AS red_nombre,
            r.direccion_red AS red_direccion,
            r.mascara       AS red_mascara,
            e.id            AS equipo_id,
            e.hostname      AS equipo_hostname,
            e.usuario_asignado AS equipo_usuario,
            e.departamento  AS equipo_departamento
        FROM ips_equipos i
        LEFT JOIN redes r   ON r.id = i.red_id
        LEFT JOIN equipos e ON e.id = i.equipo_id
        WHERE i.ip LIKE :busqueda
        ORDER BY i.ip ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':busqueda' => '%' . $ipBuscada . '%'
    ]);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<?php include 'includes/header.php'; ?>

<div class="container mt-4">
    <h1>Buscar IP</h1>

    <form method="get" class="row g-3 mb-4">
        <div class="col-auto">
            <input 
                type="text"
                name="ip"
                class="form-control"
                placeholder="Ej: 10.52.2.6 o 10.52.2."
                value="<?= htmlspecialchars($ipBuscada) ?>"
            >
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary">Buscar</button>
        </div>
    </form>

    <?php if ($ipBuscada !== ''): ?>
        <h5>Resultados para: <code><?= htmlspecialchars($ipBuscada) ?></code></h5>

        <?php if (empty($resultados)): ?>
            <p class="text-muted">No se encontraron IPs que coincidan.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-striped">
                    <thead>
                        <tr>
                            <th>IP</th>
                            <th>Estado</th>
                            <th>Red</th>
                            <th>Dirección red</th>
                            <th>Máscara</th>
                            <th>Equipo</th>
                            <th>Usuario</th>
                            <th>Departamento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultados as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['ip']) ?></td>
                                <td><?= htmlspecialchars($row['estado']) ?></td>
                                <td><?= htmlspecialchars($row['red_nombre']) ?></td>
                                <td><?= htmlspecialchars($row['red_direccion']) ?></td>
                                <td>/<?= (int)$row['red_mascara'] ?></td>
                                <td>
                                    <?php if (!empty($row['equipo_id'])): ?>
                                        <?= 'EQ-' . (int)$row['equipo_id'] . ' - ' . htmlspecialchars($row['equipo_hostname'] ?? '') ?>
                                    <?php else: ?>
                                        <span class="text-muted">Sin asignar</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['equipo_usuario'] ?? '') ?></td>
                                <td><?= htmlspecialchars($row['equipo_departamento'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
