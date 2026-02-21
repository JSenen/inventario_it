<?php
namespace App\Controllers;

use App\Models\{DB, Person, Laptop, Course, Handover, Location};
use App\Services\PdfService;

class HandoversController
{
    /** Localiza la plantilla (sirve en public/recibos_templates o recibos_templates) */
    private function tpl(string $nombre): string {
        $candidatas = [
            BASE_PATH . "/public/recibos_templates/{$nombre}.html",
            BASE_PATH . "/recibos_templates/{$nombre}.html",
        ];
        foreach ($candidatas as $p) {
            if (is_file($p)) return $p;
        }
        die("Plantilla no encontrada: {$nombre}.html");
    }

    /** Logos (file:// o http://) e items por defecto para el PDF */
    /** Logos (DATA-URI base64) e items por defecto para el PDF */
private function logosIncludes(): array {
    // candidatos por orden de preferencia
    $leftCandidates = [
        BASE_PATH . '/public/assets/img/logo_left.png',
        BASE_PATH . '/public/assets/img/logo_izq.png',
        BASE_PATH . '/public/assets/img/logo_left.svg',
        BASE_PATH . '/public/assets/img/logo_izq.svg',
    ];
    $rightCandidates = [
        BASE_PATH . '/public/assets/img/logo_right.png',
        BASE_PATH . '/public/assets/img/logo_der.png',
        BASE_PATH . '/public/assets/img/logo_right.svg',
        BASE_PATH . '/public/assets/img/logo_der.svg',
    ];

    $left  = '';
    foreach ($leftCandidates as $p) { if (is_file($p)) { $left  = \App\Services\PdfService::imgDataUri($p);  break; } }
    $right = '';
    foreach ($rightCandidates as $p) { if (is_file($p)) { $right = \App\Services\PdfService::imgDataUri($p); break; } }

    return [
        'logo_izq' => $left,
        'logo_der' => $right,
        'incluye_1' => 'Cargador',
        'incluye_2' => 'Cable alimentación',
        'incluye_3' => 'Maletín/Mochila',
        'incluye_4' => 'Ratón',
        'incluye_5' => '',
    ];
}


    private function fmtFecha(string $ts): string {
        return date('Y-m-d H:i', strtotime($ts));
    }

    // ---------------------------------------------------------------------

    public function index() {
  $page = max(1, (int)($_GET['page'] ?? 1));
  $perPage = 25; $offset = ($page-1)*$perPage;

  $total = (int)DB::pdo()->query("SELECT COUNT(*) FROM handovers")->fetchColumn();

  $sql = "SELECT h.*, p.nombre, p.apellidos, l.num_serie,
                 c.nombre AS curso, loc.nombre AS almacen, p.unidad_destino AS unidad
          FROM handovers h
          JOIN people p ON p.id=h.person_id
          JOIN laptops l ON l.id=h.laptop_id
          LEFT JOIN courses c ON c.id=h.course_id
          LEFT JOIN locations loc ON loc.id=h.location_id
          ORDER BY h.fecha DESC, h.id DESC
          LIMIT ? OFFSET ?";
  $st = DB::pdo()->prepare($sql);
  $st->bindValue(1, $perPage, \PDO::PARAM_INT);
  $st->bindValue(2, $offset,  \PDO::PARAM_INT);
  $st->execute();
  $history = $st->fetchAll();

  return view('handovers/index', compact('history','total','page','perPage'));
}


    // ----------------------------- ENTREGA --------------------------------

    public function entrega() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_check();

            $obs       = trim($_POST['observaciones'] ?? '');
            $fecha     = $_POST['fecha'] ?: date('Y-m-d H:i:s');
            $courseId  = ($_POST['course_id']  ?? '') !== '' ? (int)$_POST['course_id']  : null;
            $locationId= ($_POST['location_id']?? '') !== '' ? (int)$_POST['location_id']: null;

