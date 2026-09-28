CREATE TABLE commerce_sync_runs (
 id BINARY(16) PRIMARY KEY, merchant_id BINARY(16) NOT NULL, store_id BINARY(16) NOT NULL,
 connection_id BINARY(16) NOT NULL, provider_key VARCHAR(64) NOT NULL,
 status VARCHAR(16) NOT NULL CHECK(status IN ('running','completed','cancelled')),
 since_at VARCHAR(40) NULL, started_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), completed_at DATETIME(6) NULL,
 UNIQUE(merchant_id,store_id,id),
 FOREIGN KEY(merchant_id,store_id) REFERENCES stores(merchant_id,id),
 FOREIGN KEY(merchant_id,connection_id) REFERENCES provider_connections(merchant_id,id)
) ENGINE=InnoDB;

CREATE TABLE commerce_sync_heads (
 merchant_id BINARY(16) NOT NULL, store_id BINARY(16) NOT NULL, provider_key VARCHAR(64) NOT NULL,
 run_id BINARY(16) NULL, watermark VARCHAR(40) NULL,
 PRIMARY KEY(merchant_id,store_id,provider_key),
 FOREIGN KEY(merchant_id,store_id) REFERENCES stores(merchant_id,id),
 FOREIGN KEY(merchant_id,store_id,run_id) REFERENCES commerce_sync_runs(merchant_id,store_id,id)
) ENGINE=InnoDB;

CREATE TABLE commerce_sync_tasks (
 id BINARY(16) PRIMARY KEY, merchant_id BINARY(16) NOT NULL, store_id BINARY(16) NOT NULL, run_id BINARY(16) NOT NULL,
 collection VARCHAR(24) NOT NULL, parent_id VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
 cursor_value VARCHAR(2048) NULL, page_number INT UNSIGNED NOT NULL DEFAULT 0, done BOOLEAN NOT NULL DEFAULT FALSE,
 job_id BINARY(16) NULL,
 UNIQUE(run_id,collection,parent_id),
 FOREIGN KEY(merchant_id,store_id,run_id) REFERENCES commerce_sync_runs(merchant_id,store_id,id),
 FOREIGN KEY(merchant_id,job_id) REFERENCES jobs(merchant_id,id)
) ENGINE=InnoDB;

CREATE TABLE commerce_sync_records (
 merchant_id BINARY(16) NOT NULL, store_id BINARY(16) NOT NULL, run_id BINARY(16) NOT NULL,
 kind VARCHAR(24) NOT NULL, external_id VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 record_id BINARY(16) NOT NULL, source_version VARCHAR(40) NULL, document JSON NOT NULL,
 PRIMARY KEY(run_id,kind,external_id),
 FOREIGN KEY(merchant_id,store_id,run_id) REFERENCES commerce_sync_runs(merchant_id,store_id,id)
) ENGINE=InnoDB;

CREATE TABLE commerce_records (
 id BINARY(16) PRIMARY KEY, merchant_id BINARY(16) NOT NULL, store_id BINARY(16) NOT NULL,
 provider_key VARCHAR(64) NOT NULL, kind VARCHAR(24) NOT NULL CHECK(kind IN ('order','product','variant','inventory')),
 external_id VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 parent_id VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NULL,
 source_version VARCHAR(40) NULL, document JSON NOT NULL, content_hash BINARY(32) NOT NULL,
 version INT UNSIGNED NOT NULL DEFAULT 1, active BOOLEAN NOT NULL DEFAULT TRUE,
 last_run_id BINARY(16) NOT NULL, observed_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 UNIQUE(merchant_id,store_id,provider_key,kind,external_id), UNIQUE(merchant_id,store_id,id),
 FOREIGN KEY(merchant_id,store_id) REFERENCES stores(merchant_id,id),
 FOREIGN KEY(merchant_id,store_id,last_run_id) REFERENCES commerce_sync_runs(merchant_id,store_id,id),
 INDEX(merchant_id,store_id,kind,active,id)
) ENGINE=InnoDB;

CREATE TABLE commerce_privacy_blocks (
 merchant_id BINARY(16) NOT NULL, store_id BINARY(16) NOT NULL,
 subject_type VARCHAR(16) NOT NULL CHECK(subject_type IN ('order','customer','shop')),
 external_id VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 PRIMARY KEY(merchant_id,store_id,subject_type,external_id),
 FOREIGN KEY(merchant_id,store_id) REFERENCES stores(merchant_id,id)
) ENGINE=InnoDB;
