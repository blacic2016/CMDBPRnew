/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.6.22-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: CMDBVilaseca2
-- ------------------------------------------------------
-- Server version	10.6.22-MariaDB-ubu2204-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `asset_sequence`
--

DROP TABLE IF EXISTS `asset_sequence`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `asset_sequence` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `prefix` varchar(10) NOT NULL DEFAULT 'AE',
  `last_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `images`
--

DROP TABLE IF EXISTS `images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(100) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `filepath` varchar(255) NOT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `import_logs`
--

DROP TABLE IF EXISTS `import_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `import_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `sheet_name` varchar(255) DEFAULT NULL,
  `mode` varchar(20) NOT NULL,
  `added_count` int(11) DEFAULT 0,
  `skipped_count` int(11) DEFAULT 0,
  `errors` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_count` int(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sheet_ap`
--

DROP TABLE IF EXISTS `sheet_ap`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sheet_ap` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `_row_hash` varchar(64) NOT NULL,
  `estado_actual` enum('USADO','ENTREGADO','NO_APARECE','DANADO') DEFAULT 'USADO',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `excel_id` varchar(255) DEFAULT NULL,
  `unidad` varchar(255) DEFAULT NULL,
  `empresa` varchar(255) DEFAULT NULL,
  `ciudad_lugar` varchar(255) DEFAULT NULL,
  `marca` varchar(255) DEFAULT NULL,
  `nombre` varchar(255) DEFAULT NULL,
  `ip_address` varchar(255) DEFAULT NULL,
  `model` varchar(255) DEFAULT NULL,
  `serial` varchar(255) DEFAULT NULL,
  `mac` varchar(255) DEFAULT NULL,
  `referencia` varchar(255) DEFAULT NULL,
  `observaciones` varchar(255) DEFAULT NULL,
  `existencia_on_site` varchar(255) DEFAULT NULL,
  `existe_en_inv_ilum` varchar(255) DEFAULT NULL,
  `estado` varchar(255) DEFAULT NULL,
  `zabbix_host_id` varchar(255) DEFAULT NULL,
  `asset_code` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_sheet_ap_rowhash` (`_row_hash`),
  UNIQUE KEY `asset_code` (`asset_code`)
) ENGINE=InnoDB AUTO_INCREMENT=141 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sheet_configs`
--

DROP TABLE IF EXISTS `sheet_configs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sheet_configs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sheet_name` varchar(255) NOT NULL,
  `table_name` varchar(255) NOT NULL,
  `unique_columns` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `sheet_name` (`sheet_name`),
  UNIQUE KEY `table_name` (`table_name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sheet_distrib_rack`
--

DROP TABLE IF EXISTS `sheet_distrib_rack`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sheet_distrib_rack` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `_row_hash` varchar(64) NOT NULL,
  `estado_actual` enum('USADO','ENTREGADO','NO_APARECE','DANADO') DEFAULT 'USADO',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `col` varchar(255) DEFAULT NULL,
  `col_1` varchar(255) DEFAULT NULL,
  `col_2` varchar(255) DEFAULT NULL,
  `col_3` varchar(255) DEFAULT NULL,
  `col_4` varchar(255) DEFAULT NULL,
  `col_5` varchar(255) DEFAULT NULL,
  `col_6` varchar(255) DEFAULT NULL,
  `col_7` varchar(255) DEFAULT NULL,
  `col_8` varchar(255) DEFAULT NULL,
  `col_9` varchar(255) DEFAULT NULL,
  `col_10` varchar(255) DEFAULT NULL,
  `col_11` varchar(255) DEFAULT NULL,
  `col_12` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_sheet_distrib_rack_rowhash` (`_row_hash`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sheet_equipos`
--

