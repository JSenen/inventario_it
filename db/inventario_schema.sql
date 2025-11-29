-- Crear BD si no existe (aunque el contenedor ya la crea)
CREATE DATABASE IF NOT EXISTS inventario_it
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE inventario_it;

-- 1) TABLA REDES
CREATE TABLE IF NOT EXISTS redes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(50) NOT NULL,
  direccion_red VARCHAR(50) NOT NULL,
  mascara VARCHAR(20) NOT NULL,
  gateway VARCHAR(45),
  vlan VARCHAR(20),
  notas TEXT,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO redes (nombre, direccion_red, mascara, gateway, vlan, notas) VALUES
('Red 2', '10.52.2.0', '255.255.255.0', '10.52.2.1', NULL, 'Red 2 10.52.2.x'),
('Red 3', '10.52.3.0', '255.255.255.0', '10.52.3.1', NULL, 'Red 3 10.52.3.x');

-- 2) TABLA EQUIPOS
CREATE TABLE IF NOT EXISTS equipos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tipo VARCHAR(50) NOT NULL,
  marca VARCHAR(100),
  modelo VARCHAR(100),
  numero_serie VARCHAR(100),
  hostname VARCHAR(100),
  usuario_asignado VARCHAR(100),
  departamento VARCHAR(100),
  ubicacion VARCHAR(100),
  fecha_compra DATE,
  proveedor VARCHAR(100),
  coste DECIMAL(10,2),
  estado VARCHAR(50) DEFAULT 'En uso',
  notas TEXT,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE INDEX IF NOT EXISTS idx_equipos_hostname ON equipos (hostname);
CREATE INDEX IF NOT EXISTS idx_equipos_usuario ON equipos (usuario_asignado);
CREATE INDEX IF NOT EXISTS idx_equipos_departamento ON equipos (departamento);

-- 3) TABLA IPs ASIGNADAS A EQUIPOS
CREATE TABLE IF NOT EXISTS ips_equipos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  equipo_id INT NOT NULL,
  red_id INT NOT NULL,
  ip VARCHAR(45) NOT NULL,
  mac VARCHAR(17),
  es_principal TINYINT(1) DEFAULT 1,
  notas TEXT,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ips_equipo
    FOREIGN KEY (equipo_id) REFERENCES equipos(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_ips_red
    FOREIGN KEY (red_id) REFERENCES redes(id)
    ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE INDEX IF NOT EXISTS idx_ips_ip ON ips_equipos (ip);
CREATE INDEX IF NOT EXISTS idx_ips_equipo ON ips_equipos (equipo_id);

-- 4) TABLA LICENCIAS (opcional)
CREATE TABLE IF NOT EXISTS licencias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre_software VARCHAR(100) NOT NULL,
  clave VARCHAR(255),
  num_licencias INT DEFAULT 1,
  fecha_compra DATE,
  fecha_expiracion DATE,
  proveedor VARCHAR(100),
  notas TEXT,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 5) TABLA LICENCIAS ASIGNADAS (opcional)
CREATE TABLE IF NOT EXISTS licencias_asignadas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  licencia_id INT NOT NULL,
  equipo_id INT NOT NULL,
  usuario VARCHAR(100),
  fecha_asignacion DATE,
  notas TEXT,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_lic_asig_lic
    FOREIGN KEY (licencia_id) REFERENCES licencias(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_lic_asig_equipo
    FOREIGN KEY (equipo_id) REFERENCES equipos(id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- 6) TABLA AVERIAS
CREATE TABLE averias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipo_id INT NOT NULL,
    tipo_averia VARCHAR(100) NOT NULL,
    num_asunto VARCHAR(50) NOT NULL,
    descripcion TEXT,
    empresa_ext VARCHAR(100),
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    estado VARCHAR(20) NOT NULL DEFAULT 'ABIERTA',
    FOREIGN KEY (equipo_id) REFERENCES equipos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE averias
ADD COLUMN fecha_cierre DATETIME NULL AFTER fecha_creacion;

ALTER TABLE equipos
ADD COLUMN fecha_baja DATETIME NULL AFTER fecha_compra;

-- PARA HACER TRUNCATE A TABLAS
-- SET FOREIGN_KEY_CHECKS = 0;
-- TRUNCATE TABLE ips_equipos;
-- TRUNCATE TABLE licencias_asignadas;
-- TRUNCATE TABLE licencias;
-- TRUNCATE TABLE averias;
-- TRUNCATE TABLE equipos;
-- TRUNCATE TABLE redes;
-- SET FOREIGN_KEY_CHECKS = 1;

ALTER TABLE equipos 
ADD COLUMN imagen TEXT NULL AFTER proveedor;
-- La columna 'imagen' almacenará la ruta o el nombre del archivo de imagen asociado al equipo.

ALTER TABLE redes 
    MODIFY mascara VARCHAR(20) NULL DEFAULT '24';
-- Permitir valores NULL en la columna 'mascara' y establecer un valor predeterminado de '24'.

-- 7) TABLA USUARIOS
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tip VARCHAR(20) NOT NULL UNIQUE,
    password_hash CHAR(64) NOT NULL,   -- SHA3-256 en hex = 64 chars
    rol ENUM('admin','usuario') NOT NULL DEFAULT 'usuario',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Generar hash de la contraseña (ejemplo con PHP):
-- php -r "echo hash('sha3-256', 'TuClaveSuperSegura123!') . PHP_EOL;"

-- Insertar usuario admin por defecto (cambiar password después de la primera conexión)
--INSERT INTO usuarios (tip, password_hash, rol)
--VALUES ('X12345X', 'EHAS_COPIADO', 'admin');

