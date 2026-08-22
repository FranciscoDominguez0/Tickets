CREATE TABLE IF NOT EXISTS requisitions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    agent_id INT NOT NULL,
    client_id INT NULL,
    client_name VARCHAR(255) NOT NULL,
    status ENUM('pending', 'delivered') NOT NULL DEFAULT 'pending',
    admin_id_delivered INT NULL,
    agent_signature LONGTEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    delivered_at DATETIME NULL,
    signed_at DATETIME NULL,
    INDEX (empresa_id),
    INDEX (agent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS requisition_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requisition_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    FOREIGN KEY (requisition_id) REFERENCES requisitions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
