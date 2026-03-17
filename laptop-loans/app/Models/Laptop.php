<?php
namespace App\Models;
use PDO;

class Laptop {
  public static function all(?string $estado = null): array {
    $where = ''; $params = [];
    if ($estado !== null) { $where = "WHERE l.estado = ?"; $params[] = $estado; }

    $sql = "SELECT l.*, loc.nombre AS ubicacion
            FROM laptops l
            LEFT JOIN locations loc ON loc.id = l.ubicacion_id
            $where
            ORDER BY l.num_serie";
    $st = DB::pdo()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
  }

  public static function create(array $d): int {
    $st = DB::pdo()->prepare("
      INSERT INTO laptops (num_serie, marca, modelo, uso_preferente, estado, ubicacion_id)
      VALUES (?,?,?,?,?,?)
    ");
    $st->execute([
      $d['num_serie'],
      $d['marca']   ?? null,
      $d['modelo']  ?? null,
      $d['uso_preferente'] ?? null,
      $d['estado']  ?? 'disponible',
      $d['ubicacion_id'] ?? null,
    ]);

    
    return (int)DB::pdo()->lastInsertId();
  }

  public static function update(int $id, array $d): void {
    $st = DB::pdo()->prepare("
      UPDATE laptops
      SET num_serie=?, marca=?, modelo=?, uso_preferente=?, estado=?, ubicacion_id=?
      WHERE id=?
    ");
    $st->execute([
      $d['num_serie'],
      $d['marca']   ?? null,
      $d['modelo']  ?? null,
      $d['uso_preferente'] ?? null,
      $d['estado']  ?? 'disponible',
      $d['ubicacion_id'] ?? null,
      $id
    ]);
  }

  public static function findBySerie(string $serie): ?array {
    $st = DB::pdo()->prepare("
      SELECT l.*, loc.nombre AS ubicacion
      FROM laptops l
      LEFT JOIN locations loc ON loc.id=l.ubicacion_id
      WHERE l.num_serie=?
    ");
    $st->execute([$serie]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
  }

  public static function find(int $id): ?array {
    $st = DB::pdo()->prepare("
      SELECT l.*, loc.nombre AS ubicacion
      FROM laptops l
      LEFT JOIN locations loc ON loc.id=l.ubicacion_id
      WHERE l.id=?
    ");
    $st->execute([$id]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
  }

  public static function setEstado(int $id, string $estado): void {
    $st = DB::pdo()->prepare("UPDATE laptops SET estado=? WHERE id=?");
    $st->execute([$estado, $id]);
  }

  public static function canDelete(int $id): bool {
    $st = DB::pdo()->prepare("SELECT COUNT(*) FROM handovers WHERE laptop_id=?");
    $st->execute([$id]);
    return (int)$st->fetchColumn() === 0;
  }

  public static function delete(int $id): void {
    $st = DB::pdo()->prepare("DELETE FROM laptops WHERE id=?");
    $st->execute([$id]);
  }
}
