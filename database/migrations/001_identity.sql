CREATE TABLE merchants (
 id BINARY(16) PRIMARY KEY, name VARCHAR(160) NOT NULL,
 status VARCHAR(16) NOT NULL DEFAULT 'active' CHECK (status IN ('active','disabled')),
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB;
CREATE TABLE users (
 id BINARY(16) PRIMARY KEY, email VARCHAR(254) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL,
 status VARCHAR(16) NOT NULL DEFAULT 'active' CHECK (status IN ('active','disabled')),
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB;
CREATE TABLE memberships (
 id BINARY(16) PRIMARY KEY, merchant_id BINARY(16) NOT NULL, user_id BINARY(16) NOT NULL,
 role VARCHAR(16) NOT NULL CHECK (role IN ('owner','admin','operator','finance','viewer')),
 all_stores BOOLEAN NOT NULL DEFAULT FALSE,
 status VARCHAR(16) NOT NULL DEFAULT 'active' CHECK (status IN ('active','disabled')),
 UNIQUE (merchant_id, id), UNIQUE (merchant_id, user_id), UNIQUE (merchant_id, id, user_id),
 FOREIGN KEY (merchant_id) REFERENCES merchants(id), FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE stores (
 id BINARY(16) PRIMARY KEY, merchant_id BINARY(16) NOT NULL, name VARCHAR(160) NOT NULL,
 platform_key VARCHAR(40) NOT NULL,
 status VARCHAR(16) NOT NULL DEFAULT 'active' CHECK (status IN ('active','disabled')),
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 UNIQUE (merchant_id, id), FOREIGN KEY (merchant_id) REFERENCES merchants(id)
) ENGINE=InnoDB;
CREATE TABLE membership_store_grants (
 merchant_id BINARY(16) NOT NULL, membership_id BINARY(16) NOT NULL, store_id BINARY(16) NOT NULL,
 PRIMARY KEY (merchant_id, membership_id, store_id),
 FOREIGN KEY (merchant_id, membership_id) REFERENCES memberships(merchant_id, id),
 FOREIGN KEY (merchant_id, store_id) REFERENCES stores(merchant_id, id)
) ENGINE=InnoDB;
CREATE TABLE auth_sessions (
 token_hash BINARY(32) PRIMARY KEY, merchant_id BINARY(16) NOT NULL, membership_id BINARY(16) NOT NULL, user_id BINARY(16) NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), expires_at DATETIME(6) NOT NULL, revoked_at DATETIME(6) NULL,
 INDEX (expires_at), FOREIGN KEY (merchant_id, membership_id, user_id) REFERENCES memberships(merchant_id, id, user_id)
) ENGINE=InnoDB;
CREATE TABLE auth_rate_limits (
 bucket BINARY(32) PRIMARY KEY, attempts INT UNSIGNED NOT NULL, expires_at DATETIME(6) NOT NULL,
 INDEX (expires_at)
) ENGINE=InnoDB;
