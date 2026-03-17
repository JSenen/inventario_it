<?php

use Dompdf\Dompdf;
use Dompdf\Options;

function ensureRecibosSchema(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    $tables = [];
    foreach (['equipos_movimientos', 'renovaciones', 'renovaciones_telefonos'] as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
        $tables[$table] = $stmt && $stmt->fetchColumn() !== false;
    }

    $needByTable = [
        'equipos_movimientos' => [
            'pdf_unsigned_path' => "ALTER TABLE equipos_movimientos ADD COLUMN pdf_unsigned_path VARCHAR(255) DEFAULT NULL AFTER pdf_path",
            'pdf_signed_path'   => "ALTER TABLE equipos_movimientos ADD COLUMN pdf_signed_path VARCHAR(255) DEFAULT NULL AFTER pdf_unsigned_path",
        ],
        'renovaciones' => [
            'pdf_path'          => "ALTER TABLE renovaciones ADD COLUMN pdf_path VARCHAR(255) DEFAULT NULL AFTER firma_path",
            'pdf_unsigned_path' => "ALTER TABLE renovaciones ADD COLUMN pdf_unsigned_path VARCHAR(255) DEFAULT NULL AFTER pdf_path",
            'pdf_signed_path'   => "ALTER TABLE renovaciones ADD COLUMN pdf_signed_path VARCHAR(255) DEFAULT NULL AFTER pdf_unsigned_path",
        ],
        'renovaciones_telefonos' => [
            'pdf_path'          => "ALTER TABLE renovaciones_telefonos ADD COLUMN pdf_path VARCHAR(255) DEFAULT NULL AFTER firma_path",
            'pdf_unsigned_path' => "ALTER TABLE renovaciones_telefonos ADD COLUMN pdf_unsigned_path VARCHAR(255) DEFAULT NULL AFTER pdf_path",
            'pdf_signed_path'   => "ALTER TABLE renovaciones_telefonos ADD COLUMN pdf_signed_path VARCHAR(255) DEFAULT NULL AFTER pdf_unsigned_path",
        ],
    ];

    foreach ($needByTable as $table => $cols) {
        if (empty($tables[$table])) {
            continue;
        }

        $currentCols = $pdo->query("SHOW COLUMNS FROM {$table}")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($cols as $col => $alterSql) {
            if (!in_array($col, $currentCols, true)) {
                $pdo->exec($alterSql);
            }
        }
    }

    $ready = true;
}

function recibosSlug(?string $value, string $fallback): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return $fallback;
    }

    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if ($ascii !== false && $ascii !== '') {
        $value = $ascii;
    }

    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');

    return $value !== '' ? $value : $fallback;
}

function recibosNombreArchivo(
    ?string $etiqueta,
    ?string $usuario,
    ?string $fecha,
    string $prefix,
    int $id,
    bool $firmado
): string {
    $fechaRaw = trim((string)$fecha);
    $ts = strtotime($fechaRaw !== '' ? $fechaRaw : 'now');
    if ($ts === false) {
        $ts = time();
    }
    $fechaFmt = date('Ymd_His', $ts);

    $etiquetaSlug = recibosSlug($etiqueta, 'sin-etiqueta');
    $usuarioSlug = recibosSlug($usuario, 'sin-usuario');
    $firmaSlug = $firmado ? 'firmado' : 'sin-firma';

    return "{$etiquetaSlug}_{$usuarioSlug}_{$fechaFmt}_{$prefix}_{$id}_{$firmaSlug}.pdf";
}

function recibosEnsureDir(string $scope): array
{
    $baseFs = dirname(__DIR__) . '/uploads/recibos';
    $scope = trim($scope, '/');
    $dirFs = $baseFs . '/' . $scope;

    if (!is_dir($dirFs)) {
        mkdir($dirFs, 0775, true);
    }

    $dirRel = 'uploads/recibos/' . $scope;

    return [$dirFs, $dirRel];
}

