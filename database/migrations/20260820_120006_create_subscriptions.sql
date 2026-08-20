CREATE TABLE IF NOT EXISTS subscriptions (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    destination_id bigint NOT NULL REFERENCES destinations (id),
    name text NOT NULL,
    durable boolean NOT NULL DEFAULT true,
    metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT subscriptions_name_not_empty CHECK (name <> ''),
    CONSTRAINT subscriptions_metadata_object_check CHECK (jsonb_typeof(metadata) = 'object'),
    CONSTRAINT subscriptions_destination_name_unique UNIQUE (destination_id, name)
);

CREATE INDEX IF NOT EXISTS subscriptions_destination_id_idx ON subscriptions (destination_id);

INSERT INTO schema_migrations (version)
VALUES ('20260820_120006_create_subscriptions')
ON CONFLICT (version) DO NOTHING;
