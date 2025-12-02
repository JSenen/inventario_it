<?php
// admin_catalogos.php
require_once 'config.php';              // Conexión a BD ($pdo)
require_once 'auth.php';                // Autenticación / sesión
require_once 'includes/header.php';     // Cabecera HTML + menú

// Pestaña activa
$tab = $_GET['tab'] ?? 'usuarios';
$validTabs = ['usuarios', 'departamentos', 'ubicaciones', 'tipos','servicio','secciones'];
if (!in_array($tab, $validTabs)) {
    $tab = 'usuarios';
}

// Aquí podrías comprobar que el usuario logueado es admin
// if ($_SESSION['rol'] !== 'admin') { die('Acceso restringido'); }
?>

<div class="container-fluid mt-4">
    <h2>Administración</h2>

    <!-- Subpestañas -->
    <ul class="nav nav-tabs mt-3">
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'usuarios' ? 'active' : '' ?>"
               href="admin_catalogos.php?tab=usuarios">Usuarios</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'ubicaciones' ? 'active' : '' ?>"
               href="admin_catalogos.php?tab=ubicaciones">Ubicaciones</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'departamentos' ? 'active' : '' ?>"
               href="admin_catalogos.php?tab=departamentos">Departamentos</a>
        </li>
        
         <li class="nav-item">
            <a class="nav-link <?= $tab === 'secciones' ? 'active' : '' ?>"
               href="admin_catalogos.php?tab=secciones">Secciones</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'tipos' ? 'active' : '' ?>"
               href="admin_catalogos.php?tab=tipos">Tipos de equipo</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'servicio' ? 'active' : '' ?>"
               href="admin_catalogos.php?tab=servicio">Tipos de Servicio</a>
        </li>
    
    </ul>
    
       <div class="tab-content p-3 border border-top-0 w-100">

    <?php if ($tab === 'usuarios'): ?>

        <?php
        $stmt = $pdo->query("SELECT id, tip, rol, creado_en FROM usuarios ORDER BY id ASC");
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4>Usuarios de la aplicación</h4>
            <a href="usuario_nuevo.php" class="btn btn-primary btn-sm">Añadir usuario</a>
        </div>

        
        
         
                
            
            <table class="table table-hover align-middle">
           <table class="table table-striped table-sm align-middle" id="tablaUsuarios" >
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>TIP</th>
                        <th>Rol</th>
                        <th>Creado en</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['id']) ?></td>
                        <td><?= htmlspecialchars($u['tip']) ?></td>
                        <td><?= htmlspecialchars($u['rol']) ?></td>
                        <td><?= htmlspecialchars($u['creado_en']) ?></td>
                        <td>
                            <a href="usuario_editar.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-warning">
                                Editar
                            </a>
                            <a href="usuario_borrar.php?id=<?= $u['id'] ?>"
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('¿Seguro que quieres borrar este usuario?');">
                                Borrar
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
                  

   

                    
        <?php elseif ($tab === 'departamentos'): ?>

            <?php
            $departamentos = [];
            try {
                $departamentos = $pdo->query("SELECT * FROM departamentos ORDER BY nombre ASC")
                                     ->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                // Si la tabla aún no existe, mostramos un aviso suave
                echo '<div class="alert alert-info">La tabla <b>departamentos</b> todavía no existe.</div>';
            }
            ?>

            <?php if ($departamentos): ?>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4>Departamentos</h4>
                    <a href="departamento_nuevo.php" class="btn btn-primary btn-sm">Añadir departamento</a>
                </div>

                <table class="table table-striped table-sm" id="tablaDepartamentos">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($departamentos as $d): ?>
                        <tr>
                            <td><?= htmlspecialchars($d['id']) ?></td>
                            <td><?= htmlspecialchars($d['nombre']) ?></td>
                            <td><?= htmlspecialchars($d['descripcion']??'') ?></td>
                            <td>
                                <a href="departamento_editar.php?id=<?= $d['id'] ?>"
                                   class="btn btn-sm btn-warning">Editar</a>
                                <a href="departamento_borrar.php?id=<?= $d['id'] ?>"
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('¿Borrar este departamento?');">
                                    Borrar
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

        <?php elseif ($tab === 'ubicaciones'): ?>

            <?php
            $ubicaciones = [];
            try {
                $ubicaciones = $pdo->query("SELECT * FROM ubicaciones ORDER BY nombre ASC")
                                   ->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                echo '<div class="alert alert-info">La tabla <b>ubicaciones</b> todavía no existe.</div>';
            }
            ?>

            <?php if ($ubicaciones): ?>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4>Ubicaciones</h4>
                    <a href="ubicacion_nuevo.php" class="btn btn-primary btn-sm">Añadir ubicación</a>
                </div>

                <table class="table table-striped table-sm" id="tablaUbicaciones">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($ubicaciones as $u): ?>
                        <tr>
                            <td><?= htmlspecialchars($u['id']) ?></td>
                            <td><?= htmlspecialchars($u['nombre']) ?></td>
                            <td><?= htmlspecialchars($u['descripcion']??'') ?></td>
                            <td>
                                <a href="ubicacion_editar.php?id=<?= $u['id'] ?>"
                                   class="btn btn-sm btn-warning">Editar</a>
                                <a href="ubicacion_borrar.php?id=<?= $u['id'] ?>"
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('¿Borrar esta ubicación?');">
                                    Borrar
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

        <?php elseif ($tab === 'tipos'): ?>

            <?php
            $tipos = [];
            try {
                $tipos = $pdo->query("SELECT * FROM tipos_equipo ORDER BY nombre ASC")
                             ->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                echo '<div class="alert alert-info">La tabla <b>tipos_equipo</b> todavía no existe.</div>';
            }
            ?>

            <?php if ($tipos): ?>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4>Tipos de equipo</h4>
                    <a href="tipo_nuevo.php" class="btn btn-primary btn-sm">Añadir tipo</a>
                </div>

                <table class="table table-striped table-sm" id="tablaTiposEquipo">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tipos as $t): ?>
                        <tr>
                            <td><?= htmlspecialchars($t['id']) ?></td>
                            <td><?= htmlspecialchars($t['nombre']) ?></td>
                            <td><?= htmlspecialchars($t['descripcion']??'') ?></td>
                            <td>
                                <a href="tipo_editar.php?id=<?= $t['id'] ?>"
                                   class="btn btn-sm btn-warning">Editar</a>
                                <a href="tipo_borrar.php?id=<?= $t['id'] ?>"
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('¿Borrar este tipo?');">
                                    Borrar
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <?php elseif ($tab === 'servicio'): ?>

            <?php
            $servicio = [];
            try {
                $servicio = $pdo->query("SELECT * FROM tipos_servicio ORDER BY nombre ASC")
                             ->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                echo '<div class="alert alert-info">La tabla <b>tipos_servicio</b> todavía no existe.</div>';
            }
            ?>

            <?php if ($servicio): ?>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4>Tipos de servicio</h4>
                    <a href="servicio_nuevo.php" class="btn btn-primary btn-sm">Añadir tipo</a>
                </div>

                <table class="table table-striped table-sm" id="tablaTiposServicio">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Servicio</th>
                        <th>Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($servicio as $s): ?>
                        <tr>
                            <td><?= htmlspecialchars($s['id']) ?></td>
                            <td><?= htmlspecialchars($s['nombre']) ?></td>
                        
                            <td>
                                <a href="servicio_editar.php?id=<?= $s['id'] ?>"
                                   class="btn btn-sm btn-warning">Editar</a>
                                <a href="servicio_borrar.php?id=<?= $s['id'] ?>"
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('¿Borrar este tipo?');">
                                    Borrar
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>


         <?php if ($tab === 'secciones'): ?>

            <?php
            $secciones = [];
            try {
                $secciones = $pdo->query("SELECT * FROM secciones ORDER BY nombre ASC")
                             ->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                echo '<div class="alert alert-info">La tabla <b>tipos_secciones</b> todavía no existe.</div>';
            }
            ?>

            <?php if ($secciones): ?>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4>Tipos de secciones</h4>
                    <a href="secciones_nuevo.php" class="btn btn-primary btn-sm">Añadir tipo</a>
                </div>

                <table class="table table-striped table-sm" id="tablaTiposSecciones">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Sección</th>
                        <th>Descripción</th>
                        <th>Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($secciones as $s): ?>
                        <tr>
                            <td><?= htmlspecialchars($s['id']) ?></td>
                            <td><?= htmlspecialchars($s['nombre']) ?></td>
                            <td><?= htmlspecialchars($s['descripcion'] ?? '') ?></td>
                            <td>
                                <a href="secciones_editar.php?id=<?= $s['id'] ?>"
                                   class="btn btn-sm btn-warning">Editar</a>
                                <a href="secciones_borrar.php?id=<?= $s['id'] ?>"
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('¿Borrar esta sección?');">
                                    Borrar
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
      

    
        
    </div>
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
document.addEventListener('DOMContentLoaded', function () {
    var opciones = {
        pageLength: 10,
        lengthMenu: [5, 10, 25, 50, 100],
        autoWidth: false,          // 🔹 que no recalule él los anchos
        scrollX: true,           // 🔹 para tablas anchas
        order: [],
        language: {
            url: 'vendor/datatables/i18n/es-ES.json'
        }
    };

    if (window.jQuery && $.fn.DataTable) {
        $('#tablaUsuarios').DataTable(opciones);
        $('#tablaDepartamentos').DataTable(opciones);
        $('#tablaUbicaciones').DataTable(opciones);
        $('#tablaTiposEquipo').DataTable(opciones);            // ← ESTE ERA EL IMPORTANTE
        $('#tablaTiposServicio').DataTable(opciones);
        $('#tablaTiposSecciones').DataTable(opciones);
    }
}); 
</script>


<?php require_once 'includes/footer.php'; ?>


