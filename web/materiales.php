<?php
require_once 'auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . "/includes/logger.php";

$stmt = $pdo->query("SELECT * FROM materiales ORDER BY categoria, referencia");
$materiales = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Material y fungibles</h2>
        <a href="material_nuevo.php" class="btn btn-primary">Añadir material</a>
    </div>

    <table id="tablaMateriales" class="table table-striped table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Referencia</th>
                <th>Descripción</th>
                <th>Categoría</th>
                <th>Stock</th>
                <th>Mínimo</th>
                <th>Ubicación</th>
                <th>Proveedor</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($materiales as $m): ?>
            <tr class="<?= ($m['stock_actual'] <= $m['stock_minimo']) ? 'table-danger' : '' ?>">
                <td><?= (int)$m['id'] ?></td>
                <td><?= htmlspecialchars($m['referencia']) ?></td>
                <td><?= htmlspecialchars($m['descripcion']) ?></td>
                <td><?= htmlspecialchars($m['categoria']) ?></td>
                <td><?= (int)$m['stock_actual'] ?> <?= htmlspecialchars($m['unidad']) ?></td>
                <td><?= (int)$m['stock_minimo'] ?></td>
                <td><?= htmlspecialchars($m['ubicacion']) ?></td>
                <td><?= htmlspecialchars($m['proveedor']) ?></td>
                <td>
                    <a href="material_ver.php?id=<?= (int)$m['id'] ?>" class="btn btn-sm btn-info">Ver</a>
                    <a href="material_editar.php?id=<?= (int)$m['id'] ?>" class="btn btn-sm btn-warning">Editar</a>

                    <form action="material_sacar1.php" method="post" style="display:inline;"
                        onsubmit="return confirm('¿Sacar 1 unidad de <?= htmlspecialchars($m['descripcion'], ENT_QUOTES) ?>?');">
                        <input type="hidden" name="material_id" value="<?= (int)$m['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger">Sacar 1</button>
                    </form>
                </td>

            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
$(document).ready(function () {
    $('#tablaMateriales').DataTable();
});
</script>

<?php include 'includes/footer.php'; ?>
