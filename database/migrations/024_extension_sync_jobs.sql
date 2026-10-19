-- Metadata must be registered by a fresh PHP request, not the pre-update class definitions.
CREATE TABLE extension_sync_jobs (singleton BOOLEAN PRIMARY KEY DEFAULT TRUE CHECK(singleton), requested_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP);
INSERT INTO extension_sync_jobs(singleton) VALUES(TRUE);