function recibosLoadDompdf(): bool
{
    if (class_exists(Dompdf::class)) {
        return true;
    }

    $autoloadLoans = dirname(__DIR__, 2) . '/laptop-loans/vendor/autoload.php';
    if (is_file($autoloadLoans)) {
        require_once $autoloadLoans;
    }
    if (class_exists(Dompdf::class)) {
        return true;
    }

    // En algunos entornos este vendor exige PHP >= 8.3.
    // Evitamos el fatal si el runtime es 8.2.x.
    if (PHP_VERSION_ID >= 80300) {
        $autoloadWeb = dirname(__DIR__) . '/vendor/autoload.php';
        if (is_file($autoloadWeb)) {
            require_once $autoloadWeb;
        }
    }

    return class_exists(Dompdf::class);
}

function recibosRenderPdf(string $html, string $destFs): bool
{
    if (!recibosLoadDompdf()) {
        return false;
    }

    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');

    $dompdf = new Dompdf($options);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->render();

    return file_put_contents($destFs, $dompdf->output()) !== false;
}

function recibosFirmaDataUri(?string $relPath): string
{
    if ($relPath === null || trim($relPath) === '') {
        return '';
    }

    $fs = dirname(__DIR__) . '/' . ltrim($relPath, '/');
    if (!is_file($fs)) {
        return '';
    }

    $bin = @file_get_contents($fs);
    if ($bin === false || $bin === '') {
        return '';
    }

    return 'data:image/png;base64,' . base64_encode($bin);
}

function recibosHtmlBase(string $title, array $rows, string $observaciones = '', string $firmaDataUri = '', string $firmadoFecha = ''): string
{
    $rowsHtml = '';
    foreach ($rows as [$k, $v]) {
        $rowsHtml .= '<tr><th>' . htmlspecialchars((string)$k) . '</th><td>' . htmlspecialchars((string)$v) . '</td></tr>';
    }

    $obsHtml = '';
    if (trim($observaciones) !== '') {
        $obsHtml = '<h3>Observaciones</h3><div class="box">' . nl2br(htmlspecialchars($observaciones)) . '</div>';
    }

    $firmaHtml = '<div class="firma-box"></div><div class="firma-caption">Pendiente de firma</div>';
    if ($firmaDataUri !== '') {
        $firmaHtml = '<img class="firma-img" src="' . htmlspecialchars($firmaDataUri) . '" alt="Firma">'
            . '<div class="firma-caption">Firmado' . ($firmadoFecha !== '' ? ' el ' . htmlspecialchars($firmadoFecha) : '') . '</div>';
    }

    return '<!doctype html>'
        . '<html lang="es"><head><meta charset="utf-8"><title>' . htmlspecialchars($title) . '</title>'
        . '<style>'
        . 'body{font-family:DejaVu Sans,Arial,sans-serif;font-size:12px;color:#111}'
        . 'h1{font-size:18px;margin:0 0 12px 0} h3{font-size:13px;margin:14px 0 6px}'
        . 'table{width:100%;border-collapse:collapse}th,td{border:1px solid #bbb;padding:6px;vertical-align:top}'
        . 'th{width:32%;text-align:left;background:#f5f5f5}.box{border:1px solid #bbb;padding:8px;min-height:40px}'
        . '.firma-wrap{margin-top:18px}.firma-box{height:90px;border:1px solid #bbb}.firma-img{max-width:280px;max-height:120px;border:1px solid #bbb;padding:4px}'
        . '.firma-caption{margin-top:6px;font-size:11px;color:#444}'
        . '</style></head><body>'
        . '<h1>' . htmlspecialchars($title) . '</h1>'
        . '<table>' . $rowsHtml . '</table>'
        . $obsHtml
        . '<div class="firma-wrap"><h3>Firma</h3>' . $firmaHtml . '</div>'
        . '</body></html>';
}

