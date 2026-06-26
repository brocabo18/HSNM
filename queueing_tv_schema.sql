-- Table structure for Queueing TVs (PostgreSQL)
CREATE TABLE IF NOT EXISTS queueing_tvs (
  id SERIAL PRIMARY KEY,
  location_id INTEGER,
  department_id INTEGER,
  remote_status VARCHAR(50) DEFAULT 'No',
  remote_link VARCHAR(255) DEFAULT '',
  queuing_link VARCHAR(255) DEFAULT '',
  ip_address VARCHAR(50) DEFAULT '',
  anydesk_id VARCHAR(100) DEFAULT '',
  teamviewer_id VARCHAR(100) DEFAULT '',
  rustdesk_id VARCHAR(100) DEFAULT '',
  password VARCHAR(100) DEFAULT '',
  remarks TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_queueing_tvs_location ON queueing_tvs(location_id);
CREATE INDEX IF NOT EXISTS idx_queueing_tvs_department ON queueing_tvs(department_id);
