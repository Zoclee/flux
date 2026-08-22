CREATE TABLE IF NOT EXISTS users (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    username text NOT NULL,
    password_hash text NOT NULL,
    enabled boolean NOT NULL DEFAULT true,
    created_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT users_username_unique UNIQUE (username),
    CONSTRAINT users_username_not_empty CHECK (username <> ''),
    CONSTRAINT users_password_hash_not_empty CHECK (password_hash <> '')
);

CREATE TABLE IF NOT EXISTS user_virtual_hosts (
    user_id bigint NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    virtual_host_id bigint NOT NULL REFERENCES virtual_hosts (id) ON DELETE CASCADE,
    PRIMARY KEY (user_id, virtual_host_id)
);

CREATE INDEX IF NOT EXISTS user_virtual_hosts_virtual_host_id_idx ON user_virtual_hosts (virtual_host_id);

INSERT INTO schema_migrations (version)
VALUES ('20260820_120011_create_users')
ON CONFLICT (version) DO NOTHING;
