<?php
// includes/movimientos_helper.php

require_once __DIR__ . '/logger.php';





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

    $firmaToken = bin2hex(random_bytes(32));

    // Ajusta el nombre del técnico según tu auth.php
    $tecnico = $_SESSION['usuario'] ?? ($_SESSION['user_name'] ?? 'SISTEMA');

    $stmt = $pdo->prepare("
        INSERT INTO equipos_movimientos
        (id_equipo, tipo, usuario_destino, tecnico, estado_origen, estado_destino, firma_token)
        VALUES
        (:id_equipo, :tipo, :usuario_destino, :tecnico, :estado_origen, :estado_destino, :token)
    ");
    $stmt->execute([
        ':id_equipo'      => $idEquipo,
        ':tipo'           => $tipo,
        ':usuario_destino'=> $usuarioDestino,
        ':tecnico'        => $tecnico,
        ':estado_origen'  => $estadoAnterior,
        ':estado_destino' => $estadoNuevo,
        ':token'          => $firmaToken,
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

    logActividad($pdo, 'MOVIMIENTO_EQUIPO', "Movimiento $tipo equipo ID=$idEquipo, mov=$idMov");

    return $idMov;
}

function generarPdfMovimiento(PDO $pdo, int $idMov): ?string
{
    // De momento ya no generamos PDF en servidor.
    // Usaremos la página HTML recibo_movimiento.php para ver/imprimir el recibo.
    return null;
}