            // --- RUTA A: selects (persona + portátil) ---
            if (!empty($_POST['person_id']) && !empty($_POST['laptop_id'])) {
                $personId = (int)$_POST['person_id'];
                $laptopId = (int)$_POST['laptop_id'];

                $last = Handover::lastForLaptop($laptopId);
                if ($last && $last['tipo'] === 'entrega') { die('Este portátil ya está prestado'); }

                DB::pdo()->beginTransaction();
                try {
                    $hid = Handover::create([
                        'laptop_id'       => $laptopId,
                        'person_id'       => $personId,
                        'course_id'       => $courseId,
                        'tipo'            => 'entrega',
                        'fecha'           => $fecha,
                        'observaciones'   => $obs,
                        'location_id'     => $locationId,    // almacén del movimiento
                        'recibido_por'    => null,
                        'recibo_pdf_path' => null,
                    ]);

                    // Estado + ubicación: al prestar queda sin almacén
                    Laptop::setEstado($laptopId, 'prestado');
                    DB::pdo()->prepare("UPDATE laptops SET ubicacion_id=NULL WHERE id=?")->execute([$laptopId]);

                    // Datos del movimiento
                    $st = DB::pdo()->prepare("
                        SELECT h.id,h.tipo,h.fecha,h.observaciones,
                               p.nombre,p.apellidos,p.dni,p.tip,p.telefono,p.email,
                               l.num_serie, c.nombre AS curso, p.unidad_destino AS unidad
                        FROM handovers h
                        JOIN people p ON p.id=h.person_id
                        JOIN laptops l ON l.id=h.laptop_id
                        LEFT JOIN courses c ON c.id=h.course_id
                        WHERE h.id=?
                    ");
                    $st->execute([$hid]);
                    $row = $st->fetch();

                    $lugar = $locationId ? (Location::find($locationId)['nombre'] ?? '') : '';

                    $data = array_merge($this->logosIncludes(), [
                        'curso' => $row['curso'] ?? '',
                        'nombre' => $row['nombre'],
                        'apellidos' => $row['apellidos'],
                        'dni' => $row['dni'],
                        'tip' => $row['tip'],
                        'telefono' => $row['telefono'],
                        'email' => $row['email'],
                        'empleo' => $row['empleo'] ?? '',
                        'unidad' => $row['unidad'] ?? '',
                        'equipo_descripcion' => 'Portátil',
                        'num_serie' => $row['num_serie'],
                        'fecha_entrega' => $this->fmtFecha($row['fecha']),
                        'lugar' => $lugar,
                        'firma_receptor_nombre' => $row['nombre'].' '.$row['apellidos'],
                    ]);
                    
                    // ------------------    RECIBO ENTREGA ---------------------------------
                    $serieSlug = $this->slug($row['num_serie'] ?? 'sin_serie');
                    $cursoSlug = $this->slug($row['curso'] ?? 'sin_curso');
                    $filename  = "entrega_{$serieSlug}_{$cursoSlug}_{$hid}.pdf";
                    
                    $pdf   = PdfService::renderTemplate($this->tpl('entrega'), $data);
                    $saved = PdfService::savePdf($pdf, BASE_PATH . "/storage/recibos", $filename);
                    DB::pdo()->prepare("UPDATE handovers SET recibo_pdf_path=? WHERE id=?")->execute([$saved, $hid]);

                    
                    DB::pdo()->commit();
                } catch (\Throwable $e) {
                    DB::pdo()->rollBack(); throw $e;
                }

                header('Location: ' . \url('handovers/index')); exit;
            }

            // --- RUTA B: fallback (DNI/TIP + Nº de serie) ---
            $dni = trim($_POST['dni'] ?? '');
            $tip = trim($_POST['tip'] ?? '');
            if ($dni === '' && $tip === '') { die('Indica persona (select) o DNI/TIP.'); }

            $person = ($dni !== '') ? Person::findByDniOrTip($dni) : null;
            if (!$person && $tip !== '') { $person = Person::findByDniOrTip($tip); }
            if (!$person) {
                if ($dni === '') { die('La persona no existe. Crea la ficha primero en Personas (DNI obligatorio).'); }
                $personId = Person::create([
                    'nombre'    => $_POST['nombre']    ?? '',
                    'apellidos' => $_POST['apellidos'] ?? '',
                    'dni'       => $dni,
                    'tip'       => $tip ?: null,
                    'telefono'  => $_POST['telefono']  ?? null,
                    'email'     => $_POST['email']     ?? null,
                ]);
                $person = ['id' => (int)$personId];
            }

            $serie  = trim($_POST['num_serie'] ?? '');
            $laptop = Laptop::findBySerie($serie);
            if (!$laptop) { die('Portátil no encontrado'); }

            $last = Handover::lastForLaptop((int)$laptop['id']);
            if ($last && $last['tipo'] === 'entrega') { die('Este portátil ya está prestado'); }

            DB::pdo()->beginTransaction();
            try {
                $hid = Handover::create([
                    'laptop_id'       => (int)$laptop['id'],
                    'person_id'       => (int)$person['id'],
                    'course_id'       => $courseId,
                    'tipo'            => 'entrega',
                    'fecha'           => $fecha,
                    'observaciones'   => $obs,
                    'location_id'     => $locationId,
                    'recibido_por'    => null,
                    'recibo_pdf_path' => null,
                ]);
                Laptop::setEstado((int)$laptop['id'], 'prestado');
                DB::pdo()->prepare("UPDATE laptops SET ubicacion_id=NULL WHERE id=?")->execute([(int)$laptop['id']]);

                $st = DB::pdo()->prepare("
                    SELECT h.id,h.tipo,h.fecha,h.observaciones,
                           p.nombre,p.apellidos,p.dni,p.tip,p.telefono,p.email,
                           l.num_serie, c.nombre AS curso, p.unidad_destino AS unidad
                    FROM handovers h
                    JOIN people p ON p.id=h.person_id
                    JOIN laptops l ON l.id=h.laptop_id
                    LEFT JOIN courses c ON c.id=h.course_id
                    WHERE h.id=?
                ");
                $st->execute([$hid]);
                $row = $st->fetch();

                $lugar = $locationId ? (Location::find($locationId)['nombre'] ?? '') : '';

                $data = array_merge($this->logosIncludes(), [
                    'curso' => $row['curso'] ?? '',
                    'nombre' => $row['nombre'],
                    'apellidos' => $row['apellidos'],
                    'dni' => $row['dni'],
                    'tip' => $row['tip'],
                    'telefono' => $row['telefono'],
                    'email' => $row['email'],
                    'empleo' => $row['empleo'] ?? '',
                    'unidad' => $row['unidad'] ?? '',
                    'equipo_descripcion' => 'Portátil',
                    'num_serie' => $row['num_serie'],
                    'fecha_entrega' => $this->fmtFecha($row['fecha']),
                    'lugar' => $lugar,
                    'firma_receptor_nombre' => $row['nombre'].' '.$row['apellidos'],
                ]);

                //-----------------   RECIBO ENTREGA B -----------------------------------------
                $serieSlug = $this->slug($row['num_serie'] ?? 'sin_serie');
                    $cursoSlug = $this->slug($row['curso'] ?? 'sin_curso');
                    $filename  = "entrega_{$serieSlug}_{$cursoSlug}_{$hid}.pdf";

                    $pdf   = PdfService::renderTemplate($this->tpl('entrega'), $data);
                    $saved = PdfService::savePdf($pdf, BASE_PATH . "/storage/recibos", $filename);
                    DB::pdo()->prepare("UPDATE handovers SET recibo_pdf_path=? WHERE id=?")->execute([$saved, $hid]);

                DB::pdo()->commit();
            } catch (\Throwable $e) {
                DB::pdo()->rollBack(); throw $e;
            }

            header('Location: ' . \url('handovers/index')); exit;
        }

        // GET: selects
        $people  = DB::pdo()->query("SELECT id,nombre,apellidos,dni,tip FROM people WHERE activo=1 ORDER BY nombre,apellidos")->fetchAll();
        $courses = DB::pdo()->query("SELECT * FROM courses WHERE activo=1 ORDER BY nombre")->fetchAll();
        $laptops   = DB::pdo()->query("SELECT id,num_serie FROM laptops WHERE estado='disponible' ORDER BY num_serie")->fetchAll();
        $locations = Location::all();
        return view('handovers/entrega', compact('people','laptops','courses','locations'));
    }

