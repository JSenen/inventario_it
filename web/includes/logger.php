<?php

function ensureActividadLogsSchema(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS actividad_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_tip VARCHAR(20) NOT NULL,
            accion VARCHAR(255) NOT NULL,
            modulo VARCHAR(80) DEFAULT NULL,
            nivel VARCHAR(20) NOT NULL DEFAULT 'INFO',
            detalles TEXT,
            ip VARCHAR(45) DEFAULT NULL,
            ruta VARCHAR(255) DEFAULT NULL,
            metodo VARCHAR(10) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $cols = $pdo->query("SHOW COLUMNS FROM actividad_logs")->fetchAll(PDO::FETCH_COLUMN);
    $faltantes = [
        'modulo'     => "ALTER TABLE actividad_logs ADD COLUMN modulo VARCHAR(80) DEFAULT NULL AFTER accion",
        'nivel'      => "ALTER TABLE actividad_logs ADD COLUMN nivel VARCHAR(20) NOT NULL DEFAULT 'INFO' AFTER modulo",
        'ruta'       => "ALTER TABLE actividad_logs ADD COLUMN ruta VARCHAR(255) DEFAULT NULL AFTER ip",
        'metodo'     => "ALTER TABLE actividad_logs ADD COLUMN metodo VARCHAR(10) DEFAULT NULL AFTER ruta",
        'user_agent' => "ALTER TABLE actividad_logs ADD COLUMN user_agent VARCHAR(255) DEFAULT NULL AFTER metodo",
    ];

    foreach ($faltantes as $col => $sql) {
        if (!in_array($col, $cols, true)) {
            $pdo->exec($sql);
        }
    }

    $idxCols = $pdo->query("SHOW INDEX FROM actividad_logs")->fetchAll(PDO::FETCH_COLUMN, 4);
    $indexMap = array_flip($idxCols ?: []);

    if (!isset($indexMap['creado_en'])) {
        $pdo->exec("CREATE INDEX idx_actividad_creado_en ON actividad_logs (creado_en)");
    }
    if (!isset($indexMap['usuario_tip'])) {
        $pdo->exec("CREATE INDEX idx_actividad_usuario ON actividad_logs (usuario_tip)");
    }
    if (!isset($indexMap['accion'])) {
        $pdo->exec("CREATE INDEX idx_actividad_accion ON actividad_logs (accion)");
    }
    if (!isset($indexMap['modulo'])) {
        $pdo->exec("CREATE INDEX idx_actividad_modulo ON actividad_logs (modulo)");
    }
    if (!isset($indexMap['nivel'])) {
        $pdo->exec("CREATE INDEX idx_actividad_nivel ON actividad_logs (nivel)");
    }

    $ready = true;
}

function logActividad(PDO $pdo, string $accion, string $detalles = '', array $context = []): void
{
    ensureActividadLogsSchema($pdo);

    $usuario = (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['tip']))
        ? (string)$_SESSION['tip']
        : 'DESCONOCIDO';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ruta = $context['ruta'] ?? ($_SERVER['REQUEST_URI'] ?? null);
    $metodo = $context['metodo'] ?? ($_SERVER['REQUEST_METHOD'] ?? null);
    $ua = $context['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? null);

    if (is_string($ua) && strlen($ua) > 255) {
        $ua = substr($ua, 0, 255);
    }

    $nivel = strtoupper(trim((string)($context['nivel'] ?? 'INFO')));
    if (!in_array($nivel, ['INFO', 'WARN', 'ERROR', 'SECURITY'], true)) {
        $nivel = 'INFO';
    }

    $modulo = $context['modulo'] ?? null;
    if ($modulo === null || $modulo === '') {
        $script = strtoupper(pathinfo($_SERVER['SCRIPT_NAME'] ?? '', PATHINFO_FILENAME));
        if ($script !== '') {
            $modulo = $script;
        } else {
            $modulo = 'GENERAL';
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO actividad_logs (usuario_tip, accion, modulo, nivel, detalles, ip, ruta, metodo, user_agent)
        VALUES (:u, :a, :m, :n, :d, :ip, :r, :me, :ua)
    ");
    $stmt->execute([
        ':u'  => $usuario,
        ':a'  => $accion,
        ':m'  => $modulo,
        ':n'  => $nivel,
        ':d'  => $detalles,
        ':ip' => $ip,
        ':r'  => $ruta,
        ':me' => $metodo,
        ':ua' => $ua,
    ]);
}
