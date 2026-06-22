-- Migration: add Gedung/Lantai master + link access_point -> lantai
-- Jalankan ini jika database sudah terlanjur dibuat dari schema lama.

USE wifi_monitoring;

CREATE TABLE IF NOT EXISTS gedung (
  id_gedung INT NOT NULL AUTO_INCREMENT,
  nama_gedung VARCHAR(100) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_gedung),
  UNIQUE KEY uq_gedung_nama (nama_gedung)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS lantai (
  id_lantai INT NOT NULL AUTO_INCREMENT,
  id_gedung INT NOT NULL,
  nama_lantai VARCHAR(50) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_lantai),
  UNIQUE KEY uq_lantai_gedung_nama (id_gedung, nama_lantai),
  KEY idx_lantai_gedung (id_gedung),
  CONSTRAINT fk_lantai_gedung FOREIGN KEY (id_gedung)
    REFERENCES gedung (id_gedung)
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- Add column/index (MariaDB supports IF NOT EXISTS)
ALTER TABLE access_point
  ADD COLUMN IF NOT EXISTS id_lantai INT NULL;

ALTER TABLE access_point
  ADD INDEX IF NOT EXISTS idx_access_point_lantai (id_lantai);

-- Add FK (if it doesn't exist yet, run once)
-- NOTE: MariaDB/MySQL tidak punya IF NOT EXISTS untuk constraint.
-- Jika error constraint sudah ada, abaikan.
ALTER TABLE access_point
  ADD CONSTRAINT fk_access_point_lantai FOREIGN KEY (id_lantai)
    REFERENCES lantai (id_lantai)
    ON DELETE SET NULL;
