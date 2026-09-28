CREATE TABLE shopify_links (
 shop_domain VARCHAR(253) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 merchant_id BINARY(16) NOT NULL, store_id BINARY(16) NOT NULL,
 connection_id BINARY(16) NULL, installed_at BIGINT UNSIGNED NULL, external_shop_id VARCHAR(32) NULL,
 UNIQUE(merchant_id,store_id),
 FOREIGN KEY(merchant_id,store_id) REFERENCES stores(merchant_id,id),
 FOREIGN KEY(merchant_id,connection_id) REFERENCES provider_connections(merchant_id,id)
) ENGINE=InnoDB;

CREATE TABLE shopify_link_intents (
 code_hash BINARY(32) PRIMARY KEY, merchant_id BINARY(16) NOT NULL,
 membership_id BINARY(16) NOT NULL, user_id BINARY(16) NOT NULL,
 store_id BINARY(16) NOT NULL, shop_domain VARCHAR(253) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 expires_at DATETIME(6) NOT NULL, connection_id BINARY(16) NULL,
 FOREIGN KEY(merchant_id,membership_id,user_id) REFERENCES memberships(merchant_id,id,user_id),
 FOREIGN KEY(merchant_id,store_id) REFERENCES stores(merchant_id,id),
 FOREIGN KEY(merchant_id,connection_id) REFERENCES provider_connections(merchant_id,id),
 INDEX(expires_at)
) ENGINE=InnoDB;

CREATE TABLE shopify_webhook_events (
 id BINARY(16) PRIMARY KEY, merchant_id BINARY(16) NOT NULL, store_id BINARY(16) NOT NULL,
 connection_id BINARY(16) NULL, shop_domain VARCHAR(253) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 delivery_hash BINARY(32) NOT NULL, body_hash BINARY(32) NOT NULL, topic VARCHAR(64) NOT NULL,
 payload_envelope JSON NOT NULL, triggered_at BIGINT UNSIGNED NULL,
 status VARCHAR(24) NOT NULL CHECK(status IN ('received','processed','ignored','needs_review')),
 received_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 UNIQUE(shop_domain,delivery_hash),
 FOREIGN KEY(merchant_id,store_id) REFERENCES stores(merchant_id,id),
 FOREIGN KEY(merchant_id,connection_id) REFERENCES provider_connections(merchant_id,id),
 INDEX(merchant_id,status)
) ENGINE=InnoDB;
