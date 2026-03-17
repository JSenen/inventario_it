<?php
namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

class PdfService {
    private static function normalizeKey(string $k): string {
        $k = trim($k);
        $k = function_exists('mb_strtolower') ? mb_strtolower($k, 'UTF-8') : strtolower($k);
        if (function_exists('iconv')) {
            $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $k);
            if ($t !== false) $k = $t;
        }
        $k = preg_replace('/[^a-z0-9]+/', '_', $k);
        return trim(preg_replace('/_+/', '_', $k), '_');
    }

    public static function renderTemplate(string $templatePath, array $data, bool $debugSaveHtml=false, ?string $debugOutPath=null): string {
        $html = file_get_contents($templatePath);

        // normaliza claves y no escapa URLs de imagen
        $vars = [];
        foreach ($data as $k=>$v) $vars[self::normalizeKey((string)$k)] = (string)$v;

        $cb = function(array $m) use ($vars) {
            $key = self::normalizeKey($m[1]);
            $val = $vars[$key] ?? '';
            if (preg_match('~^(file|https?)://~i', $val)) return $val; // no escapar logos/urls
            return htmlspecialchars($val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };

        // {{campo}}, [[campo]] y {campo}
        $html = preg_replace_callback('/\{\{\s*([^}]+?)\s*\}\}/u', $cb, $html);
        $html = preg_replace_callback('/\[\[\s*([^\]]+?)\s*\]\]/u', $cb, $html);
        $html = preg_replace_callback('/\{\s*([A-Za-z0-9_]+)\s*\}/u',   $cb, $html);

        if ($debugSaveHtml && $debugOutPath) @file_put_contents($debugOutPath, $html);

        $opts = new Options();
        $opts->setIsRemoteEnabled(true);       // permitir http/file
        $opts->setIsHtml5ParserEnabled(true);
        $opts->setChroot(BASE_PATH);           // ✅ permite leer ficheros bajo el proyecto

        $dompdf = new Dompdf($opts);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        // // Guarda SIEMPRE una copia para depuración
        //     $defaultDebug = BASE_PATH . '/storage/recibos/__last_rendered.html';
        //     @mkdir(dirname($defaultDebug), 0775, true);
        //     @file_put_contents($defaultDebug, $html);

        //     // Si además pasas ruta específica
        //     if ($debugSaveHtml && $debugOutPath) {
        //         @file_put_contents($debugOutPath, $html);
        //     }
         if (defined('DEBUG_PDF') && DEBUG_PDF && $debugOutPath) {
            @file_put_contents($debugOutPath, $html);
        }

        return $dompdf->output();

       

    }

    public static function savePdf(string $binary, string $dir, string $filename): string {
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $path = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($path, $binary);
        return $path;
    }

    /** Devuelve file:///C:/... válido para Dompdf en Windows */
    public static function fileUrl(string $path): string {
        $abs = realpath($path) ?: $path;
        $abs = str_replace('\\', '/', $abs);
        if (preg_match('~^[A-Za-z]:/~', $abs)) {  // C:/...
            $abs = '/' . $abs;                     // /C:/...
        }
        return 'file://' . $abs;                   // -> file:///C:/...
    }


    public static function imgDataUri(string $path): string {
    $real = realpath($path) ?: $path;
    if (!is_file($real)) return '';
    // Detecta MIME
    $mime = 'image/png';
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        if ($fi) { $m = finfo_file($fi, $real); if ($m) $mime = $m; finfo_close($fi); }
    } else {
        $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
        $mime = $ext === 'jpg' || $ext === 'jpeg' ? 'image/jpeg' :
                ($ext === 'gif' ? 'image/gif' :
                ($ext === 'svg' ? 'image/svg+xml' : 'image/png'));
    }
    $data = @file_get_contents($real);
    if ($data === false) return '';
    return 'data:'.$mime.';base64,'.base64_encode($data);
}

}
