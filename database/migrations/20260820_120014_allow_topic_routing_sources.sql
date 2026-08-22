ALTER TABLE routing_sources
DROP CONSTRAINT IF EXISTS routing_sources_type_check;

ALTER TABLE routing_sources
ADD CONSTRAINT routing_sources_type_check CHECK (type IN ('direct', 'fanout', 'topic'));

INSERT INTO schema_migrations (version)
VALUES ('20260820_120014_allow_topic_routing_sources')
ON CONFLICT (version) DO NOTHING;
