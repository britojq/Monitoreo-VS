/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19-11.8.6-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: 127.0.0.1    Database: monitoreo_vs
-- ------------------------------------------------------
-- Server version	11.8.6-MariaDB-0+deb13u1 from Debian

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` bigint(20) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` bigint(20) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` varchar(255) NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` smallint(5) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `monitored_proxies`
--

DROP TABLE IF EXISTS `monitored_proxies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `monitored_proxies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `letter` varchar(5) NOT NULL,
  `name` varchar(255) NOT NULL,
  `ip_port` varchar(255) NOT NULL,
  `auth_userpass` varchar(255) DEFAULT NULL,
  `test_url` varchar(255) NOT NULL DEFAULT 'https://core.telegram.org/bots',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `monitored_proxies_letter_unique` (`letter`)
) ENGINE=InnoDB AUTO_INCREMENT=2493 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `monitored_services`
--

DROP TABLE IF EXISTS `monitored_services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `monitored_services` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `letter` varchar(5) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'WEB',
  `host_ip` varchar(255) DEFAULT NULL,
  `web_url` varchar(255) DEFAULT NULL,
  `port` int(11) DEFAULT NULL,
  `credentials` varchar(255) DEFAULT NULL,
  `check_interface` varchar(255) DEFAULT NULL,
  `dns_test_domain` varchar(255) DEFAULT NULL,
  `normal_state_msg` varchar(255) DEFAULT NULL,
  `error_state_msg` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `monitored_services_letter_unique` (`letter`)
) ENGINE=InnoDB AUTO_INCREMENT=16218 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `monitored_site_devices`
--

DROP TABLE IF EXISTS `monitored_site_devices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `monitored_site_devices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `monitored_site_id` bigint(20) unsigned NOT NULL,
  `device_number` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `ip` varchar(255) NOT NULL,
  `normal_state_msg` varchar(255) DEFAULT NULL,
  `error_state_msg` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `monitored_site_devices_monitored_site_id_device_number_unique` (`monitored_site_id`,`device_number`),
  CONSTRAINT `monitored_site_devices_monitored_site_id_foreign` FOREIGN KEY (`monitored_site_id`) REFERENCES `monitored_sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=39873 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `monitored_sites`
--

DROP TABLE IF EXISTS `monitored_sites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `monitored_sites` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `letter` varchar(5) NOT NULL,
  `name` varchar(255) NOT NULL,
  `ip` varchar(255) DEFAULT NULL,
  `phone_1` varchar(255) DEFAULT NULL,
  `phone_2` varchar(255) DEFAULT NULL,
  `phone_3` varchar(255) DEFAULT NULL,
  `phone_4` varchar(255) DEFAULT NULL,
  `phone_5` varchar(255) DEFAULT NULL,
  `phone_6` varchar(255) DEFAULT NULL,
  `phone_7` varchar(255) DEFAULT NULL,
  `phone_8` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `normal_state_msg` varchar(255) DEFAULT NULL,
  `error_state_msg` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `monitored_sites_letter_unique` (`letter`)
) ENGINE=InnoDB AUTO_INCREMENT=4985 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `monitoring_snapshots`
--

DROP TABLE IF EXISTS `monitoring_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `monitoring_snapshots` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `global_status` varchar(255) NOT NULL DEFAULT 'OPERACIONAL',
  `services_online` int(11) NOT NULL DEFAULT 0,
  `services_total` int(11) NOT NULL DEFAULT 0,
  `sites_online` int(11) NOT NULL DEFAULT 0,
  `sites_total` int(11) NOT NULL DEFAULT 0,
  `proxies_online` int(11) NOT NULL DEFAULT 0,
  `proxies_total` int(11) NOT NULL DEFAULT 0,
  `payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload_json`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=635 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `proxy_check_histories`
--

DROP TABLE IF EXISTS `proxy_check_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `proxy_check_histories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `monitored_proxy_id` bigint(20) unsigned NOT NULL,
  `is_up` tinyint(1) NOT NULL DEFAULT 1,
  `latency_ms` decimal(8,2) NOT NULL DEFAULT 0.00,
  `http_code` varchar(10) DEFAULT NULL,
  `status_message` varchar(255) DEFAULT NULL,
  `checked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `proxy_check_histories_monitored_proxy_id_checked_at_index` (`monitored_proxy_id`,`checked_at`),
  KEY `proxy_check_histories_is_up_index` (`is_up`),
  KEY `proxy_check_histories_checked_at_index` (`checked_at`),
  CONSTRAINT `proxy_check_histories_monitored_proxy_id_foreign` FOREIGN KEY (`monitored_proxy_id`) REFERENCES `monitored_proxies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2289 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `service_check_histories`
