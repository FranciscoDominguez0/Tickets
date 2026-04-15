START TRANSACTION;

CREATE TABLE IF NOT EXISTS staff_departments (
  staff_id INT NOT NULL,
  dept_id  INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  -- PK compuesta: un staff no puede repetirse en el mismo dept
  PRIMARY KEY (staff_id, dept_id),

  -- Índices para queries comunes
  KEY idx_sd_dept_id (dept_id),
  KEY idx_sd_staff_id (staff_id),

  -- FKs
  CONSTRAINT fk_sd_staff
    FOREIGN KEY (staff_id) REFERENCES staff(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,

  CONSTRAINT fk_sd_department
    FOREIGN KEY (dept_id) REFERENCES departments(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;





START TRANSACTION;

INSERT IGNORE INTO staff_departments (staff_id, dept_id)
SELECT s.id, s.dept_id
FROM staff s
WHERE s.dept_id IS NOT NULL AND s.dept_id > 0;

COMMIT;