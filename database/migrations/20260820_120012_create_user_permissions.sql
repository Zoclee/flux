CREATE TABLE IF NOT EXISTS user_permissions (
    user_id bigint NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    virtual_host_id bigint NOT NULL REFERENCES virtual_hosts (id) ON DELETE CASCADE,
    configure_pattern text NOT NULL,
    write_pattern text NOT NULL,
    read_pattern text NOT NULL,
    created_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, virtual_host_id)
);

CREATE INDEX IF NOT EXISTS user_permissions_virtual_host_id_idx ON user_permissions (virtual_host_id);

INSERT INTO schema_migrations (version)
VALUES ('20260820_120012_create_user_permissions')
ON CONFLICT (version) DO NOTHING;
