<?php

function ensureBajasDefinitivasSchema(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bajas_definitivas_lotes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            codigo VARCHAR(40) NOT NULL UNIQUE,
            estado ENUM('PENDIENTE','CONFIRMADO') NOT NULL DEFAULT 'PENDIENTE',
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            creado_por VARCHAR(100) DEFAULT NULL,
            confirmado_en DATETIME DEFAULT NULL,
            confirmado_por VARCHAR(100) DEFAULT NULL,
            punto_limpio VARCHAR(255) DEFAULT NULL,
            transportado_por VARCHAR(150) DEFAULT NULL,
            observaciones TEXT,
            reporte_path VARCHAR(255) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bajas_definitivas_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            lote_id INT NOT NULL,
            activo_tipo ENUM('equipo','telefono') NOT NULL,
            activo_id INT NOT NULL,
            activo_etiqueta VARCHAR(120) DEFAULT NULL,
            activo_descripcion VARCHAR(255) NOT NULL,
            activo_identificador VARCHAR(150) DEFAULT NULL,
            estado_previo VARCHAR(50) NOT NULL DEFAULT 'Baja',
            fecha_baja_original DATETIME DEFAULT NULL,
            agregado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            confirmado_en DATETIME DEFAULT NULL,
            CONSTRAINT fk_bajas_def_items_lote
                FOREIGN KEY (lote_id) REFERENCES bajas_definitivas_lotes(id)
                ON DELETE CASCADE,
            UNIQUE KEY uniq_bajas_def_lote_activo (lote_id, activo_tipo, activo_id),
            KEY idx_bajas_def_activo (activo_tipo, activo_id),
            KEY idx_bajas_def_lote (lote_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $colsLotes = $pdo->query("SHOW COLUMNS FROM bajas_definitivas_lotes")->fetchAll(PDO::FETCH_COLUMN);
    $faltantesLotes = [
        'codigo'          => "ALTER TABLE bajas_definitivas_lotes ADD COLUMN codigo VARCHAR(40) NOT NULL UNIQUE AFTER id",
        'estado'          => "ALTER TABLE bajas_definitivas_lotes ADD COLUMN estado ENUM('PENDIENTE','CONFIRMADO') NOT NULL DEFAULT 'PENDIENTE' AFTER codigo",
        'creado_por'      => "ALTER TABLE bajas_definitivas_lotes ADD COLUMN creado_por VARCHAR(100) DEFAULT NULL AFTER creado_en",
        'confirmado_en'   => "ALTER TABLE bajas_definitivas_lotes ADD COLUMN confirmado_en DATETIME DEFAULT NULL AFTER creado_por",
        'confirmado_por'  => "ALTER TABLE bajas_definitivas_lotes ADD COLUMN confirmado_por VARCHAR(100) DEFAULT NULL AFTER confirmado_en",
        'punto_limpio'    => "ALTER TABLE bajas_definitivas_lotes ADD COLUMN punto_limpio VARCHAR(255) DEFAULT NULL AFTER confirmado_por",
        'transportado_por'=> "ALTER TABLE bajas_definitivas_lotes ADD COLUMN transportado_por VARCHAR(150) DEFAULT NULL AFTER punto_limpio",
        'observaciones'   => "ALTER TABLE bajas_definitivas_lotes ADD COLUMN observaciones TEXT AFTER transportado_por",
        'reporte_path'    => "ALTER TABLE bajas_definitivas_lotes ADD COLUMN reporte_path VARCHAR(255) DEFAULT NULL AFTER observaciones",
    ];

    foreach ($faltantesLotes as $col => $sql) {
        if (!in_array($col, $colsLotes, true)) {
            $pdo->exec($sql);
        }
    }

    $colsItems = $pdo->query("SHOW COLUMNS FROM bajas_definitivas_items")->fetchAll(PDO::FETCH_COLUMN);
    $faltantesItems = [
        'activo_etiqueta'       => "ALTER TABLE bajas_definitivas_items ADD COLUMN activo_etiqueta VARCHAR(120) DEFAULT NULL AFTER activo_id",
        'activo_descripcion'    => "ALTER TABLE bajas_definitivas_items ADD COLUMN activo_descripcion VARCHAR(255) NOT NULL AFTER activo_etiqueta",
        'activo_identificador'  => "ALTER TABLE bajas_definitivas_items ADD COLUMN activo_identificador VARCHAR(150) DEFAULT NULL AFTER activo_descripcion",
        'estado_previo'         => "ALTER TABLE bajas_definitivas_items ADD COLUMN estado_previo VARCHAR(50) NOT NULL DEFAULT 'Baja' AFTER activo_identificador",
        'fecha_baja_original'   => "ALTER TABLE bajas_definitivas_items ADD COLUMN fecha_baja_original DATETIME DEFAULT NULL AFTER estado_previo",
        'agregado_en'           => "ALTER TABLE bajas_definitivas_items ADD COLUMN agregado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER fecha_baja_original",
        'confirmado_en'         => "ALTER TABLE bajas_definitivas_items ADD COLUMN confirmado_en DATETIME DEFAULT NULL AFTER agregado_en",
    ];

    foreach ($faltantesItems as $col => $sql) {
        if (!in_array($col, $colsItems, true)) {
            $pdo->exec($sql);
        }
    }

    $ready = true;
}
