use gaukmedi_auctions;


DROP TABLE IF EXISTS `input_source_type`;
CREATE TABLE `input_source_type` (
  `input_source_type_id` int(11) NOT NULL AUTO_INCREMENT,
  `input_source_type_name` varchar(32) CHARACTER SET utf8 NOT NULL,
  `input_source_type_code` varchar(4) CHARACTER SET utf8 NOT NULL,
  `input_source_type_description` varchar(128) CHARACTER SET utf8 NOT NULL,
  `created_by` varchar(128) CHARACTER SET utf8 DEFAULT NULL,
  `created_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(128) CHARACTER SET utf8 DEFAULT NULL,
  `updated_date` datetime NOT NULL,
  PRIMARY KEY (`input_source_type_id`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

INSERT INTO `input_source_type` (input_source_type_id, input_source_type_name, input_source_type_code, input_source_type_description) VALUES (1, 'Spider', 'SPD1', 'Spider Data Imported');




DROP TABLE IF EXISTS `input_source`;
CREATE TABLE `input_source` (
  `input_source_id` int(11) NOT NULL AUTO_INCREMENT,
  `input_source_type_id` int(11) NOT NULL,
  `spider_profile_id` int(11) NOT NULL,
  `input_source_code` varchar(10) CHARACTER SET utf8 NOT NULL,
  `input_source_is_enabled` bit(1) NOT NULL,
  `created_by` varchar(128) CHARACTER SET utf8 DEFAULT NULL,
  `created_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(128) CHARACTER SET utf8 DEFAULT NULL,
  `updated_date` datetime NOT NULL,
  PRIMARY KEY (`input_source_id`),
  KEY `input_source_type_id_idx` (`input_source_type_id`),
  KEY `spider_profile_id_idx` (`spider_profile_id`),
  CONSTRAINT `input_source_type_id` FOREIGN KEY (`input_source_type_id`) REFERENCES `input_source_type` (`input_source_type_id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `input_spider_profile_id` FOREIGN KEY (`spider_profile_id`) REFERENCES `spider_profile` (`spider_profile_id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

INSERT INTO `input_source` (input_source_id, input_source_type_id, spider_profile_id, input_source_code, input_source_is_enabled) VALUES (1, 1, 1, 'MATHEWSONS', 1);






DROP TABLE IF EXISTS `input_source_file`;
CREATE TABLE `input_source_file` (
  `input_source_file_id` int(11) NOT NULL AUTO_INCREMENT,
  `input_source_id` int(11) NOT NULL,
  `input_source_filename` varchar(128) CHARACTER SET utf8 NOT NULL,
  `input_source_file_is_enabled` bit(1) NOT NULL,
  `created_by` varchar(128) CHARACTER SET utf8 DEFAULT NULL,
  `created_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(128) CHARACTER SET utf8 DEFAULT NULL,
  `updated_date` datetime NOT NULL,
  PRIMARY KEY (`input_source_file_id`),
  KEY `input_source_id_idx` (`input_source_id`),
  CONSTRAINT `input_source_id` FOREIGN KEY (`input_source_id`) REFERENCES `input_source` (`input_source_id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

INSERT INTO `input_source_file` (input_source_file_id, input_source_id, input_source_filename, input_source_file_is_enabled) VALUES (1, 1, 'mathewsons.txt', 1);





DROP TABLE IF EXISTS `input_source_file_log`;
CREATE TABLE `input_source_file_log` (
  `input_source_file_log_id` int(11) NOT NULL AUTO_INCREMENT,
  `input_source_file_id` int(11) NOT NULL,
  `input_source_filename` varchar(128) CHARACTER SET utf8 NOT NULL,
  `batch_name` varchar(50) CHARACTER SET utf8 NOT NULL,
  `received` datetime NOT NULL,
  `started` datetime NOT NULL,
  `completed` datetime NOT NULL,
  `total` int(11) NOT NULL,
  `total_added` int(11) NOT NULL,
  `total_updated` int(11) NOT NULL,
  `total_unchanged` int(11) NOT NULL,
  `total_deleted` int(11) NOT NULL,
  `created_by` varchar(128) CHARACTER SET utf8 DEFAULT NULL,
  `created_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(128) CHARACTER SET utf8 DEFAULT NULL,
  `updated_date` datetime NOT NULL,
  PRIMARY KEY (`input_source_file_log_id`),
  KEY `input_source_file_id_idx` (`input_source_file_id`),
  CONSTRAINT `input_source_file_id` FOREIGN KEY (`input_source_file_id`) REFERENCES `input_source_file` (`input_source_file_id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;




DROP TABLE IF EXISTS `input_source_item_log`;
CREATE TABLE `input_source_item_log` (
  `input_source_item_log_id` int(11) NOT NULL AUTO_INCREMENT,
  `input_source_file_log_id` int(11) NOT NULL,
  `item_unique_id` varchar(255) CHARACTER SET utf8_unicode_ci NOT NULL,
  `item_hash_value` varchar(50) CHARACTER SET utf8 NOT NULL,
  `created_by` varchar(128) CHARACTER SET utf8 DEFAULT NULL,
  `created_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(128) CHARACTER SET utf8 DEFAULT NULL,
  `updated_date` datetime NOT NULL,
  PRIMARY KEY (`input_source_item_log_id`),
  KEY `input_source_file_log_id_idx` (`input_source_file_log_id`),
  CONSTRAINT `input_source_file_log_id` FOREIGN KEY (`input_source_file_log_id`) REFERENCES `input_source_file_log` (`input_source_file_log_id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;





DROP TABLE IF EXISTS `input_item`;
CREATE TABLE `input_item` (
  `input_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `input_source_item_log_id` int(11) NOT NULL,
  `item_guid` varchar(100) CHARACTER SET utf8 NOT NULL,
  `created_by` varchar(128) CHARACTER SET utf8 DEFAULT NULL,
  `created_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(128) CHARACTER SET utf8 DEFAULT NULL,
  `updated_date` datetime NOT NULL,
  PRIMARY KEY (`input_item_id`),
  KEY `input_source_item_log_id_idx` (`input_source_item_log_id`),
  CONSTRAINT `input_source_item_log_id` FOREIGN KEY (`input_source_item_log_id`) REFERENCES `input_source_item_log` (`input_source_item_log_id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;





DROP TABLE IF EXISTS `input_source_file_config`;
CREATE TABLE `input_source_file_config` (
  `input_source_file_config_id` int(11) NOT NULL AUTO_INCREMENT,
  `input_source_file_id` int(11) NOT NULL,
  `input_source_file_config_name` varchar(100) CHARACTER SET utf8 NOT NULL,
  `input_source_file_config_value` varchar(255) CHARACTER SET utf8 NOT NULL,
  PRIMARY KEY (`input_source_file_config_id`),
  KEY `input_source_file_id_idx` (`input_source_file_id`),
  CONSTRAINT `input_config_source_file_id` FOREIGN KEY (`input_source_file_id`) REFERENCES `input_source_file` (`input_source_file_id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

INSERT INTO `input_source_file_config` (input_source_file_config_id, input_source_file_id, input_source_file_config_name, input_source_file_config_value) VALUES (1, 1, 'etl_source_mapping_id', '5c355dda95a4955328ceffde');





DROP TABLE IF EXISTS `input_source_notify`;
CREATE TABLE `input_source_notify` (
  `input_source_notify_id` int(11) NOT NULL AUTO_INCREMENT,
  `input_source_id` int(11) NOT NULL,
  `recipient_name` varchar(255) CHARACTER SET utf8 NOT NULL,
  `created_by` varchar(128) CHARACTER SET utf8 DEFAULT NULL,
  `created_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(128) CHARACTER SET utf8 DEFAULT NULL,
  `updated_date` datetime NOT NULL,
  PRIMARY KEY (`input_source_notify_id`),
  KEY `input_source_id_idx` (`input_source_id`),
  CONSTRAINT `input_source_notify_ibfk_1` FOREIGN KEY (`input_source_id`) REFERENCES `input_source` (`input_source_id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


INSERT INTO `input_source_notify` (input_source_id, recipient_name) VALUES (1, 'Processing.InputLoad');



DROP TABLE IF EXISTS `input_source_config`;
CREATE TABLE `input_source_config` (
  `input_source_config_id` int(11) NOT NULL AUTO_INCREMENT,
  `input_source_id` int(11) NOT NULL,
  `input_source_config_name` varchar(100) CHARACTER SET utf8 NOT NULL,
  `input_source_config_value` varchar(255) CHARACTER SET utf8 NOT NULL,
  PRIMARY KEY (`input_source_config_id`),
  KEY `input_source_id_idx` (`input_source_id`),
  CONSTRAINT `input_source_id_config` FOREIGN KEY (`input_source_id`) REFERENCES `input_source` (`input_source_id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;





























