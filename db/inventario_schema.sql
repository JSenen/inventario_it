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


-- 29-11-2025 CONTROL DE MOVILES / SIM

--  Tabla para gestionar teléfonos móviles
CREATE TABLE telefonos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    marca VARCHAR(100) NOT NULL,
    modelo VARCHAR(100) NOT NULL,
    imei VARCHAR(20) NOT NULL UNIQUE,
    numero_serie VARCHAR(100) UNIQUE,
    
    -- Relación lógica con tu inventario
    departamento VARCHAR(100) DEFAULT NULL,
    ubicacion VARCHAR(100) DEFAULT NULL,
    seccion VARCHAR(100) DEFAULT NULL,         -- opcional, si quieres usar tus secciones aquí
    usuario_asignado VARCHAR(150) DEFAULT NULL, -- nombre o TIP del usuario

    estado VARCHAR(30) DEFAULT 'Activo',       -- Activo, Baja, Almacén, Prestado, Averiado...
    fecha_alta DATE DEFAULT (CURRENT_DATE),
    fecha_baja DATE DEFAULT NULL,
    proveedor VARCHAR(100) DEFAULT NULL,
    coste DECIMAL(10,2) DEFAULT NULL,

    observaciones TEXT,
    imagen VARCHAR(255) DEFAULT NULL,          -- como en equipos (logo, foto del móvil, etc.)

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

ALTER TABLE telefonos
ADD usuario_receptor VARCHAR(150) DEFAULT NULL,
ADD fecha_entrega DATE DEFAULT NULL;


--  Tabla para gestionar SIMs
CREATE TABLE sims (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(20) NOT NULL UNIQUE,         -- número de teléfono
    iccid VARCHAR(30) NOT NULL UNIQUE,          -- código único de la SIM
    operador VARCHAR(50) NOT NULL,              -- Movistar, Orange, Vodafone...
    tarifa VARCHAR(100) DEFAULT NULL,

    pin VARCHAR(10) DEFAULT NULL,
    puk VARCHAR(20) DEFAULT NULL,

    estado VARCHAR(30) DEFAULT 'Disponible',    -- Disponible, Asignada, Baja, Averiada
    fecha_alta DATE DEFAULT (CURRENT_DATE),
    fecha_baja DATE DEFAULT NULL,

    observaciones TEXT,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla intermedia para asignar SIMs a teléfonos
CREATE TABLE telefono_sim (
    id INT AUTO_INCREMENT PRIMARY KEY,
    telefono_id INT NOT NULL,
    sim_id INT NOT NULL,

    fecha_asignacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_liberacion DATETIME DEFAULT NULL,

    observaciones TEXT,

    -- Índices para búsquedas rápidas
    INDEX idx_tel (telefono_id),
    INDEX idx_sim (sim_id),

    FOREIGN KEY (telefono_id) REFERENCES telefonos(id),
    FOREIGN KEY (sim_id) REFERENCES sims(id)
);


-- 21 Tabla para partes de entrega / recepción de teléfonos
CREATE TABLE partes_telefono (
    id INT AUTO_INCREMENT PRIMARY KEY,
    telefono_id INT NOT NULL,
    usuario_receptor VARCHAR(150) NOT NULL,
    usuario_entrega VARCHAR(150) NOT NULL,
    fecha_entrega DATE NOT NULL,
    observaciones TEXT,
    FOREIGN KEY (telefono_id) REFERENCES telefonos(id)
);


-- 01-12-2025 Añadido campo estado a las ips
ALTER TABLE ips_equipos
ADD COLUMN estado ENUM('LIBRE','USADA','RESERVADA')
    NOT NULL
    DEFAULT 'USADA'
    AFTER ip;


-- El resto quedarán LIBRE (por el DEFAULT)

-- Marcar como RESERVADA las IPs específicas en red_id = 3
--UPDATE ips_equipos
--SET estado = 'RESERVADA'
--WHERE red_id = 3 AND ip IN ('192.168.8.1', '192.168.8.2');

ALTER TABLE ips_equipos
MODIFY COLUMN equipo_id INT NULL;

-- EQUIPOS MOVIMIENTOS (ENTREGA, RECOGIDA, PRÉSTAMO, DEVOLUCIÓN)
CREATE TABLE equipos_movimientos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_equipo INT NOT NULL,
    tipo ENUM('entrega','recogida','prestamo','devolucion') NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    usuario_destino VARCHAR(150) NOT NULL,
    tecnico VARCHAR(150) DEFAULT NULL,
    estado_origen VARCHAR(50),
    estado_destino VARCHAR(50),
    observaciones VARCHAR(255) DEFAULT NULL,
    pdf_path VARCHAR(255) DEFAULT NULL,
    firma_token VARCHAR(80) DEFAULT NULL,
    firma_path VARCHAR(255) DEFAULT NULL,
    firmado TINYINT(1) DEFAULT 0,
    firmado_fecha DATETIME DEFAULT NULL,
    FOREIGN KEY (id_equipo) REFERENCES equipos(id)
);


-- 06-12-2025 Añadido campo etiquetas a equipos
ALTER TABLE equipos
ADD COLUMN etiqueta TEXT NULL AFTER notas; 

-- La columna 'etiqueta' almacenará etiquetas o códigos asociados al equipo.

-- Se añade la tblequipos.sql a la base de datos
-- Se deja la misma codificación que el resto de la base de datos
ALTER TABLE tblequipos
  CONVERT TO CHARACTER SET utf8mb4
  COLLATE utf8mb4_uca1400_ai_ci;

-- Buscamos los datos de etiquetas que coinciden por el numero de serie y actualziamos nuuestra tabla
UPDATE equipos e
JOIN tblequipos t ON e.numero_serie = t.serie
SET e.etiqueta = t.codigoequipo
WHERE (e.etiqueta IS NULL OR e.etiqueta = '')
  AND t.codigoequipo IS NOT NULL
  AND t.codigoequipo <> '';
-- Finalmente eliminamos la tabla temporal si deseamos
DROP TABLE IF EXISTS tblequipos;
-- Ahora actualizamos las fechas de compra de los equipos que no las tienen -- 
UPDATE equipos e
JOIN tblequipos t ON e.numero_serie = t.serie
SET e.fecha_compra = t.falta
WHERE (e.fecha_compra IS NULL OR e.fecha_compra = '0000-00-00')
  AND t.falta IS NOT NULL
  AND t.falta <> '0000-00-00';


-- 07-12-2025 Añadido campo solucion_aplicada a averias
ALTER TABLE averias
ADD COLUMN solucion_aplicada TEXT NULL AFTER empresa_ext;


-- 15/12/2025

CREATE TABLE  equipos_verificaciones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            equipo_id INT NOT NULL,
            fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ubicacion VARCHAR(255) DEFAULT NULL,
            estado_equipo VARCHAR(50) DEFAULT NULL,
            usuario_verificador VARCHAR(100) DEFAULT NULL,
            ip VARCHAR(50) DEFAULT NULL,
            seccion VARCHAR(255) DEFAULT NULL,
            departamento VARCHAR(255) DEFAULT NULL,
            notas TEXT,
            INDEX idx_equipo_fecha (equipo_id, fecha),
            CONSTRAINT fk_equipo_verificaciones_equipo
                FOREIGN KEY (equipo_id) REFERENCES equipos(id)
                ON DELETE CASCADE
        ) ;