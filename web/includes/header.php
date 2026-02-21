<?php
// includes/header.php
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inventario Informático</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap local -->
    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css"> 
    <!-- <link rel="stylesheet" href="vendor/bootstrap/css/theme_wildwest.css"> -->
     <!-- CSS local -->
    <link rel="stylesheet" href="vendor/datatables/css/dataTables.dataTables.min.css">
    <link rel="stylesheet" href="vendor/datatables/css/buttons.dataTables.min.css">

    <!-- Iconos Bootstrap -->
    <link rel="stylesheet" href="vendor/bootstrap/bootstrap-icons/bootstrap-icons.css">


    <style>
    /* Overlay de puertas del saloon */
.saloon-overlay {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: flex;
    background: radial-gradient(circle at 50% 10%, rgba(250, 230, 190, 0.35), rgba(0, 0, 0, 0.9));
    overflow: hidden;
}

/* Cada puerta ocupa media pantalla */
.saloon-door {
    flex: 1;
    height: 100%;
    background:
        linear-gradient(90deg, rgba(0,0,0,0.3), transparent 20%, transparent 80%, rgba(0,0,0,0.5)),
        repeating-linear-gradient(
            to bottom,
            #5b3a1b 0,
            #5b3a1b 12px,
            #6d4821 12px,
            #6d4821 24px
        );
    background-size: cover;
    box-shadow: inset 0 0 20px rgba(0,0,0,0.8);
}

/* Puerta izquierda */
.saloon-door-left {
    border-right: 3px solid #c79a5d;
    transform-origin: left center;
    animation: doorLeftOpen 1.8s forwards ease-out;
}

/* Puerta derecha */
.saloon-door-right {
    border-left: 3px solid #c79a5d;
    transform-origin: right center;
    animation: doorRightOpen 1.8s forwards ease-out;
}

/* Desvanecimiento del overlay al final */
.saloon-overlay.hidden {
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.6s ease-out;
}

/* Animaciones */
@keyframes doorLeftOpen {
    0% {
        transform: translateX(0);
    }
    100% {
        transform: translateX(-100%);
    }
}

@keyframes doorRightOpen {
    0% {
        transform: translateX(0);
    }
    100% {
        transform: translateX(100%);
    }
}

.estado-activo {
    background-color: #28a745 !important;  /* verde */
    color: white !important;
    font-weight: bold;
    text-align: center;
}

.estado-averiado {
    background-color: #ffc107 !important;  /* amarillo */
    color: #000 !important;
    font-weight: bold;
    text-align: center;
}

.estado-baja {
    background-color: #dc3545 !important;  /* rojo */
    color: white !important;
    font-weight: bold;
    text-align: center;
}
.estado-almacen {
    background-color: #939595ff !important;  /* azul */
    color: white !important;
    font-weight: bold;
    text-align: center;

}
.estado-prestado {
    background-color: #17a2b8 !important;  /* cyan */
    color: white !important;
    font-weight: bold;
    text-align: center;
}

