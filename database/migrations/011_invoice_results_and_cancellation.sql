ALTER TABLE invoice_issue_intents
 ADD COLUMN lifecycle VARCHAR(16) NOT NULL DEFAULT 'ACTIVE' CHECK(lifecycle IN ('ACTIVE','CANCELLED')),
 ADD COLUMN version INT UNSIGNED NOT NULL DEFAULT 1,
 ADD COLUMN cancelled_at DATETIME(6) NULL,
 ADD COLUMN active_order_id BINARY(16) GENERATED ALWAYS AS (CASE WHEN lifecycle='ACTIVE' THEN order_id ELSE NULL END) STORED,
 DROP INDEX merchant_id_2,
 ADD UNIQUE KEY active_initial_invoice(merchant_id,store_id,active_order_id);
CREATE TABLE invoice_issued_documents (
 merchant_id BINARY(16) NOT NULL, store_id BINARY(16) NOT NULL, intent_id BINARY(16) NOT NULL,
 operation_id BINARY(16) NOT NULL, connection_id BINARY(16) NOT NULL,
 reference_hash BINARY(32) NOT NULL, document_envelope MEDIUMTEXT NOT NULL,
 recorded_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY(merchant_id,intent_id), UNIQUE(merchant_id,operation_id), UNIQUE(merchant_id,connection_id,reference_hash),
 FOREIGN KEY(merchant_id,intent_id) REFERENCES invoice_issue_intents(merchant_id,id),
 FOREIGN KEY(merchant_id,operation_id) REFERENCES external_operations(merchant_id,id),
 FOREIGN KEY(merchant_id,store_id) REFERENCES stores(merchant_id,id)
) ENGINE=InnoDB;
