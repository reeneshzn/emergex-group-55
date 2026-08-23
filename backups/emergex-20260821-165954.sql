-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: emergex
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(60) DEFAULT NULL,
  `entity_id` bigint(20) unsigned DEFAULT NULL,
  `details_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details_json`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_audit_user` (`user_id`),
  KEY `idx_audit_action_time` (`action`,`created_at`),
  KEY `idx_audit_entity` (`entity_type`,`entity_id`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,4,'user.register','user',4,'{\"role\":\"student\"}','127.0.0.1','2026-08-21 16:59:15'),(2,4,'booking.created','booking',2,'{\"instant_book\":true}','127.0.0.1','2026-08-21 16:59:15'),(3,4,'booking.created','booking',3,'{\"instant_book\":false}','127.0.0.1','2026-08-21 16:59:15'),(4,2,'user.login','user',2,'[]','127.0.0.1','2026-08-21 16:59:15'),(5,2,'booking.approved','booking',3,'[]','127.0.0.1','2026-08-21 16:59:15'),(6,6,'user.login','user',6,'[]','127.0.0.1','2026-08-21 16:59:16'),(7,4,'booking.created','booking',5,'{\"instant_book\":false}','127.0.0.1','2026-08-21 16:59:16'),(8,4,'escrow.simulated_hold','booking',2,'[]','127.0.0.1','2026-08-21 16:59:16'),(9,4,'escrow.simulated_release','booking',2,'[]','127.0.0.1','2026-08-21 16:59:16'),(10,4,'document.uploaded','user',4,'{\"type\":\"nsfas_proof\"}','127.0.0.1','2026-08-21 16:59:16'),(11,4,'booking.created','booking',6,'{\"instant_book\":false}','127.0.0.1','2026-08-21 16:59:17'),(12,2,'user.login','user',2,'[]','127.0.0.1','2026-08-21 16:59:17'),(13,1,'user.login','user',1,'[]','127.0.0.1','2026-08-21 16:59:17'),(14,1,'user.login','user',1,'[]','127.0.0.1','2026-08-21 16:59:17'),(15,1,'user.logout','user',1,'[]','127.0.0.1','2026-08-21 16:59:17'),(16,3,'user.login','user',3,'[]','127.0.0.1','2026-08-21 16:59:17'),(17,3,'admin.user_verified','user',7,'[]','127.0.0.1','2026-08-21 16:59:17'),(18,3,'user.login','user',3,'[]','127.0.0.1','2026-08-21 16:59:18'),(19,3,'admin.property_verified','property',6,'[]','127.0.0.1','2026-08-21 16:59:18'),(20,3,'user.login','user',3,'[]','127.0.0.1','2026-08-21 16:59:18'),(21,3,'admin.document_approved','document',1,'[]','127.0.0.1','2026-08-21 16:59:18'),(22,3,'user.login','user',3,'[]','127.0.0.1','2026-08-21 16:59:28'),(23,3,'user.login','user',3,'[]','127.0.0.1','2026-08-21 16:59:40');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bookings`
--

DROP TABLE IF EXISTS `bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bookings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `property_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `move_in_date` date NOT NULL,
  `message` varchar(1000) DEFAULT NULL,
  `monthly_rent` decimal(10,2) NOT NULL,
  `deposit_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','approved','deposit_held','checked_in','completed','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `response_deadline` datetime DEFAULT NULL,
  `responded_at` datetime DEFAULT NULL,
  `checked_in_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_booking_student` (`student_id`,`status`),
  KEY `idx_booking_property` (`property_id`,`status`),
  KEY `idx_booking_deadline` (`status`,`response_deadline`),
  CONSTRAINT `fk_booking_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`),
  CONSTRAINT `fk_booking_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bookings`
--

LOCK TABLES `bookings` WRITE;
/*!40000 ALTER TABLE `bookings` DISABLE KEYS */;
INSERT INTO `bookings` VALUES (1,2,1,'2026-09-20','I am a second-year student looking for a quiet room near campus.',6100.00,6100.00,'pending','2026-08-22 04:59:15',NULL,NULL,'2026-08-21 16:59:15',NULL),(2,1,4,'2026-10-05','Instant acceptance test',5200.00,5200.00,'completed',NULL,NULL,'2026-08-21 16:59:16','2026-08-21 16:59:15','2026-08-21 16:59:16'),(3,2,4,'2026-10-10','Standard acceptance test',6100.00,6100.00,'approved','2026-08-22 04:59:15','2026-08-21 16:59:15',NULL,'2026-08-21 16:59:15','2026-08-21 16:59:15'),(4,5,1,'2026-11-09','CSRF fixture',4000.00,4000.00,'pending','2026-08-22 04:59:16',NULL,NULL,'2026-08-21 16:59:16',NULL),(5,2,4,'2026-10-20','Pending state check',6100.00,6100.00,'pending','2026-08-22 04:59:16',NULL,NULL,'2026-08-21 16:59:16',NULL),(6,2,4,'2026-10-30','<script>alert(1)</script>',6100.00,6100.00,'pending','2026-08-22 04:59:17',NULL,NULL,'2026-08-21 16:59:17',NULL);
/*!40000 ALTER TABLE `bookings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `escrow_transactions`
--

