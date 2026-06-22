-- WIFI Monitoring schema (MySQL/MariaDB)

CREATE DATABASE IF NOT EXISTS wifi_monitoring
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_general_ci;

USE wifi_monitoring;

-- Gedung (building master)
CREATE TABLE IF NOT EXISTS gedung (
  id_gedung INT NOT NULL AUTO_INCREMENT,
  nama_gedung VARCHAR(100) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_gedung),
  UNIQUE KEY uq_gedung_nama (nama_gedung)
) ENGINE=InnoDB;

-- Lantai (floor master), belongs to gedung
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

-- Users
CREATE TABLE IF NOT EXISTS users (
  id_user INT NOT NULL AUTO_INCREMENT,
  username VARCHAR(50) NOT NULL,
  password VARCHAR(255) NOT NULL,
  nama VARCHAR(100) NOT NULL,
  role ENUM('superadmin','admin') NOT NULL DEFAULT 'admin',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_user),
  UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB;

-- Access point master
CREATE TABLE IF NOT EXISTS access_point (
  id_ap INT NOT NULL AUTO_INCREMENT,
  nama_ap VARCHAR(100) NOT NULL,
  merk VARCHAR(50) DEFAULT NULL,
  lokasi VARCHAR(150) DEFAULT NULL,
  id_lantai INT DEFAULT NULL,
  ip_address VARCHAR(45) NOT NULL,
  community VARCHAR(100) NOT NULL,
  oid_status VARCHAR(100) DEFAULT NULL,
  oid_traffic_in VARCHAR(100) DEFAULT NULL,
  oid_traffic_out VARCHAR(100) DEFAULT NULL,
  oid_user VARCHAR(100) DEFAULT NULL,
  status ENUM('Online','Offline') NOT NULL DEFAULT 'Offline',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_ap),
  UNIQUE KEY uq_access_point_ip (ip_address),
  KEY idx_access_point_lantai (id_lantai),
  CONSTRAINT fk_access_point_lantai FOREIGN KEY (id_lantai)
    REFERENCES lantai (id_lantai)
    ON DELETE SET NULL
) ENGINE=InnoDB;

-- Monitoring log
CREATE TABLE IF NOT EXISTS monitoring (
  id_monitor BIGINT NOT NULL AUTO_INCREMENT,
  id_ap INT NOT NULL,
  trafik_in DOUBLE NOT NULL DEFAULT 0,
  trafik_out DOUBLE NOT NULL DEFAULT 0,
  jumlah_user INT NOT NULL DEFAULT 0,
  status_ap ENUM('Online','Offline') NOT NULL DEFAULT 'Offline',
  waktu_monitoring DATETIME NOT NULL,
  PRIMARY KEY (id_monitor),
  KEY idx_monitoring_ap (id_ap),
  KEY idx_monitoring_time (waktu_monitoring),
  CONSTRAINT fk_monitoring_ap FOREIGN KEY (id_ap)
    REFERENCES access_point (id_ap)
    ON DELETE CASCADE
) ENGINE=InnoDB;
