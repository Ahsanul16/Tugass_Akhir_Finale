-- Default users for testing
USE wifi_monitoring;

INSERT INTO users (username, password, nama, role)
VALUES
  ('superadmin', '$2y$10$oVlkprHjVaqxSWYbI1EWzuaAnc0ukk.ZvOzjzOYQ7v4DyKw7oEmcW', 'Super Admin', 'superadmin'),
  ('admin', '$2y$10$v4HrODGF2N5snE7eMHTrMuMEE/NnfjSmFLpNoMFPm.jpcrxrdZzWy', 'Admin', 'admin')
ON DUPLICATE KEY UPDATE
  password = VALUES(password),
  nama = VALUES(nama),
  role = VALUES(role);

-- Sample location master data
INSERT INTO gedung (nama_gedung)
VALUES ('Gedung A')
ON DUPLICATE KEY UPDATE nama_gedung = VALUES(nama_gedung);

SET @gid := (SELECT id_gedung FROM gedung WHERE nama_gedung = 'Gedung A' LIMIT 1);

INSERT INTO lantai (id_gedung, nama_lantai)
VALUES (@gid, 'Lantai 1')
ON DUPLICATE KEY UPDATE nama_lantai = VALUES(nama_lantai);
