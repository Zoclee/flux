ALTER TABLE deliveries
    ADD COLUMN IF NOT EXISTS destination_id bigint;

UPDATE deliveries
SET destination_id = message_routes.destination_id
FROM message_routes
WHERE deliveries.message_route_id = message_routes.id
  AND deliveries.destination_id IS NULL;

ALTER TABLE deliveries
    ALTER COLUMN destination_id SET NOT NULL;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'message_routes_id_destination_unique'
    ) THEN
        ALTER TABLE message_routes
            ADD CONSTRAINT message_routes_id_destination_unique UNIQUE (id, destination_id);
    END IF;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'subscriptions_id_destination_unique'
    ) THEN
        ALTER TABLE subscriptions
            ADD CONSTRAINT subscriptions_id_destination_unique UNIQUE (id, destination_id);
    END IF;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'deliveries_message_route_destination_fk'
    ) THEN
        ALTER TABLE deliveries
            ADD CONSTRAINT deliveries_message_route_destination_fk
            FOREIGN KEY (message_route_id, destination_id)
            REFERENCES message_routes (id, destination_id);
    END IF;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'deliveries_subscription_destination_fk'
    ) THEN
        ALTER TABLE deliveries
            ADD CONSTRAINT deliveries_subscription_destination_fk
            FOREIGN KEY (subscription_id, destination_id)
            REFERENCES subscriptions (id, destination_id);
    END IF;
END $$;

INSERT INTO schema_migrations (version)
VALUES ('20260820_120009_enforce_delivery_route_subscription_destination')
ON CONFLICT (version) DO NOTHING;
