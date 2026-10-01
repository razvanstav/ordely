CREATE TABLE invoice_issue_intents (
 id BINARY(16) PRIMARY KEY, merchant_id BINARY(16) NOT NULL, store_id BINARY(16) NOT NULL,
 order_id BINARY(16) NOT NULL, draft_version INT UNSIGNED NOT NULL CHECK(draft_version>0),
 connection_id BINARY(16) NOT NULL, connection_version BIGINT UNSIGNED NOT NULL,
 profile_version INT UNSIGNED NOT NULL CHECK(profile_version>0),
 snapshot_envelope MEDIUMTEXT NOT NULL, snapshot_hash BINARY(32) NOT NULL,
 correlation_id BINARY(16) NOT NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 UNIQUE(merchant_id,id), UNIQUE(merchant_id,store_id,order_id), INDEX(merchant_id,store_id,created_at),
 FOREIGN KEY(merchant_id,store_id) REFERENCES stores(merchant_id,id)
) ENGINE=InnoDB;
