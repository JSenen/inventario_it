<?php
// includes/movimientos_helper.php

require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/recibos_pdf_helper.php';

function tecnicoSesionMovimiento(): string
{
    return $_SESSION['tip']
        ?? $_SESSION['usuario']
        ?? ($_SESSION['user_name'] ?? 'Tecnico');
}

function insertarMovimientoEquipoRegistro(
    PDO $pdo,
    int $idEquipo,
    string $tipo,
    string $usuarioDestino,
    ?string $estadoOrigen,
    ?string $estadoDestino,
    ?string $observaciones = null
): int {
    $firmaToken = bin2hex(random_bytes(32));
    $tecnico = tecnicoSesionMovimiento();

    $stmt = $pdo->prepare("
        INSERT INTO equipos_movimientos
        (id_equipo, tipo, usuario_destino, tecnico, estado_origen, estado_destino, observaciones, firma_token)
        VALUES
        (:id_equipo, :tipo, :usuario_destino, :tecnico, :estado_origen, :estado_destino, :observaciones, :token)
    ");
    $stmt->execute([
        ':id_equipo'       => $idEquipo,
        ':tipo'            => $tipo,
        ':usuario_destino' => $usuarioDestino,
        ':tecnico'         => $tecnico,
        ':estado_origen'   => $estadoOrigen,
        ':estado_destino'  => $estadoDestino,
        ':observaciones'   => $observaciones,
        ':token'           => $firmaToken,
    ]);

    $idMov = (int)$pdo->lastInsertId();

    // Generar PDF inicial sin firma
    $pdfPath = generarPdfMovimiento($pdo, $idMov);
    if ($pdfPath) {
        $stmtUp = $pdo->prepare("UPDATE equipos_movimientos SET pdf_path = :pdf WHERE id = :id");
        $stmtUp->execute([
            ':pdf' => $pdfPath,
            ':id'  => $idMov,
        ]);
    }

    return $idMov;
}

function normalizarTextoMovimiento(?string $valor): string
{
    return strtoupper(trim((string)$valor));
}

function registrarMovimientoEquipo(
    PDO $pdo,
    int $idEquipo,
    ?string $estadoAnterior,
    ?string $usuarioAnterior,
    string $estadoNuevo,
    string $usuarioNuevo
): ?int {
    $estadoAnterior  = $estadoAnterior  ?? '';
    $estadoNuevo     = $estadoNuevo     ?? '';
    $usuarioAnterior = strtoupper(trim($usuarioAnterior ?? ''));
    $usuarioNuevo    = strtoupper(trim($usuarioNuevo ?? ''));

    $tipo = null;
    $usuarioDestino = '';

        // 🔹 ALMACÉN → ACTIVO    => ENTREGA
    // 🔹 ALMACÉN → PRESTADO  => PRÉSTAMO
    if ($estadoAnterior === 'Almacén' && $usuarioNuevo !== '') {
        if ($estadoNuevo === 'Activo') {
            $tipo = 'entrega';
        } elseif ($estadoNuevo === 'Prestado') {
            $tipo = 'prestamo';
        }
        if ($tipo !== null) {
            $usuarioDestino = $usuarioNuevo;
        }
    }
    // 🔹 ACTIVO/PRESTADO → ALMACÉN
    elseif ($estadoNuevo === 'Almacén' && $usuarioAnterior !== '') {
        if ($estadoAnterior === 'Prestado') {
            $tipo = 'devolucion';
        } else {
            $tipo = 'recogida';
        }
        $usuarioDestino = $usuarioAnterior;
    }


    if ($tipo === null || $usuarioDestino === '') {
        return null; // No hay movimiento que registrar
    }

    $idMov = insertarMovimientoEquipoRegistro(
        $pdo,
        $idEquipo,
        $tipo,
        $usuarioDestino,
        $estadoAnterior,
        $estadoNuevo
    );

    logActividad($pdo, 'MOVIMIENTO_EQUIPO', "Movimiento $tipo equipo ID=$idEquipo, mov=$idMov");

    return $idMov;
}

function registrarMovimientosTrasladoEquipo(
    PDO $pdo,
    int $idEquipo,
    ?string $usuarioDestino,
    array $origen,
    array $destino,
    ?string $estadoActual
): array {
    $origenUbi = normalizarTextoMovimiento($origen['ubicacion'] ?? '');
    $origenDep = normalizarTextoMovimiento($origen['departamento'] ?? '');
    $origenSec = normalizarTextoMovimiento($origen['seccion'] ?? '');
    $destinoUbi = normalizarTextoMovimiento($destino['ubicacion'] ?? '');
    $destinoDep = normalizarTextoMovimiento($destino['departamento'] ?? '');
    $destinoSec = normalizarTextoMovimiento($destino['seccion'] ?? '');

    $sinCambios = $origenUbi === $destinoUbi
        && $origenDep === $destinoDep
        && $origenSec === $destinoSec;
    if ($sinCambios) {
        return [];
    }

    $usuarioMov = normalizarTextoMovimiento($usuarioDestino);
    if ($usuarioMov === '') {
        $usuarioMov = 'SIN USUARIO';
    }

    $estadoMov = trim((string)($estadoActual ?? ''));
    $origenTxt = 'Ubicación: ' . ($origenUbi !== '' ? $origenUbi : '-') .
        ' | Departamento: ' . ($origenDep !== '' ? $origenDep : '-') .
        ' | Sección: ' . ($origenSec !== '' ? $origenSec : '-');
    $destinoTxt = 'Ubicación: ' . ($destinoUbi !== '' ? $destinoUbi : '-') .
        ' | Departamento: ' . ($destinoDep !== '' ? $destinoDep : '-') .
        ' | Sección: ' . ($destinoSec !== '' ? $destinoSec : '-');

    $obsBaja = "BAJA por traslado interno.\n" .
        "Origen: {$origenTxt}\n" .
        "Destino: {$destinoTxt}";
    $obsAlta = "ALTA por traslado interno.\n" .
        "Origen: {$origenTxt}\n" .
        "Destino: {$destinoTxt}";

    $idBaja = insertarMovimientoEquipoRegistro(
        $pdo,
        $idEquipo,
        'recogida',
        $usuarioMov,
        $estadoMov,
        $estadoMov,
        $obsBaja
    );
    $idAlta = insertarMovimientoEquipoRegistro(
        $pdo,
        $idEquipo,
        'entrega',
        $usuarioMov,
        $estadoMov,
        $estadoMov,
        $obsAlta
    );

    logActividad(
        $pdo,
        'MOVIMIENTO_TRASLADO_EQUIPO',
        "Traslado equipo ID={$idEquipo}, mov_baja={$idBaja}, mov_alta={$idAlta}"
    );

    return [$idBaja, $idAlta];
}

function generarPdfMovimiento(PDO $pdo, int $idMov): ?string
{
    return generarReciboMovimientoPdf($pdo, $idMov, false);
}