-- 8) TABLA ACTIVIDAD LOGS
CREATE TABLE actividad_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_tip VARCHAR(20) NOT NULL,
    accion VARCHAR(255) NOT NULL,
    detalles TEXT,
    ip VARCHAR(45) DEFAULT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Impedir duplicados en numero_serie de equipos
ALTER TABLE equipos
ADD UNIQUE KEY uniq_numero_serie (numero_serie);


-- 19 Tabla para relacionar PCs con Monitores

CREATE TABLE pc_monitores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_pc INT NOT NULL,
    id_monitor INT NOT NULL,
    fecha_asignacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pc_monitores_pc
        FOREIGN KEY (id_pc) REFERENCES equipos(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_pc_monitores_monitor
        FOREIGN KEY (id_monitor) REFERENCES equipos(id)
        ON DELETE CASCADE,
    -- 1 monitor solo puede estar en un PC
    CONSTRAINT uq_monitor_unico UNIQUE (id_monitor)
);

-- 20 Tabla para gestionar materiales de almacén
-- Tabla principal de material / fungibles
CREATE TABLE materiales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    referencia     VARCHAR(50)  NOT NULL,
    descripcion    VARCHAR(255) NOT NULL,
    categoria      VARCHAR(50)  NOT NULL,
    unidad         VARCHAR(20)  NOT NULL DEFAULT 'ud',
    stock_actual   INT NOT NULL DEFAULT 0,
    stock_minimo   INT NOT NULL DEFAULT 0,
    ubicacion      VARCHAR(100) DEFAULT NULL,
    proveedor      VARCHAR(100) DEFAULT NULL,
    coste_unitario DECIMAL(10,2) DEFAULT NULL,
    notas          TEXT,
    creado_en      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                  ON UPDATE CURRENT_TIMESTAMP
);

-- Movimientos de stock (entradas / salidas)
CREATE TABLE materiales_movimientos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    material_id INT NOT NULL,
    fecha       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tipo        ENUM('ENTRADA','SALIDA') NOT NULL,
    cantidad    INT NOT NULL,
    motivo      VARCHAR(255) DEFAULT NULL,
    usuario     VARCHAR(100) DEFAULT NULL,
    equipo_id   INT DEFAULT NULL,  -- opcional: equipos.id si lo asocias a un PC
    notas       TEXT,
    CONSTRAINT fk_mm_material
        FOREIGN KEY (material_id) REFERENCES materiales(id)
        ON DELETE CASCADE
);

-- FK opcional a equipos desde movimientos de materiales
ALTER TABLE materiales_movimientos
    ADD CONSTRAINT fk_mm_equipo
    FOREIGN KEY (equipo_id) REFERENCES equipos(id)
    ON DELETE SET NULL;


-- ADDED 29-11-2025


-- Departamentos
CREATE TABLE departamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion VARCHAR(255) DEFAULT NULL
);

CREATE TABLE secciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion VARCHAR(255) DEFAULT NULL
);

INSERT INTO `secciones` (`id`, `nombre`, `descripcion`) VALUES
(1, 'GATI', 'Oficina'),
(2, 'Almacén GATI', 'Almacén GATI'),
(3, 'Deposito Armas', 'Deposito Armas IAE Especial Barcelona'),
(4, 'Secretaría', 'Secretaría');

ALTER TABLE equipos
ADD COLUMN seccion_id INT DEFAULT NULL,
ADD CONSTRAINT fk_equipos_seccion
    FOREIGN KEY (seccion_id) REFERENCES secciones(id);


-- Ubicaciones (edificios, sedes, plantas…)
CREATE TABLE ubicaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion VARCHAR(255) DEFAULT NULL
);

INSERT INTO `ubicaciones` (`id`, `nombre`, `descripcion`) VALUES
(1, 'Zona', 'Travessera de Gracia 291, Barcelona'),
(2, 'Aeropuerto', 'Avenida Pepa Colomer, El Prat de Llobregat, Barcelona'),
(3, 'Puerto', 'MuelleÁlvarez de la Campa, 08039,Barcelona'),
(5, 'Sala Coordinacion Policial', NULL);

-- Tipos de equipo (PC, Portátil, Impresora, Monitor…)
CREATE TABLE tipos_equipo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion VARCHAR(255) DEFAULT NULL
);

INSERT INTO `tipos_equipo` (`id`, `nombre`, `descripcion`) VALUES
(1, 'PC', NULL),
(2, 'Portatil', NULL),
(3, 'Impresora', NULL),
(4, 'Monitor', NULL),
(5, 'Escaner', NULL),
(6, 'Switch', NULL),
(7, 'Router', NULL),
(8, 'Video Conferencia', NULL),
(9, 'Movil', NULL),
(10, 'Tablet', NULL),
(11, 'Otro', NULL),
(12, 'Impresora Guias', NULL),
(13, 'Impresora Multifuncion', NULL);


-- Tipos de Servicio (Intranet, Internet, ...)
CREATE TABLE tipos_servicio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL
);

INSERT INTO `tipos_servicio` (`id`, `nombre`) VALUES
(1, 'Intranet'),
(2, 'Internet'),
(3, 'Otro'),
(4, '-----'),
(5, 'SITEL');

CREATE TABLE estados_equipo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    descripcion VARCHAR(255) DEFAULT NULL
);
INSERT INTO estados_equipo (nombre) VALUES
('Activo'),
('Averiado'),
('Baja'),
('Almacén'),
('Prestado');
CREATE INDEX idx_equipos_seccion ON equipos (seccion_id);
CREATE INDEX idx_equipos_estado ON equipos (estado);