CREATE TABLE invoice_drafts (
 id BINARY(16) PRIMARY KEY, merchant_id BINARY(16) NOT NULL, store_id BINARY(16) NOT NULL,
 status VARCHAR(16) NOT NULL DEFAULT 'DRAFT' CHECK(status IN ('DRAFT','ARCHIVED')),
 version INT UNSIGNED NOT NULL DEFAULT 1,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 UNIQUE(merchant_id,store_id,id),
 FOREIGN KEY(merchant_id,store_id) REFERENCES stores(merchant_id,id),
 INDEX(merchant_id,store_id,status,id)
) ENGINE=InnoDB;
CREATE TABLE invoice_draft_revisions (
 merchant_id BINARY(16) NOT NULL, store_id BINARY(16) NOT NULL, draft_id BINARY(16) NOT NULL,
 version INT UNSIGNED NOT NULL, document_envelope JSON NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY(draft_id,version),
 FOREIGN KEY(merchant_id,store_id,draft_id) REFERENCES invoice_drafts(merchant_id,store_id,id)
) ENGINE=InnoDB;
