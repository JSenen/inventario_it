<?php
require_once 'auth.php';
require_once 'config.php';
require_once 'includes/header.php';

// --------- RESÚMENES RÁPIDOS ---------

// Total equipos
$totalEquipos = (int)$pdo->query("SELECT COUNT(*) FROM equipos")->fetchColumn();

// Total teléfonos
$totalTelefonos = (int)$pdo->query("SELECT COUNT(*) FROM telefonos")->fetchColumn();

// Total SIMs
$totalSims = (int)$pdo->query("SELECT COUNT(*) FROM sims")->fetchColumn();

// SIMs disponibles
$totalSimsDisponibles = (int)$pdo->query("SELECT COUNT(*) FROM sims WHERE estado = 'Disponible'")->fetchColumn();


// --------- EQUIPOS POR ESTADO ---------
$sqlEquiposEstado = "
    SELECT estado, COUNT(*) AS total
    FROM equipos
    GROUP BY estado
";
$equiposPorEstado = $pdo->query($sqlEquiposEstado)->fetchAll(PDO::FETCH_ASSOC);

// Calcular total para porcentajes
$totalEquiposEstados = array_sum(array_column($equiposPorEstado, 'total')) ?: 1;


// --------- TELÉFONOS POR DEPARTAMENTO (TOP 5) ---------
$sqlTelfDept = "
    SELECT departamento, COUNT(*) AS total
    FROM telefonos
    WHERE departamento IS NOT NULL AND departamento <> ''
    GROUP BY departamento
    ORDER BY total DESC
    LIMIT 5
";
$telefonosPorDept = $pdo->query($sqlTelfDept)->fetchAll(PDO::FETCH_ASSOC);


// --------- ÚLTIMOS TELÉFONOS ENTREGADOS / ALTAS ---------
$sqlUltimosTel = "
    SELECT id, marca, modelo, imei,
           usuario_asignado, departamento,
           COALESCE(fecha_entrega, fecha_alta) AS fecha_ref
    FROM telefonos
    ORDER BY fecha_ref DESC
    LIMIT 5
";
$ultimosTelefonos = $pdo->query($sqlUltimosTel)->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="container mt-4">

    <h2 class="mb-4">Dashboard inventario</h2>

    <!-- Tarjetas resumen -->
    <div class="row g-3 mb-4">
         <!--<div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body">
                    <h5 class="card-title">Equipos</h5>
                    <p class="card-text fs-3"><?= $totalEquipos ?></p>
                    <small class="text-muted">Total registrados</small>
                </div>
            </div>
        </div>-->

        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body">
                    <h5 class="card-title">Teléfonos móviles</h5>
                    <p class="card-text fs-3"><?= $totalTelefonos ?></p>
                    <small class="text-muted">En inventario</small>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body">
                    <h5 class="card-title">Tarjetas SIM</h5>
                    <p class="card-text fs-3"><?= $totalSims ?></p>
                    <small class="text-muted">Total SIMs</small>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body">
                    <h5 class="card-title">SIMs disponibles</h5>
                    <p class="card-text fs-3"><?= $totalSimsDisponibles ?></p>
                    <small class="text-muted">Listas para asignar</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Dos columnas: equipos por estado / teléfonos por departamento -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    Equipos por estado
                </div>
                <div class="card-body">
                    <?php if (!$equiposPorEstado): ?>
                        <p class="text-muted">No hay equipos registrados.</p>
                    <?php else: ?>
                        <?php foreach ($equiposPorEstado as $e): 
                            $estado = $e['estado'] ?: 'Sin estado';
                            $total  = (int)$e['total'];
                            $porc   = round($total * 100 / $totalEquiposEstados);
                        ?>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between">
                                    <span><strong><?= htmlspecialchars($estado) ?></strong></span>
                                    <span><?= $total ?> (<?= $porc ?>%)</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar" role="progressbar" 
                                         style="width: <?= $porc ?>%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    Teléfonos por departamento (Top 5)
                </div>
                <div class="card-body">
                    <?php if (!$telefonosPorDept): ?>
                        <p class="text-muted">No hay teléfonos con departamento asignado.</p>
                    <?php else: ?>
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Departamento</th>
                                    <th class="text-end">Teléfonos</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($telefonosPorDept as $d): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($d['departamento']) ?></td>
                                        <td class="text-end"><?= (int)$d['total'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Últimas entregas / altas de teléfonos -->
    <div class="card mb-4">
        <div class="card-header">
            Últimos teléfonos entregados / dados de alta
        </div>
        <div class="card-body">
            <?php if (!$ultimosTelefonos): ?>
                <p class="text-muted">No hay teléfonos registrados todavía.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Teléfono</th>
                                <th>IMEI</th>
                                <th>Usuario</th>
                                <th>Departamento</th>
                                <th>Fecha entrega / alta</th>
                                <th>Ver</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ultimosTelefonos as $t): ?>
                                <tr>
                                    <td><?= (int)$t['id'] ?></td>
                                    <td><?= htmlspecialchars($t['marca'] . ' ' . $t['modelo']) ?></td>
                                    <td><?= htmlspecialchars($t['imei']) ?></td>
                                    <td><?= htmlspecialchars($t['usuario_asignado'] ?: '-') ?></td>
                                    <td><?= htmlspecialchars($t['departamento'] ?: '-') ?></td>
                                    <td><?= htmlspecialchars($t['fecha_ref'] ?: '-') ?></td>
                                    <td>
                                        <a href="telefonos_ver.php?id=<?= (int)$t['id'] ?>" 
                                           class="btn btn-sm btn-info">
                                            Ver
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>
