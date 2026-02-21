<?php
namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class ExcelService {

  /** Limpia cualquier buffer abierto para evitar “headers already sent” */
  private static function cleanOutputBuffers(): void {
    while (ob_get_level() > 0) { ob_end_clean(); }
  }

  /** Envía cabeceras estándar de descarga XLSX */
  private static function sendXlsxHeaders(string $filename): void {
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Cache-Control: max-age=0, no-cache, no-store, must-revalidate');
    header('Pragma: public');
    header('Expires: 0');
  }

  /** (Opcional) Sanea nombre de archivo */
  public static function safeFilename(string $s): string {
    $s = preg_replace('/[^A-Za-z0-9._-]+/', '_', $s);
    return trim($s, '_') ?: 'reporte.xlsx';
  }

  public static function downloadXlsx(string $filename, array $headers, array $rows): void {
    $ss = new Spreadsheet();
    $sheet = $ss->getActiveSheet();
    $sheet->setTitle('Datos');

    // Cabecera
    $sheet->fromArray($headers, null, 'A1');
    $lastCol = Coordinate::stringFromColumnIndex(count($headers));
    $sheet->getStyle('A1:'.$lastCol.'1')->getFont()->setBold(true);

    // Datos
    if (!empty($rows)) { $sheet->fromArray($rows, null, 'A2'); }

    // Estética
    for ($i = 1; $i <= count($headers); $i++) {
      $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
    }
    $sheet->freezePane('A2');
    $sheet->setAutoFilter('A1:'.$lastCol.$sheet->getHighestRow());

    // Descargar
    self::cleanOutputBuffers();
    self::sendXlsxHeaders(self::safeFilename($filename));
    $writer = new Xlsx($ss);
    $writer->save('php://output');
    exit;
  }

  public static function downloadCourseReport(string $filename, array $detailHeaders, array $detailRows, array $summaryPairs): void {
    $ss = new Spreadsheet();

    // --- Hoja 1: Detalle ---
    $sheet1 = $ss->getActiveSheet();
    $sheet1->setTitle('Detalle');
    $sheet1->fromArray($detailHeaders, null, 'A1');
    if (!empty($detailRows)) { $sheet1->fromArray($detailRows, null, 'A2'); }
    $lastCol1 = Coordinate::stringFromColumnIndex(count($detailHeaders));
    $sheet1->getStyle('A1:'.$lastCol1.'1')->getFont()->setBold(true);
    for ($i=1; $i<=count($detailHeaders); $i++) {
      $sheet1->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
    }
    $sheet1->freezePane('A2');
    $sheet1->setAutoFilter('A1:'.$lastCol1.$sheet1->getHighestRow());

    // --- Hoja 2: Resumen ---
    $sheet2 = $ss->createSheet();
    $sheet2->setTitle('Resumen');
    $sheet2->fromArray([['Métrica','Valor']], null, 'A1');
    if (!empty($summaryPairs)) { $sheet2->fromArray($summaryPairs, null, 'A2'); }
    $sheet2->getStyle('A1:B1')->getFont()->setBold(true);
    $sheet2->getColumnDimension('A')->setAutoSize(true);
    $sheet2->getColumnDimension('B')->setAutoSize(true);
    $sheet2->freezePane('A2');
    $sheet2->setAutoFilter('A1:B'.$sheet2->getHighestRow());

    // Descargar
    self::cleanOutputBuffers();
    self::sendXlsxHeaders(self::safeFilename($filename));
    $writer = new Xlsx($ss);
    $writer->save('php://output');
    exit;
  }
}
