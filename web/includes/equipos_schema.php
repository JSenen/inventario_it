<?php

function ensureEquiposSchema(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM equipos LIKE 'imei'");
    $col = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;

    if (!$col) {
        $pdo->exec("ALTER TABLE equipos ADD COLUMN imei VARCHAR(20) NULL AFTER numero_serie");
    }

    $ready = true;
}