function generarReciboMovimientoPdf(PDO $pdo, int $idMov, bool $firmado = false): ?string
{
    ensureRecibosSchema($pdo);

    $stmt = $pdo->prepare("\n        SELECT m.*, e.etiqueta, e.marca, e.modelo, e.numero_serie, e.hostname\n        FROM equipos_movimientos m\n        JOIN equipos e ON e.id = m.id_equipo\n        WHERE m.id = :id\n        LIMIT 1\n    ");
    $stmt->execute([':id' => $idMov]);
    $mov = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$mov) {
        return null;
    }

    [$dirFs, $dirRel] = recibosEnsureDir('movimientos');
    $filename = recibosNombreArchivo(
        (string)($mov['etiqueta'] ?? ''),
        (string)($mov['usuario_destino'] ?? ''),
        (string)($mov['fecha'] ?? ''),
        'mov',
        $idMov,
        $firmado
    );

    $destFs = $dirFs . '/' . $filename;
    $destRel = $dirRel . '/' . $filename;

    $equipo = trim((string)($mov['marca'] ?? '') . ' ' . (string)($mov['modelo'] ?? ''));
    if ($equipo === '') {
        $equipo = 'Equipo';
    }

    $firmaDataUri = $firmado ? recibosFirmaDataUri($mov['firma_path'] ?? null) : '';

    $rows = [
        ['ID movimiento', (string)$idMov],
        ['Fecha', (string)($mov['fecha'] ?? '')],
        ['Tipo', strtoupper((string)($mov['tipo'] ?? ''))],
        ['Etiqueta', (string)($mov['etiqueta'] ?? '-')],
        ['Equipo', $equipo],
        ['Nº serie', (string)($mov['numero_serie'] ?? '-')],
        ['Servicio', (string)($mov['hostname'] ?? '-')],
        ['Usuario destino', (string)($mov['usuario_destino'] ?? '-')],
        ['Técnico', (string)($mov['tecnico'] ?? '-')],
        ['Estado origen', (string)($mov['estado_origen'] ?? '-')],
        ['Estado destino', (string)($mov['estado_destino'] ?? '-')],
    ];

    $html = recibosHtmlBase(
        'Recibo de movimiento de equipo',
        $rows,
        (string)($mov['observaciones'] ?? ''),
        $firmaDataUri,
        (string)($mov['firmado_fecha'] ?? '')
    );

    if (!recibosRenderPdf($html, $destFs)) {
        return null;
    }

    $updates = [':curr' => $destRel, ':id' => $idMov];
    $sql = "UPDATE equipos_movimientos SET pdf_path = :curr";
    if ($firmado) {
        $sql .= ", pdf_signed_path = :signed";
        $updates[':signed'] = $destRel;
    } else {
        $sql .= ", pdf_unsigned_path = :unsigned";
        $updates[':unsigned'] = $destRel;
    }
    $sql .= " WHERE id = :id";

    $up = $pdo->prepare($sql);
    $up->execute($updates);

    return $destRel;
}

function generarReciboRenovacionPdf(PDO $pdo, int $idRen, bool $firmado = false): ?string
{
    ensureRecibosSchema($pdo);

    $stmt = $pdo->prepare("\n        SELECT r.*,\n               eold.etiqueta AS old_etiqueta, eold.marca AS old_marca, eold.modelo AS old_modelo,\n               enew.etiqueta AS new_etiqueta, enew.marca AS new_marca, enew.modelo AS new_modelo,\n               enew.usuario_asignado AS usuario_asignado\n        FROM renovaciones r\n        JOIN equipos eold ON eold.id = r.equipo_old_id\n        JOIN equipos enew ON enew.id = r.equipo_new_id\n        WHERE r.id = :id\n        LIMIT 1\n    ");
    $stmt->execute([':id' => $idRen]);
    $ren = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ren) {
        return null;
    }

    [$dirFs, $dirRel] = recibosEnsureDir('renovaciones/equipos');
    $filename = recibosNombreArchivo(
        (string)($ren['new_etiqueta'] ?? $ren['old_etiqueta'] ?? ''),
        (string)($ren['usuario_asignado'] ?? ''),
        (string)($ren['fecha'] ?? ''),
        'renov-eq',
        $idRen,
        $firmado
    );

    $destFs = $dirFs . '/' . $filename;
    $destRel = $dirRel . '/' . $filename;

    $firmaDataUri = $firmado ? recibosFirmaDataUri($ren['firma_path'] ?? null) : '';

    $rows = [
        ['ID renovación', (string)$idRen],
        ['Fecha', (string)($ren['fecha'] ?? '')],
        ['Equipo renovado', trim((string)($ren['old_etiqueta'] ?? '') . ' ' . (string)($ren['old_marca'] ?? '') . ' ' . (string)($ren['old_modelo'] ?? ''))],
        ['Equipo nuevo', trim((string)($ren['new_etiqueta'] ?? '') . ' ' . (string)($ren['new_marca'] ?? '') . ' ' . (string)($ren['new_modelo'] ?? ''))],
        ['Usuario', (string)($ren['usuario_asignado'] ?? '-')],
        ['IP trasladada', ((int)($ren['ip_move'] ?? 0) === 1) ? 'Sí' : 'No'],
        ['Acción monitores', (string)($ren['mon_accion'] ?? '-')],
        ['Estado equipo renovado', (string)($ren['estado_old'] ?? '-')],
    ];

    $html = recibosHtmlBase(
        'Recibo de renovación de equipo',
        $rows,
        '',
        $firmaDataUri,
        (string)($ren['firmado_fecha'] ?? '')
    );

    if (!recibosRenderPdf($html, $destFs)) {
        return null;
    }

    $updates = [':curr' => $destRel, ':id' => $idRen];
    $sql = "UPDATE renovaciones SET pdf_path = :curr";
    if ($firmado) {
        $sql .= ", pdf_signed_path = :signed";
        $updates[':signed'] = $destRel;
    } else {
        $sql .= ", pdf_unsigned_path = :unsigned";
        $updates[':unsigned'] = $destRel;
    }
    $sql .= " WHERE id = :id";

    $up = $pdo->prepare($sql);
    $up->execute($updates);

    return $destRel;
}

