<?php
namespace App\Controllers;

use App\Models\DB;
use App\Services\PdfService;
use PDO;

// usa los nombres de archivo que tienes en /public/assets/img/
$logoIzq = PdfService::fileUrl(BASE_PATH . '/public/assets/img/logo_izq.png');
$logoDer = PdfService::fileUrl(BASE_PATH . '/public/assets/img/logo_der.png');

$data['logo_izq'] = $logoIzq;
$data['logo_der'] = $logoDer;

class ReceiptsController {
    private function dataEntregaDevolucion(int $handoverId): ?array {
        $sql = "SELECT h.id,h.tipo,h.fecha,h.observaciones, h.recibo_pdf_path,
                       p.nombre,p.apellidos,p.dni,p.tip,p.telefono,p.email,
                       l.num_serie, c.nombre AS curso
                FROM handovers h
                JOIN people p ON p.id=h.person_id
                JOIN laptops l ON l.id=h.laptop_id
                LEFT JOIN courses c ON c.id=h.course_id
                WHERE h.id=?";
        $st = DB::pdo()->prepare($sql);
        $st->execute([$handoverId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // Muestra el PDF guardado; si no existe, lo regenera al vuelo
    public function ver() {
        $id = (int)($_GET['id'] ?? 0);
        $row = $this->dataEntregaDevolucion($id);
        if (!$row) { http_response_code(404); echo "No encontrado"; return; }
        $path = $row['recibo_pdf_path'] ?? '';
        if (!$path || !file_exists($path)) {
            return $row['tipo'] === 'entrega' ? $this->entrega() : $this->devolucion();
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="recibo_'.$row['tipo'].'_'.$id.'.pdf"');
        readfile($path);
    }

    public function entrega() {
        $id = (int)($_GET['id'] ?? 0);
        $row = $this->dataEntregaDevolucion($id);
        if (!$row || $row['tipo'] !== 'entrega') { http_response_code(404); echo "No encontrado"; return; }

        $data = [
            'curso' => $row['curso'] ?? '',
            'nombre' => $row['nombre'],
            'apellidos' => $row['apellidos'],
            'dni' => $row['dni'],
            'tip' => $row['tip'],
            'telefono' => $row['telefono'],
            'email' => $row['email'],
            'equipo_descripcion' => 'Portátil',
            'num_serie' => $row['num_serie'],
            'fecha_entrega' => $row['fecha'],
            'lugar' => '',
            'firma_receptor_nombre' => $row['nombre'].' '.$row['apellidos'],
            'logo_izq' => PdfService::fileUrl(BASE_PATH . '/public/assets/img/logo_izq.png'),
            'logo_der' => PdfService::fileUrl(BASE_PATH . '/public/assets/img/logo_der.png'),
        ];

        $tpl = BASE_PATH . "/recibos_templates/entrega.html";
        $pdf = PdfService::renderTemplate($tpl, $data);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="recibo_entrega_'.$id.'.pdf"');
        echo $pdf;
    }

    public function devolucion() {
        $id = (int)($_GET['id'] ?? 0);
        $row = $this->dataEntregaDevolucion($id);
        if (!$row || $row['tipo'] !== 'devolucion') { http_response_code(404); echo "No encontrado"; return; }

        $data = [
            'curso' => $row['curso'] ?? '',
            'nombre' => $row['nombre'],
            'apellidos' => $row['apellidos'],
            'dni' => $row['dni'],
            'tip' => $row['tip'],
            'telefono' => $row['telefono'],
            'email' => $row['email'],
            'equipo_descripcion' => 'Portátil',
            'num_serie' => $row['num_serie'],
            'fecha_devolucion' => $row['fecha'],
            'lugar' => '',
            'firma_receptor_nombre' => $row['nombre'].' '.$row['apellidos'],
            'logo_izq' => PdfService::fileUrl(BASE_PATH . '/public/assets/img/logo_izq.png'),
            'logo_der' => PdfService::fileUrl(BASE_PATH . '/public/assets/img/logo_der.png'),
        ];

        $tpl = BASE_PATH . "/recibos_templates/devolucion.html";
        $pdf = PdfService::renderTemplate($tpl, $data);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="recibo_devolucion_'.$id.'.pdf"');
        echo $pdf;
    }

    // (opcional) Declaración de responsabilidad
    public function declaracion() {
        $data = [
            'curso' => $_GET['curso'] ?? '',
            'nombre' => $_GET['nombre'] ?? '',
            'apellidos' => $_GET['apellidos'] ?? '',
            'dni' => $_GET['dni'] ?? '',
            'tip' => $_GET['tip'] ?? '',
            'equipo_descripcion' => $_GET['equipo'] ?? 'Portátil',
            'num_serie' => $_GET['num_serie'] ?? '',
            'fecha_documento' => $_GET['fecha'] ?? date('Y-m-d'),
            'lugar' => $_GET['lugar'] ?? '',
            'firma_receptor_nombre' => ($_GET['nombre'] ?? '') . ' ' . ($_GET['apellidos'] ?? ''),
            'logo_izq' => PdfService::fileUrl(BASE_PATH . '/public/assets/img/logo_izq.png'),
            'logo_der' => PdfService::fileUrl(BASE_PATH . '/public/assets/img/logo_der.png'),
        ];
        $tpl = BASE_PATH . "/recibos_templates/declaracion_responsabilidad.html";
        $pdf = PdfService::renderTemplate($tpl, $data);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="declaracion_responsabilidad.pdf"');
        echo $pdf;
    }

    /** Listado de todos los PDFs guardados en storage/recibos */
    public function index() {
        $dir = BASE_PATH . '/storage/recibos';
        $files = [];
        if (is_dir($dir)) {
            foreach (glob($dir.'/*.pdf') ?: [] as $p) {
                $files[] = [
                    'name'  => basename($p),
                    'size'  => filesize($p),
                    'mtime' => filemtime($p),
                ];
            }
            usort($files, fn($a,$b) => $b['mtime'] <=> $a['mtime']); // más recientes primero
        }
        return view('receipts/index', compact('files'));
    }

    /** Sirve el PDF (inline) de forma segura */
    public function download() {
        $name = basename($_GET['f'] ?? '');
        if (!$name || !preg_match('/\.pdf$/i', $name)) {
            http_response_code(400); echo 'Archivo no válido'; return;
        }
        $path = BASE_PATH . '/storage/recibos/' . $name;
        if (!is_file($path)) { http_response_code(404); echo 'No existe'; return; }

        header('Content-Type: application/pdf');
        header('Content-Length: '.filesize($path));
        header('Content-Disposition: inline; filename="'.$name.'"'); // o attachment para descargar
        readfile($path); exit;
    }
}
