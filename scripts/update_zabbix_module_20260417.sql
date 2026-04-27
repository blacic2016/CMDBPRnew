-- CMDB VILASECA - Migración Módulo Zabbix Avanzado
-- Fecha: 2026-04-17

-- 1. Tabla para configuración de API Zabbix
CREATE TABLE IF NOT EXISTS `zabbix_api_config` (
  `id` int(11) NOT NULL DEFAULT 1,
  `url` varchar(255) DEFAULT NULL,
  `token` varchar(255) DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Tabla para habilitar tablas de inventario en Zabbix
CREATE TABLE IF NOT EXISTS `zabbix_cmdb_config` (
  `table_name` varchar(255) NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`table_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabla para mapeos de campos CMDB -> Zabbix
CREATE TABLE IF NOT EXISTS `zabbix_mappings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cmdb_table_name` varchar(100) NOT NULL,
  `hostname_template` varchar(255) DEFAULT NULL,
  `visible_name_template` varchar(255) DEFAULT NULL,
  `hostgroup_template` varchar(255) DEFAULT NULL,
  `ip_field` varchar(100) DEFAULT NULL,
  `snmp_community_field` varchar(100) DEFAULT NULL,
  `template_name` varchar(255) DEFAULT NULL,
  `inventory_fields_json` longtext DEFAULT NULL,
  `tags_json` longtext DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cmdb_table_name` (`cmdb_table_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. Tabla para palabras clave de segmentación (NUEVA)
CREATE TABLE IF NOT EXISTS `zabbix_keywords` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `keyword` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `keyword` (`keyword`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Datos iniciales para pruebas
INSERT IGNORE INTO `zabbix_keywords` (`keyword`) VALUES 
('VIRTUAL'), ('SWITCH'), ('ESXI'), ('FIREWALL'), ('UPS');

-- Logs de revisión:
-- - Se consolida la estructura de Zabbix para permitir gestión masiva y segmentación.
-- - Se añade soporte para coordenadas geográficas en el join con sheet_localidades.
