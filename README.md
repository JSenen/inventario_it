# Inventario IT

Aplicación web en PHP para la gestión interna de inventario IT: equipos, redes/IP, móviles/SIM, averías, materiales, verificaciones, recibos con firma y trazabilidad operativa.

## Estado actual del proyecto

Implementado:

- Gestión de equipos:
  - Alta, edición, ficha y baja lógica.
  - Flujo recomendado de alta/asignación: registrar equipos nuevos en `Almacén` y, al asignarlos a una unidad, completar datos de destino (departamento/sección/ubicación/usuario), pasar a `Activo` y emitir recibo de movimiento.
  - En edición, si se cambia `ubicación`, `departamento` o `sección`, el sistema pide confirmación y genera doble recibo de movimiento por traslado interno (`baja` + `alta`).
  - Asociación de IP principal.
  - Asociación de monitores (`pc_monitores`).
  - Renovación de equipos y generación de recibos.
  - Ficha de equipo con estado visual por color y acceso directo a SIM asociada en equipos PTI.
  - Renovación iniciable desde la ficha para transferir relaciones al nuevo equipo cuando aplica.
- PTI:
  - Asignación de SIM (`equipo_sim`).
  - Asignación de DOCK (`equipo_dock`).
- Móviles/SIM:
  - Altas, edición, historial y renovaciones de teléfonos.
  - Asignación/liberación/cambio de SIM en teléfono.
  - Gestión de SIM con `etiqueta`, `número`, `número corto`, `ICCID`, operador, estado y observaciones.
  - Filtros por estado y operador en listado de SIMs.
  - Exportación de SIMs (Excel), además de copia/CSV/impresión desde DataTables.
  - Ficha de teléfono con estado visual por color.
  - Ficha de SIM con acceso directo a teléfono o equipo PTI asignado.
  - Renovación iniciable desde la ficha para mantener trazabilidad y generar recibo.
  - Parte de entrega de teléfono ampliado: si el equipo actual proviene de una renovación, incluye el teléfono retirado y dado de baja.
- Redes:
  - Catálogo de redes.
  - Control de IP y búsqueda de IP.
- Averías:
  - Apertura/cierre/listado y parte imprimible.
- Material/fungibles:
  - Gestión de stock y movimientos.
- Verificaciones (revistas):
  - Registro de verificaciones por equipo.
  - Buscador general (etiqueta, número de serie, etc.).
  - Listado mostrando `Etiqueta / Número de serie`.
  - Reportes con filtros y agrupaciones (fechas, unidades, equipos).
  - Exportación en CSV, Excel (`.xls`) y vista imprimible PDF.
- Recibos:
  - Recibos de movimiento, renovación de equipo y renovación de teléfono.
  - En renovaciones de teléfono, la tabla de renovaciones muestra la SIM trasladada (etiqueta o número) con enlace directo a su ficha.
  - Listados de renovaciones y movimientos sin límite fijo de 500; se consulta histórico completo con búsqueda y paginación de DataTables.
  - Firma digital por token.
  - Envío opcional por correo a la sección con popup de confirmación.
  - Fallback a `mailto:` si falla el envío desde servidor.
- Administración:
  - Catálogos (usuarios, tipos, servicios, ubicaciones, departamentos, secciones).
  - Secciones con campo de correo electrónico.
- Manual de usuario:
  - `manual_usuario.php` accesible desde barra de navegación.
- Dashboard y logs de actividad.

## Stack técnico

- PHP 8.2 + Apache
- MariaDB 11
- Bootstrap 5
- DataTables (listados)
- Docker Compose para entorno local

## Estructura del repositorio

```text
inventario-it/
├── db/
│   └── inventario_schema.sql
├── web/
│   ├── *.php
│   ├── includes/
│   ├── assets/
│   ├── uploads/
│   └── vendor/
├── docker-compose.yml
└── README.md
```

## Puesta en marcha (Docker)

Requisitos:

- Docker
- Docker Compose

Pasos:

1. Levantar servicios:

```bash
docker compose up -d --build
```

2. Acceder a:

