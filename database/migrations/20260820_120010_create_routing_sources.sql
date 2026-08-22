CREATE TABLE IF NOT EXISTS routing_sources (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    virtual_host_id bigint NOT NULL REFERENCES virtual_hosts (id),
    name text NOT NULL,
    type text NOT NULL,
    durable boolean NOT NULL DEFAULT true,
    auto_delete boolean NOT NULL DEFAULT false,
    metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT routing_sources_name_not_empty CHECK (name <> ''),
    CONSTRAINT routing_sources_type_check CHECK (type IN ('direct')),
    CONSTRAINT routing_sources_metadata_object_check CHECK (jsonb_typeof(metadata) = 'object'),
    CONSTRAINT routing_sources_virtual_host_name_unique UNIQUE (virtual_host_id, name)
);

CREATE INDEX IF NOT EXISTS routing_sources_virtual_host_id_idx ON routing_sources (virtual_host_id);

INSERT INTO schema_migrations (version)
VALUES ('20260820_120010_create_routing_sources')
ON CONFLICT (version) DO NOTHING;
