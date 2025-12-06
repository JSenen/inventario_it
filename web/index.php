<?php
// index.php
require_once 'auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . "/includes/logger.php";

// Reproducir sonido de entrada si se ha iniciado sesión correctamente
if (!empty($_SESSION['play_saloon_sound'])): ?>
    <!-- Overlay de puertas del saloon -->
    <div id="saloonOverlay" class="saloon-overlay">
        <div class="saloon-door saloon-door-left"></div>
        <div class="saloon-door saloon-door-right"></div>
    </div>

    <!-- Sonido de puertas -->
    <audio id="saloonSound" autoplay>
        <source src="sounds/west.mp3" type="audio/mpeg">
    </audio>

    <script>
        window.addEventListener('DOMContentLoaded', () => {
            const audio   = document.getElementById('saloonSound');
            const overlay = document.getElementById('saloonOverlay');

            // Reproducir sonido (por si autoplay se bloquea)
            if (audio) {
                audio.volume = 1.0;
                audio.play().catch(() => {});
            }

            if (overlay) {
                const removeOverlay = () => {
                    overlay.classList.add('hidden');
                    setTimeout(() => {
                        if (overlay && overlay.parentNode) {
                            overlay.parentNode.removeChild(overlay);
                        }
                    }, 700);
                };

                // Cuando terminen las animaciones de las puertas
                overlay.addEventListener('animationend', removeOverlay, { once: true });

                // Por si acaso, lo quitamos también tras un tiempo máximo
                setTimeout(removeOverlay, 3000);
            }
        });
    </script>

    <?php unset($_SESSION['play_saloon_sound']); ?>
<?php endif; ?>

<?php

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
        e.numero_serie,
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

// Stats movimientos de hoy
$movStmt = $pdo->query("
    SELECT
        COUNT(*) AS total_hoy,
        SUM(tipo = 'entrega')  AS entregas_hoy,
        SUM(tipo = 'recogida') AS recogidas_hoy
    FROM equipos_movimientos
    WHERE DATE(fecha) = CURDATE()
");
$movStatsHoy = $movStmt->fetch(PDO::FETCH_ASSOC);


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

        <div class="col-md-2 col-sm-4 mb-2">
        <div class="card text-bg-info h-100">
            <div class="card-body py-2">
                <div class="small text-uppercase">Movimientos hoy</div>
                <div class="fs-6">
                    Entregas: <strong><?= (int)($movStatsHoy['entregas_hoy'] ?? 0) ?></strong><br>
                    Recogidas: <strong><?= (int)($movStatsHoy['recogidas_hoy'] ?? 0) ?></strong>
                </div>
            </div>
            <div class="card-footer p-1 text-end">
                <a href="movimientos.php" class="small text-white">Ver detalle</a>
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

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'ok'): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        Operación realizada correctamente.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
<?php endif; ?> 
<!-- 
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
    <?php endif; ?> -->


    <ul class="nav nav-tabs mb-3" id="equiposTabs">
    <li class="nav-item">
        <a class="nav-link active" data-tipo="">Todos</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-tipo="PC">PCs</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-tipo="PORTATIL">Portátiles</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-tipo="MONITOR">Monitores</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-tipo="IMPRESORA">Impresoras</a>
    </li>
     <li class="nav-item">
        <a class="nav-link" data-tipo="ESCANER">Escaner</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-tipo="DOCK">Dock / Otros</a>
    </li>
</ul>


    <div class="table-responsive" >
        <table class="table table-hover align-middle">
           <table id="tablaEquipos" class="table table-striped table-sm align-middle">
    <thead>
        <tr>
            <th>ID</th>
            <th>Etiqueta</th>
            <th>Imagen</th>
            <th>Nº serie</th>
            <th>Tipo</th>
            <th>Marca / Modelo</th>
            <th>Usuario / Depto / Sección</th>
            <th>Servicio</th>
            <th>Ubicación</th>
            <th>IP Asignada</th>
            <!--<th>Red</th>-->
            <th>Monitores</th>
            <th>Estado</th>
            <th style="width: 150px;">Acciones</th>
        </tr>
    </thead>
    <tbody>
        <!-- Ahora lo rellena DataTables por AJAX -->
    </tbody>
</table>


    </div>
