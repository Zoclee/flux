CREATE TABLE IF NOT EXISTS message_routes (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    message_id bigint NOT NULL REFERENCES messages (id),
    destination_id bigint NOT NULL REFERENCES destinations (id),
    available_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at timestamptz,
    created_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT message_routes_expires_after_created_check CHECK (
        expires_at IS NULL OR expires_at > created_at
    ),
    CONSTRAINT message_routes_message_destination_unique UNIQUE (message_id, destination_id)
);

CREATE INDEX IF NOT EXISTS message_routes_destination_available_idx ON message_routes (destination_id, available_at, id);
CREATE INDEX IF NOT EXISTS message_routes_expires_at_idx ON message_routes (expires_at) WHERE expires_at IS NOT NULL;

INSERT INTO schema_migrations (version)
VALUES ('20260820_120005_create_message_routes')
ON CONFLICT (version) DO NOTHING;
