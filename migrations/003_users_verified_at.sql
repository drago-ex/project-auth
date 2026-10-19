--  Email verification
-- -------------------
-- Existing accounts are marked as verified, so nobody loses access when verification is turned on.
ALTER TABLE users
    ADD verified_at DATETIME NULL;

UPDATE users SET verified_at = NOW();
