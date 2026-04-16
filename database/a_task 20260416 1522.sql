-- MySQL Administrator dump 1.4
--
-- ------------------------------------------------------
-- Server version	5.5.5-10.4.32-MariaDB


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;


--
-- Create schema a_task_dev
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ a_task;
USE a_task;

--
-- Table structure for table `a_task_dev`.`audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action_type` varchar(50) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `description` text NOT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_entity` (`entity_type`,`entity_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_action` (`action_type`),
  CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=274 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `a_task_dev`.`audit_logs`
--



--
-- Table structure for table `a_task_dev`.`business_plans`
--

DROP TABLE IF EXISTS `business_plans`;
CREATE TABLE `business_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `month` tinyint(4) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('planned','in_progress','completed','cancelled') DEFAULT 'planned',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_dept_year_month` (`department_id`,`year`,`month`),
  CONSTRAINT `business_plans_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `business_plans_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `a_task_dev`.`business_plans`
--



--
-- Table structure for table `a_task_dev`.`checklist_template_items`
--

DROP TABLE IF EXISTS `checklist_template_items`;
CREATE TABLE `checklist_template_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_id` int(11) NOT NULL,
  `item_text` text NOT NULL,
  `order_index` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `template_id` (`template_id`),
  CONSTRAINT `checklist_template_items_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `checklist_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `a_task_dev`.`checklist_template_items`
--




--
-- Table structure for table `a_task_dev`.`checklist_templates`
--

DROP TABLE IF EXISTS `checklist_templates`;
CREATE TABLE `checklist_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `checklist_templates_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `a_task_dev`.`checklist_templates`
--




--
-- Table structure for table `a_task_dev`.`department_permissions`
--

DROP TABLE IF EXISTS `department_permissions`;
CREATE TABLE `department_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) NOT NULL,
  `can_view_department_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_permission` (`department_id`,`can_view_department_id`),
  KEY `can_view_department_id` (`can_view_department_id`),
  CONSTRAINT `department_permissions_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `department_permissions_ibfk_2` FOREIGN KEY (`can_view_department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `a_task_dev`.`department_permissions`
--

/*!40000 ALTER TABLE `department_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `department_permissions` ENABLE KEYS */;


--
-- Table structure for table `a_task_dev`.`departments`
--

DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `a_task_dev`.`departments`
--

/*!40000 ALTER TABLE `departments` DISABLE KEYS */;
INSERT INTO `departments` (`id`,`name`,`description`,`created_at`) VALUES 
 (1,'IT','Bilgi Teknolojileri','2026-01-14 13:58:09')

/*!40000 ALTER TABLE `departments` ENABLE KEYS */;


--
-- Table structure for table `a_task_dev`.`main_topics`
--

DROP TABLE IF EXISTS `main_topics`;
CREATE TABLE `main_topics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `a_task_dev`.`main_topics`
--

/*!40000 ALTER TABLE `main_topics` DISABLE KEYS */;
INSERT INTO `main_topics` (`id`,`name`,`description`) VALUES 
 (1,'Yazılım Geliştirme','Yazılım projelerini içerir'),
 (2,'Sistem Bakımı','Sistem ve altyapı bakım görevleri'),
 (3,'Eğitim','Eğitim ve dokümantasyon'),
 (4,'Destek','Kullanıcı destek talepleri');
/*!40000 ALTER TABLE `main_topics` ENABLE KEYS */;


--
-- Table structure for table `a_task_dev`.`priorities`
--

DROP TABLE IF EXISTS `priorities`;
CREATE TABLE `priorities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `color` varchar(7) NOT NULL DEFAULT '#6B7280',
  `order_index` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `a_task_dev`.`priorities`
--

/*!40000 ALTER TABLE `priorities` DISABLE KEYS */;
INSERT INTO `priorities` (`id`,`name`,`color`,`order_index`) VALUES 
 (1,'Düşük','#10B981',1),
 (2,'Normal','#3B82F6',2),
 (3,'Yüksek','#F59E0B',3),
 (4,'Kritik','#EF4444',4);
/*!40000 ALTER TABLE `priorities` ENABLE KEYS */;


--
-- Table structure for table `a_task_dev`.`project_steps`
--

DROP TABLE IF EXISTS `project_steps`;
CREATE TABLE `project_steps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `order_index` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  CONSTRAINT `project_steps_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `a_task_dev`.`project_steps`
--




--
-- Table structure for table `a_task_dev`.`projects`
--

DROP TABLE IF EXISTS `projects`;
CREATE TABLE `projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `type` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_project_department` (`department_id`),
  CONSTRAINT `fk_project_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `a_task_dev`.`projects`
--


--
-- Table structure for table `a_task_dev`.`statuses`
--

DROP TABLE IF EXISTS `statuses`;
CREATE TABLE `statuses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `color` varchar(7) NOT NULL DEFAULT '#6B7280',
  `kanban_column` varchar(50) NOT NULL DEFAULT 'todo',
  `order_index` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `a_task_dev`.`statuses`
--

/*!40000 ALTER TABLE `statuses` DISABLE KEYS */;
INSERT INTO `statuses` (`id`,`name`,`color`,`kanban_column`,`order_index`) VALUES 
 (1,'Yeni','#6B7280','todo',1),
 (2,'İşlemde','#3b82f6','in_progress',3),
 (3,'Beklemede','#f59e0b','in_progress',4),
 (5,'Tamamlandı','#10b981','done',6),
 (6,'İptal','#ef4444','done',7),
 (7,'Bu Gün Yap','#ad06ea','in_progress',2);
