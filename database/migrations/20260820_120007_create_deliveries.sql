CREATE TABLE IF NOT EXISTS deliveries (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    message_route_id bigint NOT NULL REFERENCES message_routes (id),
    subscription_id bigint NOT NULL REFERENCES subscriptions (id),
    state text NOT NULL DEFAULT 'pending',
    consumer_id text,
    delivery_tag text,
    attempts integer NOT NULL DEFAULT 0,
    reserved_at timestamptz,
    acknowledged_at timestamptz,
    available_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT deliveries_state_check CHECK (state IN ('pending', 'reserved', 'acknowledged', 'rejected')),
    CONSTRAINT deliveries_attempts_non_negative_check CHECK (attempts >= 0),
    CONSTRAINT deliveries_acknowledged_timestamp_check CHECK (
        state <> 'acknowledged' OR acknowledged_at IS NOT NULL
    ),
    CONSTRAINT deliveries_message_route_subscription_unique UNIQUE (message_route_id, subscription_id)
);

CREATE INDEX IF NOT EXISTS deliveries_pending_claim_idx
    ON deliveries (subscription_id, available_at, id)
    WHERE state = 'pending';

CREATE INDEX IF NOT EXISTS deliveries_message_route_id_idx ON deliveries (message_route_id);
CREATE INDEX IF NOT EXISTS deliveries_consumer_id_idx ON deliveries (consumer_id) WHERE consumer_id IS NOT NULL;

INSERT INTO schema_migrations (version)
VALUES ('20260820_120007_create_deliveries')
ON CONFLICT (version) DO NOTHING;
