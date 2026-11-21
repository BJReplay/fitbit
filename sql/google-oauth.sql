CREATE TABLE `oauth_credentials` (
    `id` tinyint unsigned NOT NULL,
    `encrypted_token` mediumtext NOT NULL,
    `updated_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `oauth_credentials_single_row`
        CHECK (`id` = 1)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COMMENT='Encrypted Google OAuth credentials';