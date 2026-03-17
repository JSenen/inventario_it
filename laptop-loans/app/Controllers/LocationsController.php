<?php
namespace App\Controllers;

use App\Models\Location;

class LocationsController {
  public function index() {
    $locations = Location::all();
    return view('locations/index', compact('locations'));
  }

  public function create() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      csrf_check();
      Location::create([
        'nombre'      => trim($_POST['nombre']),
        'tipo'        => $_POST['tipo'] ?? 'Otro',
        'descripcion' => $_POST['descripcion'] ?? null,
      ]);
      header('Location: ' . url('locations/index')); exit;
    }
    return view('locations/create');
  }

  public function edit() {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { http_response_code(400); echo "ID requerido"; return; }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      csrf_check();
      Location::update($id, [
        'nombre'      => trim($_POST['nombre']),
        'tipo'        => $_POST['tipo'] ?? 'Otro',
        'descripcion' => $_POST['descripcion'] ?? null,
      ]);
      header('Location: ' . url('locations/index')); exit;
    }

    $location = Location::find($id);
    if (!$location) { http_response_code(404); echo "Ubicación no encontrada"; return; }
    return view('locations/edit', compact('location'));
  }

  public function delete() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo "Método no permitido"; return; }
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { http_response_code(400); echo "ID requerido"; return; }

    // Al tener ON DELETE SET NULL en FKs, es seguro
    Location::delete($id);
    header('Location: ' . url('locations/index')); exit;
  }
}
