<?php
// index.php
require_once 'auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . "/includes/logger.php";


// --- Buscar ---
$search = strtoupper(trim($_GET['q'] ?? ''));
$params = [];

$whereSql = '';
if ($search !== '') {
    $whereSql = "WHERE 
        UPPER(e.tipo) LIKE :search
        OR UPPER(e.marca) LIKE :search
        OR UPPER(e.modelo) LIKE :search
        OR UPPER(e.hostname) LIKE :search
        OR UPPER(e.usuario_asignado) LIKE :search
        OR UPPER(e.departamento) LIKE :search
        OR UPPER(e.ubicacion) LIKE :search
        OR UPPER(ip.ip) LIKE :search
        OR UPPER(r.nombre) LIKE :search
    ";
    $params[':search'] = "%$search%";
}

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

require_once __DIR__ . '/includes/header.php';
?>

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
            Mostrando resultados para <strong><?= htmlspecialchars($_GET['q']) ?></strong>
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
