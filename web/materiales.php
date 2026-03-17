<?php
require_once 'auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . "/includes/logger.php";

$stmt = $pdo->query("
    SELECT m.*, GROUP_CONCAT(mc.modelo_impresora SEPARATOR ', ') as modelos_compatibles
    FROM materiales m
    LEFT JOIN materiales_compatibilidad mc ON mc.material_id = m.id
    GROUP BY m.id
    ORDER BY m.categoria, m.referencia
");
$materiales = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Material y fungibles</h2>
        <a href="material_nuevo.php" class="btn btn-primary">AÃ±adir material</a>
    </div>

    <table id="tablaMateriales" class="table table-striped table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Imagen</th>
                <th>Referencia</th>
                <th>DescripciÃ³n</th>
                <th>CategorÃ­a</th>
                <th>Compatibilidad</th>
                <th>Stock</th>
                <th>MÃ­nimo</th>
                <th>UbicaciÃ³n</th>
                <!--<th>Proveedor</th>-->
                <th>Fecha actualizaciÃ³n</th>    
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($materiales as $m): ?>
            <tr class="<?= ($m['stock_actual'] <= $m['stock_minimo']) ? 'table-danger' : '' ?>">
                <td><?= (int)$m['id'] ?></td>
                <td class="text-center">
                    <?php if (!empty($m['imagen'])): ?>
                        <img src="<?= htmlspecialchars($m['imagen']) ?>" alt="Img" style="height: 40px; width: auto; object-fit: contain;">
                    <?php else: ?>
                        <span class="text-muted small">-</span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($m['referencia']) ?></td>
                <td><?= htmlspecialchars($m['descripcion']) ?></td>
                <td><?= htmlspecialchars($m['categoria']) ?></td>
                <td>
                    <?php if (!empty($m['modelos_compatibles'])): ?>
                        <small class="text-muted"><?= htmlspecialchars($m['modelos_compatibles']) ?></small>
                    <?php endif; ?>
                </td>
                <td><?= (int)$m['stock_actual'] ?> <?= htmlspecialchars($m['unidad']) ?></td>
                <td><?= (int)$m['stock_minimo'] ?></td>
                <td><?= htmlspecialchars($m['ubicacion']) ?></td>
                <!--<td><?= htmlspecialchars($m['proveedor']) ?></td>-->
                <td><?= date("d-m-y H:i",strtotime(htmlspecialchars($m['actualizado_en']))) ?></td>
                <td>
                    <a href="material_ver.php?id=<?= (int)$m['id'] ?>" class="btn btn-sm btn-info">Ver</a>
                    <a href="material_editar.php?id=<?= (int)$m['id'] ?>" class="btn btn-sm btn-warning">Editar</a>

                    <form action="material_sacar1.php" method="post" style="display:inline;"
                        data-confirm-message="Â¿Sacar 1 unidad de <?= htmlspecialchars($m['descripcion'], ENT_QUOTES) ?>?">
                        <input type="hidden" name="material_id" value="<?= (int)$m['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger">Sacar 1</button>
                    </form>
                </td>

            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<!-- DataTables (puedes pasar a local mÃ¡s adelante si quieres) -->
<!-- jQuery local -->
<script src="vendor/jquery/jquery-3.7.1.min.js"></script>

<!-- DataTables nÃºcleo -->
<script src="vendor/datatables/js/dataTables.min.js"></script>

<!-- ExtensiÃ³n Buttons -->
<script src="vendor/datatables/js/dataTables.buttons.min.js"></script>
<script src="vendor/datatables/js/jszip.min.js"></script>
<script src="vendor/datatables/js/pdfmake.min.js"></script>
<script src="vendor/datatables/js/vfs_fonts.js"></script>
<script src="vendor/datatables/js/buttons.html5.min.js"></script>
<script src="vendor/datatables/js/buttons.print.min.js"></script>

<!-- Idioma espaÃ±ol -->
<script src="vendor/datatables/i18n/es-ES.json"></script>
<style>
    #tablaMateriales mark.dt-search-hit {
        background-color: #ffe08a;
        color: #111;
        font-weight: 700;
        padding: 0 .1em;
        border-radius: 2px;
    }
</style>
<script>
$(document).ready(function () {
    function escapeRegex(value) {
        return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function highlightTextNodes(container, regex) {
        const walker = document.createTreeWalker(container, NodeFilter.SHOW_TEXT);
        const textNodes = [];

        while (walker.nextNode()) {
            textNodes.push(walker.currentNode);
        }

        textNodes.forEach((node) => {
            const text = node.nodeValue || '';
            regex.lastIndex = 0;
            if (!regex.test(text)) return;

            const frag = document.createDocumentFragment();
            let lastIndex = 0;

            text.replace(regex, (match, _g1, offset) => {
                if (offset > lastIndex) {
                    frag.appendChild(document.createTextNode(text.slice(lastIndex, offset)));
                }

                const mark = document.createElement('mark');
                mark.className = 'dt-search-hit';
                mark.textContent = match;
                frag.appendChild(mark);

                lastIndex = offset + match.length;
                return match;
            });

            if (lastIndex < text.length) {
                frag.appendChild(document.createTextNode(text.slice(lastIndex)));
            }

            node.parentNode.replaceChild(frag, node);
        });
    }

    function applySearchHighlight(api) {
        const term = (api.search() || '').trim();
        const actionColIndex = api.columns().count() - 1;
        const rows = api.rows({ page: 'current' }).nodes().toArray();

        rows.forEach((row) => {
            row.querySelectorAll('td').forEach((cell) => {
                if (!cell.dataset.originalHtml) {
                    cell.dataset.originalHtml = cell.innerHTML;
                } else {
                    cell.innerHTML = cell.dataset.originalHtml;
                }
            });
        });

        if (!term) return;

        const parts = term.split(/\s+/).filter(Boolean).map(escapeRegex);
        if (!parts.length) return;
        const regex = new RegExp('(' + parts.join('|') + ')', 'gi');

        rows.forEach((row) => {
            row.querySelectorAll('td').forEach((cell, index) => {
                if (index === 1 || index === actionColIndex) return;

                const baseHtml = cell.dataset.originalHtml ?? cell.innerHTML;
                const wrapper = document.createElement('div');
                wrapper.innerHTML = baseHtml;
                highlightTextNodes(wrapper, regex);
                cell.innerHTML = wrapper.innerHTML;
            });
        });
    }

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
        order: [[2, 'asc']],

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
        },

        drawCallback: function () {
            applySearchHighlight(this.api());
        },
        initComplete: function () {
            applySearchHighlight(this.api());
        }
    });
});
</script>


<?php include 'includes/footer.php'; ?>
