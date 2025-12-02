<?php
require_once 'auth.php';
require_once 'config.php';
require_once 'includes/header.php';


// Total SIMs
$totalSims = (int)$pdo->query("SELECT COUNT(*) FROM sims")->fetchColumn();

// SIMs disponibles
$totalSimsDisponibles = (int)$pdo->query("SELECT COUNT(*) FROM sims WHERE estado = 'Disponible'")->fetchColumn();

// SIMs en baja
$totalSimsBaja = (int)$pdo->query("SELECT COUNT(*) FROM sims WHERE estado = 'Baja'")->fetchColumn();



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

    <table id="tablaSims" class="table table-striped table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Número</th>
                <th>ICCID</th>
                <th>Operador</th>
                <th>Tarifa</th>
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
        dom: 'Bfrtip',
        buttons: ['copy', 'excel', 'csv', 'print'],
        columns: [
            { data: 'id' },
            { data: 'numero' },
            { data: 'iccid' },
            { data: 'operador' },
            { data: 'tarifa' },
            { data: 'estado' },
            { data: 'acciones', orderable: false, searchable: false }
        ]
    });

});
</script>

<?php require_once 'includes/footer.php'; ?>
