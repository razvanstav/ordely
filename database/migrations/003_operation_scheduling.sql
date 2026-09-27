CREATE INDEX jobs_claim ON jobs(status,available_at,id);
ALTER TABLE external_operations ADD COLUMN next_attempt_at DATETIME(6) NULL;