/*!40000 ALTER TABLE `statuses` ENABLE KEYS */;


--
-- Table structure for table `a_task_dev`.`sub_topics`
--

DROP TABLE IF EXISTS `sub_topics`;
CREATE TABLE `sub_topics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `main_topic_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_sub_topic` (`main_topic_id`,`name`),
  CONSTRAINT `sub_topics_ibfk_1` FOREIGN KEY (`main_topic_id`) REFERENCES `main_topics` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `a_task_dev`.`sub_topics`
--

/*!40000 ALTER TABLE `sub_topics` DISABLE KEYS */;
INSERT INTO `sub_topics` (`id`,`main_topic_id`,`name`,`description`) VALUES 
 (1,1,'Frontend','Frontend geliştirme'),
 (2,1,'Backend','Backend geliştirme'),
 (3,1,'Mobile','Mobil uygulama'),
 (4,2,'Sunucu','Sunucu bakımı'),
 (5,2,'Veritabanı','Veritabanı bakımı'),
 (6,3,'Kullanıcı Eğitimi','Son kullanıcı eğitimi'),
 (7,4,'Teknik Destek','Teknik destek talepleri');
/*!40000 ALTER TABLE `sub_topics` ENABLE KEYS */;


--
-- Table structure for table `a_task_dev`.`task_attachments`
--

DROP TABLE IF EXISTS `task_attachments`;
CREATE TABLE `task_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `task_id` (`task_id`),
  KEY `uploaded_by` (`uploaded_by`),
  CONSTRAINT `task_attachments_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_attachments_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `a_task_dev`.`task_attachments`
--




--
-- Table structure for table `a_task_dev`.`task_checklist_items`
--

DROP TABLE IF EXISTS `task_checklist_items`;
CREATE TABLE `task_checklist_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `item_text` text NOT NULL,
  `is_completed` tinyint(1) DEFAULT 0,
  `order_index` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_id` (`task_id`),
  CONSTRAINT `task_checklist_items_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=69 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `a_task_dev`.`task_checklist_items`
--




--
-- Table structure for table `a_task_dev`.`task_comments`
--

DROP TABLE IF EXISTS `task_comments`;
CREATE TABLE `task_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `task_id` (`task_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `task_comments_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `a_task_dev`.`task_comments`
--




--
-- Table structure for table `a_task_dev`.`tasks`
--

DROP TABLE IF EXISTS `tasks`;
CREATE TABLE `tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) DEFAULT NULL,
  `project_step_id` int(11) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `priority_id` int(11) DEFAULT NULL,
  `status_id` int(11) DEFAULT NULL,
  `main_topic_id` int(11) DEFAULT NULL,
  `sub_topic_id` int(11) DEFAULT NULL,
  `owner_id` int(11) NOT NULL,
  `requester_id` int(11) NOT NULL,
  `requesting_department_id` int(11) NOT NULL,
  `responsible_department_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `target_completion_date` date DEFAULT NULL,
  `order_index` int(11) DEFAULT 0,
  `hashtags` varchar(255) DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `recurring_group_id` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `priority_id` (`priority_id`),
  KEY `status_id` (`status_id`),
  KEY `main_topic_id` (`main_topic_id`),
  KEY `sub_topic_id` (`sub_topic_id`),
  KEY `owner_id` (`owner_id`),
  KEY `requester_id` (`requester_id`),
  KEY `requesting_department_id` (`requesting_department_id`),
  KEY `responsible_department_id` (`responsible_department_id`),
  KEY `fk_task_project` (`project_id`),
  KEY `fk_task_project_step` (`project_step_id`),
  KEY `idx_recurring_group` (`recurring_group_id`),
  CONSTRAINT `fk_task_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_task_project_step` FOREIGN KEY (`project_step_id`) REFERENCES `project_steps` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`priority_id`) REFERENCES `priorities` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`status_id`) REFERENCES `statuses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tasks_ibfk_3` FOREIGN KEY (`main_topic_id`) REFERENCES `main_topics` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tasks_ibfk_4` FOREIGN KEY (`sub_topic_id`) REFERENCES `sub_topics` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tasks_ibfk_5` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`),
  CONSTRAINT `tasks_ibfk_6` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`),
  CONSTRAINT `tasks_ibfk_7` FOREIGN KEY (`requesting_department_id`) REFERENCES `departments` (`id`),
  CONSTRAINT `tasks_ibfk_8` FOREIGN KEY (`responsible_department_id`) REFERENCES `departments` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `a_task_dev`.`tasks`
--




--
-- Table structure for table `a_task_dev`.`user_departments`
--

DROP TABLE IF EXISTS `user_departments`;
CREATE TABLE `user_departments` (
  `user_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  PRIMARY KEY (`user_id`,`department_id`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `user_departments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_departments_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `a_task_dev`.`user_departments`
--

/*!40000 ALTER TABLE `user_departments` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_departments` ENABLE KEYS */;


--
-- Table structure for table `a_task_dev`.`users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `department_id` int(11) NOT NULL,
  `is_admin` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `a_task_dev`.`users`
--

/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` (`id`,`username`,`password_hash`,`full_name`,`email`,`department_id`,`is_admin`,`is_active`,`created_at`) VALUES 
 (1,'admin','$2y$10$oQukYPgtaQalq7AW3K3i3OR3FoyfnDztT0KJNx9Qbk.RcEwNdf8nC','Sistem Admin','admin@company.com',1,1,1,'2026-01-14 13:58:09')
/*!40000 ALTER TABLE `users` ENABLE KEYS */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