DROP TABLE IF EXISTS `escrow_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `escrow_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('not_paid','held','released','refunded') NOT NULL DEFAULT 'not_paid',
  `reference_code` varchar(40) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `released_at` datetime DEFAULT NULL,
  `refunded_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_id` (`booking_id`),
  UNIQUE KEY `reference_code` (`reference_code`),
  CONSTRAINT `fk_escrow_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_escrow_amount` CHECK (`amount` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `escrow_transactions`
--

LOCK TABLES `escrow_transactions` WRITE;
/*!40000 ALTER TABLE `escrow_transactions` DISABLE KEYS */;
INSERT INTO `escrow_transactions` VALUES (1,1,6100.00,'not_paid',NULL,NULL,NULL,NULL,'2026-08-21 16:59:15'),(2,2,5200.00,'released','SIM-1AC2E01E','2026-08-21 16:59:16','2026-08-21 16:59:16',NULL,'2026-08-21 16:59:15'),(3,3,6100.00,'not_paid',NULL,NULL,NULL,NULL,'2026-08-21 16:59:15'),(4,4,4000.00,'not_paid',NULL,NULL,NULL,NULL,'2026-08-21 16:59:16'),(5,5,6100.00,'not_paid',NULL,NULL,NULL,NULL,'2026-08-21 16:59:16'),(6,6,6100.00,'not_paid',NULL,NULL,NULL,NULL,'2026-08-21 16:59:17');
/*!40000 ALTER TABLE `escrow_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `properties`
--

DROP TABLE IF EXISTS `properties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `properties` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `landlord_id` bigint(20) unsigned NOT NULL,
  `title` varchar(160) NOT NULL,
  `description` text NOT NULL,
  `address_line` varchar(190) NOT NULL,
  `suburb` varchar(100) NOT NULL,
  `city` varchar(100) NOT NULL,
  `province` varchar(80) NOT NULL,
  `near_institution` varchar(160) NOT NULL,
  `monthly_rent` decimal(10,2) NOT NULL,
  `deposit_amount` decimal(10,2) NOT NULL,
  `available_rooms` smallint(5) unsigned NOT NULL DEFAULT 0,
  `instant_book` tinyint(1) NOT NULL DEFAULT 0,
  `nsfas_accredited` tinyint(1) NOT NULL DEFAULT 0,
  `rating` decimal(2,1) NOT NULL DEFAULT 0.0,
  `cover_image` varchar(190) NOT NULL DEFAULT 'property-1.svg',
  `amenities_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`amenities_json`)),
  `status` enum('draft','pending_review','published','suspended') NOT NULL DEFAULT 'pending_review',
  `verified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_properties_landlord` (`landlord_id`),
  KEY `idx_property_search` (`status`,`city`,`monthly_rent`),
  KEY `idx_property_flags` (`nsfas_accredited`,`instant_book`),
  CONSTRAINT `fk_properties_landlord` FOREIGN KEY (`landlord_id`) REFERENCES `users` (`id`),
  CONSTRAINT `chk_monthly_rent` CHECK (`monthly_rent` > 0),
  CONSTRAINT `chk_deposit_amount` CHECK (`deposit_amount` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `properties`
--

LOCK TABLES `properties` WRITE;
/*!40000 ALTER TABLE `properties` DISABLE KEYS */;
INSERT INTO `properties` VALUES (1,2,'Emerald House Hatfield','A professionally managed student residence with quiet study zones, furnished rooms, controlled access and a verified visual walkthrough.','18 Festival Street','Hatfield','Pretoria','Gauteng','University of Pretoria',5200.00,5200.00,8,1,1,4.8,'property-1.svg','[\"Uncapped Wi-Fi\", \"Furnished room\", \"Study lounge\", \"Laundry\", \"Controlled access\", \"Backup power\"]','published','2026-08-21 16:59:15','2026-08-21 16:59:15',NULL),(2,2,'Braamfontein Studio Living','Compact private studios within walking distance of major campuses and transport routes. Each unit includes a kitchenette and en-suite bathroom.','77 Juta Street','Braamfontein','Johannesburg','Gauteng','Wits University',6100.00,6100.00,4,0,1,4.6,'property-2.svg','[\"Private studio\", \"En-suite bathroom\", \"Kitchenette\", \"24-hour security\", \"Wi-Fi\", \"On-site manager\"]','published','2026-08-21 16:59:15','2026-08-21 16:59:15',NULL),(3,2,'Durban North Student Lofts','Bright shared lofts with modern communal areas, secure parking and scheduled shuttle access to nearby campuses.','12 Riverside Road','Durban North','Durban','KwaZulu-Natal','Varsity College Durban North',4550.00,3000.00,11,1,0,4.7,'property-3.svg','[\"Shuttle service\", \"Secure parking\", \"Communal kitchen\", \"Furnished room\", \"Wi-Fi\", \"Outdoor courtyard\"]','published','2026-08-21 16:59:15','2026-08-21 16:59:15',NULL),(4,2,'Mowbray Campus Rooms','Affordable shared student rooms close to rail and bus links, with a dedicated study room and resident support.','42 Main Road','Mowbray','Cape Town','Western Cape','University of Cape Town',4800.00,4800.00,6,0,1,4.5,'property-4.svg','[\"Study room\", \"Public transport access\", \"Furnished room\", \"Laundry\", \"Wi-Fi\", \"CCTV\"]','published','2026-08-21 16:59:15','2026-08-21 16:59:15',NULL),(5,6,'Other Landlord Fixture','Ownership test fixture','16 Fixture Road','Hatfield','Pretoria','Gauteng','Test University',4000.00,4000.00,1,0,0,4.0,'property-1.svg','[\"Wi-Fi\"]','published','2026-08-21 16:59:16','2026-08-21 16:59:16',NULL),(6,7,'Acceptance Pending Residence','Acceptance verification fixture','55 Test Street','Hatfield','Pretoria','Gauteng','Test University',4300.00,4300.00,2,0,1,4.0,'property-1.svg','[\"Wi-Fi\"]','published','2026-08-21 16:59:18','2026-08-21 16:59:17','2026-08-21 16:59:18');
/*!40000 ALTER TABLE `properties` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `property_media`
--

DROP TABLE IF EXISTS `property_media`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `property_media` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `property_id` bigint(20) unsigned NOT NULL,
  `media_type` enum('image','video','floor_plan','tour_360') NOT NULL,
  `media_url` varchar(500) NOT NULL,
  `caption` varchar(190) DEFAULT NULL,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_media_property` (`property_id`),
  CONSTRAINT `fk_media_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `property_media`
--

LOCK TABLES `property_media` WRITE;
/*!40000 ALTER TABLE `property_media` DISABLE KEYS */;
/*!40000 ALTER TABLE `property_media` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_documents`
--

DROP TABLE IF EXISTS `user_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `document_type` enum('nsfas_proof','identity','accreditation','ownership') NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_size` int(10) unsigned NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `stored_name` (`stored_name`),
  KEY `fk_document_owner` (`user_id`),
  KEY `fk_document_reviewer` (`reviewed_by`),
  KEY `idx_document_review` (`status`,`uploaded_at`),
  CONSTRAINT `fk_document_owner` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_document_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_documents`
--

LOCK TABLES `user_documents` WRITE;
/*!40000 ALTER TABLE `user_documents` DISABLE KEYS */;
INSERT INTO `user_documents` VALUES (1,4,'nsfas_proof','valid-small.pdf','6a47fe9afd2c5c495d94d745be572e6e161c7a02.pdf','application/pdf',48,'approved',3,'2026-08-21 16:59:18','2026-08-21 16:59:16');
/*!40000 ALTER TABLE `user_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `role` enum('student','landlord','admin') NOT NULL,
  `first_name` varchar(80) NOT NULL,
  `last_name` varchar(80) NOT NULL,
  `email` varchar(190) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `student_number` varchar(30) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `status` enum('active','suspended') NOT NULL DEFAULT 'active',
  `verified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `student_number` (`student_number`),
  KEY `idx_users_role_status` (`role`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'student','Demo','Student','student@emergex.test','0820000001','400000001','$2y$10$wvL9l6tlY6bY.MvXr8oM8OtfmbVbFodM1MVGbGaMDt65RSIq0oUHi','active',NULL,'2026-08-21 16:59:15',NULL),(2,'landlord','Demo','Landlord','landlord@emergex.test','0820000002',NULL,'$2y$10$dO3sYAYrTbEFxTvPyikkD.UbndZrjcd9hgUzprUD70m/6923kjwji','active','2026-08-21 16:59:15','2026-08-21 16:59:15',NULL),(3,'admin','System','Administrator','admin@emergex.test','0820000003',NULL,'$2y$10$m40Beg1L30kwuKmyVN8JmO8DV1T.lqF7rUga5tfbN.JSHbbUEY2Hq','active','2026-08-21 16:59:15','2026-08-21 16:59:15',NULL),(4,'student','Acceptance','Student','acceptance.student@example.test','0825550155','ACC-2026-55','$2y$10$yNXfNuGth36X.fiSDflzIep07X4sUeW3hML6orTOYc2ElfgcqCz.6','active',NULL,'2026-08-21 16:59:15',NULL),(6,'landlord','Other','Landlord','other.landlord@example.test','0825550166',NULL,'$2y$10$DAYIjF8g4OVL3XxuTyt7wOW/c2GJLxaNGiApQCKQ/hFUKFg2HQ3Oe','active','2026-08-21 16:59:16','2026-08-21 16:59:16',NULL),(7,'landlord','Pending','Landlord','pending.landlord@example.test','0825550177',NULL,'$2y$10$vQru54PrbUQeiPjH8ZJJiuHORAnkupXl.1noZv6mOA5dfxVcTSa0m','active','2026-08-21 16:59:17','2026-08-21 16:59:17','2026-08-21 16:59:17');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'emergex'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-21 16:59:54
