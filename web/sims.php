<?php
require_once 'auth.php';
require_once 'config.php';
require_once __DIR__ . '/includes/sims_schema.php';
require_once 'includes/header.php';

ensureSimsSchema($pdo);


// Total SIMs
$totalSims = (int)$pdo->query("SELECT COUNT(*) FROM sims")->fetchColumn();

// SIMs disponibles
$totalSimsDisponibles = (int)$pdo->query("SELECT COUNT(*) FROM sims WHERE estado = 'Disponible'")->fetchColumn();

// SIMs en baja
$totalSimsBaja = (int)$pdo->query("SELECT COUNT(*) FROM sims WHERE estado = 'Baja'")->fetchColumn();

$operadores = $pdo->query("
    SELECT DISTINCT operador
    FROM sims
    WHERE operador IS NOT NULL AND operador <> ''
    ORDER BY operador ASC
")->fetchAll(PDO::FETCH_COLUMN);


?>
<div class="container mt-4">
    <h2>Tarjetas SIM</h2>

    <div class="row mb-4">
         <div class="col-md-2 col-sm-4 mb-2">
            <div class="card text-bg-success h-100">
                <div class="card-body">
                    <h5 class="card-title">SIMs totales</h5>
                    <p class="fs-3"><?= $totalSims ?></p>
                </div>
            </div>
        </div>

        <div class="col-md-2 col-sm-4 mb-2">
            <div class="card text-bg-warning h-100">
                <div class="card-body">
                    <h5 class="card-title">SIMs disponibles</h5>
                    <p class="fs-3"><?= $totalSimsDisponibles ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 mb-2">
            <div class="card text-bg-danger h-100">
                <div class="card-body">
                    <h5 class="card-title">SIMs baja</h5>
                    <p class="fs-3"><?= $totalSimsBaja ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="mb-3">
        <a href="sims_nuevo.php" class="btn btn-primary">Nueva SIM</a>
    </div>

    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="row g-2 align-items-end">
                <div class="col-sm-4 col-md-3 col-lg-2">
                    <label class="form-label mb-0 small text-muted">Estado</label>
                    <select id="filtroEstado" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="Disponible">Disponible</option>
                        <option value="Asignada">Asignada</option>
                        <option value="Averiada">Averiada</option>
                        <option value="Baja">Baja</option>
                    </select>
                </div>
                <div class="col-sm-4 col-md-3 col-lg-2">
                    <label class="form-label mb-0 small text-muted">Operador</label>
                    <select id="filtroOperador" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($operadores as $op): ?>
                            <option value="<?= htmlspecialchars($op) ?>"><?= htmlspecialchars($op) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-4 col-md-3 col-lg-2 d-flex">
                    <button id="limpiarFiltros" class="btn btn-outline-secondary btn-sm w-100">Limpiar</button>
                </div>
            </div>
        </div>
    </div>

    <table id="tablaSims" class="table table-striped table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Etiqueta</th>
                <th>Número</th>
                <th>Número corto</th>
                <th>ICCID</th>
                <th>Operador</th>
                <th>PUK</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
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

    $('#tablaSims').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'sims_data.php',
            type: 'GET'
        },
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        stateSave: true,
        dom: 'Bfrtip',
        buttons: [
            'copy',
            {
                text: 'Excel',
                action: function (e, dt, node, config) {

                    var params = dt.ajax.params();

                    var query = $.param({
                        search: params.search.value,
                        estado: $('#filtroEstado').val(),
                        operador: $('#filtroOperador').val()
                    });

                    window.location.href = 'sims_export.php?' + query;
                }
            },
            'csv',
            'print'
        ],

        columns: [
            { data: 'id' },
            { data: 'etiqueta' },
            { data: 'numero' },
            { data: 'numero_corto' },
            { data: 'iccid' },
            { data: 'operador' },
            { data: 'puk' },
            { data: 'estado' },
            { data: 'acciones', orderable: false, searchable: false }
        ],
        ajax: {
            url: 'sims_data.php',
            type: 'GET',
            data: function (d) {
                d.estado   = $('#filtroEstado').val() || '';
                d.operador = $('#filtroOperador').val() || '';
            }
        },
           // 👇 AÑADIMOS ESTO
        createdRow: function (row, data, dataIndex) {
            // Índice de la columna "Estado"
            // ID(0), Imagen(1), Nº serie(2), Tipo(3), Marca(4),
            // Usuario(5), Servicio(6), Ubicación(7),
            // IP principal(8), Red(9), Estado(10), Acciones(11)

            var indiceEstado = 7;

            var $celda = $('td:eq(' + indiceEstado + ')', row);
            var estado = $celda.text().toLowerCase().trim();

            if (estado === 'asignada') {
                $celda.addClass('estado-activo');
            } else if (estado === 'averiada') {
                $celda.addClass('estado-averiado');
            } else if (estado === 'baja') {
                $celda.addClass('estado-baja');
            } else if ( estado === 'disponible') {
                $celda.addClass('estado-almacen');
             
        }
        },

        // Traducción al castellano
        language: {
            search: "Buscar:",
            searchPlaceholder: "Buscar en la tabla...",
            url: 'vendor/datatables/i18n/es-ES.json'
        }
    });

    $('#filtroEstado, #filtroOperador').on('change', function () {
        $('#tablaSims').DataTable().ajax.reload();
    });

    $('#limpiarFiltros').on('click', function () {
        $('#filtroEstado').val('');
        $('#filtroOperador').val('');
        $('#tablaSims').DataTable().ajax.reload();
    });

});
</script>

<?php require_once 'includes/footer.php'; ?>