/* Resalta etiquetas visibles en selects/listados */
.etiqueta-ok {
    display: block;
    width: 100%;
    box-sizing: border-box;
    padding: 0.2rem 0.45rem;
    background: linear-gradient(180deg, #2f85ff 0%, #175fce 100%) !important;
    color: #fff !important;
    font-weight: 700;
    text-align: center;
    text-shadow: 0 1px 0 rgba(0, 0, 0, 0.25);
    box-shadow: inset 0 -1px 0 rgba(255, 255, 255, 0.15), 0 1px 2px rgba(0, 0, 0, 0.18);
    border-radius: 0.35rem;
}
.etiqueta-missing {
    display: block;
    width: 100%;
    box-sizing: border-box;
    padding: 0.2rem 0.45rem;
    background: #f1f3f5;
    color: #6c757d;
    font-style: italic;
    text-align: center;
    border-radius: 0.35rem;
}
.etiqueta-inline {
    display: inline-block;
    width: auto;
}
.estado-provado {
    background-color: #d876cfff !important;  /* cyan */
    color: white !important;
    font-weight: bold;
    text-align: center;
}

.etiqueta-numero {
    display: block;
    background-color: #d65252ff !important;  /* rojo */
    color: white !important;
    font-weight: bold;
    text-align: center;
    font-size: 18px;
    padding: 0.2rem 0.45rem;
    border-radius: 0.35rem;
}

.custom-dropdown-menu {
    position: absolute;
    top: 100%;
    left: 0;
    min-width: 220px;
    display: none;
    padding: 0.25rem 0;
    z-index: 1050;
}

.custom-dropdown-menu .dropdown-item {
    display: block;
    width: 100%;
    padding: 0.25rem 1.0rem;
    clear: both;
    text-align: left;
    white-space: nowrap;
    text-decoration: none;
    background-color: transparent;
}

.custom-dropdown-menu .dropdown-item:hover {
    background-color: rgba(255,255,255,0.1);
}

/* Clase que muestra el menú */
.custom-dropdown-menu.show {
    display: block;
}

.monitor-actual {
    background-color: #e8ec9dff !important;  /* verde */
    color: #414141 !important;
    font-weight: bold;
    text-align: center;
}

.campo-destacado {
    background-color: #fff !important;
    border: 1px solid #ffe08a !important;
    box-shadow: inset 0 0 0 1px #fff3cd;
}

.campo-averia {
    background-color: #ffbcbcff !important;
}

.campo-etiqueta {
    background-color: #2c7ee2ff !important;
    color: white !important;
}

/* Resalte de datos sensibles de usuario/telefono */
.dato-contacto-destacado {
    display: inline-block;
    padding: 0.2rem 0.45rem;
    border-radius: 0.35rem;
    background: #050505;
    color: #9cff8a;
    font-weight: 700;
    line-height: 1.2;
    letter-spacing: 0.01em;
}
.dato-contacto-destacado-vacio {
    background: #1f1f1f;
    color: #bfc6c9;
}

.sim-numero-destacado {
    display: inline-block;
    padding: 0.16rem 0.45rem;
    border-radius: 0.35rem;
    background: #c81e1e;
    color: #fff;
    font-weight: 700;
    line-height: 1.2;
}
.sim-numero-destacado-vacio {
    background: #5e5e5e;
    color: #f0f0f0;
}
#tablaEquipos
#tablaTelefonos
#tablaSims td {
    padding: 4px 6px !important;
    font-size: 14px !important;
}

#equiposTabs .nav-link {
    position: relative;
    padding-bottom: 6px;
}

#equiposTabs .nav-link.active::after {
    content: "";
    position: absolute;
    left: 0;
    bottom: -2px;
    width: 100%;
    height: 3px;
    background: #0d6efd;
    border-radius: 3px;
}

.col-tipo-averia {
    max-width: 45px;        /* ajusta el tamaño que quieras */
    white-space: nowrap;     /* evita salto de línea */
    overflow: hidden;        /* oculta exceso */
    text-overflow: ellipsis; /* pone "..." */
}

.table-sticky thead th {
    position: sticky;
    top: 0;
    background: #f8f9fa;
    z-index: 2;
}

.table-sticky .col-acciones {
    position: sticky;
    right: 0;
    background: #fff;
    z-index: 3;
    box-shadow: -6px 0 10px -8px rgba(0, 0, 0, 0.25);
}

.table-sticky thead .col-acciones {
    z-index: 4;
}

.form-actions-fixed {
    position: sticky;
    bottom: 0;
    z-index: 15;
    background: rgba(255,255,255,0.95);
    backdrop-filter: blur(3px);
    border-top: 1px solid #dee2e6;
}

.thumb-grid img {
    border-radius: 8px;
    border: 1px solid #e0e0e0;
    transition: transform 0.12s ease, box-shadow 0.12s ease;
    max-height: 90px;
    object-fit: cover;
}

.thumb-grid img:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.1);
}

.thumb-grid-wrapper {
    max-height: 280px;
    overflow-y: auto;
    padding-right: 4px;
}

.seleccionar-imagen.active-selection {
    border: 1px solid #0d6efd !important;
    box-shadow: 0 0 0 2px rgba(13,110,253,0.2);
}

