<?php

function ensureSeccionesCorreo(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS secciones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(100) NOT NULL,
            descripcion VARCHAR(255) DEFAULT NULL,
            correo VARCHAR(150) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $cols = $pdo->query("SHOW COLUMNS FROM secciones")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('correo', $cols, true)) {
        $pdo->exec("ALTER TABLE secciones ADD COLUMN correo VARCHAR(150) DEFAULT NULL AFTER descripcion");
    }

    $ready = true;
}
