<?php
require_once 'auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/logger.php';

// Directorio raíz para los ficheros.
define('RECURSOS_BASE_DIR', realpath(__DIR__ . '/uploads/recursos'));

if (!RECURSOS_BASE_DIR) {
    http_response_code(500);
    die("Error de configuración del servidor.");
}

$relative_path = $_GET['path'] ?? '';

// --- Validación de seguridad ---
$relative_path = preg_replace('/[^a-zA-Z0-9\s_.\-\/]/', '', $relative_path);
$relative_path = str_replace('\\', '/', $relative_path);

$full_path = realpath(RECURSOS_BASE_DIR . '/' . $relative_path);

if ($full_path === false || strpos($full_path, RECURSOS_BASE_DIR) !== 0 || is_dir($full_path)) {
    http_response_code(404);
    die("Fichero no encontrado o no permitido.");
}

// --- Servir el fichero ---
$filename = basename($full_path);
$filesize = filesize($full_path);

// Log de la descarga
logActividad($pdo, 'FICHEROS_DESCARGAR', "Descarga de fichero: " . $relative_path);

// Cabeceras para forzar la descarga
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . $filesize);

// Limpiar el buffer de salida y enviar el fichero
flush();
readfile($full_path);
exit;
?>