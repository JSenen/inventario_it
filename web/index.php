<?php
// index.php
require_once 'auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . "/includes/logger.php";


// --- Buscar ---
// --- Filtros ---
$buscar = strtoupper(trim($_GET['q'] ?? ''));
$estado = $_GET['estado'] ?? '';
$tipo   = $_GET['tipo'] ?? '';
$search = $buscar;
$params = [];
$where = [];

// Filtro: búsqueda general
if ($buscar !== '') {
    $where[] =
        "(UPPER(e.tipo) LIKE :buscar
        OR UPPER(e.marca) LIKE :buscar
        OR UPPER(e.modelo) LIKE :buscar
        OR UPPER(e.hostname) LIKE :buscar
        OR UPPER(e.usuario_asignado) LIKE :buscar
        OR UPPER(e.departamento) LIKE :buscar
        OR UPPER(e.ubicacion) LIKE :buscar
        OR UPPER(ip.ip) LIKE :buscar
        OR UPPER(r.nombre) LIKE :buscar)";

    $params[':buscar'] = "%$buscar%";
}

// Filtro: estado exacto
if ($estado !== '') {
    $where[] = "e.estado = :estado";
    $params[':estado'] = $estado;
}

// Filtro: tipo exacto
if ($tipo !== '') {
    $where[] = "e.tipo = :tipo";
    $params[':tipo'] = $tipo;
}

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";


// Consulta: equipos + IP principal + nombre de red
$sql = "
    SELECT 
        e.id,
        e.tipo,
        e.marca,
        e.modelo,
        e.hostname,
        e.usuario_asignado,
        e.departamento,
        e.ubicacion,
        e.estado,
        ip.ip,
        r.nombre AS red_nombre,
        e.imagen
    FROM equipos e
    LEFT JOIN ips_equipos ip 
        ON e.id = ip.equipo_id AND ip.es_principal = 1
    LEFT JOIN redes r 
        ON ip.red_id = r.id
    $whereSql
    ORDER BY e.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalequipos = count($equipos);
$totalactivos = 0;
$totalaveriados = 0;
$totalbaja = 0;
foreach ($equipos as $eq) {
    if ($eq['estado'] === 'Activo') {
        $totalactivos++;
    } elseif ($eq['estado'] === 'Averiado') {
        $totalaveriados++;
    } elseif ($eq['estado'] === 'Baja') {
        $totalbaja++;
    }
}

// --- Dashboard: métricas globales (sin filtros) ---

