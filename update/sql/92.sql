ALTER TABLE import_profile
  ADD COLUMN target VARCHAR(255) DEFAULT 'product' AFTER name;