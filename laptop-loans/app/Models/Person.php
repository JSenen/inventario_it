<?php
namespace App\Models;
use PDO;

class Person {
  public static function all(bool $soloActivos = true): array {
    $where = $soloActivos ? "WHERE activo=1" : "";
    $sql = "SELECT * FROM people $where ORDER BY apellidos, nombre";
    return DB::pdo()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  }

  public static function find(int $id): ?array {
    $st = DB::pdo()->prepare("SELECT * FROM people WHERE id=?");
    $st->execute([$id]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
  }

  // public static function create(array $d): int {
  //   $st = DB::pdo()->prepare("
  //     INSERT INTO people (nombre,apellidos,dni,tip,telefono,email,unidad_destino,activo)
  //     VALUES (?,?,?,?,?,?,?,1)
  //   ");
  //   $st->execute([
  //     $d['nombre'] ?? '', $d['apellidos'] ?? '', $d['dni'] ?? '',
  //     $d['tip'] ?? null, $d['telefono'] ?? null, $d['email'] ?? null,
  //     $d['unidad_destino'] ?? null
  //   ]);
  //   return (int)DB::pdo()->lastInsertId();
  // }

  // public static function update(int $id, array $d): void {
  //   $st = DB::pdo()->prepare("
  //     UPDATE people SET nombre=?, apellidos=?, dni=?, tip=?, telefono=?, email=?, unidad_destino=?
  //     WHERE id=?
  //   ");
  //   $st->execute([
  //     $d['nombre'] ?? '', $d['apellidos'] ?? '', $d['dni'] ?? '',
  //     $d['tip'] ?? null, $d['telefono'] ?? null, $d['email'] ?? null,
  //     $id['unidad_destino'] ?? null, $id
  //   ]);
  // }
  // app/Models/Person.php (ejemplo)
// app/Models/Person.php
public static function create(array $d): int {
    $dni = isset($d['dni']) ? strtoupper(preg_replace('/\s+/u','', trim((string)$d['dni']))) : null;
    $tip = isset($d['tip']) ? strtoupper(preg_replace('/\s+/u','', trim((string)$d['tip']))) : null;
    $tel = isset($d['telefono']) ? trim((string)$d['telefono']) : null;
    $eml = isset($d['email']) ? trim((string)$d['email']) : null;
    $uni = isset($d['unidad_destino']) ? trim((string)$d['unidad_destino']) : null;

    $dni = ($dni === '') ? null : $dni;
    $tip = ($tip === '') ? null : $tip;
    $tel = ($tel === '') ? null : $tel;
    $eml = ($eml === '') ? null : $eml;
    $uni = ($uni === '') ? null : $uni;

    $st = DB::pdo()->prepare("
      INSERT INTO people (nombre, apellidos, dni, tip, telefono, email, unidad_destino)
      VALUES (?,?,?,?,?,?,?)
    ");
    $st->execute([
      trim((string)$d['nombre']),
      trim((string)$d['apellidos']),
      $dni, $tip, $tel, $eml, $uni
    ]);
    return (int)DB::pdo()->lastInsertId();
}

public static function update(int $id, array $d): void {
    $dni = array_key_exists('dni',$d) ? strtoupper(preg_replace('/\s+/u','', trim((string)$d['dni']))) : null;
    $tip = array_key_exists('tip',$d) ? strtoupper(preg_replace('/\s+/u','', trim((string)$d['tip']))) : null;
    $tel = array_key_exists('telefono',$d) ? trim((string)$d['telefono']) : null;
    $eml = array_key_exists('email',$d) ? trim((string)$d['email']) : null;
    $uni = array_key_exists('unidad_destino',$d) ? trim((string)$d['unidad_destino']) : null;

    if ($dni !== null && $dni==='') $dni = null;
    if ($tip !== null && $tip==='') $tip = null;
    if ($tel !== null && $tel==='') $tel = null;
    if ($eml !== null && $eml==='') $eml = null;
    if ($uni !== null && $uni==='') $uni = null;

    $st = DB::pdo()->prepare("
      UPDATE people
      SET nombre=?, apellidos=?, dni=?, tip=?, telefono=?, email=?, unidad_destino=?
      WHERE id=?
    ");
    $st->execute([
      trim((string)$d['nombre']),
      trim((string)$d['apellidos']),
      $dni, $tip, $tel, $eml, $uni, $id
    ]);
}


  public static function setActivo(int $id, bool $on): void {
    $st = DB::pdo()->prepare("UPDATE people SET activo=? WHERE id=?");
    $st->execute([$on ? 1 : 0, $id]);
  }

  // ya lo tenías, lo dejo por si acaso
  public static function findByDniOrTip(string $dniOrTip): ?array {
    $st = DB::pdo()->prepare("SELECT * FROM people WHERE dni=? OR tip=? LIMIT 1");
    $st->execute([$dniOrTip, $dniOrTip]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
  }
}
