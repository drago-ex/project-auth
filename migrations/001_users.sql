--
--  Database users
-- ---------------
-- Email addresses can have up to 254 characters, and password hashes other than bcrypt
-- (e.g. Argon2, or a future PASSWORD_DEFAULT) are longer than 60 characters.
-- verified_at is set once the email address is confirmed (or right away without verification).
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(254) NOT NULL,
    password VARCHAR(255) NOT NULL,
    token VARCHAR(32) NOT NULL,
    verified_at DATETIME NULL,
    CONSTRAINT uq_users_email UNIQUE (email)
)
    ENGINE = InnoDB
    DEFAULT CHARSET = utf8mb4
    COLLATE = utf8mb4_unicode_ci;