DROP TABLE IF EXISTS `sheet_equipos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sheet_equipos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `_row_hash` varchar(64) NOT NULL,
  `estado_actual` enum('USADO','ENTREGADO','NO_APARECE','DANADO') DEFAULT 'USADO',
  `asset_code` varchar(50) DEFAULT NULL,
  `zabbix_host_id` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `item_id` text DEFAULT NULL,
  `pais` text DEFAULT NULL,
  `ciudad` text DEFAULT NULL,
  `sucursal` text DEFAULT NULL,
  `unidad` text DEFAULT NULL,
  `hostgroup` text DEFAULT NULL,
  `tipo` text DEFAULT NULL,
  `area` text DEFAULT NULL,
  `nomenclatura` text DEFAULT NULL,
  `ip` text DEFAULT NULL,
  `visiblename` text DEFAULT NULL,
  `snmp` text DEFAULT NULL,
  `seccion` text DEFAULT NULL,
  `modelo` text DEFAULT NULL,
  `serial` text DEFAULT NULL,
  `vlan` text DEFAULT NULL,
  `estado` text DEFAULT NULL,
  `propietario` text DEFAULT NULL,
  `marca` text DEFAULT NULL,
  `clasificacion` text DEFAULT NULL,
  `visible` text DEFAULT NULL,
  `mac` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_code` (`asset_code`)
) ENGINE=InnoDB AUTO_INCREMENT=156 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sheet_firewall`
--

DROP TABLE IF EXISTS `sheet_firewall`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sheet_firewall` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `_row_hash` varchar(64) NOT NULL,
  `estado_actual` enum('USADO','ENTREGADO','NO_APARECE','DANADO') DEFAULT 'USADO',
  `asset_code` varchar(50) DEFAULT NULL,
  `zabbix_host_id` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `item_id` text DEFAULT NULL,
  `isp` text DEFAULT NULL,
  `unidad` text DEFAULT NULL,
  `empresa` text DEFAULT NULL,
  `localidad` text DEFAULT NULL,
  `custodio` text DEFAULT NULL,
  `rentado` text DEFAULT NULL,
  `tipo` text DEFAULT NULL,
  `device_name` text DEFAULT NULL,
  `hardware_model` text DEFAULT NULL,
  `monitoring_access_ip` text DEFAULT NULL,
  `device_version_fortios` text DEFAULT NULL,
  `mac` text DEFAULT NULL,
  `serial_number` text DEFAULT NULL,
  `existencia_on_site` text DEFAULT NULL,
  `existe_seg_n_inventario_ilum` text DEFAULT NULL,
  `estado` text DEFAULT NULL,
  `ubicaci_n_rack_ur` text DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_code` (`asset_code`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sheet_history`
--

DROP TABLE IF EXISTS `sheet_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sheet_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `table_name` varchar(255) NOT NULL,
  `row_id` int(11) NOT NULL,
  `action` varchar(20) NOT NULL,
  `changed_by` varchar(255) DEFAULT NULL,
  `old_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_data`)),
  `new_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_data`)),
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sheet_hoja1`
--

