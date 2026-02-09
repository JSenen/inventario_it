<?php
require_once 'auth.php';
require_once 'config.php';
require_once 'includes/header.php';

// ------ Datos resumen ------

// Total teléfonos
$totalTelefonos = (int)$pdo->query("SELECT COUNT(*) FROM telefonos")->fetchColumn();

// Teléfonos activos
$totalTelefonosActivos = (int)$pdo->query("SELECT COUNT(*) FROM telefonos WHERE estado = 'Activo'")->fetchColumn();

// Telefonos Averiados
$totalTelefonosAveriados = (int)$pdo->query("SELECT COUNT(*) FROM telefonos WHERE estado = 'Averiado'")->fetchColumn();

// Telefonos en baja
$totalTelefonosBaja = (int)$pdo->query("SELECT COUNT(*) FROM telefonos WHERE estado = 'Baja'")->fetchColumn();

$departamentosFiltro = $pdo->query("
    SELECT DISTINCT departamento 
    FROM telefonos 
    WHERE departamento IS NOT NULL AND departamento <> ''
    ORDER BY departamento ASC
")->fetchAll(PDO::FETCH_COLUMN);

$operadoresFiltro = $pdo->query("
    SELECT DISTINCT operador
    FROM sims
    WHERE operador IS NOT NULL AND operador <> ''
    ORDER BY operador ASC
")->fetchAll(PDO::FETCH_COLUMN);


// ------ SIMs asignadas por operador ------
$sqlOperador = "
    SELECT operador, COUNT(*) AS total
    FROM sims
    GROUP BY operador
";
$simPorOperador = $pdo->query($sqlOperador)->fetchAll(PDO::FETCH_ASSOC);

// ------ Teléfonos por departamento ------
$sqlDept = "
    SELECT departamento, estado, COUNT(*) AS total
    FROM telefonos
    WHERE departamento IS NOT NULL AND departamento <> '' AND estado <> 'Baja'
    GROUP BY departamento
    ORDER BY total DESC
    LIMIT 6
";
$telPorDept = $pdo->query($sqlDept)->fetchAll(PDO::FETCH_ASSOC);

?>

<!--<div class="container mt-4">-->

    <h2 class="mb-4">Gestión de Teléfonos</h2>

    <!-- TARJETAS RESUMEN -->
    <div class="row mb-4">

        <div class="col-md-2 col-sm-4 mb-2">
            <div class="card border-primary">
                <div class="card-body">
                    <h5 class="card-title">Teléfonos totales</h5>
                    <p class="fs-3"><?= $totalTelefonos ?></p>
                </div>
            </div>
        </div>

        <div class="col-md-2 col-sm-4 mb-2">
            <div class="card text-bg-dark h-100">
                <div class="card-body py-2">
                    <h5 class="card-title">Teléfonos activos</h5>
                    <p class="fs-3"><?= $totalTelefonosActivos ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 mb-2">
            <div class="card text-bg-warning h-100">
                <div class="card-body">
                    <h5 class="card-title">Teléfonos averiados</h5>
                    <p class="fs-3"><?= $totalTelefonosAveriados ?></p>
                </div>
            </div>
        </div>      
        <div class="col-md-2 col-sm-4 mb-2">
            <div class="card text-bg-danger h-100">
                <div class="card-body">
                    <h5 class="card-title">Teléfonos baja</h5>
                    <p class="fs-3"><?= $totalTelefonosBaja ?></p>
                </div>
            </div>
        </div>  
       
    </div>

    <!-- DOS COLUMNAS -->
    <div class="row g-3 mb-4">

        <!-- SIM POR OPERADOR -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">SIMs por operador</div>
                <div class="card-body">
                    <?php if (!$simPorOperador): ?>
                        <p class="text-muted">No hay datos.</p>
                    <?php else: ?>
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>Operador</th>
                                    <th class="text-end">SIMs</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($simPorOperador as $o): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($o['operador'] ?: 'Desconocido') ?></td>
                                        <td class="text-end"><?= (int)$o['total'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- TELÉFONOS POR DEPARTAMENTO -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">Teléfonos por departamento</div>
                <div class="card-body">
                    <?php if (!$telPorDept): ?>
                        <p class="text-muted">No hay teléfonos con departamento asignado.</p>
                    <?php else: ?>
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>Departamento</th>
                                    <th class="text-end">Teléfonos</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($telPorDept as $d): ?>
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

    <!-- BOTÓN AÑADIR -->
    <div class="mb-3">
        <a href="telefonos_nuevo.php" class="btn btn-primary">Nuevo teléfono</a>
    </div>

    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="row g-2 align-items-end">
                <div class="col-sm-3 col-md-2">
                    <label class="form-label mb-0 small text-muted">Estado</label>
                    <select id="filtroEstado" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="Activo">Activo</option>
                        <option value="Averiado">Averiado</option>
                        <option value="Baja">Baja</option>
                        <option value="Prestado">Prestado</option>
                        <option value="Almacén">Almacén</option>
                    </select>
                </div>
                <div class="col-sm-3 col-md-3">
                    <label class="form-label mb-0 small text-muted">Departamento</label>
                    <select id="filtroDepartamento" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($departamentosFiltro as $dep): ?>
                            <option value="<?= htmlspecialchars($dep) ?>"><?= htmlspecialchars($dep) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-3 col-md-3">
                    <label class="form-label mb-0 small text-muted">Operador SIM</label>
                    <select id="filtroOperador" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($operadoresFiltro as $op): ?>
                            <option value="<?= htmlspecialchars($op) ?>"><?= htmlspecialchars($op) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-3 col-md-2">
                    <label class="form-label mb-0 small text-muted">SIM asignada</label>
                    <select id="filtroSim" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <option value="con">Con SIM</option>
                        <option value="sin">Sin SIM</option>
                    </select>
                </div>
                <div class="col-sm-12 col-md-2 d-flex">
                    <button id="limpiarFiltros" class="btn btn-outline-secondary btn-sm w-100">Limpiar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- LISTADO -->
    <table id="tablaTelefonos" class="table table-striped table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Etiqueta</th>
                <th>Marca</th>
                <th>Modelo</th>
                <th>IMEI</th>
                <th>Usuario</th>
                <th>Departamento</th>
                <th>SIM nº</th>
                <th>Operador</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
    </table>
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
<!--</div>-->

<script>
$(document).ready(function () {
    $('#tablaTelefonos').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'telefonos_data.php',
            type: 'GET',
            data: function (d) {
                d.estado       = $('#filtroEstado').val() || '';
                d.departamento = $('#filtroDepartamento').val() || '';
                d.operador     = $('#filtroOperador').val() || '';
                d.sim_asignada = $('#filtroSim').val() || '';
            }
        },
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        stateSave: true,
        dom: 'Bfrtip',
        buttons: ['copy', 'excel', 'csv', 'print'],
        columns: [
            { data: 'id' },
            { data: 'etiqueta' },
            { data: 'marca' },
            { data: 'modelo' },
            { data: 'imei' },
            { data: 'usuario_asignado' },
            { data: 'departamento' },
            { data: 'sim_numero' },
            { data: 'sim_operador' },
            { data: 'estado' },
            { data: 'acciones', orderable: false, searchable: false }
        ],
           // 👇 AÑADIMOS ESTO
        createdRow: function (row, data, dataIndex) {
            // Índice de la columna "Estado"
            var indiceEstado = 9;

            var $celda = $('td:eq(' + indiceEstado + ')', row);
            var estado = $celda.text().toLowerCase().trim();

            if (estado === 'activo') {
                $celda.addClass('estado-activo');
            } else if (estado === 'averiado') {
                $celda.addClass('estado-averiado');
            } else if (estado === 'baja') {
                $celda.addClass('estado-baja');
            } else if ( estado === 'almacen') {
                $celda.addClass('estado-almacen');
            } else if (estado === 'prestado') {
                $celda.addClass('estado-prestado');
            }   
        },

        // Traducción al castellano
        language: {
            search: "Buscar:",
            searchPlaceholder: "Buscar en la tabla...",
            url: 'vendor/datatables/i18n/es-ES.json'
        }
    });

    $('#filtroEstado, #filtroDepartamento, #filtroOperador, #filtroSim').on('change', function () {
        $('#tablaTelefonos').DataTable().ajax.reload();
    });

    $('#limpiarFiltros').on('click', function () {
        $('#filtroEstado').val('');
        $('#filtroDepartamento').val('');
        $('#filtroOperador').val('');
        $('#filtroSim').val('');
        $('#tablaTelefonos').DataTable().ajax.reload();
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
