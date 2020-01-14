/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

use gaukmedi_auctions

DROP TABLE IF EXISTS `verticals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `verticals` (
  `vertical_id` int(11) NOT NULL AUTO_INCREMENT,
  `vertical_name` varchar(255) NOT NULL,
  `vertical_description` varchar(255) NOT NULL,
  `created_by` varchar(255) DEFAULT NULL,
  `created_date` datetime DEFAULT NULL,
  `updated_by` varchar(255) DEFAULT NULL,
  `updated_date` datetime DEFAULT NULL,
  PRIMARY KEY (`vertical_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `verticals`
--

LOCK TABLES `verticals` WRITE;
/*!40000 ALTER TABLE `verticals` DISABLE KEYS */;
INSERT INTO `verticals` VALUES (1,'Gauk Motors','Vertical for Motor Vehicles',NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `verticals` ENABLE KEYS */;
UNLOCK TABLES;


--
-- Table structure for table `spider_cron_queue`
--

DROP TABLE IF EXISTS `spider_cron_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `spider_cron_queue` (
  `spider_cron_queue_id` int(11) NOT NULL AUTO_INCREMENT,
  `spider_profile_id` int(11) NOT NULL,
  `error` tinyint(1) NOT NULL DEFAULT '0',
  `processing` tinyint(1) NOT NULL DEFAULT '1',
  `created_date` timestamp NULL DEFAULT NULL,
  `updated_date` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`spider_cron_queue_id`),
  KEY `spider_profile_id_idx` (`spider_profile_id`),
  CONSTRAINT `spider_profile_id` FOREIGN KEY (`spider_profile_id`) REFERENCES `spider_profile` (`spider_profile_id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `spider_cron_queue`
--

LOCK TABLES `spider_cron_queue` WRITE;
/*!40000 ALTER TABLE `spider_cron_queue` DISABLE KEYS */;
/*!40000 ALTER TABLE `spider_cron_queue` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `spider_error`
--

DROP TABLE IF EXISTS `spider_error`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `spider_error` (
  `spider_error_id` int(11) NOT NULL AUTO_INCREMENT,
  `spider_profile_id` int(11) NOT NULL,
  `spider_error_message` text,
  `created_date` timestamp NULL DEFAULT NULL,
  `updated_date` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`spider_error_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `spider_error`
--

LOCK TABLES `spider_error` WRITE;
/*!40000 ALTER TABLE `spider_error` DISABLE KEYS */;
/*!40000 ALTER TABLE `spider_error` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `spider_profile`
--

DROP TABLE IF EXISTS `spider_profile`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `spider_profile` (
  `spider_profile_id` int(11) NOT NULL AUTO_INCREMENT,
  `vertical_id` int(11) NOT NULL,
  `site_name` varchar(255) NOT NULL,
  `site_description` varchar(255) DEFAULT NULL,
  `site_starting_url` varchar(255) NOT NULL,
  `spider_name` varchar(255) NOT NULL,
  `spider_type` enum('casper','scrappy') NOT NULL DEFAULT 'casper',
  `cron_schedule` varchar(255) NOT NULL,
  `last_run` timestamp NULL DEFAULT NULL,
  `next_run` timestamp NULL DEFAULT NULL,
  `imagecollectiontype` enum('normal','curl') NOT NULL DEFAULT 'normal',
  `status` enum('enabled','disabled','error') NOT NULL DEFAULT 'enabled',
  `is_require_login` enum('0','1') NOT NULL DEFAULT '0',
  `username` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `notes` text,
  `created_by` varchar(255) DEFAULT NULL,
  `created_date` timestamp NULL DEFAULT NULL,
  `updated_by` varchar(255) DEFAULT NULL,role_useractivity_log
  `updated_date` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`spider_profile_id`),
  KEY `vertical_id_idx` (`vertical_id`),
  CONSTRAINT `vertical_id` FOREIGN KEY (`vertical_id`) REFERENCES `verticals` (`vertical_id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `spider_profile`
--

LOCK TABLES `spider_profile` WRITE;
/*!40000 ALTER TABLE `spider_profile` DISABLE KEYS */;
INSERT INTO `spider_profile` VALUES (1,1,'Mathewsons',NULL,'http://www.mathewsons.co.uk/auctions/auctions/vehicles?view=datavw','mathewsons','casper','0 1 * * 1',NULL,NULL,'normal','enabled','0',NULL,NULL,NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `spider_profile` ENABLE KEYS */;
UNLOCK TABLES;