<?php
namespace App\Controllers;

use App\Models\DB;
use App\Models\Course;
use App\Services\ExcelService;
use PDO;

class ExportsController {

    // /?r=exports/course&id={course_id}
    public function course() {
    $courseId = (int)($_GET['course_id'] ?? $_GET['id'] ?? 0);
    if (!$courseId) { http_response_code(400); echo "ID de curso requerido"; return; }

    // Curso
    $stc = DB::pdo()->prepare("SELECT * FROM courses WHERE id=?");
    $stc->execute([$courseId]);
    $course = $stc->fetch(PDO::FETCH_ASSOC);
    if (!$course) { http_response_code(404); echo "Curso no encontrado"; return; }

    // Último movimiento por (laptop, curso) -> estado en el curso
    $sql = "
        SELECT h.id, h.tipo, h.fecha, h.observaciones,
               l.num_serie, l.marca, l.modelo, l.estado AS estado_global,
               p.nombre, p.apellidos, p.dni, p.tip, p.telefono, p.email
        FROM handovers h
        JOIN laptops  l ON l.id = h.laptop_id
        JOIN people   p ON p.id = h.person_id
        WHERE h.course_id = ?
          AND h.id = (
             SELECT h2.id FROM handovers h2
             WHERE h2.laptop_id = h.laptop_id
               AND h2.course_id = h.course_id
             ORDER BY h2.fecha DESC, h2.id DESC
             LIMIT 1
          )
        ORDER BY l.num_serie ASC
    ";
    $st = DB::pdo()->prepare($sql);
    $st->execute([$courseId]);
    $rowsDb = $st->fetchAll(PDO::FETCH_ASSOC);

    // Detalle (filas)
    $headers = [
        'Curso', 'Nº Serie', 'Marca', 'Modelo',
        'Nombre', 'Apellidos', 'DNI', 'TIP', 'Teléfono', 'Email',
        'Estado en el curso', 'Fecha último mov.', 'Observaciones',
        'Estado actual (global)'
    ];

    $rows = [];
    $totCurso = ['ENTREGADO'=>0, 'DEVUELTO'=>0];
    $totGlobal = []; // p.ej. ['prestado'=>n, 'disponible'=>m]

    foreach ($rowsDb as $r) {
        $estadoCurso = ($r['tipo'] === 'entrega') ? 'ENTREGADO' : 'DEVUELTO';
        $totCurso[$estadoCurso]++;

        $eg = strtolower($r['estado_global'] ?? '');
        if ($eg !== '') $totGlobal[$eg] = ($totGlobal[$eg] ?? 0) + 1;

        $rows[] = [
            $course['nombre'] ?? '',
            $r['num_serie'],
            $r['marca'],
            $r['modelo'],
            $r['nombre'],
            $r['apellidos'],
            $r['dni'],
            $r['tip'],
            $r['telefono'],
            $r['email'],
            $estadoCurso,
            $r['fecha'],
            $r['observaciones'],
            strtoupper($r['estado_global']),
        ];
    }

    // Resumen (pares Métrica/Valor)
    $summary = [
        ['Curso', $course['nombre'] ?? ("curso_".$courseId)],
        ['Fecha de exportación', date('Y-m-d H:i')],
        ['Total portátiles con movimiento en el curso', count($rowsDb)],
        ['— ENTREGADO (último mov. en el curso)', $totCurso['ENTREGADO']],
        ['— DEVUELTO (último mov. en el curso)', $totCurso['DEVUELTO']],
    ];
    // añadir estado global desglosado
    foreach ($totGlobal as $k=>$v) {
        $summary[] = ['Estado actual global — '.strtoupper($k), $v];
    }

    $safeName = preg_replace('/[^A-Za-z0-9_-]+/','_', $course['nombre'] ?? ('curso_'.$courseId));
    \App\Services\ExcelService::downloadCourseReport(
        "reporte_curso_{$safeName}.xlsx",
        $headers,
        $rows,
        $summary
    );
}

  public function summary() {
    $show = $_GET['show'] ?? 'all';

    // 1) Cursos (según filtro)
    $where = ''; $params = [];
    if ($show === 'active')   { $where = 'WHERE activo=1'; }
    elseif ($show === 'archived') { $where = 'WHERE activo=0'; }

    $stc = DB::pdo()->prepare("SELECT id,nombre,fecha_inicio,fecha_fin,activo FROM courses $where ORDER BY fecha_inicio DESC, id DESC");
    $stc->execute($params);
    $courses = $stc->fetchAll(PDO::FETCH_ASSOC);

    // 2) Contadores por curso (último movimiento por portátil dentro del curso)
    $stCounts = DB::pdo()->prepare("
      SELECT
        SUM(tipo='entrega')   AS entregados,
        SUM(tipo='devolucion') AS devueltos
      FROM (
        SELECT h.tipo
        FROM handovers h
        WHERE h.course_id = ?
          AND h.id = (
            SELECT h2.id FROM handovers h2
            WHERE h2.course_id = h.course_id AND h2.laptop_id = h.laptop_id
            ORDER BY h2.fecha DESC, h2.id DESC
            LIMIT 1
          )
      ) t
    ");

    $headers = ['Curso','Inicio','Fin','Activo','Equipos con movimiento','Entregados','Devueltos'];
    $rows = [];

    foreach ($courses as $c) {
      $stCounts->execute([(int)$c['id']]);
      $cnt = $stCounts->fetch(PDO::FETCH_ASSOC) ?: ['entregados'=>0,'devueltos'=>0];
      $ent = (int)($cnt['entregados'] ?? 0);
      $dev = (int)($cnt['devueltos'] ?? 0);
      $mov = $ent + $dev;

      $rows[] = [
        $c['nombre'],
        $c['fecha_inicio'],   // deja ISO: mejor para Excel
        $c['fecha_fin'],
        ((int)$c['activo']===1 ? 'Sí' : 'No'),
        $mov, $ent, $dev,
      ];
    }

    $fname = 'resumen_cursos_'.date('Ymd_His').'.xlsx';
    \App\Services\ExcelService::downloadXlsx($fname, $headers, $rows);
  }
}

