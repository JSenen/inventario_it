<?php

// 1. Iniciar el buffer de salida para evitar errores de headers
ob_start(); 

require_once 'auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/logger.php';

// --- CONFIGURACIÓN ---
// Directorio raíz para los ficheros. DEBE existir y tener permisos de escritura para el servidor web.
define('RECURSOS_BASE_DIR', realpath(__DIR__ . '/uploads/recursos'));
if (!RECURSOS_BASE_DIR || !is_dir(RECURSOS_BASE_DIR)) {
    // Intentar crear el directorio si no existe
    if (!mkdir(__DIR__ . '/uploads/recursos', 0775, true)) {
        die("El directorio base de recursos no existe y no se pudo crear. Por favor, crea la carpeta 'uploads/recursos' manualmente y dale permisos de escritura.");
    }
}

// --- FUNCIONES HELPER ---

// Función para limpiar y validar una ruta
function get_safe_path($path_str) {
    $path_str = preg_replace('/[^a-zA-Z0-9\s_.\-\/]/', '', $path_str);
    $path_str = str_replace('\\', '/', $path_str);

    $real_base = RECURSOS_BASE_DIR;
    $user_path = realpath($real_base . '/' . $path_str);

    if ($user_path === false || strpos($user_path, $real_base) !== 0) {
        return '';
    }

    return substr($user_path, strlen($real_base) + 1);
}

// Función para formatear el tamaño de los ficheros
function format_size($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    if ($bytes > 1) return $bytes . ' bytes';
    if ($bytes == 1) return '1 byte';
    return '0 bytes';
}

// Función para borrar un directorio recursivamente
function delete_dir($dirPath) {
    if (!is_dir($dirPath)) return;
    if (substr($dirPath, -1) != '/') $dirPath .= '/';
    $files = glob($dirPath . '*', GLOB_MARK);
    foreach ($files as $file) {
        is_dir($file) ? delete_dir($file) : unlink($file);
    }
    rmdir($dirPath);
}

// --- LÓGICA DE LA PÁGINA ---
$current_relative_path = get_safe_path($_GET['path'] ?? '');
$current_full_path = RECURSOS_BASE_DIR . '/' . $current_relative_path;
$errores = [];
$exitos = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'create_dir') {
        $new_dir_name = preg_replace('/[^a-zA-Z0-9\s_.-]/', '', trim($_POST['dir_name'] ?? ''));
        if (empty($new_dir_name)) {
            $errores[] = "El nombre del directorio no puede estar vacío.";
        } elseif (is_dir($current_full_path . '/' . $new_dir_name)) {
            $errores[] = "El directorio '$new_dir_name' ya existe.";
        } elseif (mkdir($current_full_path . '/' . $new_dir_name, 0775)) {
            $exitos[] = "Directorio '$new_dir_name' creado correctamente.";
            logActividad($pdo, 'FICHEROS_CREAR_DIR', "Directorio creado: " . $current_relative_path . '/' . $new_dir_name);
        } else {
            $errores[] = "No se pudo crear el directorio. Revisa los permisos.";
        }
    }

    if ($accion === 'upload_file' && isset($_FILES['fichero']) && $_FILES['fichero']['error'] == UPLOAD_ERR_OK) {
        $file_name = preg_replace('/[^a-zA-Z0-9\s_.-]/', '', basename($_FILES['fichero']['name']));
        $destination = $current_full_path . '/' . $file_name;
        if (file_exists($destination)) {
            $errores[] = "Ya existe un fichero con el nombre '$file_name' en este directorio.";
        } elseif (move_uploaded_file($_FILES['fichero']['tmp_name'], $destination)) {
            $exitos[] = "Fichero '$file_name' subido correctamente.";
            logActividad($pdo, 'FICHEROS_SUBIR', "Fichero subido: " . $current_relative_path . '/' . $file_name);
        } else {
            $errores[] = "Error al subir el fichero.";
        }
    }

    if ($accion === 'delete_item') {
        $item_name = trim($_POST['item_name'] ?? '');
        $item_path = $current_full_path . '/' . $item_name;
        $item_relative_path = $current_relative_path ? $current_relative_path . '/' . $item_name : $item_name;

        if (strpos(realpath($item_path), RECURSOS_BASE_DIR) === 0) {
            if (is_dir($item_path)) {
                delete_dir($item_path);
                $exitos[] = "Directorio '$item_name' eliminado.";
                logActividad($pdo, 'FICHEROS_BORRAR_DIR', "Directorio eliminado: " . $item_relative_path);
            } elseif (is_file($item_path)) {
                unlink($item_path);
                $exitos[] = "Fichero '$item_name' eliminado.";
                logActividad($pdo, 'FICHEROS_BORRAR_FICHERO', "Fichero eliminado: " . $item_relative_path);
            }
        } else {
            $errores[] = "Acción no permitida.";
        }
    }
}

