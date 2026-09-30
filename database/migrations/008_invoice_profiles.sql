CREATE TABLE invoice_profiles (
 merchant_id BINARY(16) NOT NULL, store_id BINARY(16) NOT NULL,
 connection_id BINARY(16) NOT NULL, connection_version BIGINT UNSIGNED NOT NULL,
 kind VARCHAR(16) NOT NULL DEFAULT 'invoice' CHECK (kind='invoice'),
 version BIGINT UNSIGNED NOT NULL, profile_envelope JSON NOT NULL,
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 PRIMARY KEY(merchant_id,store_id),
 FOREIGN KEY(merchant_id,store_id) REFERENCES stores(merchant_id,id),
 FOREIGN KEY(merchant_id,connection_id,kind) REFERENCES provider_connections(merchant_id,id,kind)
) ENGINE=InnoDB;
