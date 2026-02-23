<?php
namespace App\Models;
use PDO;

class Course {
  public static function all(bool $soloActivos = true): array {
    $where = $soloActivos ? "WHERE activo=1" : "";
    $sql = "SELECT * FROM courses $where ORDER BY fecha_inicio DESC, nombre";
    return DB::pdo()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  }

  public static function find(int $id): ?array {
    $st = DB::pdo()->prepare("SELECT * FROM courses WHERE id=?");
    $st->execute([$id]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
  }

  public static function create(array $d): int {
    $st = DB::pdo()->prepare("
      INSERT INTO courses (nombre, fecha_inicio, fecha_fin, activo)
      VALUES (?, ?, ?, 1)
    ");
    $st->execute([
      $d['nombre'] ?? '',
      $d['fecha_inicio'] ?? null,
      $d['fecha_fin'] ?? null
    ]);
    return (int)DB::pdo()->lastInsertId();
  }

  public static function update(int $id, array $d): void {
    $st = DB::pdo()->prepare("
      UPDATE courses SET nombre=?, fecha_inicio=?, fecha_fin=?
      WHERE id=?
    ");
    $st->execute([
      $d['nombre'] ?? '',
      $d['fecha_inicio'] ?? null,
      $d['fecha_fin'] ?? null,
      $id
    ]);
  }

  public static function setActivo(int $id, bool $on): void {
    $st = DB::pdo()->prepare("UPDATE courses SET activo=? WHERE id=?");
    $st->execute([$on ? 1 : 0, $id]);
  }
}
