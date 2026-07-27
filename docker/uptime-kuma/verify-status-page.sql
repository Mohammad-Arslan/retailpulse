SELECT id, slug, title, published FROM status_page;
SELECT g.id, g.name, g.status_page_id FROM "group" g;
SELECT COUNT(*) AS links FROM monitor_group;
