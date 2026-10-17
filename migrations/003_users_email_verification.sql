--  Email verification
-- -------------------
-- Existing accounts are marked as verified, so nobody loses access when verification is turned on.
ALTER TABLE users
    ADD verified_at DATETIME NULL;

UPDATE users SET verified_at = NOW();

-- One-time links for confirming an email address (after registration or when changing it).
-- Only a hash of the token is stored; the token itself is sent in the email.
CREATE TABLE user_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    type VARCHAR(20) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    email VARCHAR(254) NULL,
    expires_at DATETIME NOT NULL,
    CONSTRAINT uq_user_tokens_hash UNIQUE (token_hash),
    CONSTRAINT fk_user_tokens_user
        FOREIGN KEY (user_id)
            REFERENCES users (id)
            ON DELETE CASCADE
)
    ENGINE = InnoDB
    DEFAULT CHARSET = utf8mb4
    COLLATE = utf8mb4_unicode_ci;
