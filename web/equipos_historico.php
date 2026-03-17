<?php
require_once 'auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Histórico de equipos (Baja definitiva)</h1>
        <div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Volver al inventario activo
            </a>
        </div>
    </div>

    <div class="alert alert-dark py-2 small">
        <i class="bi bi-info-circle"></i> Mostrando únicamente equipos dados de baja definitiva. Estos equipos no aparecen en el listado general ni en las estadísticas del dashboard.
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="tablaEquiposHistorico" class="table table-striped table-hover align-middle mb-0" style="width:100%">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Etiqueta</th>
                            <th>Imagen</th>
                            <th>Nº Serie</th>
                            <th>Tipo</th>
                            <th>Marca / Modelo</th>
                            <th>Usuario / Depto</th>
                            <th>Servicio</th>
                            <th>Ubicación</th>
                            <th>IP</th>
                            <th>Mon.</th>
                            <th>Ult. Renov.</th>
                            <th>Ult. Verif.</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- DataTables scripts (asumiendo que están disponibles como en index.php) -->
<script src="vendor/jquery/jquery-3.7.1.min.js"></script>
<script src="vendor/datatables/js/dataTables.min.js"></script>
<script src="vendor/datatables/js/dataTables.bootstrap5.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tabla = $('#tablaEquiposHistorico').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'equipos_data.php',
            data: function (d) {
                d.historico = '1'; // IMPORTANTE: Solicitar solo históricos
            }
        },
        columns: [
            { data: 0 },  // ID
            { data: 1 },  // Etiqueta
            { data: 2, orderable: false, searchable: false }, // Imagen
            { data: 3 },  // Nº Serie
            { data: 4 },  // Tipo
            { data: 5 },  // Marca/Modelo
            { data: 6 },  // Usuario
            { data: 7 },  // Hostname
            { data: 8 },  // Ubicación
            { data: 9 },  // IP
            { data: 10, searchable: false }, // Monitores
            { data: 11, searchable: false }, // Ult. Renov
            { data: 12, searchable: false }, // Ult. Verif
            { data: 13 }, // Estado
            { data: 14, orderable: false, searchable: false } // Acciones
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        language: {
            url: 'vendor/datatables/i18n/es-ES.json'
        },
        dom: '<"d-flex justify-content-between align-items-center m-2"lf>rt<"d-flex justify-content-between align-items-center m-2"ip>',
    });

    // Botones de exportación personalizados
    const container = document.querySelector('.dataTables_filter');
    if (container) {
        const div = document.createElement('div');
        div.className = 'btn-group ms-2';
        div.innerHTML = `
            <a href="equipos_export.php?historico=1" class="btn btn-sm btn-outline-success">CSV</a>
            <a href="equipos_export_excel.php?historico=1" class="btn btn-sm btn-outline-success">Excel</a>
        `;
        container.appendChild(div);
    }
});
</script>

<style>
    /* Ajustes visuales para tabla */
    .etiqueta-ok { font-weight: bold; color: #198754; }
    .etiqueta-missing { color: #999; font-style: italic; }
    .dato-contacto-destacado { font-weight: 600; color: #0d6efd; }
    .dato-contacto-destacado-vacio { font-weight: normal; color: #adb5bd; }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>