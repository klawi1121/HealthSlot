-- HealthSlot: Barangay Health Center Appointment and Vaccination Schedule System
-- Database structure for a FRESH install (import in phpMyAdmin: Import tab > choose this file > Go)
--
-- Already have a working database? Do NOT import this over it.
-- Use admin_accounts.sql instead, which only adds what is new.

CREATE DATABASE IF NOT EXISTS `healthslotdb`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

USE `healthslotdb`;

-- 1. Residents (self-registered users)
CREATE TABLE IF NOT EXISTS `resident_tbl` (
  `residentid` int(11) NOT NULL AUTO_INCREMENT,
  `fname` varchar(50) NOT NULL,
  `mname` varchar(50) NOT NULL,
  `lname` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `uname` varchar(100) NOT NULL,
  `age` int(11) NOT NULL,
  `birthday` date NOT NULL,
  `address` varchar(100) NOT NULL,
  `sex` varchar(15) NOT NULL,
  `contactnum` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `photo` varchar(50) NOT NULL,
  PRIMARY KEY (`residentid`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `uname` (`uname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Staff and admin accounts (created by an administrator; the role column tells them apart)
--    Age is not stored for staff and admins, it is calculated from the birthday.
CREATE TABLE IF NOT EXISTS `staff_tbl` (
  `staffid` int(11) NOT NULL AUTO_INCREMENT,
  `fname` varchar(50) NOT NULL,
  `mname` varchar(50) NOT NULL DEFAULT '',
  `lname` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `uname` varchar(100) NOT NULL,
  `contactnum` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `role` enum('staff','admin') NOT NULL DEFAULT 'staff',
  `birthday` date DEFAULT NULL,
  `sex` varchar(10) NOT NULL DEFAULT '',
  `address` varchar(100) NOT NULL DEFAULT '',
  `photo` varchar(255) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`staffid`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `uname` (`uname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Health center services
CREATE TABLE IF NOT EXISTS `service_tbl` (
  `serviceid` int(11) NOT NULL AUTO_INCREMENT,
  `servicename` varchar(50) NOT NULL,
  `servicecategory` varchar(50) NOT NULL,
  `servicedate` date NOT NULL,
  `starttime` time NOT NULL,
  `endtime` time NOT NULL,
  `capacity` int(11) NOT NULL DEFAULT 10,
  `location` varchar(50) NOT NULL,
  `description` varchar(100) NOT NULL,
  `beneficiaries` varchar(100) NOT NULL,
  `requirements` varchar(100) NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Available',
  PRIMARY KEY (`serviceid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. Vaccination sessions
CREATE TABLE IF NOT EXISTS `vaccination_schedules` (
  `vaccination_id` int(11) NOT NULL AUTO_INCREMENT,
  `vaccine_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `target_beneficiaries` varchar(255) DEFAULT NULL,
  `dose_information` varchar(100) DEFAULT NULL,
  `vaccination_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `capacity` int(11) NOT NULL DEFAULT 10,
  `location` varchar(255) DEFAULT NULL,
  `requirements` text DEFAULT NULL,
  `additional_notes` text DEFAULT NULL,
  `availability_status` enum('Available','Limited Slots','Full','Cancelled','Completed') NOT NULL DEFAULT 'Available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`vaccination_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5. Appointments (each row is for either a service or a vaccination session, never both)
CREATE TABLE IF NOT EXISTS `appointment_tbl` (
  `appointmentid` int(11) NOT NULL AUTO_INCREMENT,
  `residentid` int(11) DEFAULT NULL,
  `booked_via` enum('online','staff') NOT NULL DEFAULT 'online',
  `encoded_by` int(11) DEFAULT NULL,
  `serviceid` int(11) DEFAULT NULL,
  `vaccination_id` int(11) DEFAULT NULL,
  `appointment_time` time NOT NULL,
  `queue_no` int(11) DEFAULT NULL,
  `is_priority` tinyint(1) NOT NULL DEFAULT 0,
  `patient_name` varchar(100) NOT NULL,
  `patient_age` int(11) NOT NULL,
  `patient_address` varchar(255) NOT NULL,
  `patient_contact` varchar(15) NOT NULL,
  `companion_name` varchar(100) DEFAULT NULL,
  `companion_contact` varchar(15) DEFAULT NULL,
  `handled_by` int(11) DEFAULT NULL,
  `status` enum('pending','confirmed','completed','missed','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`appointmentid`),
  FOREIGN KEY (`residentid`) REFERENCES `resident_tbl`(`residentid`),
  FOREIGN KEY (`encoded_by`) REFERENCES `staff_tbl`(`staffid`) ON DELETE SET NULL,
  FOREIGN KEY (`serviceid`) REFERENCES `service_tbl`(`serviceid`),
  FOREIGN KEY (`vaccination_id`) REFERENCES `vaccination_schedules`(`vaccination_id`),
  FOREIGN KEY (`handled_by`) REFERENCES `staff_tbl`(`staffid`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 6. Activity history
--    NOT INCLUDED: the original file did not have this table, and I have not seen its columns.
--    Export it from your current database (phpMyAdmin > activity_log > Export) and paste it here.

-- 7. First administrator (username: admin).
--    Same password as before: the hash below was copied from the old hardcoded login.
--    Change the name, email and contact later from My profile > Edit Profile.
--    INSERT IGNORE makes it safe to run twice.
INSERT IGNORE INTO `staff_tbl`
  (`fname`, `lname`, `email`, `uname`, `contactnum`, `password`, `status`, `role`)
VALUES
  ('System', 'Administrator', 'admin@healthslot.local', 'admin', '09000000000',
   '$2y$10$Eu5La3G4xsPaspsSCA.Iv.k1xIKLnLjCLq1ejlekgpxOkS8Kq9NT.', 'active', 'admin');
