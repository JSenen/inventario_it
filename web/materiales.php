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
<!-- DataTables (puedes pasar a local más adelante si quieres) -->
<!-- jQuery local -->
<script src="vendor/jquery/jquery-3.7.1.min.js"></script>

<!-- DataTables núcleo -->
<script src="vendor/datatables/js/dataTables.min.js"></script>

<!-- Extensión Buttons -->
<script src="vendor/datatables/js/dataTables.buttons.min.js"></script>
<script src="vendor/datatables/js/jszip.min.js"></script>
<script src="vendor/datatables/js/pdfmake.min.js"></script>
<script src="vendor/datatables/js/vfs_fonts.js"></script>
<script src="vendor/datatables/js/buttons.html5.min.js"></script>
<script src="vendor/datatables/js/buttons.print.min.js"></script>

<!-- Idioma español -->
<script src="vendor/datatables/i18n/es-ES.json"></script>
<script>
$(document).ready(function () {
    $('#tablaMateriales').DataTable({
        // Tamaño de página por defecto y opciones
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],

        // Botones de exportación (como en index, pero sin scripts extra)
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'copy',
                text: 'Copiar'
            },
            {
                extend: 'excel',
                text: 'Excel'
            },
            {
                extend: 'csv',
                text: 'CSV'
            },
            {
                extend: 'print',
                text: 'Imprimir'
            }
        ],

        // Orden por defecto (ajusta el índice de columna si quieres otra)
        order: [[0, 'asc']],

        // La última columna (Acciones) sin ordenar ni buscar
        columnDefs: [
            {
                orderable: false,
                searchable: false,
                targets: -1   // última columna
            }
        ],

        // Idioma español, igual que en index
        language: {
            url: 'vendor/datatables/i18n/es-ES.json'
        }
    });
});
</script>


<?php include 'includes/footer.php'; ?>
