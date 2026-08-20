CREATE TABLE IF NOT EXISTS bindings (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    virtual_host_id bigint NOT NULL REFERENCES virtual_hosts (id),
    source text NOT NULL,
    destination_id bigint NOT NULL REFERENCES destinations (id),
    routing_key text NOT NULL DEFAULT '',
    metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT bindings_source_not_empty CHECK (source <> ''),
    CONSTRAINT bindings_metadata_object_check CHECK (jsonb_typeof(metadata) = 'object'),
    CONSTRAINT bindings_source_destination_routing_key_unique UNIQUE (
        virtual_host_id,
        source,
        destination_id,
        routing_key
    )
);

CREATE INDEX IF NOT EXISTS bindings_route_lookup_idx ON bindings (virtual_host_id, source, routing_key);
CREATE INDEX IF NOT EXISTS bindings_destination_id_idx ON bindings (destination_id);

INSERT INTO schema_migrations (version)
VALUES ('20260820_120003_create_bindings')
ON CONFLICT (version) DO NOTHING;