function generarReciboRenovacionTelefonoPdf(PDO $pdo, int $idRen, bool $firmado = false): ?string
{
    ensureRecibosSchema($pdo);

    $stmt = $pdo->prepare("\n        SELECT r.*,\n               told.etiqueta AS old_etiqueta, told.marca AS old_marca, told.modelo AS old_modelo,\n               tnew.etiqueta AS new_etiqueta, tnew.marca AS new_marca, tnew.modelo AS new_modelo,\n               tnew.usuario_asignado AS usuario_asignado\n        FROM renovaciones_telefonos r\n        JOIN telefonos told ON told.id = r.tel_old_id\n        JOIN telefonos tnew ON tnew.id = r.tel_new_id\n        WHERE r.id = :id\n        LIMIT 1\n    ");
    $stmt->execute([':id' => $idRen]);
    $ren = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ren) {
        return null;
    }

    [$dirFs, $dirRel] = recibosEnsureDir('renovaciones/telefonos');
    $filename = recibosNombreArchivo(
        (string)($ren['new_etiqueta'] ?? $ren['old_etiqueta'] ?? ''),
        (string)($ren['usuario_asignado'] ?? ''),
        (string)($ren['fecha'] ?? ''),
        'renov-tel',
        $idRen,
        $firmado
    );

    $destFs = $dirFs . '/' . $filename;
    $destRel = $dirRel . '/' . $filename;

    $firmaDataUri = $firmado ? recibosFirmaDataUri($ren['firma_path'] ?? null) : '';

    $rows = [
        ['ID renovación', (string)$idRen],
        ['Fecha', (string)($ren['fecha'] ?? '')],
        ['Teléfono renovado', trim((string)($ren['old_etiqueta'] ?? '') . ' ' . (string)($ren['old_marca'] ?? '') . ' ' . (string)($ren['old_modelo'] ?? ''))],
        ['Teléfono nuevo', trim((string)($ren['new_etiqueta'] ?? '') . ' ' . (string)($ren['new_marca'] ?? '') . ' ' . (string)($ren['new_modelo'] ?? ''))],
        ['Usuario', (string)($ren['usuario_asignado'] ?? '-')],
        ['SIM trasladada', ((int)($ren['sim_movida'] ?? 0) === 1) ? 'Sí' : 'No'],
        ['Estado teléfono renovado', (string)($ren['estado_old'] ?? '-')],
    ];

    $html = recibosHtmlBase(
        'Recibo de renovación de teléfono',
        $rows,
        '',
        $firmaDataUri,
        (string)($ren['firmado_fecha'] ?? '')
    );

    if (!recibosRenderPdf($html, $destFs)) {
        return null;
    }

    $updates = [':curr' => $destRel, ':id' => $idRen];
    $sql = "UPDATE renovaciones_telefonos SET pdf_path = :curr";
    if ($firmado) {
        $sql .= ", pdf_signed_path = :signed";
        $updates[':signed'] = $destRel;
    } else {
        $sql .= ", pdf_unsigned_path = :unsigned";
        $updates[':unsigned'] = $destRel;
    }
    $sql .= " WHERE id = :id";

    $up = $pdo->prepare($sql);
    $up->execute($updates);

    return $destRel;
}
