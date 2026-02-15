<?php
require_once 'auth.php';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Manual de usuario</h1>
        <div class="text-muted small">Inventario IT</div>
    </div>

    <div class="card mb-3 shadow-sm">
        <div class="card-body">
            <p class="mb-2">
                Esta guía explica el uso diario de la aplicación: alta y gestión de equipos, móviles/SIM, redes, averías,
                verificaciones, reportes y recibos.
            </p>
            <p class="mb-0 text-muted small">
                Consejo: usa la barra superior para moverte por módulos y la opción de búsqueda/filtros en cada listado.
            </p>
        </div>
    </div>

    <div class="card mb-3 shadow-sm">
        <div class="card-header"><strong>1. Acceso y menú principal</strong></div>
        <div class="card-body">
            <ul class="mb-0">
                <li>Inicia sesión con tu TIP y contraseña.</li>
                <li>En la barra superior tienes los módulos: Equipos, Redes, Móviles/SIMS, Material, Averías, Revistas, Administración y Recibos.</li>
                <li>Tu usuario y rol aparecen arriba a la derecha.</li>
            </ul>
        </div>
    </div>

    <div class="card mb-3 shadow-sm">
        <div class="card-header"><strong>2. Gestión de equipos</strong></div>
        <div class="card-body">
            <ul class="mb-0">
                <li><strong>Alta:</strong> entra en Equipos y pulsa nuevo equipo. Completa tipo, etiqueta, serie, ubicación, departamento, sección, estado y usuario si procede.</li>
                <li><strong>Entrada de equipo nuevo:</strong> cuando llega un equipo nuevo, regístralo inicialmente en estado <strong>Almacén</strong>. En el momento de asignarlo a una unidad, completa departamento, sección, ubicación y usuario; cambia el estado a <strong>Activo</strong> y genera el recibo de movimiento.</li>
                <li><strong>Edición:</strong> desde la ficha del equipo puedes cambiar datos, estado, red/IP, monitores y relaciones PTI.</li>
                <li><strong>PTI:</strong> los equipos PTI permiten asignar SIM y DOCK de forma opcional.</li>
                <li><strong>Ficha:</strong> en detalle verás datos técnicos, relaciones (SIM/DOCK/monitores), IP y acciones rápidas. Si hay SIM asignada en un PTI, su etiqueta enlaza a la ficha de esa SIM.</li>
                <li><strong>Renovación:</strong> desde la ficha del equipo puedes iniciar la renovación para crear/asociar el nuevo equipo, trasladar relaciones cuando aplique y generar el recibo de renovación.</li>
                <li><strong>Estado visual:</strong> en listados y fichas, el estado se muestra con colores para identificar rápidamente activo, averiado, baja, almacén, prestado o privado.</li>
            </ul>
        </div>
    </div>

    <div class="card mb-3 shadow-sm">
        <div class="card-header"><strong>3. Redes e IPs</strong></div>
        <div class="card-body">
            <ul class="mb-0">
                <li><strong>Control IPs:</strong> visualiza estados y disponibilidad de direcciones.</li>
                <li><strong>Gestión redes:</strong> crea y edita redes (segmento, máscara, gateway, etc.).</li>
                <li><strong>Buscar IP:</strong> localiza rápidamente qué equipo usa una IP concreta.</li>
            </ul>
        </div>
    </div>

    <div class="card mb-3 shadow-sm">
        <div class="card-header"><strong>4. Móviles y SIM</strong></div>
        <div class="card-body">
            <ul class="mb-0">
                <li>Gestiona teléfonos y tarjetas SIM desde su menú específico.</li>
                <li>Asigna, cambia o libera SIMs en teléfonos según disponibilidad.</li>
                <li>Consulta historial de SIM y operaciones de renovación cuando corresponda.</li>
                <li>Desde la ficha del teléfono puedes renovar para crear/asociar el nuevo terminal, mantener la trazabilidad de la asignación y generar el recibo de renovación.</li>
            </ul>
        </div>
    </div>

    <div class="card mb-3 shadow-sm">
        <div class="card-header"><strong>5. Averías</strong></div>
        <div class="card-body">
            <ul class="mb-0">
                <li>Abre una avería desde el equipo o desde el listado de averías.</li>
                <li>Registra tipo, descripción y datos de seguimiento.</li>
                <li>Cierra la avería cuando esté resuelta y genera el parte imprimible si es necesario.</li>
            </ul>
        </div>
    </div>

    <div class="card mb-3 shadow-sm">
        <div class="card-header"><strong>6. Revistas y verificaciones</strong></div>
        <div class="card-body">
            <ul class="mb-0">
                <li>En <strong>Revistas</strong> puedes registrar verificaciones rápidas de inventario.</li>
                <li>Usa filtros por ubicación/departamento/sección/estado y el buscador general (serie, etiqueta, IP, etc.).</li>
                <li>Desde <strong>Administración &gt; Reportes verificaciones</strong> puedes agrupar datos y descargar en CSV, Excel o PDF.</li>
            </ul>
        </div>
    </div>

    <div class="card mb-3 shadow-sm">
        <div class="card-header"><strong>7. Recibos y firma</strong></div>
        <div class="card-body">
            <ul class="mb-0">
                <li>Consulta recibos de movimientos y renovaciones desde el menú <strong>Recibos</strong>.</li>
                <li>Los recibos se pueden imprimir/guardar en PDF.</li>
                <li>Cuando aplica, puedes gestionar firma digital desde los enlaces de firma.</li>
            </ul>
        </div>
    </div>

    <div class="card mb-3 shadow-sm">
        <div class="card-header"><strong>8. Administración y catálogos</strong></div>
        <div class="card-body">
            <ul class="mb-0">
                <li>Gestiona maestros: usuarios, tipos de equipo, servicios, ubicaciones, departamentos y secciones.</li>
                <li>Mantener estos catálogos actualizados mejora la calidad de datos en toda la aplicación.</li>
            </ul>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header"><strong>9. Buenas prácticas</strong></div>
        <div class="card-body">
            <ul class="mb-0">
                <li>Antes de crear un equipo, revisa si ya existe por número de serie o etiqueta.</li>
                <li>Cuando cambies estado a Activo o Prestado, informa siempre usuario y ubicación actual.</li>
                <li>Registra notas útiles (incidencias, cambios importantes, particularidades).</li>
                <li>Usa verificaciones periódicas para mantener inventario y ubicación al día.</li>
            </ul>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
