ALTER TABLE stores ADD COLUMN version BIGINT UNSIGNED NOT NULL DEFAULT 1;
CREATE TABLE audit_logs (
 id BINARY(16) PRIMARY KEY, merchant_id BINARY(16) NOT NULL, store_id BINARY(16) NULL,
 actor_type VARCHAR(16) NOT NULL CHECK(actor_type IN ('user','system')), actor_id BINARY(16) NULL,
 action VARCHAR(64) NOT NULL, entity_id BINARY(16) NOT NULL, correlation_id BINARY(16) NOT NULL,
 safe_data JSON NOT NULL, occurred_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 UNIQUE(merchant_id,id), INDEX(merchant_id,store_id,occurred_at),
 FOREIGN KEY(merchant_id) REFERENCES merchants(id), FOREIGN KEY(merchant_id,store_id) REFERENCES stores(merchant_id,id)
) ENGINE=InnoDB;
CREATE TABLE outbox_events (
 id BINARY(16) PRIMARY KEY, merchant_id BINARY(16) NOT NULL, store_id BINARY(16) NULL,
 event_type VARCHAR(64) NOT NULL, schema_version INT UNSIGNED NOT NULL, aggregate_id BINARY(16) NOT NULL,
 aggregate_version BIGINT UNSIGNED NOT NULL, correlation_id BINARY(16) NOT NULL, payload JSON NOT NULL,
 occurred_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), published_at DATETIME(6) NULL,
 UNIQUE(merchant_id,id), INDEX(published_at,occurred_at),
 FOREIGN KEY(merchant_id) REFERENCES merchants(id), FOREIGN KEY(merchant_id,store_id) REFERENCES stores(merchant_id,id)
) ENGINE=InnoDB;
CREATE TABLE jobs (
 id BINARY(16) PRIMARY KEY, merchant_id BINARY(16) NOT NULL, store_id BINARY(16) NULL,
 type VARCHAR(64) NOT NULL, payload JSON NOT NULL, request_hash BINARY(32) NOT NULL, dedupe_key BINARY(32) NOT NULL,
 status VARCHAR(16) NOT NULL DEFAULT 'READY' CHECK(status IN ('READY','RUNNING','SUCCEEDED','DEAD')),
 available_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), lease_until DATETIME(6) NULL, lease_owner BINARY(16) NULL,
 fencing_version BIGINT UNSIGNED NOT NULL DEFAULT 0, attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
 max_attempts INT UNSIGNED NOT NULL DEFAULT 5 CHECK(max_attempts BETWEEN 1 AND 25),
 last_error VARCHAR(32) NULL, completed_at DATETIME(6) NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 UNIQUE(merchant_id,id), UNIQUE(merchant_id,dedupe_key), INDEX(status,available_at,lease_until),
 FOREIGN KEY(merchant_id) REFERENCES merchants(id), FOREIGN KEY(merchant_id,store_id) REFERENCES stores(merchant_id,id)
) ENGINE=InnoDB;
CREATE TABLE job_attempts (
 merchant_id BINARY(16) NOT NULL, job_id BINARY(16) NOT NULL, attempt_number INT UNSIGNED NOT NULL,
 status VARCHAR(32) NOT NULL, started_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 ended_at DATETIME(6) NULL, safe_error VARCHAR(32) NULL,
 PRIMARY KEY(merchant_id,job_id,attempt_number), FOREIGN KEY(merchant_id,job_id) REFERENCES jobs(merchant_id,id)
) ENGINE=InnoDB;
CREATE TABLE event_deliveries (
 merchant_id BINARY(16) NOT NULL, event_id BINARY(16) NOT NULL, consumer VARCHAR(64) NOT NULL,
 job_id BINARY(16) NOT NULL, processed_at DATETIME(6) NULL,
 PRIMARY KEY(merchant_id,event_id,consumer),
 FOREIGN KEY(merchant_id,event_id) REFERENCES outbox_events(merchant_id,id),
 FOREIGN KEY(merchant_id,job_id) REFERENCES jobs(merchant_id,id)
) ENGINE=InnoDB;
CREATE TABLE inbox_events (
 id BINARY(16) PRIMARY KEY, merchant_id BINARY(16) NOT NULL, store_id BINARY(16) NOT NULL,
 source_key BINARY(32) NOT NULL, delivery_key BINARY(32) NOT NULL, request_hash BINARY(32) NOT NULL,
 payload_refs JSON NOT NULL, job_id BINARY(16) NOT NULL, received_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 UNIQUE(merchant_id,id), UNIQUE(merchant_id,store_id,source_key,delivery_key),
 FOREIGN KEY(merchant_id,store_id) REFERENCES stores(merchant_id,id),
 FOREIGN KEY(merchant_id,job_id) REFERENCES jobs(merchant_id,id)
) ENGINE=InnoDB;
CREATE TABLE idempotency_requests (
 merchant_id BINARY(16) NOT NULL, scope_hash BINARY(32) NOT NULL, key_hash BINARY(32) NOT NULL, request_hash BINARY(32) NOT NULL,
 result_refs JSON NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY(merchant_id,scope_hash,key_hash), FOREIGN KEY(merchant_id) REFERENCES merchants(id)
) ENGINE=InnoDB;
CREATE TABLE external_operations (
 id BINARY(16) PRIMARY KEY, merchant_id BINARY(16) NOT NULL, store_id BINARY(16) NOT NULL, connection_id BINARY(16) NOT NULL,
 type VARCHAR(64) NOT NULL, business_key BINARY(32) NOT NULL, request_hash BINARY(32) NOT NULL,
 provider_key VARCHAR(128) NOT NULL,
 status VARCHAR(16) NOT NULL DEFAULT 'PENDING' CHECK(status IN ('PENDING','IN_FLIGHT','CONFIRMED','RETRYABLE','FAILED','UNKNOWN')),
 fencing_version BIGINT UNSIGNED NOT NULL DEFAULT 0, lease_owner BINARY(16) NULL, lease_until DATETIME(6) NULL,
 attempt_count INT UNSIGNED NOT NULL DEFAULT 0, provider_reference VARCHAR(255) COLLATE utf8mb4_bin NULL, safe_error VARCHAR(32) NULL,
 correlation_id BINARY(16) NOT NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 UNIQUE(merchant_id,id), UNIQUE(merchant_id,store_id,type,business_key), UNIQUE(merchant_id,connection_id,provider_key),
 FOREIGN KEY(merchant_id,store_id) REFERENCES stores(merchant_id,id)
) ENGINE=InnoDB;
CREATE TABLE external_attempts (
 merchant_id BINARY(16) NOT NULL, operation_id BINARY(16) NOT NULL, attempt_number INT UNSIGNED NOT NULL,
 status VARCHAR(16) NOT NULL, safe_error VARCHAR(32) NULL, started_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), ended_at DATETIME(6) NULL,
 PRIMARY KEY(merchant_id,operation_id,attempt_number), FOREIGN KEY(merchant_id,operation_id) REFERENCES external_operations(merchant_id,id)
) ENGINE=InnoDB;
