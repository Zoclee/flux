ALTER TABLE messages
    ADD COLUMN IF NOT EXISTS metadata jsonb NOT NULL DEFAULT '{}'::jsonb;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'messages_metadata_object_check'
    ) THEN
        ALTER TABLE messages
            ADD CONSTRAINT messages_metadata_object_check CHECK (jsonb_typeof(metadata) = 'object');
    END IF;
END $$;

INSERT INTO schema_migrations (version)
VALUES ('20260820_120015_add_message_metadata')
ON CONFLICT (version) DO NOTHING;
