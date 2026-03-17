<?php
namespace App\Controllers;
use App\Models\{Course, DB};

class CoursesController {
  public function index() {
  $show = $_GET['show'] ?? 'active'; // active|archived|all
  $where = $show==='active' ? "WHERE activo=1" : ($show==='archived' ? "WHERE activo=0" : "");
  $page = max(1, (int)($_GET['page'] ?? 1));
  $perPage = 25; $offset = ($page-1)*$perPage;

  $total = (int)DB::pdo()->query("SELECT COUNT(*) FROM courses $where")->fetchColumn();
  $st = DB::pdo()->prepare("SELECT * FROM courses $where ORDER BY fecha_inicio DESC, nombre LIMIT ? OFFSET ?");
  $st->bindValue(1, $perPage, \PDO::PARAM_INT);
  $st->bindValue(2, $offset,  \PDO::PARAM_INT);
  $st->execute();
  $courses = $st->fetchAll();

  return view('courses/index', compact('courses','show','total','page','perPage'));
}


  public function create() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      csrf_check();
      Course::create([
        'nombre'       => trim($_POST['nombre']),
        'fecha_inicio' => $_POST['fecha_inicio'] ?? null,
        'fecha_fin'    => $_POST['fecha_fin'] ?? null,
      ]);
      header('Location: ' . url('courses/index')); exit;
    }
    return view('courses/create');
  }

  public function edit() {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { http_response_code(400); echo "ID requerido"; return; }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      csrf_check();
      Course::update($id, [
        'nombre'       => trim($_POST['nombre']),
        'fecha_inicio' => $_POST['fecha_inicio'] ?? null,
        'fecha_fin'    => $_POST['fecha_fin'] ?? null,
      ]);
      header('Location: ' . url('courses/index')); exit;
    }
    $course = Course::find($id);
    if (!$course) { http_response_code(404); echo "Curso no encontrado"; return; }
    return view('courses/edit', compact('course'));
  }

  public function archive() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo "Método no permitido"; return; }
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { http_response_code(400); echo "ID requerido"; return; }
    Course::setActivo($id, false);
    header('Location: ' . url('courses/index')); exit;
  }

  public function restore() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo "Método no permitido"; return; }
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { http_response_code(400); echo "ID requerido"; return; }
    Course::setActivo($id, true);
    header('Location: ' . url('courses/index')); exit;
  }
}
