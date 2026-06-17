<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/db.php';

$pdo = getPDO();

try {
    echo "Starting schema migration...\n";

    // 1. Add rotation to dc_racks if it doesn't exist
    $stmt = $pdo->query("SHOW COLUMNS FROM `dc_racks` LIKE 'rotation'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE `dc_racks` ADD COLUMN `rotation` INT NOT NULL DEFAULT 0 AFTER `depth_tiles`");
        echo "Added 'rotation' column to dc_racks.\n";
    } else {
        echo "'rotation' column already exists in dc_racks.\n";
    }

    // 2. Create dc_floor_layers
    $sql_layers = "
        CREATE TABLE IF NOT EXISTS `dc_floor_layers` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `z_index` int(11) NOT NULL DEFAULT 10,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql_layers);
    echo "Created dc_floor_layers table.\n";

    // Check if we need to insert default layers
    $stmt = $pdo->query("SELECT COUNT(*) FROM `dc_floor_layers`");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO `dc_floor_layers` (`name`, `z_index`) VALUES ('Piso Perforado', 1), ('Aire Acondicionado', 5), ('Racks', 10), ('UPS', 11), ('Escalerillas', 20)");
        echo "Inserted default layers.\n";
    }

    // 3. Create dc_floor_items
    $sql_items = "
        CREATE TABLE IF NOT EXISTS `dc_floor_items` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `room_id` int(11) NOT NULL,
            `name` varchar(255) NOT NULL,
            `type` varchar(50) NOT NULL,
            `layer_id` int(11) DEFAULT NULL,
            `grid_x` int(11) DEFAULT 0,
            `grid_y` int(11) DEFAULT 0,
            `width_tiles` int(11) DEFAULT 1,
            `depth_tiles` int(11) DEFAULT 1,
            `height_meters` decimal(10,2) DEFAULT 0.0,
            `rotation` int(11) NOT NULL DEFAULT 0,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `room_id` (`room_id`),
            KEY `layer_id` (`layer_id`),
            CONSTRAINT `dc_floor_items_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `dc_rooms` (`id`) ON DELETE CASCADE,
            CONSTRAINT `dc_floor_items_ibfk_2` FOREIGN KEY (`layer_id`) REFERENCES `dc_floor_layers` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql_items);
    echo "Created dc_floor_items table.\n";

    echo "Migration completed successfully!\n";

} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
