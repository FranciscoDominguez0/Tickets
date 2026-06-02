-- Usuario puede pertenecer a varias organizaciones
CREATE TABLE IF NOT EXISTS user_organizations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id INT UNSIGNED NOT NULL DEFAULT 1,
    user_id INT UNSIGNED NOT NULL,
    organization_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_user_org (user_id, organization_id),
    KEY idx_empresa_user (empresa_id, user_id),
    KEY idx_org (organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrar vínculos existentes desde users.company (nombre = organizations.name)
INSERT IGNORE INTO user_organizations (empresa_id, user_id, organization_id, created_at)
SELECT u.empresa_id, u.id, o.id, NOW()
FROM users u
INNER JOIN organizations o ON o.empresa_id = u.empresa_id AND o.name = u.company
WHERE u.company IS NOT NULL AND TRIM(u.company) <> '';
