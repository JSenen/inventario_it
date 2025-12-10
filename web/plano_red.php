<?php
require_once 'auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">
            <i class="bi bi-diagram-3"></i> Plano físico de la red
        </h3>

        <div class="btn-group btn-group-sm" role="group" aria-label="Controles de zoom">
            <button type="button" class="btn btn-outline-secondary" id="btnZoomOut">
                <i class="bi bi-zoom-out"></i>
            </button>
            <button type="button" class="btn btn-outline-secondary" id="btnZoomReset">
                <i class="bi bi-aspect-ratio"></i>
            </button>
            <button type="button" class="btn btn-outline-secondary" id="btnZoomIn">
                <i class="bi bi-zoom-in"></i>
            </button>
            <span class="ms-2 small text-muted" id="zoomValue">100%</span>
        </div>
    </div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="index.php" class="btn btn-secondary">Volver al listado</a>
</div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div id="planoWrapper"
                 style="width:100%; height:80vh; overflow:auto; border:1px solid #ccc;">
                <!-- Contenedor escalable -->
                <div id="planoInner" style="transform-origin: 0 0;">
                    <object id="planoSvg"
                            data="assets/plano_racks_zona.drawio.svg"
                            type="image/svg+xml"
                            style="width:100%; height:100%;">
                    </object>
                </div>
            </div>
            <small class="text-muted d-block mt-2">
                Usa el ratón para desplazarte y los botones para acercar/alejar.
            </small>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    let scale = 1.0;
    const step = 0.1;
    const minScale = 0.3;
    const maxScale = 3.0;

    const planoInner = document.getElementById('planoInner');
    const zoomValue  = document.getElementById('zoomValue');

    function aplicarZoom() {
        planoInner.style.transform = 'scale(' + scale + ')';
        if (zoomValue) {
            zoomValue.textContent = Math.round(scale * 100) + '%';
        }
    }

    document.getElementById('btnZoomIn').addEventListener('click', function () {
        if (scale < maxScale) {
            scale += step;
            aplicarZoom();
        }
    });

    document.getElementById('btnZoomOut').addEventListener('click', function () {
        if (scale > minScale) {
            scale -= step;
            aplicarZoom();
        }
    });

    document.getElementById('btnZoomReset').addEventListener('click', function () {
        scale = 1.0;
        aplicarZoom();
        // Opcional: volver al origen
        const wrapper = document.getElementById('planoWrapper');
        wrapper.scrollTop = 0;
        wrapper.scrollLeft = 0;
    });

    // Aplicar zoom inicial
    aplicarZoom();
});
</script>

<!-- Enlace a inventario -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const obj = document.getElementById('planoSvg');

    obj.addEventListener('load', function () {
        const svgDoc = obj.contentDocument;
        if (!svgDoc) return;

        const links = svgDoc.querySelectorAll('a');

        links.forEach(a => {
            // En algunos SVGs el enlace está en xlink:href
            let href = a.getAttribute('xlink:href') || a.getAttribute('href');
            if (!href) return;

            // 1) Si viene con "https://app.diagrams.net/..." lo recortamos
            if (href.startsWith('https://app.diagrams.net/')) {
                href = href.replace('https://app.diagrams.net', '');
            }

            // 2) Si sigue siendo relativo (no empieza por http), lo hacemos absoluto a TU servidor
            if (!href.startsWith('http')) {
                // window.location.origin = "http://192.168.1.36" o lo que toque
                if (!href.startsWith('/')) {
                    href = '/' + href;
                }
                href = window.location.origin + href;
            }

            // 3) Escribimos de vuelta el href corregido y salimos del <object> al hacer clic
            a.setAttribute('href', href);
            a.setAttribute('target', '_top'); // abre en la página principal
        });
    });
});
</script>


<?php require_once __DIR__ . '/includes/footer.php'; ?>
