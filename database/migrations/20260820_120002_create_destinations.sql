CREATE TABLE IF NOT EXISTS destinations (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    virtual_host_id bigint NOT NULL REFERENCES virtual_hosts (id),
    name text NOT NULL,
    type text NOT NULL,
    durable boolean NOT NULL DEFAULT true,
    auto_delete boolean NOT NULL DEFAULT false,
    metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT destinations_name_not_empty CHECK (name <> ''),
    CONSTRAINT destinations_type_check CHECK (type IN ('queue')),
    CONSTRAINT destinations_metadata_object_check CHECK (jsonb_typeof(metadata) = 'object'),
    CONSTRAINT destinations_virtual_host_name_unique UNIQUE (virtual_host_id, name)
);

CREATE INDEX IF NOT EXISTS destinations_virtual_host_id_idx ON destinations (virtual_host_id);

INSERT INTO schema_migrations (version)
VALUES ('20260820_120002_create_destinations')
ON CONFLICT (version) DO NOTHING;
