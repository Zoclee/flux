CREATE TABLE IF NOT EXISTS virtual_hosts (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name text NOT NULL,
    created_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT virtual_hosts_name_unique UNIQUE (name),
    CONSTRAINT virtual_hosts_name_not_empty CHECK (name <> '')
);

INSERT INTO virtual_hosts (name)
VALUES ('/')
ON CONFLICT (name) DO NOTHING;

INSERT INTO schema_migrations (version)
VALUES ('20260820_120001_create_virtual_hosts')
ON CONFLICT (version) DO NOTHING;
