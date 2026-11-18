-- A prescription written from the doctor workspace remembers the queue token (sc_records, module tokens) it was written for.
ALTER TABLE sc_prescriptions ADD COLUMN IF NOT EXISTS queue_record_id BIGINT UNSIGNED NULL COMMENT 'sc_records.id of the token this prescription was written for' AFTER parent_id;
ALTER TABLE sc_prescriptions ADD KEY IF NOT EXISTS sc_rx_queue (tenant_id, queue_record_id);
