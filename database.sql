CREATE DATABASE IF NOT EXISTS `sistema_inventario`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `sistema_inventario`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `evidencias_ftp`;
DROP TABLE IF EXISTS `detalle_movimientos`;
DROP TABLE IF EXISTS `movimientos`;
DROP TABLE IF EXISTS `inventario`;
DROP TABLE IF EXISTS `productos`;
DROP TABLE IF EXISTS `almacenes`;
DROP TABLE IF EXISTS `categorias`;
DROP TABLE IF EXISTS `usuarios`;
DROP TABLE IF EXISTS `rol_permiso`;
DROP TABLE IF EXISTS `permisos`;
DROP TABLE IF EXISTS `roles`;

SET FOREIGN_KEY_CHECKS = 1;

-- ==========================================================
-- 1. SEGURIDAD Y CONTROL DE ACCESO (RBAC)
-- ==========================================================

CREATE TABLE `roles` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(50) NOT NULL UNIQUE,
  `descripcion` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE `permisos` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `clave` VARCHAR(80) NOT NULL UNIQUE,
  `descripcion` VARCHAR(255) NULL
) ENGINE=InnoDB;

CREATE TABLE `rol_permiso` (
  `rol_id` INT UNSIGNED NOT NULL,
  `permiso_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`rol_id`, `permiso_id`),
  CONSTRAINT `fk_rp_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rp_permiso` FOREIGN KEY (`permiso_id`) REFERENCES `permisos`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `usuarios` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(100) NOT NULL,
  `email` VARCHAR(120) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `rol_id` INT UNSIGNED NOT NULL,
  `token_recuperacion` VARCHAR(255) NULL,
  `token_expira` DATETIME NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_usuario_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ==========================================================
-- 2. CATÁLOGOS BASE
-- ==========================================================

CREATE TABLE `categorias` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(100) NOT NULL UNIQUE,
  `descripcion` VARCHAR(255) NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE `almacenes` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(100) NOT NULL UNIQUE,
  `ubicacion` VARCHAR(255) NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE `productos` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `sku` VARCHAR(50) NOT NULL UNIQUE,
  `nombre` VARCHAR(150) NOT NULL,
  `descripcion` TEXT NULL,
  `categoria_id` INT UNSIGNED NOT NULL,
  `precio_unitario` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `stock_minimo` INT UNSIGNED NOT NULL DEFAULT 5,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_producto_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ==========================================================
-- 3. STOCK POR ALMACÉN Y MOVIMIENTOS TRANSACCIONALES
-- ==========================================================

CREATE TABLE `inventario` (
  `producto_id` INT UNSIGNED NOT NULL,
  `almacen_id` INT UNSIGNED NOT NULL,
  `stock_actual` INT NOT NULL DEFAULT 0,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`producto_id`, `almacen_id`),
  CONSTRAINT `fk_inv_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_inv_almacen` FOREIGN KEY (`almacen_id`) REFERENCES `almacenes`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `movimientos` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tipo` ENUM('ENTRADA', 'SALIDA', 'TRANSFERENCIA') NOT NULL,
  `almacen_origen_id` INT UNSIGNED NULL,
  `almacen_destino_id` INT UNSIGNED NULL,
  `usuario_id` INT UNSIGNED NOT NULL,
  `observaciones` TEXT NULL,
  `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_movimientos_fecha` (`fecha`),
  INDEX `idx_movimientos_tipo` (`tipo`),
  CONSTRAINT `fk_mov_origen` FOREIGN KEY (`almacen_origen_id`) REFERENCES `almacenes`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_mov_destino` FOREIGN KEY (`almacen_destino_id`) REFERENCES `almacenes`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_mov_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `detalle_movimientos` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `movimiento_id` INT UNSIGNED NOT NULL,
  `producto_id` INT UNSIGNED NOT NULL,
  `cantidad` INT UNSIGNED NOT NULL,
  `costo_unitario` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  CONSTRAINT `fk_det_movimiento` FOREIGN KEY (`movimiento_id`) REFERENCES `movimientos`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_det_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ==========================================================
-- 4. EVIDENCIAS REMOTAS VÍA FTP
-- ==========================================================

CREATE TABLE `evidencias_ftp` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `movimiento_id` INT UNSIGNED NULL,
  `nombre_archivo` VARCHAR(255) NOT NULL,
  `ruta_remota` VARCHAR(500) NOT NULL,
  `tamano_bytes` INT UNSIGNED NOT NULL DEFAULT 0,
  `usuario_id` INT UNSIGNED NOT NULL,
  `fecha_subida` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_ftp_movimiento` FOREIGN KEY (`movimiento_id`) REFERENCES `movimientos`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ftp_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ==========================================================
-- 5. DATOS SEMILLA (PARA NO ARRANCAR EN CEROS)
-- ==========================================================

INSERT INTO `roles` (`id`, `nombre`, `descripcion`) VALUES
(1, 'Administrador', 'Acceso total a todos los módulos del sistema'),
(2, 'Almacenista', 'Gestión de catálogos, entradas, salidas, transferencias y evidencias FTP'),
(3, 'Auditor', 'Consulta de inventario y exportación de reportes');

INSERT INTO `permisos` (`id`, `clave`, `descripcion`) VALUES
(1, 'usuarios.gestionar', 'Crear, editar, activar/desactivar usuarios'),
(2, 'roles.gestionar', 'Crear, editar roles y asignar permisos'),
(3, 'catalogos.ver', 'Consultar productos, categorías y almacenes'),
(4, 'catalogos.gestionar', 'Crear y editar productos, categorías y almacenes'),
(5, 'movimientos.ver', 'Consultar historial de movimientos'),
(6, 'movimientos.entrada', 'Registrar entradas de inventario'),
(7, 'movimientos.salida', 'Registrar salidas de inventario'),
(8, 'movimientos.transferencia', 'Transferir mercancía entre almacenes'),
(9, 'ftp.gestionar', 'Subir, descargar y eliminar archivos/carpetas vía FTP'),
(10, 'reportes.exportar', 'Exportar reportes en Excel, PDF y CSV');

-- Administrador: todos los permisos
INSERT INTO `rol_permiso` (`rol_id`, `permiso_id`)
SELECT 1, `id` FROM `permisos`;

-- Almacenista: operación diaria sin tocar usuarios ni roles
INSERT INTO `rol_permiso` (`rol_id`, `permiso_id`) VALUES
(2, 3), (2, 4), (2, 5), (2, 6), (2, 7), (2, 8), (2, 9), (2, 10);

-- Auditor: pura lectura y exportación
INSERT INTO `rol_permiso` (`rol_id`, `permiso_id`) VALUES
(3, 3), (3, 5), (3, 10);

-- Usuario Admin inicial -> Correo: admin@inventario.local | Contraseña: password
INSERT INTO `usuarios` (`nombre`, `email`, `password_hash`, `rol_id`, `activo`) VALUES
('Administrador General', 'admin@inventario.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1);

INSERT INTO `categorias` (`id`, `nombre`, `descripcion`) VALUES
(1, 'Cómputo y Periféricos', 'Equipos de escritorio, laptops, monitores y accesorios'),
(2, 'Redes y Telecomunicaciones', 'Switches, routers, cableado estructurado y access points'),
(3, 'Consumibles', 'Tóner, papelería y suministros operativos');

INSERT INTO `almacenes` (`id`, `nombre`, `ubicacion`) VALUES
(1, 'Almacén Central', 'Nave Industrial A - Planta Baja'),
(2, 'Bodega Norte', 'Sucursal Zona Norte'),
(3, 'Almacén de Resguardo', 'Edificio Administrativo Piso 2');

INSERT INTO `productos` (`id`, `sku`, `nombre`, `descripcion`, `categoria_id`, `precio_unitario`, `stock_minimo`) VALUES
(1, 'COMP-001', 'Monitor Dell 24" IPS Full HD', 'Monitor empresarial entrada HDMI/DisplayPort', 1, 3450.00, 5),
(2, 'RED-014', 'Switch Gigabit 24 Puertos', 'Switch administrable rackeable', 2, 4890.50, 3),
(3, 'CONS-102', 'Bobina Cable UTP Cat6 305m', 'Cable 100% cobre color azul', 3, 2150.00, 4);

INSERT INTO `inventario` (`producto_id`, `almacen_id`, `stock_actual`) VALUES
(1, 1, 25),
(1, 2, 8),
(2, 1, 12),
(3, 1, 15),
(3, 3, 3);