.sticky-summary {
    position: sticky;
    top: 84px;
}

/* Mantener visible la barra de desplazamiento horizontal en tablas anchas */
.table-responsive,
.dataTables_scrollBody {
    overflow-x: scroll !important;
    scrollbar-gutter: stable;
}

.table-responsive::-webkit-scrollbar,
.dataTables_scrollBody::-webkit-scrollbar {
    height: 12px;
}

.table-responsive::-webkit-scrollbar-thumb,
.dataTables_scrollBody::-webkit-scrollbar-thumb {
    background-color: #b1b1b1;
    border-radius: 8px;
}

.table-responsive::-webkit-scrollbar-track,
.dataTables_scrollBody::-webkit-scrollbar-track {
    background-color: #f1f1f1;
}

:root {
    --sidebar-width: 280px;
}

.sidebar-toggle {
    position: fixed;
    top: 12px;
    left: 12px;
    z-index: 1100;
}

.app-sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: var(--sidebar-width);
    height: 100vh;
    background: #212529;
    color: #fff;
    z-index: 1090;
    overflow-y: auto;
    padding: 16px 12px;
    transition: transform 0.2s ease;
    box-shadow: 2px 0 16px rgba(0, 0, 0, 0.25);
}

.app-sidebar .sidebar-brand {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
}

.app-sidebar .sidebar-brand img {
    height: 56px;
}

.app-sidebar .sidebar-brand a {
    color: #fff;
    text-decoration: none;
    font-weight: 600;
}

.app-sidebar .nav-link {
    color: rgba(255, 255, 255, 0.9);
}

.app-sidebar .nav-link:hover,
.app-sidebar .nav-link:focus,
.app-sidebar .nav-link.active {
    color: #fff;
    background: rgba(255, 255, 255, 0.08);
    border-radius: 0.35rem;
}

.app-sidebar .dropdown-menu {
    position: static !important;
    float: none;
    transform: none !important;
    margin: 4px 0 8px 10px;
    min-width: unset;
    background: rgba(255, 255, 255, 0.95);
}

.app-sidebar .dropdown-item {
    white-space: normal;
}

.app-sidebar .sidebar-footer {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid rgba(255, 255, 255, 0.15);
}

.app-main {
    margin-left: calc(var(--sidebar-width) + 12px);
    transition: margin-left 0.2s ease;
}

body > footer {
    margin-left: var(--sidebar-width);
    transition: margin-left 0.2s ease;
}

body.sidebar-collapsed .app-sidebar {
    transform: translateX(-100%);
}

body.sidebar-collapsed .app-main,
body.sidebar-collapsed > footer {
    margin-left: 0;
}

body.sidebar-collapsed .app-main {
    margin-left: auto;
    margin-right: auto;
}

@media (max-width: 991.98px) {
    .app-main,
    body > footer {
        margin-left: 0 !important;
    }
    .app-sidebar {
        transform: translateX(-100%);
    }
    body.sidebar-open .app-sidebar {
        transform: translateX(0);
    }
}

</style>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</head>
<body>

<?php
// En qué página estoy (index.php, dashboard.php, etc.)
$currentPage = basename($_SERVER['PHP_SELF']);
$adminTab = strtolower((string)($_GET['tab'] ?? ''));

$isDashboard = ($currentPage === 'dashboard.php');
$isEquipos = ($currentPage === 'index.php') || str_starts_with($currentPage, 'equipo_');
$isRedes = in_array($currentPage, ['redes.php', 'redes_gestion.php', 'buscar_ip.php', 'plano_red.php'], true);
$isMoviles = in_array($currentPage, ['telefonos.php', 'sims.php'], true)
    || str_starts_with($currentPage, 'telefono_')
    || str_starts_with($currentPage, 'sim_');
