<?php
require_once 'auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/header.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

?>
<div class="container-fluid mt-3">
    <h1 class="h3 mb-3">Resultados de búsqueda</h1>

    <?php if ($q === ''): ?>
        <div class="alert alert-info">
            Escribe algo en el buscador (nº de serie, modelo, servicio, IP...).
        </div>
    </div>
    <?php
        require_once __DIR__ . '/includes/footer.php';
        exit;
    endif;

    // Usaremos %q% para varios campos
    $like = '%' . $q . '%';
?>

    <div class="alert alert-secondary py-2">
        Buscando: <strong><?= htmlspecialchars($q) ?></strong>
    </div>

    <!-- ================= EQUIPOS ================= -->
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-light">
            <strong>Equipos coincidentes</strong>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Marca / Modelo</th>
                            <th>Serie </th>
                            <th>Usu / Dpto / Seccion</th>
                            <th>Ubicación</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $stmtEq = $pdo->prepare("
                        SELECT
                            e.id,
                            e.marca,
                            e.modelo,
                            e.numero_serie,
                            e.hostname,
                            e.usuario_asignado,
                            e.departamento,
                            e.ubicacion,
                            e.estado,
                            s.nombre AS seccion_nombre
                        FROM equipos e
                        LEFT JOIN secciones s ON s.id = e.seccion_id
                        WHERE
                            e.numero_serie     LIKE :q
                            OR e.hostname      LIKE :q
                            OR e.marca         LIKE :q
                            OR e.modelo        LIKE :q
                            OR e.usuario_asignado LIKE :q
                            OR e.departamento  LIKE :q
                            OR s.nombre        LIKE :q
                    ");
                    $stmtEq->execute([':q' => $like]);
                    $equipos = $stmtEq->fetchAll(PDO::FETCH_ASSOC);



                    if (empty($equipos)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">
                                No hay equipos que coincidan.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($equipos as $e): ?>
                            <tr>
                                <td><?= (int)$e['id'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($e['marca']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($e['modelo']) ?></small>
                                </td>
                                <!-- <td>
                                    <?= htmlspecialchars($e['numero_serie']) ?><br>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($e['etiqueta']) ?>
                                    </small>
                                </td> -->
                                <td><?= htmlspecialchars($e['hostname']) ?></td>
                                <td>
                                    <?= htmlspecialchars($e['usuario_asignado']) ?><br>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($e['departamento']) ?>
                                        <?php if (!empty($e['seccion_nombre'])): ?>
                                            · <?= htmlspecialchars($e['seccion_nombre']) ?>
                                        <?php endif; ?>
                                    </small>

                                </td>
                                <td><?= htmlspecialchars($e['ubicacion']) ?></td>
                                <td><small><?= htmlspecialchars($e['estado']) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ================= IPs ================= -->
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-light">
            <strong>IPs coincidentes</strong>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>IP</th>
                            <th>Estado</th>
                            <th>Red</th>
                            <th>Equipo</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $stmtIp = $pdo->prepare("
                        SELECT
                            i.ip,
                            i.estado,
                            r.nombre      AS red_nombre,
                            r.direccion_red,
                            e.id          AS equipo_id,
                            e.marca,
                            e.modelo,
                            e.numero_serie                              
                        FROM ips_equipos i
                        LEFT JOIN redes  r ON r.id = i.red_id
                        LEFT JOIN equipos e ON e.id = i.id
                        WHERE i.ip LIKE :q
                    ");
                    $stmtIp->execute([':q' => $like]);
                    $ips = $stmtIp->fetchAll(PDO::FETCH_ASSOC);

                    if (empty($ips)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted">
                                No hay IPs que coincidan.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($ips as $ip): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-light text-muted border">
                                        <?= htmlspecialchars($ip['ip']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($ip['estado']) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($ip['red_nombre']) ?></strong><br>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($ip['direccion_red']) ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($ip['equipo_id']): ?>
                                        <strong>#<?= (int)$ip['equipo_id'] ?></strong><br>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($ip['marca'] . ' ' . $ip['modelo']) ?>
                                        </small><br>
                                        <!-- <small class="text-muted">
                                            <?= htmlspecialchars($ip['numero_serie'] ?: $ip['etiqueta']) ?>
                                        </small> -->
                                    <?php else: ?>
                                        <span class="text-muted">Sin equipo asociado</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php
require_once __DIR__ . '/includes/footer.php';
