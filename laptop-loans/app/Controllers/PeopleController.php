<?php
namespace App\Controllers;
use App\Models\{Person, DB};
use App\Services\ExcelService;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PeopleController {

  /** Normaliza identificadores (DNI/TIP): trim, quita espacios (incl. NBSP) y pasa a MAYÚSCULAS */
private function normId(?string $s): ?string {
    if ($s === null) return null;
    $s = preg_replace('/\x{00A0}/u', ' ', $s); // NBSP -> espacio
    $s = trim($s);
    if ($s === '') return null;
    // elimina todos los espacios internos
    $s = preg_replace('/\s+/u', '', $s);
    return strtoupper($s);
}
/** Convierte '' o solo espacios a NULL */
private function nullIfEmpty(?string $s): ?string {
    if ($s === null) return null;
    $s = preg_replace('/\x{00A0}/u', ' ', $s);
    $s = trim($s);
    return $s === '' ? null : $s;
}


  public function index() {
  $show = $_GET['show'] ?? 'active'; // active|archived|all
  $where = $show==='active' ? "WHERE activo=1" : ($show==='archived' ? "WHERE activo=0" : "");
  $page = max(1, (int)($_GET['page'] ?? 1));
  $perPage = 25;
  $offset = ($page-1) * $perPage;

  $total = (int)DB::pdo()->query("SELECT COUNT(*) FROM people $where")->fetchColumn();
  $st = DB::pdo()->prepare("SELECT * FROM people $where ORDER BY apellidos,nombre LIMIT ? OFFSET ?");
  $st->bindValue(1, $perPage, \PDO::PARAM_INT);
  $st->bindValue(2, $offset, \PDO::PARAM_INT);
  $st->execute();
  $people = $st->fetchAll();

  return view('people/index', compact('people','show','total','page','perPage'));
}

  public function create() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      csrf_check();
      Person::create([
        'nombre'    => trim($_POST['nombre']),
        'apellidos' => trim($_POST['apellidos']),
        'dni'       => trim($_POST['dni']),
        'tip'       => trim($_POST['tip'] ?? ''),
        'telefono'  => trim($_POST['telefono'] ?? ''),
        'email'     => trim($_POST['email'] ?? ''),
        'unidad_destino' => trim($_POST['unidad_destino'] ?? ''),
      ]);
      header('Location: ' . url('people/index')); exit;
    }
    return view('people/create');
  }

  public function edit() {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { http_response_code(400); echo "ID requerido"; return; }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      csrf_check();
      Person::update($id, [
        'nombre'    => trim($_POST['nombre']),
        'apellidos' => trim($_POST['apellidos']),
        'dni'       => trim($_POST['dni']),
        'tip'       => trim($_POST['tip'] ?? ''),
        'telefono'  => trim($_POST['telefono'] ?? ''),
        'email'     => trim($_POST['email'] ?? ''),
        'unidad_destino' => trim($_POST['unidad_destino'] ?? ''),
      ]);
      header('Location: ' . url('people/index')); exit;
    }
    $person = Person::find($id);
    if (!$person) { http_response_code(404); echo "Persona no encontrada"; return; }
    return view('people/edit', compact('person'));
  }

  public function archive() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo "Método no permitido"; return; }
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { http_response_code(400); echo "ID requerido"; return; }
    Person::setActivo($id, false);
    header('Location: ' . url('people/index')); exit;
  }

  public function restore() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo "Método no permitido"; return; }
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { http_response_code(400); echo "ID requerido"; return; }
    Person::setActivo($id, true);
    header('Location: ' . url('people/index')); exit;
  }

  /** Normaliza cabeceras: quita acentos, pasa a snake_case y minúsculas */
  private function normKey(string $s): string {
    $s = trim($s);
    if (function_exists('iconv')) {
      $t = @iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s);
      if ($t !== false) $s = $t;
    }
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/','_',$s);
    return trim($s,'_');
  }

  /** GET: formulario / POST: procesa el fichero */
  public function import() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      return view('people/import');
    }
    csrf_check();

    if (empty($_FILES['file']['tmp_name'])) {
      return view('people/import', ['error' => 'Sube un archivo XLSX o CSV']);
    }

    // Opciones del formulario
    $mode = $_POST['mode'] ?? 'skip'; // 'skip' o 'update' (qué hacer si ya existe)
    $dedup = $_POST['dedup'] ?? 'dni_tip_nombre'; // criterio de duplicado

    // Mover a /storage/uploads
    $dir = BASE_PATH . '/storage/uploads';
    if (!is_dir($dir)) @mkdir($dir,0775,true);
    $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
    $dest = $dir . '/' . uniqid('people_', true) . '.' . $ext;
    if (!move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
      return view('people/import', ['error' => 'No se pudo guardar el archivo subido']);
    }

    // Leer con PhpSpreadsheet (soporta xlsx y csv)
    try {
      if ($ext === 'csv') {
        $reader = IOFactory::createReader('Csv');
        $reader->setDelimiter(';'); // ajusta si usas coma
        $ss = $reader->load($dest);
      } else {
        $ss = IOFactory::load($dest);
      }
    } catch (\Throwable $e) {
      return view('people/import', ['error' => 'Archivo no válido: '.$e->getMessage()]);
    }

    $sheet = $ss->getSheet(0);
    $highestRow = $sheet->getHighestRow();
    $highestCol = $sheet->getHighestColumn();

    // Cabeceras (fila 1)
    $headers = [];
    $hRow = $sheet->rangeToArray("A1:{$highestCol}1", null, true, false)[0] ?? [];
    foreach ($hRow as $i => $name) {
      $headers[$i] = $this->normKey((string)($name ?? ''));
    }

    // Mapeo esperado -> índice de columna (según nombre)
    // Permite variantes de nombre de columna (Nombre, NOMBRE, name, etc.)
    $want = [
      'nombre'         => ['nombre','name','first_name'],
      'apellidos'      => ['apellidos','apellido','last_name','surname'],
      'dni'            => ['dni','nif','documento'],
      'tip'            => ['tip'],
      'telefono'       => ['telefono','tel','phone'],
      'email'          => ['email','correo','mail'],
      'unidad_destino' => ['unidad_destino','destino','unidad'],
    ];
    $col = array_fill_keys(array_keys($want), null);
    foreach ($want as $field => $aliases) {
      foreach ($headers as $i => $hk) {
        if (in_array($hk, $aliases, true)) { $col[$field] = $i; break; }
      }
    }

    $report = [
      'total' => 0,
      'insertados' => 0,
      'actualizados' => 0,
      'omitidos' => 0,
      'errores' => [],
      'file_saved' => $dest,
    ];

    $pdo = DB::pdo();
    $pdo->beginTransaction();
    try {
      for ($r = 2; $r <= $highestRow; $r++) {
        $row = $sheet->rangeToArray("A{$r}:{$highestCol}{$r}", null, true, false)[0] ?? [];
        $report['total']++;

       // helper para leer una celda con seguridad
$v = function($idx) use ($row) { return isset($row[$idx]) ? (string)$row[$idx] : ''; };

// Construcción de datos (NUNCA '' -> siempre NULL si vacío)
$data = [
  'nombre'         => $col['nombre']         !== null ? trim($v($col['nombre']))         : '',
  'apellidos'      => $col['apellidos']      !== null ? trim($v($col['apellidos']))      : '',
  'dni'            => $col['dni']            !== null ? $this->normId($v($col['dni']))   : null,
  'tip'            => $col['tip']            !== null ? $this->normId($v($col['tip']))   : null,
  'telefono'       => $col['telefono']       !== null ? $this->nullIfEmpty($v($col['telefono'])) : null,
  'email'          => $col['email']          !== null ? $this->nullIfEmpty($v($col['email']))    : null,
  'unidad_destino' => $col['unidad_destino'] !== null ? $this->nullIfEmpty($v($col['unidad_destino'])) : null,
];

// Reglas mínimas
if ($data['nombre']==='' || $data['apellidos']==='') {
  $report['omitidos']++;
  $report['errores'][] = "Fila {$r}: faltan Nombre/Apellidos";
  continue;
}

// DEDUPE: solo si DNI/TIP NO son null
$existing = null;
if ($dedup === 'dni_tip_nombre') {
  if ($data['dni'] !== null) {
    $st = $pdo->prepare("SELECT * FROM people WHERE dni=? LIMIT 1");
    $st->execute([$data['dni']]); $existing = $st->fetch();
  }
  if (!$existing && $data['tip'] !== null) {
    $st = $pdo->prepare("SELECT * FROM people WHERE tip=? LIMIT 1");
    $st->execute([$data['tip']]); $existing = $st->fetch();
  }
  if (!$existing) {
    $st = $pdo->prepare("SELECT * FROM people WHERE nombre=? AND apellidos=? LIMIT 1");
    $st->execute([$data['nombre'], $data['apellidos']]); $existing = $st->fetch();
  }
} elseif ($dedup === 'dni_only') {
  if ($data['dni'] !== null) {
    $st = $pdo->prepare("SELECT * FROM people WHERE dni=? LIMIT 1");
    $st->execute([$data['dni']]); $existing = $st->fetch();
  }
} elseif ($dedup === 'name_lastname') {
  $st = $pdo->prepare("SELECT * FROM people WHERE nombre=? AND apellidos=? LIMIT 1");
  $st->execute([$data['nombre'], $data['apellidos']]); $existing = $st->fetch();
}


        try {
          if ($existing) {
            if ($mode === 'update') {
              // Actualizar campos no vacíos
              $upd = [
                'nombre'         => $data['nombre']         ?: $existing['nombre'],
                'apellidos'      => $data['apellidos']      ?: $existing['apellidos'],
                'dni'            => $data['dni']            ?: $existing['dni'],
                'tip'            => $data['tip']            ?: $existing['tip'],
                'telefono'       => $data['telefono']       ?: $existing['telefono'],
                'email'          => $data['email']          ?: $existing['email'],
                'unidad_destino' => $data['unidad_destino'] ?: ($existing['unidad_destino'] ?? null),
              ];
              Person::update((int)$existing['id'], $upd);
              $report['actualizados']++;
            } else {
              $report['omitidos']++;
            }
          } else {
            Person::create($data);
            $report['insertados']++;
          }
        } catch (\Throwable $e) {
          $report['errores'][] = "Fila {$r}: ".$e->getMessage();
        }
      }

      $pdo->commit();
    } catch (\Throwable $e) {
      $pdo->rollBack();
      return view('people/import', ['error' => 'Error en importación: '.$e->getMessage()]);
    }

    return view('people/import', compact('report','mode','dedup'));
  }

  /** Plantilla de Excel para descargar */
  public function template() {
    $headers = ['Nombre','Apellidos','DNI','TIP','Teléfono','Email','Unidad destino'];
    $rows = [
      ['Ana','Pérez Gómez','12345678A','','600123123','ana@ejemplo.es','Comandancia X'],
      ['Luis','Lamas','','TIP123','600999888','luis@ejemplo.es','Unidad Y'],
    ];
    \App\Services\ExcelService::downloadXlsx('plantilla_personas.xlsx', $headers, $rows);
  }

}
