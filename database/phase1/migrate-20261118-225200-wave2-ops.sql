-- Wave 2 work-list engine: queue order, structured extras, optional patient and doctor links.
-- Safe to run twice (MariaDB: ADD COLUMN / ADD KEY IF NOT EXISTS).
ALTER TABLE sc_records ADD COLUMN IF NOT EXISTS sort_order INT NOT NULL DEFAULT 0 COMMENT 'position in the day queue, 1 is first' AFTER invoice_id;
ALTER TABLE sc_records ADD COLUMN IF NOT EXISTS meta TEXT NULL COMMENT 'JSON extras for the module (tooth chart, refraction, vitals)' AFTER sort_order;
ALTER TABLE sc_records ADD COLUMN IF NOT EXISTS person_id INT UNSIGNED NULL COMMENT 'patients.id of the same vendor, when the record is for a registered patient' AFTER meta;
ALTER TABLE sc_records ADD COLUMN IF NOT EXISTS staff_id BIGINT UNSIGNED NULL COMMENT 'doctors.id of the same vendor, when a named doctor or specialist is set' AFTER person_id;
ALTER TABLE sc_records ADD KEY IF NOT EXISTS sc_rec_sort (tenant_id, module, date1, sort_order);
ALTER TABLE sc_records ADD KEY IF NOT EXISTS sc_rec_person (tenant_id, person_id);
ALTER TABLE sc_records ADD KEY IF NOT EXISTS sc_rec_staff (tenant_id, staff_id);