$items = [];
$scan = scandir($current_full_path);
foreach ($scan as $item) {
    if ($item === '.' || $item === '..') continue;
    $item_full_path = $current_full_path . '/' . $item;
    $is_dir = is_dir($item_full_path);
    $items[] = [
        'name' => $item,
        'is_dir' => $is_dir,
        'size' => $is_dir ? null : filesize($item_full_path),
        'modified' => filemtime($item_full_path),
        'relative_path' => $current_relative_path ? $current_relative_path . '/' . $item : $item
    ];
}
usort($items, fn($a, $b) => ($a['is_dir'] !== $b['is_dir']) ? ($a['is_dir'] ? -1 : 1) : strcasecmp($a['name'], $b['name']));

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid mt-4">
    <h1 class="h3 mb-3">Gestor de Ficheros</h1>

    <?php foreach ($errores as $error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endforeach; ?>
    <?php foreach ($exitos as $exito): ?><div class="alert alert-success"><?= htmlspecialchars($exito) ?></div><?php endforeach; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 py-1">
                    <li class="breadcrumb-item"><a href="ficheros.php">Raíz</a></li>
                    <?php
                    $path_parts = explode('/', $current_relative_path);
                    $path_so_far = '';
                    foreach ($path_parts as $part):
                        if (empty($part)) continue;
                        $path_so_far .= $part . '/';
                    ?>
                        <li class="breadcrumb-item"><a href="ficheros.php?path=<?= urlencode($path_so_far) ?>"><?= htmlspecialchars($part) ?></a></li>
                    <?php endforeach; ?>
                </ol>
            </nav>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <form method="post" class="d-flex gap-2">
                        <input type="hidden" name="accion" value="create_dir">
                        <input type="text" name="dir_name" class="form-control" placeholder="Nombre del nuevo directorio" required>
                        <button type="submit" class="btn btn-outline-primary">Crear</button>
                    </form>
                </div>
                <div class="col-md-6">
                    <form method="post" enctype="multipart/form-data" class="d-flex gap-2">
                        <input type="hidden" name="accion" value="upload_file">
                        <input type="file" name="fichero" class="form-control" required>
                        <button type="submit" class="btn btn-success">Subir</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 40px;"></th>
                        <th>Nombre</th>
                        <th>Tamaño</th>
                        <th>Última modificación</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Este directorio está vacío.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><i class="bi <?= $item['is_dir'] ? 'bi-folder-fill text-primary' : 'bi-file-earmark' ?>"></i></td>
                            <td>
                                <?php if ($item['is_dir']): ?>
                                    <a href="ficheros.php?path=<?= urlencode($item['relative_path']) ?>"><?= htmlspecialchars($item['name']) ?></a>
                                <?php else: ?>
                                    <a href="descargar.php?path=<?= urlencode($item['relative_path']) ?>"><?= htmlspecialchars($item['name']) ?></a>
                                <?php endif; ?>
                            </td>
                            <td><?= $item['is_dir'] ? '-' : format_size($item['size']) ?></td>
                            <td><?= date('Y-m-d H:i', $item['modified']) ?></td>
                            <td class="text-end">
                                <form method="post" onsubmit="return confirm('¿Seguro que quieres eliminar \'<?= htmlspecialchars($item['name']) ?>\'? Esta acción no se puede deshacer.');" class="d-inline">
                                    <input type="hidden" name="accion" value="delete_item">
                                    <input type="hidden" name="item_name" value="<?= htmlspecialchars($item['name']) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>