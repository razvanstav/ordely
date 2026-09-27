CREATE TABLE provider_connections (
 id BINARY(16) PRIMARY KEY, merchant_id BINARY(16) NOT NULL,
 kind VARCHAR(16) NOT NULL CHECK (kind IN ('commerce','carrier','invoice')),
 provider_key VARCHAR(64) NOT NULL, label VARCHAR(160) NOT NULL,
 status VARCHAR(16) NOT NULL DEFAULT 'active' CHECK (status IN ('active','revoked')),
 credentials_envelope JSON NOT NULL, version BIGINT UNSIGNED NOT NULL DEFAULT 1,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 revoked_at DATETIME(6) NULL,
 UNIQUE(merchant_id,id), UNIQUE(merchant_id,id,kind),
 FOREIGN KEY(merchant_id) REFERENCES merchants(id)
) ENGINE=InnoDB;
CREATE TABLE store_provider_bindings (
 merchant_id BINARY(16) NOT NULL, store_id BINARY(16) NOT NULL, connection_id BINARY(16) NOT NULL,
 kind VARCHAR(16) NOT NULL, is_default BOOLEAN NOT NULL DEFAULT FALSE,
 default_kind VARCHAR(16) GENERATED ALWAYS AS (IF(is_default,kind,NULL)) STORED,
 PRIMARY KEY(merchant_id,store_id,connection_id), UNIQUE(merchant_id,store_id,default_kind),
 FOREIGN KEY(merchant_id,store_id) REFERENCES stores(merchant_id,id),
 FOREIGN KEY(merchant_id,connection_id,kind) REFERENCES provider_connections(merchant_id,id,kind)
) ENGINE=InnoDB;
ALTER TABLE external_operations ADD CONSTRAINT external_connection_scope FOREIGN KEY(merchant_id,connection_id) REFERENCES provider_connections(merchant_id,id);