// Totales por estado
$statsStmt = $pdo->query("
    SELECT 
        COUNT(*)                          AS total,
        SUM(estado = 'Activo')            AS activos,
        SUM(estado = 'Averiado')          AS averiados,
        SUM(estado = 'Baja')              AS baja,
        SUM(estado = 'Almacén')           AS almacen,
        SUM(estado = 'Prestado')          AS prestado
    FROM equipos
");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Top tipos
$tiposStmt = $pdo->query("
    SELECT tipo, COUNT(*) AS total
    FROM equipos
    GROUP BY tipo
    ORDER BY total DESC
    LIMIT 5
");
$tiposStats = $tiposStmt->fetchAll(PDO::FETCH_ASSOC);

// Top departamentos
$deptStmt = $pdo->query("
    SELECT departamento, COUNT(*) AS total
    FROM equipos
    WHERE departamento <> ''
    GROUP BY departamento
    ORDER BY total DESC
    LIMIT 5
");
$deptStats = $deptStmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<!-- DASHBOARD RESUMEN -->
<div class="row mb-4">

    <div class="col-md-2 col-sm-4 mb-2">
        <div class="card text-bg-dark h-100">
            <div class="card-body py-2">
                <div class="small text-uppercase">Total equipos</div>
                <div class="fs-4 fw-bold">
                    <?= (int)($stats['total'] ?? 0) ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-2 col-sm-4 mb-2">
        <div class="card text-bg-success h-100">
            <div class="card-body py-2">
                <div class="small text-uppercase">Activos</div>
                <div class="fs-4 fw-bold">
                    <?= (int)($stats['activos'] ?? 0) ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-2 col-sm-4 mb-2">
        <div class="card text-bg-warning h-100">
            <div class="card-body py-2">
                <div class="small text-uppercase">Averiados</div>
                <div class="fs-4 fw-bold">
                    <?= (int)($stats['averiados'] ?? 0) ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-2 col-sm-4 mb-2">
        <div class="card text-bg-danger h-100">
            <div class="card-body py-2">
                <div class="small text-uppercase">Baja</div>
                <div class="fs-4 fw-bold">
                    <?= (int)($stats['baja'] ?? 0) ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-2 col-sm-4 mb-2">
        <div class="card text-bg-secondary h-100">
            <div class="card-body py-2">
                <div class="small text-uppercase">Almacén</div>
                <div class="fs-4 fw-bold">
                    <?= (int)($stats['almacen'] ?? 0) ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-2 col-sm-4 mb-2">
        <div class="card text-bg-info h-100">
            <div class="card-body py-2">
                <div class="small text-uppercase">Prestados</div>
                <div class="fs-4 fw-bold">
                    <?= (int)($stats['prestado'] ?? 0) ?>
                </div>
            </div>
        </div>
    </div>

</div>

<div class="row mb-4">
    <div class="col-md-6 mb-3">
        <div class="card h-100">
            <div class="card-header py-2">
                <strong>Equipos por tipo (Top 5)</strong>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th class="text-end">Nº equipos</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($tiposStats): ?>
                        <?php foreach ($tiposStats as $t): ?>
                            <tr>
                                <td><?= htmlspecialchars($t['tipo'] ?: 'Sin tipo') ?></td>
                                <td class="text-end"><?= (int)$t['total'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="2" class="text-muted text-center">Sin datos</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-3">
        <div class="card h-100">
            <div class="card-header py-2">
                <strong>Equipos por departamento (Top 5)</strong>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Departamento</th>
                            <th class="text-end">Nº equipos</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($deptStats): ?>
                        <?php foreach ($deptStats as $d): ?>
                            <tr>
                                <td><?= htmlspecialchars($d['departamento'] ?: 'Sin depto.') ?></td>
                                <td class="text-end"><?= (int)$d['total'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="2" class="text-muted text-center">Sin datos</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Inventario de Equipos</h1>
    <a href="equipo_nuevo.php" class="btn btn-primary">+ Nuevo equipo</a>
</div>

<!-- Buscador -->
<form method="get" class="row g-2 mb-3" enctype="multipart/form-data">
    <div class="col-md-4 col-sm-8">
        <input
            type="text"
            name="q"
            class="form-control"
            placeholder="Buscar por usuario, PC, IP, marca, depto..."
            value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
            autofocus
        >
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-outline-primary">Buscar</button>
    </div>
    <div class="col-auto">
        <a href="index.php" class="btn btn-outline-secondary">Limpiar</a>
    </div>
</form>
<form method="get" class="row g-2 mb-3">

    <div class="col-md-4">
        <input type="text" name="q" class="form-control"
               placeholder="Buscar por texto..."
               value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
    </div>

    <div class="col-md-3">
        <select name="estado" class="form-select">
            <option value="">-- Estado --</option>
            <?php
            $estados = ['Activo','Almacén','Averiado','Baja','Prestado'];
            foreach ($estados as $e):
            ?>
                <option value="<?= $e ?>" <?= ($e === ($_GET['estado'] ?? '')) ? 'selected' : '' ?>>
                    <?= $e ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
<!-- Filtro por tipo -->
    <div class="col-md-3">
        <select name="tipo" class="form-select">
            <option value="">-- Tipo --</option>
            <?php
            $tipos = ['PC','Portátil','Monitor','Impresora','Switch','Router','Móvil','Tablet','Otro'];
            foreach ($tipos as $t):
            ?>
                <option value="<?= $t ?>" <?= ($t === ($_GET['tipo'] ?? '')) ? 'selected' : '' ?>>
                    <?= $t ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-2 d-flex gap-2">
        <button class="btn btn-primary w-50">Filtrar</button>
        <a href="index.php" class="btn btn-secondary w-50">Limpiar</a>
    </div>

</form>

 <div>
                    <span class="badge bg-secondary">Total: <?= $totalequipos ?></span>
                    <span class="badge bg-success">Activos: <?= $totalactivos ?></span>
                    <span class="badge bg-warning">Averiado: <?= $totalaveriados ?></span>
                    <span class="badge bg-danger">Baja: <?= $totalbaja ?></span>
                
                </div>
<?php if (isset($_GET['msg']) && $_GET['msg'] === 'ok'): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        Operación realizada correctamente.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
<?php endif; ?>

<?php if (empty($equipos)): ?>
    <div class="alert alert-info">
        <?php if ($search !== ''): ?>
            No hay resultados para <strong><?= htmlspecialchars($_GET['q']) ?></strong>.
            <a href="index.php" class="alert-link">Quitar filtro</a>.
        <?php else: ?>
            No hay equipos registrados todavía. Haz clic en <strong>“Nuevo equipo”</strong> para añadir el primero.
        <?php endif; ?>
    </div>
<?php else: ?>
    <?php if ($search !== ''): ?>
        <p class="text-muted">
            Mostrando resultados para <strong><?= htmlspecialchars($_GET['q'] ?? '')
 ?></strong>
            (<?= count($equipos) ?> equipo(s)).
        </p>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Imagen</th>
                    <th>Tipo</th>
                    <th>Marca / Modelo</th>
                    <th>Usuario / Depto.</th>
                    <th>Servicio</th>
                    <th>Ubicación</th>
                    <th>IP principal</th>
                    <th>Red</th>
                    <th>Estado</th>
                    <th style="width: 150px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($equipos as $eq): ?>
    <tr>
        <td><?= htmlspecialchars($eq['id']) ?></td>

        <!-- Imagen -->
        <td>
            <?php if (!empty($eq['imagen'])): ?>
                <img src="<?= htmlspecialchars($eq['imagen']) ?>" 
                     alt="Imagen del equipo"
                     style="width: 50px; height: auto;">
            <?php else: ?>
                <span class="text-muted">Sin imagen</span>
            <?php endif; ?>
        </td>

        <td><?= htmlspecialchars($eq['tipo']) ?></td>

        <td>
            <strong><?= htmlspecialchars($eq['marca']) ?></strong><br>
            <small class="text-muted"><?= htmlspecialchars($eq['modelo']) ?></small>
        </td>

        <td>
            <?= htmlspecialchars($eq['usuario_asignado']) ?><br>
            <small class="text-muted"><?= htmlspecialchars($eq['departamento']) ?></small>
        </td>

        <td><?= htmlspecialchars($eq['hostname']) ?></td>
        <td><?= htmlspecialchars($eq['ubicacion']) ?></td>
        <td><?= htmlspecialchars($eq['ip'] ?? '') ?></td>
        <td><?= htmlspecialchars($eq['red_nombre'] ?? '') ?></td>
        <td><?= htmlspecialchars($eq['estado']) ?></td>

        <td>
            <div class="btn-group btn-group-sm" role="group">
                <a href="equipo_ver.php?id=<?= $eq['id'] ?>" class="btn btn-outline-primary">Ver</a>
                <a href="equipo_editar.php?id=<?= $eq['id'] ?>" class="btn btn-outline-secondary">Editar</a>
                <a href="equipo_borrar.php?id=<?= $eq['id'] ?>"
                   class="btn btn-outline-danger"
                   onclick="return confirm('¿Seguro que quieres eliminar este equipo?');">
                    Borrar
                </a>
            </div>
        </td>
    </tr>
<?php endforeach; ?>

            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes/footer.php';
