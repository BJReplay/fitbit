-- Migrate the original single-row oauth_credentials table to per-user storage.
--
-- Safety rule: the legacy credential is migrated automatically only when the
-- users table contains exactly one user. If there is not exactly one user,
-- the migration deliberately fails rather than assigning the credential to
-- an ambiguous Google identity.

START TRANSACTION;

CREATE TABLE `oauth_credentials_multi_user` (
    `google_sub` varchar(255) NOT NULL,
    `encrypted_token` mediumtext NOT NULL,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`google_sub`),
    CONSTRAINT `oauth_credentials_multi_user_user`
        FOREIGN KEY (`google_sub`)
        REFERENCES `users` (`google_sub`)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COMMENT='Encrypted per-user Google OAuth credentials';

-- The scalar subquery returns the one known user. MySQL raises an error if
-- more than one user exists, preventing an ambiguous token assignment.
INSERT INTO `oauth_credentials_multi_user`
    (`google_sub`, `encrypted_token`, `updated_at`)
SELECT
    (SELECT `google_sub` FROM `users`),
    `encrypted_token`,
    `updated_at`
FROM `oauth_credentials`
WHERE `id` = 1
  AND (SELECT COUNT(*) FROM `users`) = 1;

-- If a legacy token exists, require it to have been migrated before the old
-- table can be replaced. A mismatch deliberately fails via the duplicate
-- primary key in the assertion table.
CREATE TEMPORARY TABLE `oauth_migration_assertion` (
    `id` tinyint unsigned NOT NULL,
    PRIMARY KEY (`id`)
);

INSERT INTO `oauth_migration_assertion` (`id`) VALUES (1);

INSERT INTO `oauth_migration_assertion` (`id`)
SELECT 1
WHERE
    (SELECT COUNT(*) FROM `oauth_credentials`) > 0
    AND
    (SELECT COUNT(*) FROM `oauth_credentials_multi_user`) = 0;

DROP TEMPORARY TABLE `oauth_migration_assertion`;

DROP TABLE `oauth_credentials`;

RENAME TABLE
    `oauth_credentials_multi_user`
    TO `oauth_credentials`;

COMMIT;
