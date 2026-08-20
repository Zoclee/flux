DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'destinations_id_virtual_host_id_unique'
    ) THEN
        ALTER TABLE destinations
            ADD CONSTRAINT destinations_id_virtual_host_id_unique UNIQUE (id, virtual_host_id);
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'bindings_destination_virtual_host_fk'
    ) THEN
        ALTER TABLE bindings
            ADD CONSTRAINT bindings_destination_virtual_host_fk
            FOREIGN KEY (destination_id, virtual_host_id)
            REFERENCES destinations (id, virtual_host_id);
    END IF;
END
$$;

INSERT INTO schema_migrations (version)
VALUES ('20260820_120008_enforce_binding_destination_virtual_host')
ON CONFLICT (version) DO NOTHING;
