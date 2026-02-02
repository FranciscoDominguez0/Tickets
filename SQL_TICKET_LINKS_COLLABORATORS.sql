-- Tablas auxiliares para tickets: vinculados y colaboradores
USE tickets_db;

-- Tickets vinculados (relación entre dos tickets)
CREATE TABLE IF NOT EXISTS ticket_links (
  id INT PRIMARY KEY AUTO_INCREMENT,
  ticket_id INT NOT NULL,
  linked_ticket_id INT NOT NULL,
  created DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
  FOREIGN KEY (linked_ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
  UNIQUE KEY unique_link (ticket_id, linked_ticket_id),
  KEY idx_ticket_id (ticket_id),
  KEY idx_linked (linked_ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Colaboradores por ticket (usuarios adicionales que pueden ver/participar)
CREATE TABLE IF NOT EXISTS ticket_collaborators (
  id INT PRIMARY KEY AUTO_INCREMENT,
  ticket_id INT NOT NULL,
  user_id INT NOT NULL,
  created DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_collab (ticket_id, user_id),
  KEY idx_ticket_id (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
