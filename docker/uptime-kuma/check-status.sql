SELECT m.id, m.name, m.type, h.status
FROM monitor m
LEFT JOIN heartbeat h ON h.id = (
  SELECT id FROM heartbeat WHERE monitor_id = m.id ORDER BY time DESC LIMIT 1
)
ORDER BY m.id;
