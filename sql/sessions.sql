CREATE TABLE `sessions` (
  `session_id` varchar(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `session_data` mediumblob NOT NULL,
  `last_activity` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`session_id`),
  KEY `sessions_last_activity` (`last_activity`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COMMENT='Persistent PHP application sessions';