DROP TABLE IF EXISTS `sheet_hoja1`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sheet_hoja1` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `_row_hash` varchar(64) NOT NULL,
  `estado_actual` enum('USADO','ENTREGADO','NO_APARECE','DANADO') DEFAULT 'USADO',
  `asset_code` varchar(50) DEFAULT NULL,
  `zabbix_host_id` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `item_id` text DEFAULT NULL,
  `isp` text DEFAULT NULL,
  `unidad` text DEFAULT NULL,
  `empresa` text DEFAULT NULL,
  `localidad` text DEFAULT NULL,
  `custodio` text DEFAULT NULL,
  `rentado` text DEFAULT NULL,
  `tipo` text DEFAULT NULL,
  `device_name` text DEFAULT NULL,
  `hardware_model` text DEFAULT NULL,
  `monitoring_access_ip` text DEFAULT NULL,
  `device_version_fortios` text DEFAULT NULL,
  `mac` text DEFAULT NULL,
  `serial_number` text DEFAULT NULL,
  `existencia_on_site` text DEFAULT NULL,
  `existe_seg_n_inventario_ilum` text DEFAULT NULL,
  `estado` text DEFAULT NULL,
  `ubicaci_n_rack_ur` text DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_code` (`asset_code`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sheet_localidades`
--

DROP TABLE IF EXISTS `sheet_localidades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sheet_localidades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `_row_hash` varchar(64) NOT NULL,
  `estado_actual` enum('USADO','ENTREGADO','NO_APARECE','DANADO') DEFAULT 'USADO',
  `asset_code` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `item_id` text DEFAULT NULL,
  `localidades` text DEFAULT NULL,
  `sucursal` text DEFAULT NULL,
  `novedades_encontradas` text DEFAULT NULL,
  `afectaci_n` text DEFAULT NULL,
  `ubicaci_n_coordenadas` text DEFAULT NULL,
  `latigsm` text DEFAULT NULL,
  `longgsm` text DEFAULT NULL,
  `latitud` text DEFAULT NULL,
  `longitud` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_code` (`asset_code`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sheet_plataformas_`
--

DROP TABLE IF EXISTS `sheet_plataformas_`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sheet_plataformas_` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `_row_hash` varchar(64) NOT NULL,
  `estado_actual` enum('USADO','ENTREGADO','NO_APARECE','DANADO') DEFAULT 'USADO',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `col` varchar(255) DEFAULT NULL,
  `servidor` varchar(255) DEFAULT NULL,
  `ip` varchar(255) DEFAULT NULL,
  `usuario` varchar(255) DEFAULT NULL,
  `contrase_a_actual` varchar(255) DEFAULT NULL,
  `col_1` varchar(255) DEFAULT NULL,
  `observaciones` varchar(255) DEFAULT NULL,
  `acceso` varchar(255) DEFAULT NULL,
  `unidad_de_negocio` varchar(255) DEFAULT NULL,
  `sistema_plataforma` varchar(255) DEFAULT NULL,
  `detalle` varchar(255) DEFAULT NULL,
  `nivel_de_permiso` varchar(255) DEFAULT NULL,
  `responsable_de_aprobar` varchar(255) DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `contrase_a` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_sheet_plataformas__rowhash` (`_row_hash`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sheet_relaciones`
--

DROP TABLE IF EXISTS `sheet_relaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sheet_relaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `_row_hash` varchar(64) NOT NULL,
  `estado_actual` enum('USADO','ENTREGADO','NO_APARECE','DANADO') DEFAULT 'USADO',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ci_origen_servicio_hostname` varchar(255) DEFAULT NULL,
  `relaci_n` varchar(255) DEFAULT NULL,
  `ci_destino_infraestructura_hostname` varchar(255) DEFAULT NULL,
  `observaciones` varchar(255) DEFAULT NULL,
  `col` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_sheet_relaciones_rowhash` (`_row_hash`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sheet_servers_f_sicos`
--

DROP TABLE IF EXISTS `sheet_servers_f_sicos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sheet_servers_f_sicos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `_row_hash` varchar(64) NOT NULL,
  `estado_actual` enum('USADO','ENTREGADO','NO_APARECE','DANADO') DEFAULT 'USADO',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `excel_id` varchar(255) DEFAULT NULL,
  `unidad` varchar(255) DEFAULT NULL,
  `empresa` varchar(255) DEFAULT NULL,
  `localidad` varchar(255) DEFAULT NULL,
  `area` varchar(255) DEFAULT NULL,
  `ubicaci_n_rack_u` varchar(255) DEFAULT NULL,
  `custodio` varchar(255) DEFAULT NULL,
  `rentado` varchar(255) DEFAULT NULL,
  `tipo` varchar(255) DEFAULT NULL,
  `rol` varchar(255) DEFAULT NULL,
  `marca` varchar(255) DEFAULT NULL,
  `modelo` varchar(255) DEFAULT NULL,
  `serie` varchar(255) DEFAULT NULL,
  `ilo` varchar(255) DEFAULT NULL,
  `estado_equipos` varchar(255) DEFAULT NULL,
  `direcci_n_ip` varchar(255) DEFAULT NULL,
  `validaci_n_f_sica_existencia` varchar(255) DEFAULT NULL,
  `existencia_inventario_ilum` varchar(255) DEFAULT NULL,
  `referencia` varchar(255) DEFAULT NULL,
  `observaciones` varchar(255) DEFAULT NULL,
  `estado` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_sheet_servers_f_sicos_rowhash` (`_row_hash`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sheet_servers_virtuales`
--

DROP TABLE IF EXISTS `sheet_servers_virtuales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sheet_servers_virtuales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `_row_hash` varchar(64) NOT NULL,
  `estado_actual` enum('USADO','ENTREGADO','NO_APARECE','DANADO') DEFAULT 'USADO',
  `asset_code` varchar(50) DEFAULT NULL,
  `zabbix_host_id` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `item_id` text DEFAULT NULL,
  `pais` text DEFAULT NULL,
  `ciudad` text DEFAULT NULL,
  `sucursal` text DEFAULT NULL,
  `unidad` text DEFAULT NULL,
  `hostgroup` text DEFAULT NULL,
  `departamento` text DEFAULT NULL,
  `hostname` text DEFAULT NULL,
  `visible` text DEFAULT NULL,
  `ip` text DEFAULT NULL,
  `visiblename` text DEFAULT NULL,
  `tipo` text DEFAULT NULL,
  `so` text DEFAULT NULL,
  `seccion` text DEFAULT NULL,
  `cpu` text DEFAULT NULL,
  `ram` text DEFAULT NULL,
  `disk` text DEFAULT NULL,
  `zabbixagente` text DEFAULT NULL,
  `ubicaci_n` text DEFAULT NULL,
  `porpietario` text DEFAULT NULL,
  `estado` text DEFAULT NULL,
  `servicio` text DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `umbrales` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_code` (`asset_code`)
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sheet_servicios`
--

DROP TABLE IF EXISTS `sheet_servicios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sheet_servicios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `_row_hash` varchar(64) NOT NULL,
  `estado_actual` enum('USADO','ENTREGADO','NO_APARECE','DANADO') DEFAULT 'USADO',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `nombre_del_servicio` varchar(255) DEFAULT NULL,
  `descripci_n` varchar(255) DEFAULT NULL,
  `criticidad` varchar(255) DEFAULT NULL,
  `usuarios_afectados` varchar(255) DEFAULT NULL,
  `horario_de_operaci_n` varchar(255) DEFAULT NULL,
  `sla_de_resoluci_n` varchar(255) DEFAULT NULL,
  `proveedor_de_soporte` varchar(255) DEFAULT NULL,
  `escalamiento_l3` varchar(255) DEFAULT NULL,
  `col` varchar(255) DEFAULT NULL,
  `excel_id` varchar(255) DEFAULT NULL,
  `col_1` varchar(255) DEFAULT NULL,
  `col_2` varchar(255) DEFAULT NULL,
  `col_3` varchar(255) DEFAULT NULL,
  `col_4` varchar(255) DEFAULT NULL,
  `col_5` varchar(255) DEFAULT NULL,
  `col_6` varchar(255) DEFAULT NULL,
  `col_7` varchar(255) DEFAULT NULL,
  `col_8` varchar(255) DEFAULT NULL,
  `col_9` varchar(255) DEFAULT NULL,
  `col_10` varchar(255) DEFAULT NULL,
  `col_11` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_sheet_servicios_rowhash` (`_row_hash`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sheet_ups`
--

DROP TABLE IF EXISTS `sheet_ups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sheet_ups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `_row_hash` varchar(64) NOT NULL,
  `estado_actual` enum('USADO','ENTREGADO','NO_APARECE','DANADO') DEFAULT 'USADO',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `unidad` varchar(255) DEFAULT NULL,
  `empresa` varchar(255) DEFAULT NULL,
  `localidad` varchar(255) DEFAULT NULL,
  `area` varchar(255) DEFAULT NULL,
  `rentado` varchar(255) DEFAULT NULL,
  `codigo_provedor` varchar(255) DEFAULT NULL,
  `tipo` varchar(255) DEFAULT NULL,
  `marca` varchar(255) DEFAULT NULL,
  `modelo` varchar(255) DEFAULT NULL,
  `serie` varchar(255) DEFAULT NULL,
  `estado` varchar(255) DEFAULT NULL,
  `direcci_n_ip` varchar(255) DEFAULT NULL,
  `existencia_on_site` varchar(255) DEFAULT NULL,
  `existe_seg_n_inventario_ilum` varchar(255) DEFAULT NULL,
  `observaciones` varchar(255) DEFAULT NULL,
  `custodio` varchar(255) DEFAULT NULL,
  `estado_1` varchar(255) DEFAULT NULL,
  `asset_code` varchar(50) DEFAULT NULL,
  `zabbix_host_id` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_sheet_ups_rowhash` (`_row_hash`),
  UNIQUE KEY `asset_code` (`asset_code`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sheet_vmplataformas_`
--

DROP TABLE IF EXISTS `sheet_vmplataformas_`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sheet_vmplataformas_` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `_row_hash` varchar(64) NOT NULL,
  `estado_actual` enum('USADO','ENTREGADO','NO_APARECE','DANADO') DEFAULT 'USADO',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `unidad_de_negocio` varchar(255) DEFAULT NULL,
  `servidor` varchar(255) DEFAULT NULL,
  `ip` varchar(255) DEFAULT NULL,
  `usuario` varchar(255) DEFAULT NULL,
  `contrase_a_actual` varchar(255) DEFAULT NULL,
  `data` varchar(255) DEFAULT NULL,
  `observaciones` varchar(255) DEFAULT NULL,
  `acceso` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_sheet_vmplataformas__rowhash` (`_row_hash`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `snmp_communities`
--

DROP TABLE IF EXISTS `snmp_communities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `snmp_communities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `community` varchar(255) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `community` (`community`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `snmp_scan_results`
--

DROP TABLE IF EXISTS `snmp_scan_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `snmp_scan_results` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(50) NOT NULL,
  `community_ok` varchar(255) NOT NULL,
  `table_source` varchar(100) NOT NULL,
  `row_id` int(11) NOT NULL,
  `last_success` datetime DEFAULT current_timestamp(),
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_ip_source` (`ip`,`table_source`,`row_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `zabbix_api_config`
--

DROP TABLE IF EXISTS `zabbix_api_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `zabbix_api_config` (
  `id` int(11) NOT NULL DEFAULT 1,
  `url` varchar(255) DEFAULT NULL,
  `token` varchar(255) DEFAULT NULL,
  `version` varchar(20) DEFAULT '7.0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `zabbix_cmdb_config`
--

DROP TABLE IF EXISTS `zabbix_cmdb_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `zabbix_cmdb_config` (
  `table_name` varchar(255) NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`table_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `zabbix_mappings`
--

DROP TABLE IF EXISTS `zabbix_mappings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `zabbix_mappings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cmdb_table_name` varchar(100) NOT NULL,
  `hostname_template` varchar(255) DEFAULT NULL,
  `visible_name_template` varchar(255) DEFAULT NULL,
  `hostgroup_template` varchar(255) DEFAULT NULL,
  `ip_field` varchar(100) DEFAULT NULL,
  `snmp_community_field` varchar(100) DEFAULT NULL,
  `template_name` varchar(255) DEFAULT NULL,
  `inventory_fields_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`inventory_fields_json`)),
  `tags_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags_json`)),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `cmdb_table_name` (`cmdb_table_name`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-04-08 16:06:36

-- SEED DATA
-- Roles
INSERT IGNORE INTO roles (id, name) VALUES (1, 'SUPER_ADMIN'), (2, 'ADMIN'), (3, 'USER');

INSERT IGNORE INTO users (id, username, password, role_id) VALUES (1, 'superadmin', '$2y$10$VUe3tu1Vf1jJIWSUVEZHbe4UcfNUOtUWnZKWC5PQ4tx1XPOhBYiZG', 1);

-- Zabbix API Config
INSERT IGNORE INTO zabbix_api_config (id, url, token) VALUES (1, 'http://172.32.1.51/zabbix/api_jsonrpc.php', '23c5e835efd1c26742b6848ee63b2547ce5349efb88b4ecefee83fa27683cb9a');

-- Zabbix Master Tables (Initial load)
INSERT IGNORE INTO zabbix_cmdb_config (table_name, is_enabled) VALUES 
('sheet_ap', 1), ('sheet_equipos', 1), ('sheet_firewall', 1), ('sheet_servers_virtuales', 1), ('sheet_ups', 1);

