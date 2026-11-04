--  Roles of the users
-- -------------------
-- The roles are stored by name: the base roles (guest, user, admin) live in the ACL neon files,
-- the custom ones in drago-ex/project-permission. A user without a row has the role "user".
CREATE TABLE user_roles (
    user_id INT UNSIGNED NOT NULL,
    role VARCHAR(50) NOT NULL,
    PRIMARY KEY (user_id, role),
    CONSTRAINT fk_user_roles_user
        FOREIGN KEY (user_id)
            REFERENCES users (id)
            ON DELETE CASCADE
)
    ENGINE = InnoDB
    DEFAULT CHARSET = utf8mb4
    COLLATE = utf8mb4_unicode_ci;
