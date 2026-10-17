--  Longer columns for users
-- -------------------------
-- Email addresses can have up to 254 characters, and password hashes other than bcrypt
-- (e.g. Argon2, or a future PASSWORD_DEFAULT) are longer than 60 characters.
ALTER TABLE users
    MODIFY email VARCHAR(254) NOT NULL,
    MODIFY password VARCHAR(255) NOT NULL;
