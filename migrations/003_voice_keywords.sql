-- Words that make a spoken request match this button (comma separated, any language; empty = built-in defaults).
ALTER TABLE request_types ADD COLUMN keywords VARCHAR(1000) NULL AFTER speech;
