CREATE TABLE `oauth_credentials` (
    `google_sub` varchar(255) NOT NULL,
    `encrypted_token` mediumtext NOT NULL,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`google_sub`),
    CONSTRAINT `oauth_credentials_user`
        FOREIGN KEY (`google_sub`)
        REFERENCES `users` (`google_sub`)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COMMENT='Encrypted per-user Google OAuth credentials';