    // ---------------------------- DEVOLUCIÓN ------------------------------

    public function devolucion() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_check();

            $obs       = trim($_POST['observaciones'] ?? '');
            $fecha     = $_POST['fecha'] ?: date('Y-m-d H:i:s');
            $locationId= ($_POST['location_id']?? '') !== '' ? (int)$_POST['location_id']: null;

            // --- RUTA A: select de portátil prestado ---
            if (!empty($_POST['laptop_id'])) {
                $laptopId = (int)$_POST['laptop_id'];
                $last = Handover::lastForLaptop($laptopId);
                if (!$last || $last['tipo'] !== 'entrega') { die('Este portátil no está prestado'); }

                DB::pdo()->beginTransaction();
                try {
                    $hid = Handover::create([
                        'laptop_id'       => $laptopId,
                        'person_id'       => (int)$last['person_id'],
                        'course_id'       => $last['course_id'],
                        'tipo'            => 'devolucion',
                        'fecha'           => $fecha,
                        'observaciones'   => $obs,
                        'location_id'     => $locationId,
                        'recibido_por'    => null,
                        'recibo_pdf_path' => null,
                    ]);
                    Laptop::setEstado($laptopId, 'disponible');
                    DB::pdo()->prepare("UPDATE laptops SET ubicacion_id=? WHERE id=?")->execute([$locationId, $laptopId]);

                    $st = DB::pdo()->prepare("
                        SELECT h.id,h.tipo,h.fecha,h.observaciones,
                               p.nombre,p.apellidos,p.dni,p.tip,p.telefono,p.email,
                               l.num_serie, c.nombre AS curso, p.unidad_destino AS unidad
                        FROM handovers h
                        JOIN people p ON p.id=h.person_id
                        JOIN laptops l ON l.id=h.laptop_id
                        LEFT JOIN courses c ON c.id=h.course_id
                        WHERE h.id=?
                    ");
                    $st->execute([$hid]);
                    $row = $st->fetch();

                    $lugar = $locationId ? (Location::find($locationId)['nombre'] ?? '') : '';

                    $data = array_merge($this->logosIncludes(), [
                        'curso' => $row['curso'] ?? '',
                        'nombre' => $row['nombre'],
                        'apellidos' => $row['apellidos'],
                        'dni' => $row['dni'],
                        'tip' => $row['tip'],
                        'telefono' => $row['telefono'],
                        'email' => $row['email'],
                        'empleo' => $row['empleo'] ?? '',
                        'unidad' => $row['unidad'] ?? '',
                        'equipo_descripcion' => 'Portátil',
                        'num_serie' => $row['num_serie'],
                        'fecha_devolucion' => $this->fmtFecha($row['fecha']),
                        'lugar' => $lugar,
                        'firma_receptor_nombre' => $row['nombre'].' '.$row['apellidos'],
                    ]);
                    
                    //-------------------    RECIBO DEVOLUCION ----------------------------------------
                    $serieSlug = $this->slug($row['num_serie'] ?? 'sin_serie');
                    $cursoSlug = $this->slug($row['curso'] ?? 'sin_curso');
                    $filename  = "devolucion_{$serieSlug}_{$cursoSlug}_{$hid}.pdf";

                    $pdf   = PdfService::renderTemplate($this->tpl('devolucion'), $data);
                    $saved = PdfService::savePdf($pdf, BASE_PATH . "/storage/recibos", $filename);
                    DB::pdo()->prepare("UPDATE handovers SET recibo_pdf_path=? WHERE id=?")->execute([$saved, $hid]);


                    DB::pdo()->commit();
                } catch (\Throwable $e) {
                    DB::pdo()->RollBack(); throw $e;
                }

                header('Location: ' . \url('handovers/index')); exit;
            }

            // --- RUTA B: fallback (nº de serie) ---
            $serie  = trim($_POST['num_serie'] ?? '');
            $laptop = Laptop::findBySerie($serie);
            if (!$laptop) { die('Portátil no encontrado'); }
            $last = Handover::lastForLaptop((int)$laptop['id']);
            if (!$last || $last['tipo']!=='entrega') { die('Este portátil no está prestado'); }

            DB::pdo()->beginTransaction();
            try {
                $hid = Handover::create([
                    'laptop_id'       => (int)$laptop['id'],
                    'person_id'       => (int)$last['person_id'],
                    'course_id'       => $last['course_id'],
                    'tipo'            => 'devolucion',
                    'fecha'           => $fecha,
                    'observaciones'   => $obs,
                    'location_id'     => $locationId,
                    'recibido_por'    => null,
                    'recibo_pdf_path' => null,
                ]);
                Laptop::setEstado((int)$laptop['id'], 'disponible');
                DB::pdo()->prepare("UPDATE laptops SET ubicacion_id=? WHERE id=?")->execute([$locationId, (int)$laptop['id']]);

                $st = DB::pdo()->prepare("
                    SELECT h.id,h.tipo,h.fecha,h.observaciones,
                           p.nombre,p.apellidos,p.dni,p.tip,p.telefono,p.email,
                           l.num_serie, c.nombre AS curso. p.unidad_destino AS unidad
                    FROM handovers h
                    JOIN people p ON p.id=h.person_id
                    JOIN laptops l ON l.id=h.laptop_id
                    LEFT JOIN courses c ON c.id=h.course_id
                    WHERE h.id=?
                ");
                $st->execute([$hid]);
                $row = $st->fetch();

                $lugar = $locationId ? (Location::find($locationId)['nombre'] ?? '') : '';

                $data = array_merge($this->logosIncludes(), [
                    'curso' => $row['curso'] ?? '',
                    'nombre' => $row['nombre'],
                    'apellidos' => $row['apellidos'],
                    'dni' => $row['dni'],
                    'tip' => $row['tip'],
                    'telefono' => $row['telefono'],
                    'email' => $row['email'],
                    'empleo' => $row['empleo'] ?? '',
                    'unidad' => $row['unidad'] ?? '',
                    'equipo_descripcion' => 'Portátil',
                    'num_serie' => $row['num_serie'],
                    'fecha_devolucion' => $this->fmtFecha($row['fecha']),
                    'lugar' => $lugar,
                    'firma_receptor_nombre' => $row['nombre'].' '.$row['apellidos'],
                ]);

                //-------------------    RECIBO DEVOLUCION ----------------------------------------
                    $serieSlug = $this->slug($row['num_serie'] ?? 'sin_serie');
                    $cursoSlug = $this->slug($row['curso'] ?? 'sin_curso');
                    $filename  = "devolucion_{$serieSlug}_{$cursoSlug}_{$hid}.pdf";

                    $pdf   = PdfService::renderTemplate($this->tpl('devolucion'), $data);
                    $saved = PdfService::savePdf($pdf, BASE_PATH . "/storage/recibos", $filename);
                    DB::pdo()->prepare("UPDATE handovers SET recibo_pdf_path=? WHERE id=?")->execute([$saved, $hid]);


                DB::pdo()->commit();
            } catch (\Throwable $e) {
                DB::pdo()->rollBack(); throw $e;
            }

            header('Location: ' . \url('handovers/index')); exit;
        }

        // GET: portátiles en préstamo (último mov = entrega) + ubicaciones
        $prestados = DB::pdo()->query("
            SELECT l.id, l.num_serie, p.nombre, p.apellidos
            FROM laptops l
            JOIN handovers h ON h.laptop_id = l.id
            JOIN people p    ON p.id = h.person_id
            WHERE l.estado='prestado' AND h.tipo='entrega'
              AND h.id = (
                 SELECT h2.id FROM handovers h2
                 WHERE h2.laptop_id=l.id
                 ORDER BY h2.fecha DESC, h2.id DESC LIMIT 1
              )
            ORDER BY l.num_serie
        ")->fetchAll();

        $locations = Location::all();
        return view('handovers/devolucion', compact('prestados','locations'));
    }

    // --------------------------    IMPRESION DE RECIBOS ----------------------------------------
    private function slug(string $s): string {
    // pasa acentos a ASCII (si iconv no está, simplemente quita no-alfanuméricos)
    if (function_exists('iconv')) {
        $s2 = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($s2 !== false) $s = $s2;
    }
    $s = preg_replace('/[^A-Za-z0-9]+/', '_', $s);
    $s = trim($s, '_');
    return strtolower($s) ?: 'sin_nombre';
    }

}
