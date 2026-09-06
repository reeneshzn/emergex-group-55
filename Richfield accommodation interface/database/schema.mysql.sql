-- ===========================================================================
-- Richfield Student Accommodation — MySQL / MariaDB schema
-- ===========================================================================
--
-- WHAT KIND OF DATA THIS STORES
--
--   REFERENCE DATA (read by the site, edited by staff)
--     campuses          the 8 Richfield campuses and their coordinates
--     room_types        single / sharing-2 / sharing-3 / studio / en-suite
--     amenities         Wi-Fi, backup power, biometric access, and so on
--     listings          accommodation available near each campus
--     listing_amenities which amenities each listing has (many-to-many)
--     faqs              the questions and answers on the home page
--
--   CAPTURED DATA (written by students using the site)
--     viewing_requests        a student asking to view rooms  <- the main one
--     viewing_request_rooms   which rooms each request names
--     shortlists              saved rooms, only used if logins are added
--
-- IMPORT WITH XAMPP
--   1. Start Apache and MySQL in the XAMPP control panel
--   2. Open http://localhost/phpmyadmin
--   3. Import this file (it creates the database itself)
--   4. Copy includes/config.sample.php to includes/config.php
--   5. Run:  php database/seed.php
--
-- OR FROM THE COMMAND LINE
--   mysql -u root < database/schema.mysql.sql
-- ===========================================================================

CREATE DATABASE IF NOT EXISTS `richfield_accommodation`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `richfield_accommodation`;