--

DROP TABLE IF EXISTS `service_check_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_check_histories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `monitored_service_id` bigint(20) unsigned NOT NULL,
  `is_up` tinyint(1) NOT NULL DEFAULT 1,
  `latency_ms` decimal(8,2) NOT NULL DEFAULT 0.00,
  `http_code` varchar(10) DEFAULT NULL,
  `status_message` varchar(255) DEFAULT NULL,
  `checked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `service_check_histories_monitored_service_id_checked_at_index` (`monitored_service_id`,`checked_at`),
  KEY `service_check_histories_is_up_index` (`is_up`),
  KEY `service_check_histories_checked_at_index` (`checked_at`),
  CONSTRAINT `service_check_histories_monitored_service_id_foreign` FOREIGN KEY (`monitored_service_id`) REFERENCES `monitored_services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10297 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `site_check_histories`
--

DROP TABLE IF EXISTS `site_check_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `site_check_histories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `monitored_site_id` bigint(20) unsigned NOT NULL,
  `is_up` tinyint(1) NOT NULL DEFAULT 1,
  `latency_ms` decimal(8,2) NOT NULL DEFAULT 0.00,
  `devices_online` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `devices_total` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `status_message` varchar(255) DEFAULT NULL,
  `checked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `site_check_histories_monitored_site_id_checked_at_index` (`monitored_site_id`,`checked_at`),
  KEY `site_check_histories_is_up_index` (`is_up`),
  KEY `site_check_histories_checked_at_index` (`checked_at`),
  CONSTRAINT `site_check_histories_monitored_site_id_foreign` FOREIGN KEY (`monitored_site_id`) REFERENCES `monitored_sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2861 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(255) NOT NULL DEFAULT 'admin',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `ban_reason` varchar(255) DEFAULT NULL,
  `banned_at` timestamp NULL DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(255) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `banned_ips`
--

DROP TABLE IF EXISTS `banned_ips`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `banned_ips` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `banned_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `banned_ips_ip_address_unique` (`ip_address`),
  KEY `idx_banned_ip` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2026-09-02 12:05:02
/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19-11.8.6-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: 127.0.0.1    Database: monitoreo_vs
-- ------------------------------------------------------
-- Server version	11.8.6-MariaDB-0+deb13u1 from Debian

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

