-- CMDB VILASECA - Esquema de Base de Datos
-- Este archivo contiene las tablas necesarias para el funcionamiento de los nuevos módulos.

-- 1. Tabla de Configuración de la API de Zabbix
CREATE TABLE IF NOT EXISTS `zabbix_api_config` (
  `id` int(11) NOT NULL DEFAULT 1,
  `url` varchar(255) DEFAULT NULL,
  `token` varchar(255) DEFAULT NULL,
  `version` varchar(20) DEFAULT '7.0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bloque inicial de configuración (Opcional)
INSERT IGNORE INTO `zabbix_api_config` (`id`, `url`, `token`) 
VALUES (1, 'http://172.32.1.51/zabbix/api_jsonrpc.php', '23c5e835efd1c26742b6848ee63b2547ce5349efb88b4ecefee83fa27683cb9a');

-- 2. Tabla de Comunidades SNMP
CREATE TABLE IF NOT EXISTS `snmp_communities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `community` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Tabla de Resultados de Escaneo SNMP (Persistencia)
CREATE TABLE IF NOT EXISTS `snmp_scan_results` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(50) NOT NULL,
  `community_ok` varchar(100) DEFAULT NULL,
  `last_success` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('OK','FAIL') DEFAULT 'OK',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Tabla de Visibilidad para Zabbix (Maestro de Tablas)
CREATE TABLE IF NOT EXISTS `zabbix_cmdb_config` (
  `table_name` varchar(100) NOT NULL,
  `is_enabled` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`table_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
