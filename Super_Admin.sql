CREATE TABLE IF NOT EXISTS `super_admins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(255) UNIQUE NOT NULL,
    `email` VARCHAR(255) UNIQUE NOT NULL,
    `firstname` VARCHAR(255) NOT NULL,
    `lastname` VARCHAR(255) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `dark_mode` TINYINT(1) DEFAULT 0,
    `created` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `last_login` DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `super_admins` 
    (`username`, `email`, `firstname`, `lastname`, `password`, `is_active`, `created`, `updated`) 
VALUES 
    ('superadmin', 'superadmin@admin.com', 'Super', 'Admin', '$2y$12$/wYpCXu78LVL8CH16wyo1ebzCOFothQW27Na.lZa0a6OMNriHqUyC', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `updated` = NOW();