--
-- Dumping data for table `monitored_services`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `monitored_services` WRITE;
/*!40000 ALTER TABLE `monitored_services` DISABLE KEYS */;
INSERT INTO `monitored_services` VALUES
(1,'A','OTRS','WEB','10.18.32.6','http://gsatit.corpoelec.com.ve/otrs/index.pl',NULL,'USUARIO:CLAVE','eno1','intranet.corpoelec.com.ve','✅ - $NAMESERVICEA','❌ - $NAMESERVICEA',1,0,'2026-08-31 16:09:38','2026-09-02 16:05:02'),
(2,'B','INTRANET CORPOELEC','WEB','10.16.2.28','http://intranet.corpoelec.com.ve',NULL,'USUARIO:CLAVE','eno1','intranet.corpoelec.com.ve','✅ - $NAMESERVICEB','❌ - $NAMESERVICEB',1,1,'2026-08-31 16:09:38','2026-09-02 16:05:02'),
(3,'C','CORREO WEB','WEB','10.250.34.67','http://correo.corpoelec.com.ve//login.php?phpgw_forward=%2Findex.php',NULL,'USUARIO:CLAVE','eno1','intranet.corpoelec.com.ve','✅ - $NAMESERVICEC','❌ - $NAMESERVICEC',1,2,'2026-08-31 16:09:38','2026-09-02 16:05:02'),
(4,'D','SAO','WEB','10.96.0.136','https://saocorp.corpoelec.com.ve:3002/login?service=https%3A%2F%2Fsaocorp.corpoelec.com.ve%3A6200%2F',NULL,'USUARIO:CLAVE','eno1','intranet.corpoelec.com.ve','✅ - $NAMESERVICED','❌ - $NAMESERVICED',1,3,'2026-08-31 16:09:38','2026-09-02 16:05:02'),
(5,'E','SURGE','WEB','10.16.2.62','http://surge.corpoelec.com.ve/gestion-incidencias-cliente-web/ui/FrmTablero.html',NULL,'USUARIO:CLAVE','eno1','intranet.corpoelec.com.ve','✅ - $NAMESERVICEE','❌ - $NAMESERVICEE',1,4,'2026-08-31 16:09:38','2026-09-02 16:05:02'),
(6,'F','FACTURA DIGITAL','WEB','10.16.2.28','http://facturadigital.corpoelec.com.ve/',NULL,'USUARIO:CLAVE','eno1','intranet.corpoelec.com.ve','✅ - $NAMESERVICEF','❌ - $NAMESERVICEF',1,5,'2026-08-31 16:09:38','2026-09-02 16:05:02'),
(7,'G','SISTEMA AUTOGESTION DE CLAVES','WEB','10.16.2.22','http://cambioclaves.corpoelec.com.ve/gc/',NULL,'USUARIO:CLAVE','eno1','intranet.corpoelec.com.ve','✅ - $NAMESERVICEG','❌ - $NAMESERVICEG',1,6,'2026-08-31 16:09:38','2026-09-02 16:05:02'),
(8,'H','NO CONFIGURADO','WEB','10.16.2.22','https://claver1-sb.corpoelec.com.ve/',NULL,'USUARIO:CLAVE','eno1','intranet.corpoelec.com.ve','✅ - $NAMESERVICEH','❌ - $NAMESERVICEH',0,7,'2026-08-31 16:09:38','2026-09-02 16:05:02'),
(9,'I','LDAPADMIN Corporativo','WEB','10.2.28.64','https://ldapadmin.corpoelec.com.ve/htdocs/index.php',NULL,'USUARIO:CLAVE','eno1','intranet.corpoelec.com.ve','✅ - $NAMESERVICEI','❌ - $NAMESERVICEI',1,8,'2026-08-31 16:09:38','2026-09-02 16:05:02'),
(10,'J','Sistema de Incidencia CARABOBO','OTRO','10.18.32.33','https://T4WINP33-SIAR.ev.com',NULL,'USUARIO:CLAVE','eno1','intranet.corpoelec.com.ve','✅ - $NAMESERVICEJ','❌ - $NAMESERVICEJ',1,9,'2026-08-31 16:09:38','2026-09-02 16:05:02'),
(11,'K','SAP - CORPOELEC Produccion R3','OTRO','10.100.94.230','10.100.94.230',NULL,'USUARIO:CLAVE','eno1','intranet.corpoelec.com.ve','✅ - $NAMESERVICEK','❌ - $NAMESERVICEK',1,10,'2026-08-31 16:09:38','2026-09-02 16:05:02'),
(12,'L','LDAP CARABOBO','LDAP','10.20.0.22','ldapr2-t4.corpoelec.com.ve',389,'USUARIO:CLAVE','eno1','intranet.corpoelec.com.ve','✅ - $NAMESERVICEL','❌ - $NAMESERVICEL',1,11,'2026-08-31 16:09:38','2026-09-02 16:05:02'),
(13,'M','(DNS PRIMARIO CARABOBO - ARAGUA)','DNS','100.1.1.16','100.1.1.16',53,'USUARIO:CLAVE','eno1','intranet.corpoelec.com.ve','✅ - $NAMESERVICEM','❌ - $NAMESERVICEM',1,12,'2026-08-31 16:09:38','2026-09-02 16:05:02'),
(14,'N','(DNS SECUNDARIO CARABOBO - ARAGUA)','WEB','10.100.94.230','https://ldapr2-002.ev.com:10000/',NULL,'USUARIO:CLAVE','eno1','intranet.corpoelec.com.ve','✅ - $NAMESERVICEN','❌ - $NAMESERVICEN',1,13,'2026-08-31 16:09:38','2026-09-02 16:05:02'),
(15,'O','DHCP CARABOBO - ARAGUA','DHCP','10.18.32.38','t4linp38.corpoelec.com.ve',NULL,'USUARIO:CLAVE','eno1','intranet.corpoelec.com.ve','✅ - $NAMESERVICEO','❌ - $NAMESERVICEO',1,14,'2026-08-31 16:09:38','2026-09-02 16:05:02'),
(16,'P','NO CONFIGURADO','OTRO','0.0.0.0','proxyr2-t4.corpoelec.com.ve',NULL,'A1746281:Carabobo01*','eno1','intranet.corpoelec.com.ve','✅ - $NAMESERVICEP','❌ - $NAMESERVICEP',0,15,'2026-08-31 16:09:38','2026-09-02 16:05:02'),
(17,'Q','Pfsense - CARABOBO (119)','PROXY','10.20.0.119:8080','proxyr2-vs.corpoelec.com.ve',8080,'A1746281:Abrito2026.*','eno1','intranet.corpoelec.com.ve','✅ - $NAMESERVICEQ','❌ - $NAMESERVICEQ',1,16,'2026-08-31 16:09:38','2026-09-02 16:05:02'),
(18,'R','Pfsense - CARABOBO (89)','PROXY','10.20.0.89','pfsense.corpoelec.com.ve',8080,'A1746281:Abrito2026.*','eno1','intranet.corpoelec.com.ve','✅ - $NAMESERVICER','❌ - $NAMESERVICER',1,17,'2026-08-31 16:09:38','2026-09-02 16:05:02'),
(19,'S','Pfsense - Valle Seco (65)','PROXY','10.20.23.65','pfsense.corpoelec.com.ve',8080,'jbrito:Octubre2022.','eno1','intranet.corpoelec.com.ve','✅ - $NAMESERVICES','❌ - $NAMESERVICES',1,18,'2026-08-31 16:09:38','2026-09-02 16:05:02'),
(20,'T','Servidor de Correo CARABOBO (thunderbird)','SMTP','10.18.32.6','mailr2-t4.corpoelec.com.ve',25,'USUARIO:CLAVE','eno1','intranet.corpoelec.com.ve','✅ - $NAMESERVICET','❌ - $NAMESERVICET',1,19,'2026-08-31 16:09:38','2026-09-02 16:05:02'),
(21,'U','NO CONFIGURADO','OTRO','0.0.0.0','corpoelec.com.ve',NULL,'USUARIO:CLAVE','eno1','intranet.corpoelec.com.ve','✅ - $NAMESERVICEU','❌ - $NAMESERVICEU',0,20,'2026-08-31 16:09:38','2026-09-02 16:05:02'),
(22,'V','NO CONFIGURADO','OTRO','0.0.0.0','corpoelec.com.ve',NULL,'USUARIO:CLAVE','eno1','intranet.corpoelec.com.ve','✅ - $NAMESERVICEV','❌ - $NAMESERVICEV',0,21,'2026-08-31 16:09:38','2026-09-02 16:05:02'),
(23,'W','NO CONFIGURADO','OTRO','0.0.0.0','corpoelec.com.ve',NULL,'USUARIO:CLAVE','eno1','intranet.corpoelec.com.ve','✅ - $NAMESERVICEW','❌ - $NAMESERVICEW',0,22,'2026-08-31 16:09:38','2026-09-02 16:04:09'),
(24,'X','NO CONFIGURADO','OTRO','0.0.0.0','corpoelec.com.ve',NULL,'USUARIO:CLAVE','eno1','intranet.corpoelec.com.ve','✅ - $NAMESERVICEX','❌ - $NAMESERVICEX',0,23,'2026-08-31 16:09:38','2026-09-02 16:04:09'),
(25,'Y','NO CONFIGURADO','OTRO','0.0.0.0','corpoelec.com.ve',NULL,'USUARIO:CLAVE','eno1','intranet.corpoelec.com.ve','✅ - $NAMESERVICEY','❌ - $NAMESERVICEY',0,24,'2026-08-31 16:09:38','2026-09-02 16:04:09'),
(26,'Z','NO CONFIGURADO','OTRO','0.0.0.0','corpoelec.com.ve',NULL,'USUARIO:CLAVE','eno1','intranet.corpoelec.com.ve','✅ - $NAMESERVICEZ','❌ - $NAMESERVICEZ',0,25,'2026-08-31 16:09:38','2026-09-02 16:04:09');
/*!40000 ALTER TABLE `monitored_services` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Dumping data for table `monitored_sites`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `monitored_sites` WRITE;
/*!40000 ALTER TABLE `monitored_sites` DISABLE KEYS */;
INSERT INTO `monitored_sites` VALUES
(1,'A','CIAU VALLE SECO','10.20.23.1','0242-3610061','0242-3613722 (ABBA)','0242-3612410','0242-3614625','0242-3616717','0242-3610539','0242-3612140','0242-3612140','Urb. Colinas de Valle Seco, final Av. Bolívar. Edificio CALIFE., Puerto Cabello - Edo Carabobo','✅ $NAMESITEA','❌ $NAMESITEA',1,0,'2026-08-31 16:09:38','2026-09-02 16:04:09'),
(2,'B','NO CONFIGURADO','10.20.107.194','0242 - 3611044','0242 - 3618442','NO CONFIGURADO','NO CONFIGURADO','NO CONFIGURADO','NO CONFIGURADO','NO CONFIGURADO','NO CONFIGURADO','Urb. Rancho Grande, Calle N° 29, C.C. Campo Alegre, PB, Local N° 1 y 2 B., Puerto Cabello - Edo Carabobo','✅ $NAMESITEB','❌ $NAMESITEB',0,1,'2026-08-31 16:09:38','2026-09-02 16:04:09'),
(3,'C','CIAU CONSOLIDADO','10.20.107.131','0242 - 3618047','0242 - 3619584','NO CONFIGURADO','NO CONFIGURADO','NO CONFIGURADO','NO CONFIGURADO','NO CONFIGURADO','NO CONFIGURADO','Calle Mariño, C.C. Consolidado, Edificio Planta Alta y Baja, Local N° 1., Puerto Cabello - Edo Carabobo','✅ $NAMESITEC','❌ $NAMESITEC',1,2,'2026-08-31 16:09:39','2026-09-02 16:04:09'),
(4,'D','CIAU PASEO MARIÑO','10.20.107.131','0242 - 3618706','0242 - 3611317','NO CONFIGURADO','NO CONFIGURADO','NO CONFIGURADO','NO CONFIGURADO','NO CONFIGURADO','NO CONFIGURADO','NO CONFIGURADO','✅ $NAMESITED','❌ $NAMESITED',1,3,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(5,'E','CIAU MORON','10.20.106.193','0242 - 3724250','0242 - 3720540','NO CONFIGURADO','NO CONFIGURADO','NO CONFIGURADO','NO CONFIGURADO','NO CONFIGURADO','NO CONFIGURADO','Edificio CORPOELEC, Calle San José con calle Miranda Diagonal a casa de la Cultura, Moron - Edo Carabobo','✅ $NAMESITEE','❌ $NAMESITEE',1,4,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(6,'F','CIAU -  Guaicamacuto','0.0.0.0','0242 - 3645902','0242 - 3645980','NO CONFIGURADO','NO CONFIGURADO','NO CONFIGURADO','NO CONFIGURADO','NO CONFIGURADO','NO CONFIGURADO','Dirección:Av la paz C C Guaicamacuto Nro M14B Cumboto Norte, Puerto Cabello Edo Carabobo','✅ $NAMESITEF','❌ $NAMESITEF',0,5,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(7,'G','CORDINACION TRASMISION','10.20.27.65','0242-3621429','NO CONFIGURADO','NO CONFIGURADO','NO CONFIGURADO','NO CONFIGURADO','NO CONFIGURADO','NO CONFIGURADO','NO CONFIGURADO','NO CONFIGURADO','✅ $NAMESITEG','❌ $NAMESITEG',1,6,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(8,'H','Subestación Valle Seco','0.0.0.0','0242-3620513','NO CONFIGURADO','NO CONFIGURADO','NO CONFIGURADO','NO CONFIGURADO','NO CONFIGURADO','NO CONFIGURADO','NO CONFIGURADO','Urb. Colinas de Valle Seco, final Av. Bolívar','✅ $NAMESITEH','❌ $NAMESITEH',0,7,'2026-08-31 16:09:39','2026-09-02 16:04:10');
/*!40000 ALTER TABLE `monitored_sites` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Dumping data for table `monitored_site_devices`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `monitored_site_devices` WRITE;
/*!40000 ALTER TABLE `monitored_site_devices` DISABLE KEYS */;
INSERT INTO `monitored_site_devices` VALUES
(1,1,1,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITEAEQUIPO1','❌ $NAMESITEAEQUIPO1',0,'2026-08-31 16:09:38','2026-09-02 16:04:09'),
(2,1,2,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITEAEQUIPO2','❌ $NAMESITEAEQUIPO2',0,'2026-08-31 16:09:38','2026-09-02 16:04:09'),
(3,1,3,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITEAEQUIPO3','❌ $NAMESITEAEQUIPO3',0,'2026-08-31 16:09:38','2026-09-02 16:04:09'),
(4,1,4,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITEAEQUIPO4','❌ $NAMESITEAEQUIPO4',0,'2026-08-31 16:09:38','2026-09-02 16:04:09'),
(5,1,5,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITEAEQUIPO5','❌ $NAMESITEAEQUIPO5',0,'2026-08-31 16:09:38','2026-09-02 16:04:09'),
(6,1,6,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITEAEQUIPO6','❌ $NAMESITEAEQUIPO6',0,'2026-08-31 16:09:38','2026-09-02 16:04:09'),
(7,1,7,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITEAEQUIPO7','❌ $NAMESITEAEQUIPO7',0,'2026-08-31 16:09:38','2026-09-02 16:04:09'),
(8,1,8,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITEAEQUIPO8','❌ $NAMESITEAEQUIPO8',0,'2026-08-31 16:09:38','2026-09-02 16:04:09'),
(9,2,1,'NO CONFIGURADO','10.20.107.229','✅ $NAMESITEBEQUIPO1','❌ $NAMESITEBEQUIPO1',0,'2026-08-31 16:09:38','2026-09-02 16:04:09'),
(10,2,2,'NO CONFIGURADO','10.20.107.223','✅ $NAMESITEBEQUIPO2','❌ $NAMESITEBEQUIPO2',0,'2026-08-31 16:09:38','2026-09-02 16:04:09'),
(11,2,3,'NO CONFIGURADO','10.20.107.228','✅ $NAMESITEBEQUIPO3','❌ $NAMESITEBEQUIPO3',0,'2026-08-31 16:09:39','2026-09-02 16:04:09'),
(12,2,4,'NO CONFIGURADO','10.20.107.201','✅ $NAMESITEBEQUIPO4','❌ $NAMESITEBEQUIPO4',0,'2026-08-31 16:09:39','2026-09-02 16:04:09'),
(13,2,5,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITEBEQUIPO5','❌ $NAMESITEBEQUIPO5',0,'2026-08-31 16:09:39','2026-09-02 16:04:09'),
(14,2,6,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITEBEQUIPO6','❌ $NAMESITEBEQUIPO6',0,'2026-08-31 16:09:39','2026-09-02 16:04:09'),
(15,2,7,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITEBEQUIPO7','❌ $NAMESITEBEQUIPO7',0,'2026-08-31 16:09:39','2026-09-02 16:04:09'),
(16,2,8,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITEBEQUIPO8','❌ $NAMESITEBEQUIPO8',0,'2026-08-31 16:09:39','2026-09-02 16:04:09'),
(17,3,1,'JEFE OFICINA','10.20.107.154','✅ $NAMESITECEQUIPO1','❌ $NAMESITECEQUIPO1',1,'2026-08-31 16:09:39','2026-09-02 16:04:09'),
(18,3,2,'TAQUILLA 01','10.20.107.150','✅ $NAMESITECEQUIPO2','❌ $NAMESITECEQUIPO2',1,'2026-08-31 16:09:39','2026-09-02 16:04:09'),
(19,3,3,'TAQUILLA 02','10.20.107.151','✅ $NAMESITECEQUIPO3','❌ $NAMESITECEQUIPO3',1,'2026-08-31 16:09:39','2026-09-02 16:04:09'),
(20,3,4,'ATU 01','10.20.107.153','✅ $NAMESITECEQUIPO4','❌ $NAMESITECEQUIPO4',1,'2026-08-31 16:09:39','2026-09-02 16:04:09'),
(21,3,5,'IMPRESORA RED','10.20.107.136','✅ $NAMESITECEQUIPO5','❌ $NAMESITECEQUIPO5',1,'2026-08-31 16:09:39','2026-09-02 16:04:09'),
(22,3,6,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITECEQUIPO6','❌ $NAMESITECEQUIPO6',0,'2026-08-31 16:09:39','2026-09-02 16:04:09'),
(23,3,7,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITECEQUIPO7','❌ $NAMESITECEQUIPO7',0,'2026-08-31 16:09:39','2026-09-02 16:04:09'),
(24,3,8,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITECEQUIPO8','❌ $NAMESITECEQUIPO8',0,'2026-08-31 16:09:39','2026-09-02 16:04:09'),
(25,4,1,'JEFE OFICINA','10.20.107.155','✅ $NAMESITEDEQUIPO1','❌ $NAMESITEDEQUIPO1',1,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(26,4,2,'TAQUILLA 01','10.20.107.148','✅ $NAMESITEDEQUIPO2','❌ $NAMESITEDEQUIPO2',1,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(27,4,3,'TAQUILLA 02','10.20.107.152','✅ $NAMESITEDEQUIPO3','❌ $NAMESITEDEQUIPO3',1,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(28,4,4,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITEDEQUIPO4','❌ $NAMESITEDEQUIPO4',0,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(29,4,5,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITEDEQUIPO5','❌ $NAMESITEDEQUIPO5',0,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(30,4,6,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITEDEQUIPO6','❌ $NAMESITEDEQUIPO6',0,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(31,4,7,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITEDEQUIPO7','❌ $NAMESITEDEQUIPO7',0,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(32,4,8,'IMPRESORA RED','0.0.0.0','✅ $NAMESITEDEQUIPO8','❌ $NAMESITEDEQUIPO8',0,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(33,5,1,'JEFE OFICINA','10.20.106.241','✅ $NAMESITEEEQUIPO1','❌ $NAMESITEEEQUIPO1',1,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(34,5,2,'TAQUILLA 01','10.20.106.240','✅ $NAMESITEEEQUIPO2','❌ $NAMESITEEEQUIPO2',1,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(35,5,3,'TAQUILLA 02','10.20.106.239','✅ $NAMESITEEEQUIPO3','❌ $NAMESITEEEQUIPO3',1,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(36,5,4,'TAQUILLA 03','10.20.106.238','✅ $NAMESITEEEQUIPO4','❌ $NAMESITEEEQUIPO4',1,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(37,5,5,'ATU 01','10.20.106.242','✅ $NAMESITEEEQUIPO5','❌ $NAMESITEEEQUIPO5',1,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(38,5,6,'IMPRESORA RED','10.20.106.253','✅ $NAMESITEEEQUIPO6','❌ $NAMESITEEEQUIPO6',1,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(39,5,7,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITEEEQUIPO7','❌ $NAMESITEEEQUIPO7',0,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(40,5,8,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITEEEQUIPO8','❌ $NAMESITEEEQUIPO8',0,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(41,6,1,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITEFEQUIPO1','❌ $NAMESITEFEQUIPO1',0,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(42,6,2,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITEFEQUIPO2','❌ $NAMESITEFEQUIPO2',0,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(43,6,3,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITEFEQUIPO3','❌ $NAMESITEFEQUIPO3',0,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(44,6,4,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITEFEQUIPO4','❌ $NAMESITEFEQUIPO4',0,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(45,6,5,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITEFEQUIPO5','❌ $NAMESITEFEQUIPO5',0,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(46,6,6,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITEFEQUIPO6','❌ $NAMESITEFEQUIPO6',0,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(47,6,7,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITEFEQUIPO7','❌ $NAMESITEFEQUIPO7',0,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(48,6,8,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITEFEQUIPO8','❌ $NAMESITEFEQUIPO8',0,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(49,7,1,'EQUIPO 01','10.20.27.81','✅ $NAMESITEGEQUIPO1','❌ $NAMESITEGEQUIPO1',1,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(50,7,2,'EQUIPO 02','10.20.27.82','✅ $NAMESITEGEQUIPO2','❌ $NAMESITEGEQUIPO2',1,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(51,7,3,'EQUIPO 03','10.20.27.83','✅ $NAMESITEGEQUIPO3','❌ $NAMESITEGEQUIPO3',1,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(52,7,4,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITEGEQUIPO4','❌ $NAMESITEGEQUIPO4',0,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(53,7,5,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITEGEQUIPO5','❌ $NAMESITEGEQUIPO5',0,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(54,7,6,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITEGEQUIPO6','❌ $NAMESITEGEQUIPO6',0,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(55,7,7,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITEGEQUIPO7','❌ $NAMESITEGEQUIPO7',0,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(56,7,8,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITEGEQUIPO8','❌ $NAMESITEGEQUIPO8',0,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(57,8,1,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITEHEQUIPO1','❌ $NAMESITEHEQUIPO1',0,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(58,8,2,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITEHEQUIPO2','❌ $NAMESITEHEQUIPO2',0,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(59,8,3,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITEHEQUIPO3','❌ $NAMESITEHEQUIPO3',0,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(60,8,4,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITEHEQUIPO4','❌ $NAMESITEHEQUIPO4',0,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(61,8,5,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITEHEQUIPO5','❌ $NAMESITEHEQUIPO5',0,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(62,8,6,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITEHEQUIPO6','❌ $NAMESITEHEQUIPO6',0,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(63,8,7,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITEHEQUIPO7','❌ $NAMESITEHEQUIPO7',0,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(64,8,8,'NO CONFIGURADO','0.0.0.0','✅ $NAMESITEHEQUIPO8','❌ $NAMESITEHEQUIPO8',0,'2026-08-31 16:09:39','2026-09-02 16:04:10');
/*!40000 ALTER TABLE `monitored_site_devices` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Dumping data for table `monitored_proxies`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `monitored_proxies` WRITE;
/*!40000 ALTER TABLE `monitored_proxies` DISABLE KEYS */;
INSERT INTO `monitored_proxies` VALUES
(1,'A','Squid - Dansguardian - CARABOBO','10.20.0.89:8080','A1746281:Abrito2026.*','https://core.telegram.org/bots',1,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(2,'B','Squid - Dansguardian - VALLE SECO','10.20.23.65:8080','jbrito:Octubre2022.','https://core.telegram.org/bots',1,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(3,'C','PFsense - CARABOBO (119)','10.20.0.119:8080','A1746281:Abrito2026.*','https://core.telegram.org/bots',1,'2026-08-31 16:09:39','2026-09-02 16:04:10'),
(4,'D','PFsense - CARABOBO (89)','10.20.0.89:8080','A1746281:Abrito2026.*','https://core.telegram.org/bots',1,'2026-08-31 16:09:39','2026-09-02 16:04:10');
/*!40000 ALTER TABLE `monitored_proxies` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Dumping data for table `users`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES
(1,'Jose A. Brito H.','britojq@gmail.com',NULL,'$2y$12$G5Ei/1ki20x1CKsqlgl3mOPYrfgJpqVBE4Tc0ECfCj2h50elUzVtK','admin',1,'2026-09-02 14:58:29','127.0.0.1',NULL,'2026-08-31 16:09:38','2026-09-02 14:58:29'),
(2,'Operador','operador@corpoelec.gob.ve',NULL,'$2y$12$uEMGMIQqxHbrGWslEikzb.m6WDdMmM.PN6O.bEFadzBrY14XLSTtu','operator',1,NULL,NULL,NULL,'2026-08-31 15:51:40','2026-08-31 15:51:40');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Dumping data for table `migrations`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES
(1,'0001_01_01_000000_create_users_table',1),
(2,'0001_01_01_000001_create_cache_table',1),
(3,'0001_01_01_000002_create_jobs_table',1),
(4,'2026_08_31_120654_add_roles_and_status_to_users_table',2),
(5,'2026_08_31_120654_create_monitored_services_table',2),
(6,'2026_08_31_120654_create_monitored_sites_table',3),
(7,'2026_08_31_120655_create_monitored_proxies_table',3),
(8,'2026_08_31_120655_create_monitoring_snapshots_table',3),
(9,'2026_08_31_120656_create_monitored_site_devices_table',4),
(10,'2026_08_31_120657_create_service_check_histories_table',5),
(11,'2026_08_31_120658_create_site_check_histories_table',6),
(12,'2026_08_31_120659_create_proxy_check_histories_table',7);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2026-09-02 12:05:02
