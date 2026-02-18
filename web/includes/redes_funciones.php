<?php
function obtenerContadoresIPsPorRed(PDO $pdo, int $idRed): array
{
    $sql = "
        SELECT 
            COUNT(*) AS total,
            SUM(estado = 'LIBRE')      AS libres,
            SUM(estado = 'USADA')      AS usadas,
            SUM(estado = 'RESERVADA')  AS reservadas
        FROM ips_equipos
        WHERE red_id = :id_red
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id_red' => $idRed]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);

    return [
        'total'      => (int)($res['total'] ?? 0),
        'libres'     => (int)($res['libres'] ?? 0),
        'usadas'     => (int)($res['usadas'] ?? 0),
        'reservadas' => (int)($res['reservadas'] ?? 0),
    ];
}
?>