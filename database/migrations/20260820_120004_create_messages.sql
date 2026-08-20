CREATE TABLE IF NOT EXISTS messages (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    message_id uuid NOT NULL,
    payload bytea NOT NULL,
    headers jsonb NOT NULL DEFAULT '{}'::jsonb,
    content_type text,
    content_encoding text,
    priority smallint NOT NULL DEFAULT 0,
    persistent boolean NOT NULL DEFAULT true,
    created_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT messages_message_id_unique UNIQUE (message_id),
    CONSTRAINT messages_headers_object_check CHECK (jsonb_typeof(headers) = 'object'),
    CONSTRAINT messages_priority_range_check CHECK (priority >= 0 AND priority <= 255)
);

INSERT INTO schema_migrations (version)
VALUES ('20260820_120004_create_messages')
ON CONFLICT (version) DO NOTHING;