<?php endif; ?>

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

    // 🔹 Filtro actual por TIPO desde las pestañas
    let currentFilterTipo = "";

    var tabla = $('#tablaEquipos').DataTable({

        processing: true,
        serverSide: true,
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],

        ajax: {
            url: 'equipos_data.php',
            type: 'GET',
            data: function (d) {

                // Filtro de estado (si tienes un select #filtroEstado)
                d.estado = $('#filtroEstado').val() || '';

                // Si mantienes un select #filtroTipo, que tenga prioridad
                if ($('#filtroTipo').length && $('#filtroTipo').val()) {
                    d.tipo = $('#filtroTipo').val();
                } else {
                    // Si no, usamos el tipo seleccionado en las pestañas
                    d.tipo = currentFilterTipo;
                }
            }
        },

        // Botones de exportación
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'copy',
                text: 'Copiar'
            },
            {
                extend: 'excelHtml5',
                text: 'Excel (página actual)',
                exportOptions: {
                    // sin 2 (Imagen) ni 12 (Acciones)
                    columns: [0, 1, 3, 4, 5, 6, 7, 8, 9, 10, 11]
                }
            },
            {
                extend: 'csvHtml5',
                text: 'CSV (página actual)',
                exportOptions: {
                    columns: [0, 1, 3, 4, 5, 6, 7, 8, 9, 10, 11]
                }
            },
            {
                extend: 'print',
                text: 'Imprimir',
                exportOptions: {
                    columns: [0, 1, 3, 4, 5, 6, 7, 8, 9, 10, 11]
                }
            },
            {
                text: 'CSV (todos los registros)',
                action: function (e, dt, button, config) {
                    var params = dt.ajax.params();
                    params.export = 'csv';
                    var query = $.param(params);
                    window.location = 'equipos_export.php?' + query;
                }
            },
            {
                text: 'Excel (todos los registros)',
                action: function (e, dt, button, config) {
                    var params = dt.ajax.params();
                    var query = $.param(params);
                    window.location = 'equipos_export_excel.php?' + query;
                }
            }
        ],

        order: [[0, 'desc']],
        autoWidth: false,
        scrollX: true,
        columnDefs: [
            { orderable: false, searchable: false, targets: [1, 11] } // imagen y acciones
        ],

        createdRow: function (row, data, dataIndex) {


            //  1. Obtener la celda y el valor real de la etiqueta
            var indiceEtiqueta = 1; 
            var $celdaEtiqueta = $('td:eq(' + indiceEtiqueta + ')', row);
            var etiqueta = $celdaEtiqueta.text().trim();

            //  2. Si tiene etiqueta -> aplicar CSS especial
            if (etiqueta !== '') {
                $celdaEtiqueta.addClass('etiqueta-ok'); 
            }

            // 3. Colorear celda de estado según su valor
            var indiceEstado = 11; // índice de la columna "Estado"
            var $celda = $('td:eq(' + indiceEstado + ')', row);
            var estado = $celda.text().toLowerCase().trim();

            if (estado === 'activo') {
                $celda.addClass('estado-activo');
            } else if (estado === 'averiado') {
                $celda.addClass('estado-averiado');
            } else if (estado === 'baja') {
                $celda.addClass('estado-baja');
            } else if (estado === 'almacen') {
                $celda.addClass('estado-almacen');
            } else if (estado === 'prestado') {
                $celda.addClass('estado-prestado');
            }
        },

        language: {
            search: "Buscar:",
            searchPlaceholder: "Buscar en la tabla...",
            url: 'vendor/datatables/i18n/es-ES.json'
        }
    });

    // 🔹 Click en pestañas (tabs)
    $('#equiposTabs .nav-link').on('click', function (e) {
        e.preventDefault();

        $('#equiposTabs .nav-link').removeClass('active');
        $(this).addClass('active');

        currentFilterTipo = $(this).data('tipo') || "";
        tabla.ajax.reload();
    });

    // 🔹 Opcional: activar pestaña según ?tipo= en la URL
    const urlParams  = new URLSearchParams(window.location.search);
    const tipoFiltro = urlParams.get('tipo') || '';

    if (tipoFiltro) {
        $('#equiposTabs .nav-link').each(function () {
            const tipo = $(this).data('tipo') || '';
            if (tipo === tipoFiltro) {
                $('#equiposTabs .nav-link').removeClass('active');
                $(this).addClass('active');
                currentFilterTipo = tipo;
            }
        });

        tabla.ajax.reload();
    }

});
</script>


<?php
require_once __DIR__ . '/includes/footer.php';
