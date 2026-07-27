-- RetailPulse Local status page for Uptime Kuma
-- Restart Uptime Kuma after applying.

DELETE FROM monitor_group WHERE group_id IN (SELECT id FROM "group" WHERE status_page_id IN (SELECT id FROM status_page WHERE slug = 'retailpulse-local'));
DELETE FROM "group" WHERE status_page_id IN (SELECT id FROM status_page WHERE slug = 'retailpulse-local');
DELETE FROM status_page WHERE slug = 'retailpulse-local';

INSERT INTO status_page (
  slug, title, description, icon, theme, published,
  search_engine_index, show_tags, show_powered_by, show_certificate_expiry,
  footer_text
) VALUES (
  'retailpulse-local',
  'RetailPulse Local',
  'Local Docker stack status for RetailPulse (app, data stores, and ops tooling).',
  '/icon.svg',
  'dark',
  1,
  0,
  0,
  1,
  0,
  'RetailPulse — local / staging ops status (not a public production SLA page).'
);

-- Groups (status_page_id resolved by slug)
INSERT INTO "group" (name, public, active, weight, status_page_id)
SELECT 'Application', 1, 1, 1000, id FROM status_page WHERE slug = 'retailpulse-local';

INSERT INTO "group" (name, public, active, weight, status_page_id)
SELECT 'Data stores', 1, 1, 2000, id FROM status_page WHERE slug = 'retailpulse-local';

INSERT INTO "group" (name, public, active, weight, status_page_id)
SELECT 'Ops & observability', 1, 1, 3000, id FROM status_page WHERE slug = 'retailpulse-local';

-- Application monitors
INSERT INTO monitor_group (monitor_id, group_id, weight, send_url)
SELECT m.id, g.id, 1000, 0
FROM monitor m
CROSS JOIN "group" g
JOIN status_page sp ON sp.id = g.status_page_id
WHERE sp.slug = 'retailpulse-local'
  AND g.name = 'Application'
  AND m.name IN (
    'RP App /up (Octane)',
    'RP Reverb (WebSocket port)',
    'RP Host App (browser path)'
  );

-- Data stores
INSERT INTO monitor_group (monitor_id, group_id, weight, send_url)
SELECT m.id, g.id, 1000, 0
FROM monitor m
CROSS JOIN "group" g
JOIN status_page sp ON sp.id = g.status_page_id
WHERE sp.slug = 'retailpulse-local'
  AND g.name = 'Data stores'
  AND m.name IN (
    'RP MySQL',
    'RP Redis',
    'RP MinIO health'
  );

-- Ops & observability
INSERT INTO monitor_group (monitor_id, group_id, weight, send_url)
SELECT m.id, g.id, 1000, 0
FROM monitor m
CROSS JOIN "group" g
JOIN status_page sp ON sp.id = g.status_page_id
WHERE sp.slug = 'retailpulse-local'
  AND g.name = 'Ops & observability'
  AND m.name IN (
    'RP Portainer',
    'RP Jenkins',
    'RP Grafana',
    'RP Prometheus'
  );
