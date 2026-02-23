<?php
namespace App\Models;
use PDO;

class Handover {
    public static function create(array $d): int {
                $st = DB::pdo()->prepare("
        INSERT INTO handovers
        (laptop_id, person_id, course_id, tipo, fecha, observaciones, recibido_por, recibo_pdf_path, location_id)
        VALUES (?,?,?,?,?,?,?,?,?)
        ");
        $st->execute([
        $d['laptop_id'], $d['person_id'], $d['course_id'] ?? null, $d['tipo'], $d['fecha'],
        $d['observaciones'] ?? null, $d['recibido_por'] ?? null, $d['recibo_pdf_path'] ?? null,
        $d['location_id'] ?? null
        ]);

        return (int)DB::pdo()->lastInsertId();
    }
    public static function lastForLaptop(int $laptop_id): ?array {
        $st=DB::pdo()->prepare("SELECT * FROM handovers WHERE laptop_id=? ORDER BY fecha DESC, id DESC LIMIT 1");
        $st->execute([$laptop_id]); $r=$st->fetch(); return $r ?: null;
    }
    public static function history(int $limit=200): array {
        $sql = "SELECT h.*, p.nombre, p.apellidos, l.num_serie, c.nombre AS curso
                FROM handovers h
                JOIN people p ON p.id=h.person_id
                JOIN laptops l ON l.id=h.laptop_id
                LEFT JOIN courses c ON c.id=h.course_id
                LEFT JOIN locations loc ON loc.id = h.location_id
                ORDER BY h.fecha DESC, h.id DESC
                LIMIT $limit";
        return DB::pdo()->query($sql)->fetchAll();
    }
}