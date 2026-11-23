CREATE TABLE `data_raw` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `fbdate` date NOT NULL,
  `fbuser` varchar(100) NOT NULL,
  `fbjsondata` mediumtext NOT NULL,
  `dateadded` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fbuser` (`fbdate`,`fbuser`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='Capture Google Health Mapped / Fitbit Raw JSON Data';