-- ---------------------------------------------------------------------------
-- Reference data
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `campuses` (
  `id`        VARCHAR(40)  NOT NULL COMMENT 'slug, e.g. bryanston',
  `name`      VARCHAR(120) NOT NULL,
  `city`      VARCHAR(120) NOT NULL,
  `province`  VARCHAR(120) NOT NULL,
  `lat`       DECIMAL(10,7) NOT NULL COMMENT 'suburb-level approximation',
  `lng`       DECIMAL(10,7) NOT NULL,
  `confirmed` TINYINT(1)   NOT NULL DEFAULT 0
              COMMENT '0 = location came from a directory, not richfield.ac.za. Drives the "Location to confirm" badge — do not drop this column.',
  `beds`      INT UNSIGNED NOT NULL DEFAULT 0,
  `sort_order` INT         NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `room_types` (
  `id`         VARCHAR(40)  NOT NULL COMMENT 'e.g. sharing-2',
  `label`      VARCHAR(120) NOT NULL COMMENT 'e.g. Sharing (2)',
  `sort_order` INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `amenities` (
  `id`         VARCHAR(40)  NOT NULL COMMENT 'e.g. backup-power',
  `short`      VARCHAR(80)  NOT NULL COMMENT 'chip text, e.g. Backup power',
  `label`      VARCHAR(160) NOT NULL COMMENT 'full text for tooltips',
  `sort_order` INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `listings` (
  `slug`        VARCHAR(120) NOT NULL COMMENT 'URL key, e.g. acacia-court-bryanston',
  `name`        VARCHAR(160) NOT NULL,
  `area`        VARCHAR(160) NOT NULL COMMENT 'suburb level only, never a street address',
  `campus_id`   VARCHAR(40)  NOT NULL,
  `room_type`   VARCHAR(40)  NOT NULL,
  `lat`         DECIMAL(10,7) NOT NULL,
  `lng`         DECIMAL(10,7) NOT NULL,
  `rent`        INT UNSIGNED NOT NULL
                COMMENT 'WHOLE RAND, never cents. The cost calculator rounds to whole rand throughout; a decimal here introduces drift.',
  `beds`        INT UNSIGNED NOT NULL DEFAULT 0,
  `verified`    TINYINT(1)   NOT NULL DEFAULT 0
                COMMENT '1 = physically inspected. Drives the "Inspected" badge.',
  `seed`        VARCHAR(60)  NOT NULL COMMENT 'picks the placeholder image colours',
  `image_url`   VARCHAR(500) DEFAULT NULL
                COMMENT 'real photography once supplied; NULL uses the generated placeholder',
  `blurb`       TEXT         NOT NULL,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1 COMMENT 'soft delete — hide without losing history',
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`slug`),
  KEY `idx_listings_campus` (`campus_id`),
  KEY `idx_listings_rent` (`rent`),
  KEY `idx_listings_active` (`is_active`),
  CONSTRAINT `fk_listings_campus` FOREIGN KEY (`campus_id`)
    REFERENCES `campuses` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_listings_roomtype` FOREIGN KEY (`room_type`)
    REFERENCES `room_types` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `listing_amenities` (
  `listing_slug` VARCHAR(120) NOT NULL,
  `amenity_id`   VARCHAR(40)  NOT NULL,
  PRIMARY KEY (`listing_slug`, `amenity_id`),
  KEY `idx_la_amenity` (`amenity_id`),
  CONSTRAINT `fk_la_listing` FOREIGN KEY (`listing_slug`)
    REFERENCES `listings` (`slug`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_la_amenity` FOREIGN KEY (`amenity_id`)
    REFERENCES `amenities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `faqs` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `question`   VARCHAR(300) NOT NULL,
  `answer`     TEXT         NOT NULL,
  `sort_order` INT          NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Captured data
--
-- PERSONAL INFORMATION LIVES HERE. Names, emails, phone numbers and guardian
-- emails are regulated under POPIA. Agree a retention period and a deletion
-- route before this goes live, and never commit a dump of these tables.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `viewing_requests` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference`      VARCHAR(20)  NOT NULL COMMENT 'RF-YYMMDD-XXXX shown to the student',
  `full_name`      VARCHAR(160) NOT NULL,
  `email`          VARCHAR(190) NOT NULL,
  `phone`          VARCHAR(20)  NOT NULL COMMENT 'SA mobile, 0… or +27…',
  `guardian_email` VARCHAR(190) DEFAULT NULL COMMENT 'optional',
  `campus_id`      VARCHAR(40)  NOT NULL,
  `preferred_date` DATE         NOT NULL,
  `move_in_month`  CHAR(7)      NOT NULL COMMENT 'YYYY-MM',
  `notes`          VARCHAR(500) DEFAULT NULL,
  `status`         VARCHAR(20)  NOT NULL DEFAULT 'new'
                   COMMENT 'new | contacted | viewed | closed',
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reference` (`reference`),
  KEY `idx_vr_campus` (`campus_id`),
  KEY `idx_vr_status` (`status`),
  KEY `idx_vr_created` (`created_at`),
  CONSTRAINT `fk_vr_campus` FOREIGN KEY (`campus_id`)
    REFERENCES `campuses` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `viewing_request_rooms` (
  `request_id`   INT UNSIGNED NOT NULL,
  `listing_slug` VARCHAR(120) NOT NULL,
  PRIMARY KEY (`request_id`, `listing_slug`),
  KEY `idx_vrr_listing` (`listing_slug`),
  CONSTRAINT `fk_vrr_request` FOREIGN KEY (`request_id`)
    REFERENCES `viewing_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vrr_listing` FOREIGN KEY (`listing_slug`)
    REFERENCES `listings` (`slug`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Only useful once students have accounts. Until then the shortlist lives in
-- the browser's localStorage, which is the better answer for anonymous users.
CREATE TABLE IF NOT EXISTS `shortlists` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `owner_key`    VARCHAR(64)  NOT NULL COMMENT 'user id once logins exist; a session token until then',
  `listing_slug` VARCHAR(120) NOT NULL,
  `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_owner_listing` (`owner_key`, `listing_slug`),
  CONSTRAINT `fk_sl_listing` FOREIGN KEY (`listing_slug`)
    REFERENCES `listings` (`slug`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
