# Gestor de Portátiles (PHP + MySQL, sin Docker)

## Requisitos
- PHP 8.1+ con extensiones: pdo_mysql, mbstring, json
- MySQL/MariaDB
- Apache con mod_rewrite (usa `public/` como DocumentRoot)

## Instalación
1. Crea la base de datos y tablas:
   ```sql
   CREATE DATABASE laptop_loans CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   USE laptop_loans;
   SOURCE schema.sql;
   ```
2. Revisa/edita `config/config.local.php` con tus credenciales.
3. Configura Apache para que el DocumentRoot sea `public/` y permita `.htaccess`.
4. Accede a `/?r=auth/login` (demo: **admin / admin**).

## Estructura
- `public/` → index.php (router), assets, .htaccess
- `app/Controllers` → lógica de rutas
- `app/Models` → acceso a BD (PDO)
- `app/Views` → plantillas PHP
- `recibos_templates/` → **plantillas HTML de recibos**
- `storage/recibos/` → PDFs generados
- `schema.sql` → DDL de tablas

## Recibos PDF
- Los recibos se generan con Dompdf y se guardan en `storage/recibos/`.
- El campo `handovers.recibo_pdf_path` almacena la ruta del PDF generado.
- Nomenclatura actual de archivo:
  - `numero_serie + curso + tip_o_dni + fecha`
  - Formato de fecha: `YYYYMMDD_HHMMSS`
- Si existe TIP se usa TIP; si no, DNI.

## Acceso y autenticación
- SSO con `inventario_it`: si existe sesión activa con `$_SESSION['tip']`, el acceso a `laptop-loans` se concede automáticamente con ese usuario.
- Si no hay sesión SSO, se exige login local (`/?r=auth/login`).
- El proveedor de login local se configura en `config/config.local.php` (`auth.provider`). Valor recomendado: `inventario_it`.
- El rol `admin` se toma de `inventario_it.usuarios.rol` cuando está disponible; en caso contrario entra como usuario estándar.

## Búsqueda en tablas
- Los listados incluyen buscador global por cualquier campo en todas las tablas principales del módulo.

## Próximos pasos
- Añadir validaciones adicionales (formatos y normalización de datos)
- Control de roles/usuarios desde BD

## Para XAMP Composer

extension=gd
extension=zip
extension=mbstring
extension=fileinfo
