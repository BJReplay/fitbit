CREATE TABLE `users` (
    `google_sub` varchar(255) NOT NULL,
    `email` varchar(320) DEFAULT NULL,
    `display_name` varchar(255) DEFAULT NULL,
    `health_user_id` varchar(100) DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`google_sub`),
    UNIQUE KEY `health_user_id` (`health_user_id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COMMENT='Google-authenticated application users';
