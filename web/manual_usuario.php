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
                <li>En el menú lateral tienes los módulos: Equipos, Redes, Móviles/SIMS, Material, Averías, Revistas, Activos por TIP, Administración y Recibos.</li>
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
                <li><strong>Traslado interno:</strong> si cambias ubicación, departamento o sección, el sistema pide confirmación antes de guardar y genera dos recibos de movimiento: <strong>baja</strong> en destino anterior y <strong>alta</strong> en destino nuevo.</li>
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
                <li>En SIM puedes registrar: etiqueta, número, número corto, ICCID, operador, PIN/PUK, estado y observaciones.</li>
                <li>Las pantallas de <strong>Nueva SIM</strong> y <strong>Editar SIM</strong> están organizadas por bloques (identificación/estado, línea/operador, seguridad/notas) para agilizar la carga de datos.</li>
                <li>El listado de SIM incluye filtros por estado y operador, y permite exportar resultados.</li>
                <li>Asigna, cambia o libera SIMs en teléfonos según disponibilidad.</li>
                <li>Si una SIM está vinculada, en su ficha tendrás acceso directo al teléfono o al equipo PTI asociado.</li>
                <li>Desde la ficha del teléfono puedes renovar para crear/asociar el nuevo terminal, mantener la trazabilidad de la asignación y generar el recibo de renovación.</li>
                <li>En el parte de entrega de teléfono, si el terminal procede de una renovación, se muestra también el teléfono retirado y dado de baja.</li>
            </ul>
        </div>
    </div>

    <div class="card mb-3 shadow-sm">
        <div class="card-header"><strong>5. Material / fungibles</strong></div>
        <div class="card-body">
            <ul class="mb-0">
                <li>En <strong>Nuevo movimiento de stock</strong>, al asociar a equipo tienes un buscador por etiqueta, hostname, serie, usuario, tipo, marca, modelo, departamento y ubicación.</li>
                <li>El desplegable de equipos se organiza en <strong>Con etiqueta</strong> y <strong>Sin etiqueta</strong>, y al seleccionar uno con etiqueta se muestra un indicador visual de la etiqueta elegida.</li>
            </ul>
        </div>
    </div>

    <div class="card mb-3 shadow-sm">
        <div class="card-header"><strong>6. Activos por TIP</strong></div>
        <div class="card-body">
            <ul class="mb-0">
                <li>Desde <strong>Activos por TIP</strong> puedes ver en una sola pantalla todo lo asociado a un usuario: equipos, teléfonos y SIMs activas.</li>
                <li>Tienes accesos directos a esta vista desde fichas de equipo, teléfono y SIM mediante el botón <strong>Ver todo por TIP</strong>.</li>
                <li>Incluye un bloque de <strong>TIPs destacados</strong> para acceso rápido; el valor <strong>VARIOS</strong> se excluye del ranking.</li>
            </ul>
        </div>
    </div>

    <div class="card mb-3 shadow-sm">
        <div class="card-header"><strong>7. Averías</strong></div>
        <div class="card-body">
            <ul class="mb-0">
                <li>Abre una avería desde el equipo o desde el listado de averías.</li>
                <li>Registra tipo, descripción y datos de seguimiento.</li>
                <li>Cierra la avería cuando esté resuelta y genera el parte imprimible si es necesario.</li>
            </ul>
        </div>
    </div>

    <div class="card mb-3 shadow-sm">
        <div class="card-header"><strong>8. Revistas y verificaciones</strong></div>
        <div class="card-body">
            <ul class="mb-0">
                <li>En <strong>Revistas</strong> puedes registrar verificaciones rápidas de inventario.</li>
                <li>Usa filtros por ubicación/departamento/sección/estado y el buscador general (serie, etiqueta, IP, etc.).</li>
                <li>Desde <strong>Administración &gt; Reportes verificaciones</strong> puedes agrupar datos y descargar en CSV, Excel o PDF.</li>
            </ul>
        </div>
    </div>

    <div class="card mb-3 shadow-sm">
        <div class="card-header"><strong>9. Recibos y firma</strong></div>
        <div class="card-body">
            <ul class="mb-0">
                <li>Consulta recibos de movimientos y renovaciones desde el menú <strong>Recibos</strong>.</li>
                <li>En listados de renovaciones y movimientos se muestra el histórico completo con buscador y paginación.</li>
                <li>Los recibos se pueden imprimir/guardar en PDF.</li>
                <li>En renovaciones de teléfono, la columna SIM muestra la etiqueta/número de la SIM trasladada con enlace directo a su ficha.</li>
                <li>Cuando aplica, puedes gestionar firma digital desde los enlaces de firma.</li>
                <li>En renovaciones/movimientos puede mostrarse opción de envío por correo al destino si está configurado.</li>
            </ul>
        </div>
    </div>

    <div class="card mb-3 shadow-sm">
        <div class="card-header"><strong>10. Administración y catálogos</strong></div>
        <div class="card-body">
            <ul class="mb-0">
                <li>Gestiona maestros: usuarios, tipos de equipo, servicios, ubicaciones, departamentos y secciones.</li>
                <li>Mantener estos catálogos actualizados mejora la calidad de datos en toda la aplicación.</li>
                <li>Revisa el correo de cada sección para habilitar envíos automáticos de recibos cuando corresponda.</li>
            </ul>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header"><strong>11. Buenas prácticas</strong></div>
        <div class="card-body">
            <ul class="mb-0">
                <li>Aunque la aplicación lo autogestiona, antes de crear un equipo, revisa si ya existe por número de serie o etiqueta.</li>
                <li>Cuando cambies estado a Activo o Prestado, informa siempre usuario y ubicación actual.</li>
                <li>Registra notas útiles (incidencias, cambios importantes, particularidades).</li>
                <li>Usa verificaciones periódicas para mantener inventario y ubicación al día.</li>
            </ul>
        </div>
    </div>

    <div class="card mb-3 shadow-sm">
        <div class="card-header"><strong>12. Copias de seguridad y traslado a otro equipo</strong></div>
        <div class="card-body">
            <ul class="mb-0">
                <li>Antes de mover el sistema a otro equipo, genera siempre dos copias SQL: <code>inventario_it.sql</code> y <code>laptop_loans.sql</code>.</li>
                <li>Para que la imagenes de los dispòsitivos contninuen. No olvide copiar tambien el directorio <code>/var/www/html/web/uploads/equipo</code> al nuevo equipo.</li>
                <li>Exporta las imagenes Docker del entorno y copia el archivo resultante junto a los SQL al nuevo equipo.</li>
                <li>En el nuevo equipo, carga las imagenes Docker, arranca <code>docker compose up -d</code> y restaura las dos bases.</li>
                <li>Tras crear <code>laptop_loans</code>, concede permisos a <code>inventario_user</code> para operar sobre esa base con este GRANT:</li>
                <li><code>GRANT ALL PRIVILEGES ON laptop_loans.* TO 'inventario_user'@'%'; FLUSH PRIVILEGES;</code></li>
            </ul>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
