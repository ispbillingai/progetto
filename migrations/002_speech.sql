-- Text read aloud by the staff app when a request of this type arrives (empty = the Italian name).
ALTER TABLE request_types ADD COLUMN speech VARCHAR(200) NULL AFTER hint;
