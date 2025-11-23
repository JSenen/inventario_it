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
('Red 1', '10.52.2.0', '255.255.255.0', '10.52.2.1', NULL, 'Red principal 10.52.2.x'),
('Red 2', '10.52.3.0', '255.255.255.0', '10.52.3.1', NULL, 'Red secundaria 10.52.3.x');

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

