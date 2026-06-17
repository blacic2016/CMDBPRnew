CREATE TABLE IF NOT EXISTS `ci_categories` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `parent_id` INT(11) DEFAULT NULL,
  `name` VARCHAR(100) NOT NULL,
  `schema_json` JSON DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`parent_id`) REFERENCES `ci_categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ci_instances` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `category_id` INT(11) NOT NULL,
  `hostname` VARCHAR(255) NOT NULL,
  `ip_address` VARCHAR(50) DEFAULT NULL,
  `source` ENUM('manual', 'zabbix') DEFAULT 'manual',
  `zabbix_host_id` VARCHAR(100) DEFAULT NULL,
  `attributes_json` JSON DEFAULT NULL,
  `status` ENUM('Planificación', 'Activo', 'Mantenimiento', 'Retirado') DEFAULT 'Activo',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`category_id`) REFERENCES `ci_categories`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ci_components` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `parent_ci_id` INT(11) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `attributes_json` JSON DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`parent_ci_id`) REFERENCES `ci_instances`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ci_relationships` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `source_type` VARCHAR(50) NOT NULL,
  `source_id` INT(11) NOT NULL,
  `target_type` VARCHAR(50) NOT NULL,
  `target_id` INT(11) NOT NULL,
  `relation_type` VARCHAR(50) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_relation` (`source_type`, `source_id`, `target_type`, `target_id`, `relation_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
