<?php
// includes/verificaciones.php
// Utilidades para registrar y consultar verificaciones periódicas de equipos.

function ensureTablaVerificaciones(PDO $pdo): void
{
    // Creamos la tabla si no existe para evitar depender de migraciones externas.
    $sql = "
        CREATE TABLE IF NOT EXISTS equipos_verificaciones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            equipo_id INT NOT NULL,
            fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ubicacion VARCHAR(255) DEFAULT NULL,
            estado_equipo VARCHAR(50) DEFAULT NULL,
            usuario_verificador VARCHAR(100) DEFAULT NULL,
            ip VARCHAR(50) DEFAULT NULL,
            seccion VARCHAR(255) DEFAULT NULL,
            departamento VARCHAR(255) DEFAULT NULL,
            notas TEXT,
            INDEX idx_equipo_fecha (equipo_id, fecha),
            CONSTRAINT fk_equipo_verificaciones_equipo
                FOREIGN KEY (equipo_id) REFERENCES equipos(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    $pdo->exec($sql);

    // Añadir columnas nuevas si la tabla ya existía.
    $colsStmt = $pdo->query("SHOW COLUMNS FROM equipos_verificaciones");
    $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN, 0);
    $required = [
        'ip'           => "ALTER TABLE equipos_verificaciones ADD COLUMN ip VARCHAR(50) DEFAULT NULL",
        'seccion'      => "ALTER TABLE equipos_verificaciones ADD COLUMN seccion VARCHAR(255) DEFAULT NULL",
        'departamento' => "ALTER TABLE equipos_verificaciones ADD COLUMN departamento VARCHAR(255) DEFAULT NULL",
    ];

    foreach ($required as $col => $alterSql) {
        if (!in_array($col, $cols, true)) {
            $pdo->exec($alterSql);
        }
    }
}

function registrarVerificacionEquipo(
    PDO $pdo,
    int $equipoId,
    string $estadoEquipo,
    string $ubicacion,
    string $usuario,
    ?string $ip = null,
    ?string $seccion = null,
    ?string $departamento = null,
    ?string $notas = null
): void {
    ensureTablaVerificaciones($pdo);

    $stmt = $pdo->prepare("
        INSERT INTO equipos_verificaciones (equipo_id, fecha, ubicacion, estado_equipo, usuario_verificador, ip, seccion, departamento, notas)
        VALUES (:equipo_id, NOW(), :ubicacion, :estado_equipo, :usuario, :ip, :seccion, :departamento, :notas)
    ");
    $stmt->execute([
        ':equipo_id'      => $equipoId,
        ':ubicacion'      => $ubicacion,
        ':estado_equipo'  => $estadoEquipo,
        ':usuario'        => $usuario,
        ':ip'             => $ip,
        ':seccion'        => $seccion,
        ':departamento'   => $departamento,
        ':notas'          => $notas,
    ]);
}

function obtenerUltimaVerificacionEquipo(PDO $pdo, int $equipoId): ?array
{
    ensureTablaVerificaciones($pdo);

    $stmt = $pdo->prepare("
        SELECT *
        FROM equipos_verificaciones
        WHERE equipo_id = :id
        ORDER BY fecha DESC
        LIMIT 1
    ");
    $stmt->execute([':id' => $equipoId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}