- App: `http://localhost:8080`
- phpMyAdmin: `http://localhost:8081`

3. Base de datos por defecto (`docker-compose.yml`):

- DB: `inventario_it`
- Usuario: `inventario_user`
- Password: `InventarioPass123!`
- Root: `rootpass123`

## Configuración de correo

El envío de correo de recibos usa `web/includes/mail_helper.php` (función nativa `mail()` de PHP).

Variables de entorno soportadas:

- `MAIL_ENABLED`: `1`/`0` para activar o desactivar envío real.
- `MAIL_FROM`: remitente (ej. `no-reply@inventario.local`).
- `MAIL_FROM_NAME`: nombre del remitente.

En el entorno Docker local actual está desactivado por defecto:

- `MAIL_ENABLED=0`

Esto permite probar el flujo funcional (popup, logs, fallback `mailto:`) sin depender de un servidor SMTP real.

## Autenticación

- Login contra tabla `usuarios`.
- La contraseña se valida con hash `sha3-256` (64 caracteres hex).
- `inventario_schema.sql` no deja un admin activo por defecto (insert comentado).

Ejemplo para generar hash:

```bash
php -r "echo hash('sha3-256', 'TuClaveSegura') . PHP_EOL;"
```

## Módulos principales (rutas)

- Dashboard: `web/dashboard.php`
- Equipos: `web/index.php`
- Nuevo equipo: `web/equipo_nuevo.php`
- Editar equipo: `web/equipo_editar.php`
- Ficha equipo: `web/equipo_ver.php`
- Redes/IP: `web/redes.php`, `web/redes_gestion.php`, `web/buscar_ip.php`
- Móviles/SIM: `web/telefonos.php`, `web/sims.php`
- Ficha SIM: `web/sims_ver.php`
- Averías: `web/averias_list.php`
- Materiales: `web/materiales.php`
- Verificaciones: `web/verificaciones.php`
- Reportes verificaciones: `web/verificaciones_reportes.php`
- Recibo movimiento: `web/recibo_movimiento.php`
- Recibo renovación equipo: `web/recibo_renovacion.php`
- Recibo renovación teléfono: `web/recibo_renovacion_telefono.php`
- Manual de usuario: `web/manual_usuario.php`
- Administración catálogos: `web/admin_catalogos.php`
- Logs (solo admin): `web/actividad_logs.php`

## Logging y auditoría

Helper central: `web/includes/logger.php`.

`actividad_logs.php` incluye:

- KPIs (hoy, 7 días, errores, usuarios únicos).
- Filtros por texto, usuario, acción, módulo, nivel y rango de fechas.
- Paginación en servidor y consultas eficientes.

Eventos destacados:

- Autenticación: `LOGIN_OK`, `LOGIN_FALLIDO`, `LOGOUT`.
- Exportaciones de verificaciones.
- Envío de correo de recibos y fallos:
  - `ENVIO_CORREO_RECIBO_MOV`
  - `ENVIO_CORREO_RECIBO_MOV_FALLO`
  - `ENVIO_CORREO_RECIBO_RENOV`
  - `ENVIO_CORREO_RECIBO_RENOV_FALLO`
  - `ENVIO_CORREO_RECIBO_RENOV_TEL`
  - `ENVIO_CORREO_RECIBO_RENOV_TEL_FALLO`

## Notas importantes

- Configuración DB actual en `web/config.php`.
- Varias tablas auxiliares se crean/ajustan en runtime para compatibilidad (`equipo_sim`, `equipo_dock`, `equipos_verificaciones`, `actividad_logs`, ajustes de `secciones`).
- Este `README.md` raíz es la referencia principal del proyecto.
- El manual operativo para usuarios finales está integrado en `web/manual_usuario.php`.

## Mejoras recomendadas

1. Mover credenciales y configuración a `.env` gestionado por entorno.
2. Consolidar migraciones SQL versionadas.
3. Añadir tests automáticos para flujos críticos (renovaciones, verificaciones, exportes y firmas).
4. Integrar envío SMTP corporativo (relay interno) para no depender de `mail()` local.
