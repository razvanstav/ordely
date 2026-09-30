CREATE TABLE invoice_order_drafts (
 merchant_id BINARY(16) NOT NULL, store_id BINARY(16) NOT NULL, order_id BINARY(16) NOT NULL,
 version BIGINT UNSIGNED NOT NULL, order_version INT UNSIGNED NOT NULL, profile_version BIGINT UNSIGNED NOT NULL,
 snapshot_envelope JSON NOT NULL,
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 PRIMARY KEY(merchant_id,store_id,order_id),
 FOREIGN KEY(merchant_id,store_id,order_id) REFERENCES commerce_records(merchant_id,store_id,id) ON DELETE CASCADE,
 CHECK(version>0 AND order_version>0)
) ENGINE=InnoDB;
