<?php

declare(strict_types=1);

/**
 * Renombra recibos históricos de handovers al formato:
 *   numero_serie + curso + tip_o_dni + fecha
 *
 * Uso:
 *   php scripts/renombrar_recibos_historicos.php --dry-run
 *   php scripts/renombrar_recibos_historicos.php --apply
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script debe ejecutarse por CLI.\n");
    exit(1);
}

$basePath = realpath(__DIR__ . '/..');
if ($basePath === false) {
    fwrite(STDERR, "No se pudo resolver BASE_PATH.\n");
    exit(1);
}

define('BASE_PATH', $basePath);

$args = array_slice($_SERVER['argv'], 1);
$dryRun = in_array('--dry-run', $args, true) || !in_array('--apply', $args, true);

$configFile = BASE_PATH . '/config/config.local.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "No existe config local: {$configFile}\n");
    exit(1);
}

/** @var array<string,mixed> $cfg */
$cfg = require $configFile;
$db = $cfg['db'] ?? null;
if (!is_array($db)) {
    fwrite(STDERR, "Configuración de base de datos no válida en {$configFile}\n");
    exit(1);
}

$host = (string)($db['host'] ?? '127.0.0.1');
$port = (int)($db['port'] ?? 3306);
$name = (string)($db['name'] ?? 'laptop_loans');
$user = (string)($db['user'] ?? '');
$pass = (string)($db['pass'] ?? '');
$charset = (string)($db['charset'] ?? 'utf8mb4');

$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, "Error de conexión: {$e->getMessage()}\n");
    exit(1);
}

function slugify(string $value, string $fallback): string
{
    $value = trim($value);
    if ($value === '') {
        return $fallback;
    }

    if (function_exists('iconv')) {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($t !== false) {
            $value = $t;
        }
    }

    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';
    $value = trim($value, '_');

    return $value !== '' ? $value : $fallback;
}

function buildNewFilename(array $row): string
{
    $serie = slugify((string)($row['num_serie'] ?? ''), 'sin_serie');
    $curso = slugify((string)($row['curso'] ?? ''), 'sin_curso');

    $tip = trim((string)($row['tip'] ?? ''));
    $dni = trim((string)($row['dni'] ?? ''));
    $idDoc = $tip !== '' ? $tip : ($dni !== '' ? $dni : 'sin_id');
    $idDoc = slugify($idDoc, 'sin_id');

    $fechaRaw = (string)($row['fecha'] ?? '');
    $ts = strtotime($fechaRaw);
    if ($ts === false) {
        $ts = time();
    }
    $fecha = date('Ymd_His', $ts);

    return "{$serie}_{$curso}_{$idDoc}_{$fecha}.pdf";
}

function resolveExistingPath(string $path): ?string
{
    $path = trim($path);
    if ($path === '') {
        return null;
    }

    if (is_file($path)) {
        $real = realpath($path);
        return $real !== false ? $real : $path;
    }

    $candidate = BASE_PATH . '/' . ltrim($path, '/');
    if (is_file($candidate)) {
        $real = realpath($candidate);
        return $real !== false ? $real : $candidate;
    }

    return null;
}

function uniqueTargetPath(string $dir, string $baseName, int $handoverId): string
{
    $name = $baseName;
    $path = $dir . DIRECTORY_SEPARATOR . $name;

    if (!is_file($path)) {
        return $path;
    }

    $info = pathinfo($baseName);
    $stem = (string)($info['filename'] ?? 'recibo');
    $ext = isset($info['extension']) ? '.' . $info['extension'] : '';

    $name = $stem . '_h' . $handoverId . $ext;
    $path = $dir . DIRECTORY_SEPARATOR . $name;
    if (!is_file($path)) {
        return $path;
    }

    $i = 2;
    while (true) {
        $name = $stem . '_h' . $handoverId . '_' . $i . $ext;
        $path = $dir . DIRECTORY_SEPARATOR . $name;
        if (!is_file($path)) {
            return $path;
        }
        $i++;
    }
}

$sql = "
    SELECT h.id, h.fecha, h.recibo_pdf_path,
           p.dni, p.tip,
           l.num_serie,
           c.nombre AS curso
    FROM handovers h
    JOIN people p ON p.id = h.person_id
    JOIN laptops l ON l.id = h.laptop_id
    LEFT JOIN courses c ON c.id = h.course_id
    WHERE h.recibo_pdf_path IS NOT NULL
      AND h.recibo_pdf_path <> ''
    ORDER BY h.id ASC
";

$rows = $pdo->query($sql)->fetchAll();

$total = count($rows);
$ok = 0;
$skipped = 0;
$missing = 0;
$errors = 0;

$upStmt = $pdo->prepare('UPDATE handovers SET recibo_pdf_path = :path WHERE id = :id');

echo $dryRun
    ? "[DRY-RUN] Se analizarán {$total} recibos.\n"
    : "[APPLY] Se procesarán {$total} recibos.\n";

foreach ($rows as $row) {
    $id = (int)$row['id'];
    $oldRaw = (string)$row['recibo_pdf_path'];
    $oldPath = resolveExistingPath($oldRaw);

    if ($oldPath === null) {
        $missing++;
        echo "[MISS] #{$id} no existe: {$oldRaw}\n";
        continue;
    }

    $dir = dirname($oldPath);
    $newBase = buildNewFilename($row);
    $target = uniqueTargetPath($dir, $newBase, $id);

    if ($oldPath === $target) {
        $skipped++;
        echo "[SKIP] #{$id} ya cumple nomenclatura: " . basename($oldPath) . "\n";
        continue;
    }

    $oldBase = basename($oldPath);
    $newBaseReal = basename($target);

    if ($dryRun) {
        $ok++;
        echo "[PLAN] #{$id} {$oldBase}  ->  {$newBaseReal}\n";
        continue;
    }

    try {
        $moved = @rename($oldPath, $target);
        if (!$moved) {
            if (!@copy($oldPath, $target) || !@unlink($oldPath)) {
                throw new RuntimeException('No se pudo mover el archivo');
            }
        }

        $upStmt->execute([
            ':path' => $target,
            ':id' => $id,
        ]);

        $ok++;
        echo "[DONE] #{$id} {$oldBase}  ->  {$newBaseReal}\n";
    } catch (Throwable $e) {
        $errors++;
        echo "[ERR ] #{$id} {$oldBase} :: {$e->getMessage()}\n";
    }
}

echo "\nResumen:\n";
echo "- Total: {$total}\n";
echo "- " . ($dryRun ? 'Planificados' : 'Renombrados') . ": {$ok}\n";
echo "- Ya correctos: {$skipped}\n";
echo "- Faltantes: {$missing}\n";
echo "- Errores: {$errors}\n";

if ($dryRun) {
    echo "\nEjecuta con --apply para aplicar cambios.\n";
}
