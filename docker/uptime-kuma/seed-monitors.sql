-- RetailPulse Uptime Kuma monitor seed (local Docker network targets)
-- Applied against /app/data/kuma.db — restart Uptime Kuma after import.

DELETE FROM monitor WHERE user_id = 1 AND name LIKE 'RP %';

INSERT INTO monitor (
  name, active, user_id, interval, url, type, weight, hostname, port,
  maxretries, retry_interval, ignore_tls, upside_down, maxredirects,
  accepted_statuscodes_json, method, timeout, description
) VALUES
(
  'RP App /up (Octane)', 1, 1, 60,
  'http://app:8000/up', 'http', 2000, NULL, NULL,
  2, 30, 0, 0, 10, '["200","204"]', 'GET', 30,
  'Laravel Octane health endpoint via Docker DNS'
),
(
  'RP Reverb (WebSocket port)', 1, 1, 60,
  NULL, 'port', 2000, 'app', 8080,
  2, 30, 0, 0, 10, '["200-299"]', 'GET', 30,
  'Reverb TCP listener on app:8080'
),
(
  'RP MySQL', 1, 1, 60,
  NULL, 'port', 2000, 'mysql', 3306,
  2, 20, 0, 0, 10, '["200-299"]', 'GET', 20,
  'MySQL 8 on Docker network'
),
(
  'RP Redis', 1, 1, 60,
  NULL, 'port', 2000, 'redis', 6379,
  2, 20, 0, 0, 10, '["200-299"]', 'GET', 20,
  'Redis (cache/queue/sessions)'
),
(
  'RP MinIO health', 1, 1, 60,
  'http://minio:9000/minio/health/live', 'http', 2000, NULL, NULL,
  2, 30, 0, 0, 10, '["200-299"]', 'GET', 30,
  'MinIO live health endpoint'
),
(
  'RP Portainer', 1, 1, 120,
  'http://portainer:9000/api/status', 'http', 2000, NULL, NULL,
  2, 60, 0, 0, 10, '["200-299"]', 'GET', 30,
  'Portainer CE API status'
),
(
  'RP Jenkins', 1, 1, 120,
  'http://jenkins:8080/login', 'http', 2000, NULL, NULL,
  2, 60, 0, 0, 10, '["200-299"]', 'GET', 30,
  'Jenkins login page (controller up)'
),
(
  'RP Grafana', 1, 1, 120,
  'http://grafana:3000/api/health', 'http', 2000, NULL, NULL,
  2, 60, 0, 0, 10, '["200-299"]', 'GET', 30,
  'Grafana health (observability stack)'
),
(
  'RP Prometheus', 1, 1, 120,
  'http://prometheus:9090/-/healthy', 'http', 2000, NULL, NULL,
  2, 60, 0, 0, 10, '["200-299"]', 'GET', 30,
  'Prometheus healthy endpoint'
),
(
  'RP Host App (browser path)', 1, 1, 60,
  'http://host.docker.internal:8000/up', 'http', 2000, NULL, NULL,
  2, 30, 0, 0, 10, '["200","204"]', 'GET', 30,
  'Host-published Octane port via Docker Desktop DNS'
);
