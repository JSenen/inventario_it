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
2. Copia `config/config.example.php` a `config/config.local.php` y ajusta credenciales.
3. Configura Apache para que el DocumentRoot sea `public/` y permita `.htaccess`.
4. Accede a `/?r=auth/login` (demo: **admin / admin**).

## Estructura
- `public/` → index.php (router), assets, .htaccess
- `app/Controllers` → lógica de rutas
- `app/Models` → acceso a BD (PDO)
- `app/Views` → plantillas PHP
- `recibos_templates/` → **plantillas HTML de recibos**
- `storage/recibos/` → PDFs generados (pendiente integrar dompdf)
- `schema.sql` → DDL de tablas

## Generación de PDFs (dompdf)
Instala con Composer:
```
composer require dompdf/dompdf:^2
```
Luego crea un servicio que lea `recibos_templates/*.html`, reemplace `{{placeholders}}` y guarde en `storage/recibos/`. (Ver ejemplo enviado en el chat).

## Próximos pasos
- Añadir validaciones (DNI, TIP, teléfono, email)
- Añadir DataTables (coloca los assets en `public/assets/vendor/datatables` y enlaza desde las vistas)
- Implementar descarga de recibo desde `handovers/index`
- Control de roles/usuarios desde BD
- Firma manuscrita (canvas HTML5) y almacenamiento PNG/Base64

## Para XAMP Composer

extension=gd
extension=zip
extension=mbstring
extension=fileinfo
