-- Apply once when the new migration cannot be run through the hosting console.
-- Do not run this as well as the migration.
CREATE TABLE IF NOT EXISTS delivery_locations (
  delivery_user_id INT UNSIGNED NOT NULL,
  order_id INT UNSIGNED NOT NULL,
  assignment_id INT UNSIGNED NOT NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  accuracy DECIMAL(7,2) NULL,
  captured_at_ms BIGINT UNSIGNED NOT NULL,
  recorded_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  PRIMARY KEY (delivery_user_id),
  KEY (order_id),
  KEY (expires_at),
  FOREIGN KEY (delivery_user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (assignment_id) REFERENCES delivery_assignments(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;
