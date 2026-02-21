# Inventario IT

Aplicación web en PHP para gestionar el inventario de equipos informáticos de un departamento, el control de IPs, la gestión de redes y la gestión de averías (con generación de partes/imprimibles).

Está pensada para uso interno en un departamento IT.

---

## Funcionalidades principales

- **Inventario de equipos**

  - Alta, edición, baja y visualización de equipos.
  - Campos: tipo, marca, modelo, número de serie, hostname, usuario asignado, departamento, ubicación, proveedor, fecha de alta, coste, estado, notas…
  - Campo de **imagen del equipo** (ruta a fichero) para tener una referencia visual.

- **Control de IPs**

  - Asociación de IP principal a cada equipo.
  - Control de IPs libres/ocupadas.

- **Gestión de redes**

  - Gestión básica de redes y relación con los equipos.

- **Gestión de averías**
  - Apertura y cierre de averías asociadas a un equipo.
  - Dos números de asunto: asunto interno (GATI / ID propio) y número de referencia del proveedor externo.
  - Listado de averías con buscador.
  - **Generación de parte de avería en HTML imprimible/PDF**, incluyendo:
    - Datos del equipo.
    - Datos de la avería.
    - Imagen del equipo (si está configurada).
    - Logo del departamento (opcional).

---

## Estructura básica del proyecto

(Suponiendo estructura similar a:)

```text
inventario-it/
└── web/
    ├── Dockerfile
    ├── config.php
    ├── index.php                # Listado principal de equipos
    ├── equipo_nuevo.php         # Alta de equipo
    ├── equipo_editar.php        # Edición de equipo
    ├── equipo_ver.php           # Ficha de equipo
    ├── equipo_borrar.php
    ├── averias_list.php         # Listado de averías
    ├── averia_parte.php         # Parte imprimible de avería
    ├── averia_cerrar.php
    ├── redes.php                # Gestión de redes
    ├── get_ips_libres.php       # Control de IPs
    ├── includes/                # Cabeceras, conexiones, funciones comunes
    ├── vendor/                  # Dependencias (si se usa Composer)
    ├── uploads/
    │   └── equipos/             # Imágenes de los equipos
    └── assets/
        └── logo_departamento.png  # Logo del departamento (opcional)
```

## PERMISOS UPLOAD

sudo chown -R www-data:www-data /home/jsenen/inventario-it/web/uploads
sudo chmod -R 775 /home/jsenen/inventario-it/web/uploads

# Migración del proyecto Inventario-IT a otro PC

Este documento explica cómo **mover el proyecto completo** (código + base de datos + imágenes + configuración) a otro ordenador, ya sea para clonarlo, migrarlo o ponerlo en producción.

Incluye procedimientos tanto para instalaciones **con Docker** como para **LAMP clásico (Apache + PHP + MySQL/MariaDB)**.

---

## 📁 1. Qué hay que copiar

Para que el proyecto funcione igual en otro PC necesitas tres cosas:

1. **Código completo del proyecto**, normalmente en:
2. **Base de datos MySQL/MariaDB**: contiene equipos, usuarios, averías, redes, logs, etc.
3. **Carpeta de imágenes**:
   Ya contiene las imágenes de los equipos, necesarias para que todo se vea igual.

---

## 🗄️ 2. Exportar la base de datos del PC original

### ✔️ Si MySQL/MariaDB está instalado en el sistema (LAMP)

Ejecutar:

```bash
mysqldump -u TU_USUARIO -p inventario_it > inventario_it_backup.sql
docker ps
docker exec -i inventario-db \
    mysqldump -u TU_USUARIO -p inventario_it > inventario_it_backup.sql
scp -r usuario@IP_ORIGEN:/ruta/inventario-it ~/inventario-it
scp usuario@IP_ORIGEN:/ruta/inventario_it_backup.sql ~/
mysql -u TU_USUARIO -p -e "CREATE DATABASE inventario_it CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u TU_USUARIO -p inventario_it < inventario_it_backup.sql
```

## PERMISOS Carpetas

cd ~/inventario-it/web
mkdir -p uploads/equipos
sudo chown -R www-data:www-data uploads
sudo chmod -R 775 uploads