$isMateriales = ($currentPage === 'materiales.php');
$isAverias = ($currentPage === 'averias_list.php') || str_starts_with($currentPage, 'averia_');
$isRevistas = ($currentPage === 'verificaciones.php');
$isManual = ($currentPage === 'manual_usuario.php');
$isUsuarioAsociado = ($currentPage === 'usuario_asociado.php');
$isAdmin = ($currentPage === 'admin_catalogos.php') || ($currentPage === 'verificaciones_reportes.php');
$isRecibos = in_array($currentPage, ['renovaciones.php', 'movimientos.php', 'recibo_movimiento.php', 'recibo_renovacion.php', 'recibo_renovacion_telefono.php'], true);
$isLogs = ($currentPage === 'actividad_logs.php');
?>

    <!--
    <div style="padding:10px 0; text-align:left;">
    <img src="assets/logo_departamento.png" 
         alt="Logo del departamento" 
         style="height:60px;">
    </div> -->
<button id="sidebarToggle" class="btn btn-dark btn-sm sidebar-toggle" type="button" aria-label="Mostrar u ocultar menú">
    ☰ Menú
</button>

<aside id="appSidebar" class="app-sidebar">
    <div class="sidebar-brand">
        <img src="assets/logo_departamento.png" alt="Logo">
        <a href="index.php">Inventario IT</a>
    </div>

    <a class="btn btn-sm btn-outline-light w-100 mb-3 <?= $isDashboard ? 'active' : '' ?>" href="dashboard.php">
        Dashboard
    </a>

    <ul class="navbar-nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?= $isEquipos ? 'active' : '' ?>" href="index.php">👨🏽‍💻 Equipos</a>
        </li>

        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle <?= $isRedes ? 'active' : '' ?>" href="#" id="redesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="<?= $isRedes ? 'true' : 'false' ?>">
                🛜 Redes
            </a>
            <ul class="dropdown-menu <?= $isRedes ? 'show' : '' ?>" aria-labelledby="redesDropdown">
                <li><a class="dropdown-item <?= $currentPage === 'redes.php' ? 'active' : '' ?>" href="redes.php">Control IPs</a></li>
                <li><a class="dropdown-item <?= $currentPage === 'redes_gestion.php' ? 'active' : '' ?>" href="redes_gestion.php">Gestion Redes</a></li>
                <li><a class="dropdown-item <?= $currentPage === 'buscar_ip.php' ? 'active' : '' ?>" href="buscar_ip.php">Buscar IP</a></li>
                <li><a class="dropdown-item <?= $currentPage === 'plano_red.php' ? 'active' : '' ?>" href="plano_red.php">Plano red</a></li>
            </ul>
        </li>

        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle <?= $isMoviles ? 'active' : '' ?>" href="#" id="movilesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="<?= $isMoviles ? 'true' : 'false' ?>">
                📱 Móviles / SIMS
            </a>
            <ul class="dropdown-menu <?= $isMoviles ? 'show' : '' ?>" aria-labelledby="movilesDropdown">
                <li><a class="dropdown-item <?= str_starts_with($currentPage, 'telefono') ? 'active' : '' ?>" href="telefonos.php">Teléfonos</a></li>
                <li><a class="dropdown-item <?= str_starts_with($currentPage, 'sim') || $currentPage === 'sims.php' ? 'active' : '' ?>" href="sims.php">Tarjetas SIM</a></li>
            </ul>
        </li>

        <li class="nav-item">
            <a class="nav-link <?= $isMateriales ? 'active' : '' ?>" href="materiales.php">📦 Material / Fungibles</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $isAverias ? 'active' : '' ?>" href="averias_list.php">⚠️ Gestión de averías</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $isRevistas ? 'active' : '' ?>" href="verificaciones.php">✅ Revistas</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $isManual ? 'active' : '' ?>" href="manual_usuario.php">📘 Manual</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $isUsuarioAsociado ? 'active' : '' ?>" href="usuario_asociado.php">👤 Activos por TIP</a>
        </li>

        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle <?= $isAdmin ? 'active' : '' ?>" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="<?= $isAdmin ? 'true' : 'false' ?>">
                Administración
            </a>
            <ul class="dropdown-menu <?= $isAdmin ? 'show' : '' ?>" aria-labelledby="adminDropdown">
                <li><a class="dropdown-item <?= $currentPage === 'admin_catalogos.php' && $adminTab === 'usuarios' ? 'active' : '' ?>" href="admin_catalogos.php?tab=usuarios">Usuarios</a></li>
                <li><a class="dropdown-item <?= $currentPage === 'admin_catalogos.php' && $adminTab === 'puestos' ? 'active' : '' ?>" href="admin_catalogos.php?tab=Puestos">Puestos</a></li>
                <li><a class="dropdown-item <?= $currentPage === 'admin_catalogos.php' && $adminTab === 'ubicaciones' ? 'active' : '' ?>" href="admin_catalogos.php?tab=ubicaciones">Ubicaciones</a></li>
                <li><a class="dropdown-item <?= $currentPage === 'admin_catalogos.php' && $adminTab === 'departamentos' ? 'active' : '' ?>" href="admin_catalogos.php?tab=departamentos">Departamentos</a></li>
                <li><a class="dropdown-item <?= $currentPage === 'admin_catalogos.php' && $adminTab === 'tipos' ? 'active' : '' ?>" href="admin_catalogos.php?tab=tipos">Tipos de equipo</a></li>
                <li><a class="dropdown-item <?= $currentPage === 'admin_catalogos.php' && $adminTab === 'servicio' ? 'active' : '' ?>" href="admin_catalogos.php?tab=servicio">Servicio</a></li>
                <li><a class="dropdown-item <?= $currentPage === 'admin_catalogos.php' && $adminTab === 'secciones' ? 'active' : '' ?>" href="admin_catalogos.php?tab=secciones">Secciones</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item <?= $currentPage === 'verificaciones_reportes.php' ? 'active' : '' ?>" href="verificaciones_reportes.php">Reportes verificaciones</a></li>
            </ul>
        </li>

        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle <?= $isRecibos ? 'active' : '' ?>" href="#" id="recibosMenu" role="button" data-bs-toggle="dropdown" aria-expanded="<?= $isRecibos ? 'true' : 'false' ?>">
                📄 Recibos
            </a>
            <ul class="dropdown-menu <?= $isRecibos ? 'show' : '' ?>" aria-labelledby="recibosMenu">
                <li><a class="dropdown-item <?= $currentPage === 'renovaciones.php' || $currentPage === 'recibo_renovacion.php' || $currentPage === 'recibo_renovacion_telefono.php' ? 'active' : '' ?>" href="renovaciones.php">Recibos Renovación</a></li>
                <li><a class="dropdown-item <?= $currentPage === 'movimientos.php' || $currentPage === 'recibo_movimiento.php' ? 'active' : '' ?>" href="movimientos.php">Recibos Movimiento</a></li>
            </ul>
        </li>

        <li class="nav-item">
            <a class="nav-link" href="/laptop-loans/public/">Portátiles Formación</a>
        </li>
        <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
            <li class="nav-item">
                <a class="nav-link <?= $isLogs ? 'active' : '' ?>" href="actividad_logs.php">📝 Logs</a>
            </li>
        <?php endif; ?>
    </ul>

    <?php if (!empty($_SESSION['tip'])): ?>
        <div class="sidebar-footer">
            <div class="small mb-2"><?= htmlspecialchars($_SESSION['tip']) ?> (<?= htmlspecialchars($_SESSION['rol']) ?>)</div>
            <a href="logout.php" class="btn btn-outline-light btn-sm w-100">Salir</a>
        </div>
    <?php endif; ?>
</aside>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.getElementById('sidebarToggle');
    var storageKey = 'inventario_sidebar_collapsed';
    var isMobile = window.matchMedia('(max-width: 991.98px)').matches;

    if (isMobile) {
        document.body.classList.add('sidebar-collapsed');
    } else if (localStorage.getItem(storageKey) === '1') {
        document.body.classList.add('sidebar-collapsed');
    }

    if (!toggle) return;

    toggle.addEventListener('click', function () {
        if (window.matchMedia('(max-width: 991.98px)').matches) {
            document.body.classList.toggle('sidebar-open');
            document.body.classList.toggle('sidebar-collapsed');
            return;
        }
        document.body.classList.toggle('sidebar-collapsed');
        localStorage.setItem(storageKey, document.body.classList.contains('sidebar-collapsed') ? '1' : '0');
    });
});
</script>



<div class="container mb-5 app-main">
