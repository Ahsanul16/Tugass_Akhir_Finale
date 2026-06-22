-- Performance indexes
USE wifi_monitoring;

-- Gedung / Lantai
ALTER TABLE lantai
  ADD INDEX idx_lantai_gedung (id_gedung);

ALTER TABLE access_point
  ADD INDEX idx_access_point_lantai (id_lantai);

-- Monitoring: paling sering dipakai untuk range + order by
ALTER TABLE monitoring
  ADD INDEX idx_monitoring_ap_time (id_ap, waktu_monitoring);

-- Access point: counts/filter status
ALTER TABLE access_point
  ADD INDEX idx_access_point_status (status);
