<?php
require_once 'auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . "/includes/logger.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: materiales.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM materiales WHERE id = :id");
$stmt->execute([':id' => $id]);
$mat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mat) {
    header('Location: materiales.php');
    exit;
}

// Cargar compatibilidades (modelos de impresora)
$stmtCompat = $pdo->prepare("SELECT modelo_impresora FROM materiales_compatibilidad WHERE material_id = :id ORDER BY modelo_impresora ASC");
$stmtCompat->execute([':id' => $id]);
$compatibles = $stmtCompat->fetchAll(PDO::FETCH_COLUMN);

$stmtMov = $pdo->prepare("
    SELECT mm.*,
           e.hostname,
           e.tipo AS equipo_tipo,
           e.numero_serie AS equipo_sn
    FROM materiales_movimientos mm
    LEFT JOIN equipos e ON e.id = mm.equipo_id
    WHERE mm.material_id = :id
    ORDER BY mm.fecha DESC, mm.id DESC
    LIMIT 50
");

$stmtMov->execute([':id' => $id]);
$movs = $stmtMov->fetchAll(PDO::FETCH_ASSOC);
logActividad($pdo, 'VER_MATERIAL', 'Visualización del material ID=' . $id);

include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Material: <?= htmlspecialchars($mat['descripcion']) ?></h2>
        <div>
            <a href="material_movimiento.php?id=<?= (int)$mat['id'] ?>" class="btn btn-success">Nuevo movimiento</a>
            <a href="material_editar.php?id=<?= (int)$mat['id'] ?>" class="btn btn-warning">Editar</a>
            <a href="materiales.php" class="btn btn-secondary">Volver</a>
        </div>
    </div>

    <table class="table table-bordered">
        <tr><th>ID</th><td><?= (int)$mat['id'] ?></td></tr>
        <?php if (!empty($mat['imagen']) && file_exists(__DIR__ . '/' . $mat['imagen'])): ?>
        <tr>
            <th>Imagen</th>
            <td>
                <img src="<?= htmlspecialchars($mat['imagen']) ?>" alt="Imagen" style="max-width: 250px; height: auto;" class="img-thumbnail">
            </td>
        </tr>
        <?php endif; ?>
        <tr><th>Referencia</th><td><?= htmlspecialchars($mat['referencia']) ?></td></tr>
        <tr><th>Descripción</th><td><?= htmlspecialchars($mat['descripcion']) ?></td></tr>
        <tr><th>Categoría</th><td><?= htmlspecialchars($mat['categoria']) ?></td></tr>
        <tr><th>Stock actual</th><td><?= (int)$mat['stock_actual'] . ' ' . htmlspecialchars($mat['unidad']) ?></td></tr>
        <tr><th>Stock mínimo</th><td><?= (int)$mat['stock_minimo'] ?></td></tr>
        <tr><th>Ubicación</th><td><?= htmlspecialchars($mat['ubicacion']) ?></td></tr>
        <tr><th>Proveedor</th><td><?= htmlspecialchars($mat['proveedor']) ?></td></tr>
        <tr><th>Coste unitario</th><td><?= $mat['coste_unitario'] !== null ? number_format($mat['coste_unitario'], 2) . ' €' : '-' ?></td></tr>
        <tr><th>Notas</th><td><?= nl2br(htmlspecialchars($mat['notas'])) ?></td></tr>
        <?php if (!empty($compatibles)): ?>
        <tr>
            <th>Compatibilidad</th>
            <td>
                <ul class="mb-0">
                    <?php foreach ($compatibles as $modelo): ?>
                        <li><?= htmlspecialchars($modelo) ?></li>
                    <?php endforeach; ?>
                </ul>
            </td>
        </tr>
        <?php endif; ?>
    </table>

    <h4 class="mt-4">Últimos movimientos</h4>
    <table class="table table-sm table-striped">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Cantidad</th>
                <th>Motivo</th>
                <th>Equipo</th>
                <th>Usuario</th>
            </tr>
        </thead>

        <tbody>
        <?php foreach ($movs as $mv): ?>
            <tr>
        <tr>
            <td><?= htmlspecialchars($mv['fecha']) ?></td>
            <td><?= htmlspecialchars($mv['tipo']) ?></td>
            <td><?= (int)$mv['cantidad'] ?></td>
            <td><?= htmlspecialchars($mv['motivo']) ?></td>
            <td>
                <?php if (!empty($mv['equipo_id'])): ?>
                    <?php
                        $textoEq = $mv['equipo_tipo'] . ' - ' .
                                ($mv['hostname'] ?: 'sin hostname') .
                                ($mv['equipo_sn'] ? ' [SN: '.$mv['equipo_sn'].']' : '');
                    ?>
                    <a href="equipo_ver.php?id=<?= (int)$mv['equipo_id'] ?>">
                        <?= htmlspecialchars($textoEq) ?>
                    </a>
                <?php else: ?>
                    <span class="text-muted">Sin asociar</span>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($mv['usuario']) ?></td>
        </tr>


        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include 'includes/footer.php'; ?>
