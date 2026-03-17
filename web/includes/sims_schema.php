<?php

function ensureSimsSchema(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM sims LIKE 'numero_corto'");
    $col = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;

    if (!$col) {
        $pdo->exec("ALTER TABLE sims ADD COLUMN numero_corto VARCHAR(30) NULL AFTER numero");
    }

    $ready = true;
}

