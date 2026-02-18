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
        SUM(estado = 'Prestado')          AS prestado,
        SUM(estado = 'Privado' )          AS privado
    FROM equipos
");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Totales de averías
$averiasStmt = $pdo->query("
    SELECT 
        COUNT(*)                   AS total,
        SUM(estado = 'ABIERTA')    AS abiertas,
        SUM(estado = 'CERRADA')    AS cerradas
    FROM averias
");
$averiasStats = $averiasStmt->fetch(PDO::FETCH_ASSOC);

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

// Listas para filtros rápidos
$tiposFiltro = $pdo->query("SELECT nombre FROM tipos_equipo ORDER BY nombre ASC")->fetchAll(PDO::FETCH_COLUMN);
$ubicacionesFiltro = $pdo->query("SELECT nombre FROM ubicaciones ORDER BY nombre ASC")->fetchAll(PDO::FETCH_COLUMN);
$seccionesFiltro = $pdo->query("SELECT id, nombre FROM secciones ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<!-- DASHBOARD RESUMEN -->
<div class="row mb-2">

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
                <div class="small text-uppercase"><a href="averias_list.php" style="text-decoration: none; color:white;">Averías abiertas</a></div>
                <div class="fs-4 fw-bold">
                    <?= (int)($averiasStats['abiertas'] ?? 0) ?>
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
        <div class="card text-bg-secondary h-100">
            <div class="card-body py-2">
                <div class="small text-uppercase">Privados</div>
                <div class="fs-4 fw-bold">
                    <?= (int)($stats['privado'] ?? 0) ?>
                </div>
            </div>
        </div>
    </div>

        <div class="col-md-2 col-sm-4 mb-2">
  <div class="card text-bg-info h-100 card-movimientos">
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

    <!-- POCKETWATCH (anclado a esta card) -->
    <div class="pocketwatch-card" title="Hora local">
      <div class="pocketwatch">
        <img class="pocketwatch-bg" src="assets/pocketwatch/watch.png" alt="Pocket Watch">
        <div class="hand hour"   id="pw-hour"></div>
        <div class="hand minute" id="pw-minute"></div>
        <div class="hand second" id="pw-second"></div>
        <div class="pin"></div>
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

<div class="card mb-3 shadow-sm">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-3 col-lg-4">
                <label class="form-label mb-0 small text-muted">Búsqueda rápida</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" id="busquedaGlobal" class="form-control" placeholder="Etiqueta, usuario, IP..." aria-label="Buscar">
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-0 small text-muted">Estado</label>
                <select id="filtroEstado" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach (['Activo','Almacén','Averiado','Baja','Prestado','Privado'] as $estadoOpt): ?>
                        <option value="<?= $estadoOpt ?>"><?= $estadoOpt ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-0 small text-muted">Tipo</label>
                <select id="filtroTipo" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach ($tiposFiltro as $tipoNombre): ?>
                        <option value="<?= htmlspecialchars(strtoupper($tipoNombre)) ?>">
                            <?= htmlspecialchars($tipoNombre) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-0 small text-muted">Ubicación</label>
                <select id="filtroUbicacion" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <?php foreach ($ubicacionesFiltro as $ubi): ?>
                        <option value="<?= htmlspecialchars(strtoupper($ubi)) ?>">
                            <?= htmlspecialchars($ubi) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-0 small text-muted">Sección</label>
                <select id="filtroSeccion" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <?php foreach ($seccionesFiltro as $sec): ?>
                        <option value="<?= (int)$sec['id'] ?>"><?= htmlspecialchars($sec['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-0 small text-muted">Etiqueta</label>
                <select id="filtroEtiqueta" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <option value="con">Con etiqueta</option>
                    <option value="sin">Sin etiqueta</option>
                </select>
            </div>
            <div class="col-md-1 d-flex justify-content-end">
                <button type="button" id="limpiarFiltros" class="btn btn-outline-info btn-sm w-100">Limpiar</button>
            </div>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2">
            <ul class="nav nav-tabs small mb-0" id="equiposTabs">
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
                    <a class="nav-link" data-tipo="PTI">PTI</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-tipo="MONITOR">Monitores</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-tipo="IMPRESORA">Impresoras</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-tipo="IMPRESORA MULTIFUNCION">Multifunción</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-tipo="ESCANER">Escáner</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-tipo="DOCK">Dock / Otros</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-tipo="ROUTER">Router</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-tipo="AP">AP WiFi</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-tipo="NAS">NAS</a>
                </li>
            </ul>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="btn-group">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Mostrar / ocultar
                    </button>
                    <div class="dropdown-menu p-3 small" id="colToggleMenu" style="min-width: 220px;"></div>
                </div>
                <div id="exportButtons" class="d-flex gap-2"></div>
            </div>
        </div>
    </div>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'ok'): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        Operación realizada correctamente.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
<?php endif; ?> 
<!-- Tabla -->
<div class="table-responsive">
    <table id="tablaEquipos" class="table table-striped table-sm align-middle table-hover table-sticky">
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
                <th>Últ. renovación</th>
                <th>Últ. control</th>
                <th>Estado</th>
                <th class="col-acciones text-center" style="width: 150px;">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <!-- Ahora lo rellena DataTables por AJAX -->
        </tbody>
    </table>
</div>

<!-- LINK POCKETWATCH -->

<link rel="stylesheet" href="assets/pocketwatch/watch.css">
<script src="assets/pocketwatch/watch.js"></script>

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
        stateSave: true,
        stateDuration: 60 * 60 * 24 * 30, // 30 días

        ajax: {
            url: 'equipos_data.php',
            type: 'GET',
            data: function (d) {

                d.estado = $('#filtroEstado').val() || '';
                d.ubicacion = $('#filtroUbicacion').val() || '';
                d.seccion_id = $('#filtroSeccion').val() || '';
                d.tipo = ($('#filtroTipo').val() || currentFilterTipo || '');
                d.etiqueta_estado = $('#filtroEtiqueta').val() || '';
            }
        },

        // Botones de exportación
        dom: 'Brtip',
        buttons: [
            {
                extend: 'copy',
                text: 'Copiar',
                className: 'btn btn-sm btn-outline-secondary'
            },
            {
                extend: 'excelHtml5',
                text: 'Excel (página actual)',
                className: 'btn btn-sm btn-outline-secondary',
                exportOptions: {
                    // sin 2 (Imagen) ni 14 (Acciones)
                    columns: [0, 1, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13]
                }
            },
            {
                extend: 'csvHtml5',
                text: 'CSV (página actual)',
                className: 'btn btn-sm btn-outline-secondary',
                exportOptions: {
                    columns: [0, 1, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13]
                }
            },
            {
                extend: 'print',
                text: 'Imprimir',
                className: 'btn btn-sm btn-outline-secondary',
                exportOptions: {
                    columns: [0, 1, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13]
                }
            },
            {
                text: 'CSV (todos los registros)',
                className: 'btn btn-sm btn-outline-secondary',
                action: function (e, dt, button, config) {
                    var params = dt.ajax.params();
                    params.export = 'csv';
                    var query = $.param(params);
                    window.location = 'equipos_export.php?' + query;
                }
            },
            {
                text: 'Excel (todos los registros)',
                className: 'btn btn-sm btn-outline-secondary',
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
            { orderable: false, searchable: false, targets: [2, 14] }, // imagen y acciones
            { targets: 14, className: 'col-acciones text-center' }
        ],

        createdRow: function (row, data, dataIndex) {


            // 1. Colorear celda de estado según su valor
            var indiceEstado = 13; // índice de la columna "Estado"
            var $celda = $('td:eq(' + indiceEstado + ')', row);
            if ($celda.find('.badge').length === 0) {
                var estadoOriginal = $celda.text().trim();
                var estado = estadoOriginal.toLowerCase();
                var badgeClass = 'bg-secondary';
                var icon = 'bi-info-circle-fill';

                if (estado === 'activo') {
                    badgeClass = 'bg-success';
                    icon = 'bi-check-circle-fill';
                } else if (estado === 'averiado') {
                    badgeClass = 'bg-warning text-dark';
                    icon = 'bi-exclamation-triangle-fill';
                } else if (estado === 'baja') {
                    badgeClass = 'bg-danger';
                    icon = 'bi-x-circle-fill';
                } else if (estado === 'almacen') {
                    badgeClass = 'bg-secondary';
                    icon = 'bi-archive-fill';
                } else if (estado === 'prestado') {
                    badgeClass = 'bg-info text-dark';
                    icon = 'bi-clock-history';
                } else if (estado === 'privado') {
                    badgeClass = 'bg-dark';
                    icon = 'bi-shield-lock-fill';
                }

                $celda
                    .addClass('text-center')
                    .html('<span class="badge rounded-pill ' + badgeClass + ' d-inline-flex align-items-center gap-1">' +
                        '<i class="bi ' + icon + '"></i>' +
                        estadoOriginal +
                    '</span>');
            }

            // Activar tooltips en la fila
            if (window.bootstrap) {
                $('td [data-bs-toggle="tooltip"]', row).each(function () {
                    var existing = bootstrap.Tooltip.getInstance(this);
                    if (existing) {
                        existing.dispose();
                    }
                    new bootstrap.Tooltip(this);
                });
            }
        },

        language: {
            search: "Buscar:",
            searchPlaceholder: "Buscar en la tabla...",
            url: 'vendor/datatables/i18n/es-ES.json'
        }
    });

    // Mover los botones junto al resto de acciones
    tabla.buttons().container().appendTo('#exportButtons');

    // 🔹 Toggle de columnas (seleccionadas)
    const $toggleMenu = $('#colToggleMenu');
    const toggleCols = [
        { idx: 2, label: 'Imagen' },
        { idx: 3, label: 'Nº Serie' },
        { idx: 4, label: 'Tipo' },
        { idx: 5, label: 'Marca / Modelo' },
        { idx: 6, label: 'Usuario / Dpto / Sección' },
        { idx: 7, label: 'Servicio' },
        { idx: 8, label: 'Ubicación' },
        { idx: 9, label: 'IP Asignada' },
        { idx: 10, label: 'Monitores' },
        { idx: 11, label: 'Últ. renovación' },
        { idx: 12, label: 'Últ. control' }
    ];

    function syncToggleChecks() {
        toggleCols.forEach(function (col) {
            const column = tabla.column(col.idx);
            $('#toggleCol' + col.idx).prop('checked', column.visible());
        });
    }

    toggleCols.forEach(function (col) {
        const column = tabla.column(col.idx);
        const checkboxId = 'toggleCol' + col.idx;
        const $wrapper = $('<div class="form-check mb-1"></div>');
        const $input = $('<input>', {
            type: 'checkbox',
            class: 'form-check-input',
            id: checkboxId
        }).data('col', col.idx).prop('checked', column.visible());
        const $label = $('<label>', {
            class: 'form-check-label',
            for: checkboxId,
            text: col.label
        });

        $input.on('change', function () {
            column.visible($(this).is(':checked'));
            tabla.columns.adjust();
            tabla.state.save(); // persistir al vuelo
        });

        $wrapper.append($input, $label);
        $toggleMenu.append($wrapper);
    });

    // Sincroniza checks si la visibilidad viene de stateSave
    tabla.on('stateLoaded', function () {
        syncToggleChecks();
    });

    // 🔹 Búsqueda global personalizada
    $('#busquedaGlobal').on('keyup change', function () {
        tabla.search(this.value).draw();
    });

    // 🔹 Selects de filtro
    $('#filtroEstado, #filtroTipo, #filtroUbicacion, #filtroSeccion, #filtroEtiqueta').on('change', function () {
        tabla.ajax.reload();
    });

    // 🔹 Limpiar filtros
    $('#limpiarFiltros').on('click', function () {
        $('#busquedaGlobal').val('');
        $('#filtroEstado, #filtroTipo, #filtroUbicacion, #filtroSeccion, #filtroEtiqueta').val('');
        currentFilterTipo = "";

        $('#equiposTabs .nav-link').removeClass('active');
        $('#equiposTabs .nav-link').first().addClass('active');

        tabla.search('').draw();
        tabla.ajax.reload();
    });

    // 🔹 Click en pestañas (tabs)
    $('#equiposTabs .nav-link').on('click', function (e) {
        e.preventDefault();

        $('#equiposTabs .nav-link').removeClass('active');
        $(this).addClass('active');

        currentFilterTipo = $(this).data('tipo') || "";
        $('#filtroTipo').val(currentFilterTipo);
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
                $('#filtroTipo').val(tipoFiltro);
            }
        });

        tabla.ajax.reload();
    }

});
</script>


<?php
require_once __DIR__ . '/includes/footer.php';
