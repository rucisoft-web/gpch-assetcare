-- Asset Care GPCH: struktur 67 tabel + data referensi (izin, 15 peran, departemen, kategori, SLA, kebijakan aset tetap, jenis dokumen, parameter kondisi, identitas perusahaan, Super Admin).
-- Impor ke database kosong (phpMyAdmin › Import). Super Admin: admin@assetcare.local / AssetCare@GPCH2026 (wajib diganti saat login).
-- Tanpa data contoh. Setelah impor jalankan: bash deploy/deploy-cpanel.sh update


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `app_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `app_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `app_settings_key_unique` (`key`),
  KEY `app_settings_updated_by_foreign` (`updated_by`),
  CONSTRAINT `app_settings_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `app_settings` DISABLE KEYS */;
INSERT INTO `app_settings` VALUES (1,'company_name','Grand Pasundan Convention Hotel',NULL,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(2,'company_short','GPCH',NULL,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(3,'city','Bandung',NULL,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(4,'website','grandpasundanhotel.com',NULL,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(5,'tagline','Pengelolaan aset & pemeliharaan',NULL,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(6,'label_heading','GPCH Asset Care',NULL,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(7,'primary_color','#006632',NULL,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(8,'show_ornament','1',NULL,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(9,'logo_path','branding/logo-gpch.png',NULL,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(10,'logo_dark_path','branding/logo-gpch-dark.png',NULL,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(11,'favicon_path','branding/favicon-gpch.png',NULL,'2026-11-16 04:32:33','2026-11-16 04:32:33');
/*!40000 ALTER TABLE `app_settings` ENABLE KEYS */;
DROP TABLE IF EXISTS `approval_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `approval_limits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `property_id` bigint unsigned NOT NULL,
  `object_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'work_order',
  `user_id` bigint unsigned NOT NULL,
  `max_amount` decimal(18,2) DEFAULT NULL COMMENT 'null = tanpa batas nominal',
  `delegate_user_id` bigint unsigned DEFAULT NULL,
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `approval_limits_property_id_foreign` (`property_id`),
  KEY `approval_limits_user_id_foreign` (`user_id`),
  KEY `approval_limits_delegate_user_id_foreign` (`delegate_user_id`),
  CONSTRAINT `approval_limits_delegate_user_id_foreign` FOREIGN KEY (`delegate_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `approval_limits_property_id_foreign` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  CONSTRAINT `approval_limits_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `approval_limits` DISABLE KEYS */;
/*!40000 ALTER TABLE `approval_limits` ENABLE KEYS */;
DROP TABLE IF EXISTS `asset_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `asset_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `property_id` bigint unsigned NOT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `group` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_criticality` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `tracking_mode` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'individual',
  `depreciation_class_id` bigint unsigned DEFAULT NULL,
  `fiscal_group_override` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'mis. AC split: K2 walau kelas ELC',
  `default_pm_interval_days` smallint unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_categories_property_id_code_unique` (`property_id`,`code`),
  KEY `asset_categories_depreciation_class_id_foreign` (`depreciation_class_id`),
  CONSTRAINT `asset_categories_depreciation_class_id_foreign` FOREIGN KEY (`depreciation_class_id`) REFERENCES `depreciation_classes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `asset_categories_property_id_foreign` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=82 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `asset_categories` DISABLE KEYS */;
INSERT INTO `asset_categories` VALUES (1,1,'AC-SPLIT','AC split kamar','kamar','medium','individual',11,'K2',90,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(2,1,'TV','Televisi','kamar','low','individual',11,NULL,NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(3,1,'MINIBAR','Minibar / kulkas kamar','kamar','low','individual',11,NULL,180,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(4,1,'TEL','Telepon kamar','kamar','low','individual',11,NULL,NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(5,1,'BED','Tempat tidur','kamar','low','individual',8,NULL,365,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(6,1,'LAMP','Lampu meja / baca','kamar','low','individual',11,NULL,NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(7,1,'SAFE','Safe deposit box kamar','kamar','medium','individual',11,NULL,365,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(8,1,'DOORLOCK','Door lock elektronik','kamar','high','individual',14,NULL,180,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(9,1,'FURN','Furniture kamar','kamar','low','individual',8,NULL,NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(10,1,'SANIT','Sanitary & perlengkapan mandi','kamar','medium','individual',7,NULL,180,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(11,1,'LINEN','Linen & handuk','kamar','low','quantity',20,NULL,NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(12,1,'PROJ','Projector','mice','medium','individual',13,NULL,180,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(13,1,'SCREEN','Screen','mice','low','individual',13,NULL,NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(14,1,'SOUND','Sound system','mice','medium','individual',13,NULL,180,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(15,1,'MIC','Microphone','mice','low','individual',13,NULL,NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(16,1,'CHAIR-B','Kursi banquet','mice','low','quantity',9,NULL,NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(17,1,'TABLE-B','Meja banquet','mice','low','quantity',9,NULL,NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(18,1,'PART','Partisi ruang','mice','medium','individual',4,NULL,365,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(19,1,'PANEL-MICE','Panel listrik venue','mice','high','individual',7,NULL,180,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(20,1,'CHILL','Chiller kitchen','kitchen','high','individual',12,NULL,90,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(21,1,'FREEZ','Freezer','kitchen','high','individual',12,NULL,90,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(22,1,'STOVE','Kompor / burner','kitchen','high','individual',12,NULL,90,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(23,1,'OVEN','Oven','kitchen','medium','individual',12,NULL,180,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(24,1,'MIXER','Mixer pastry','kitchen','medium','individual',12,NULL,180,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(25,1,'EXHAUST','Exhaust hood','kitchen','high','individual',12,NULL,90,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(26,1,'GREASE','Grease trap','kitchen','medium','individual',7,NULL,30,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(27,1,'GAS','Instalasi gas','kitchen','critical','individual',7,NULL,30,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(28,1,'BOILER','Boiler','mep','critical','individual',6,NULL,30,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(29,1,'PUMP','Pompa','mep','high','individual',6,NULL,90,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(30,1,'TANK','Tangki air','mep','medium','individual',7,NULL,180,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(31,1,'GENSET','Genset','mep','critical','individual',6,NULL,30,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(32,1,'PANEL','Panel listrik','mep','critical','individual',7,NULL,90,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(33,1,'LIFT','Lift','mep','critical','individual',5,NULL,30,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(34,1,'HVAC','HVAC sentral / AHU','mep','high','individual',6,NULL,90,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(35,1,'POOL-EQ','Peralatan kolam renang','publik','medium','individual',17,NULL,30,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(36,1,'GYM-EQ','Peralatan gym','publik','low','individual',17,NULL,90,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(37,1,'APAR','APAR','keselamatan','critical','individual',7,NULL,30,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(38,1,'ALARM','Panel fire alarm','keselamatan','critical','individual',7,NULL,30,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(39,1,'DETECT','Detektor asap / panas','keselamatan','critical','individual',7,NULL,180,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(40,1,'HYDRANT','Hydrant','keselamatan','critical','individual',7,NULL,90,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(41,1,'EMLIGHT','Emergency light','keselamatan','high','individual',7,NULL,90,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(42,1,'SERVER','Server','it','critical','individual',14,NULL,90,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(43,1,'AP','Access point','it','medium','individual',14,NULL,NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(44,1,'CCTV','CCTV','it','high','individual',14,NULL,90,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(45,1,'SWITCH','Network switch','it','high','individual',14,NULL,NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(46,1,'PABX','PABX','it','high','individual',14,NULL,180,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(47,1,'PC','PC / laptop','it','low','individual',14,NULL,NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(48,1,'PRINTER','Printer','it','low','individual',14,NULL,NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(49,1,'ACCESS','Access control','it','high','individual',14,NULL,180,1,'2026-11-16 04:32:32','2026-11-16 04:32:33'),(50,1,'LAND','Tanah','bangunan','medium','individual',1,NULL,NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(51,1,'BUILDING','Bangunan / struktur gedung','bangunan','critical','individual',2,NULL,NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(52,1,'FACADE','Fasad, atap & waterproofing','bangunan','high','individual',3,NULL,NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(53,1,'RENOV','Renovasi & fit-out','bangunan','medium','individual',4,NULL,NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(54,1,'ESCAL','Eskalator','mep','critical','individual',5,NULL,NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(55,1,'TRAFO','Trafo','mep','critical','individual',6,NULL,NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(56,1,'CARPET','Karpet','kamar','low','individual',10,NULL,NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(57,1,'CURTAIN','Gorden & blackout','kamar','low','individual',10,NULL,NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(58,1,'MATTRESS','Kasur / matras','kamar','low','individual',10,NULL,NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(59,1,'TROLLEY','Troli & rak stainless','mice','low','individual',9,NULL,NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(60,1,'LAUNDRY','Mesin laundry industri','kitchen','high','individual',12,NULL,NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(61,1,'LEDWALL','LED wall & panggung','mice','medium','individual',13,NULL,NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(62,1,'POS','POS & perangkat kasir','it','medium','individual',14,NULL,NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(63,1,'SOFTWARE','Perangkat lunak / lisensi','it','medium','individual',15,NULL,NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(64,1,'VEHICLE','Kendaraan','lainnya','medium','individual',16,NULL,NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(65,1,'SPA-EQ','Peralatan spa','publik','low','individual',17,NULL,NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(66,1,'CIP','Aset dalam penyelesaian (proyek)','bangunan','medium','individual',18,NULL,NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(67,1,'OE-CGS','China, glass & silver (stok dasar)','kitchen','low','quantity',19,NULL,NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(68,1,'FURN-OFF','Furnitur kantor & area umum','lainnya','low','individual',8,NULL,NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(69,1,'OFFICE-EQ','Peralatan kantor (fotokopi, faks, mesin hitung uang)','it','low','individual',14,NULL,NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(70,1,'UPS','UPS & stabilizer','it','high','individual',14,NULL,180,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(71,1,'FAN','Exhaust & blower fan','mep','medium','individual',7,NULL,90,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(72,1,'WATER','Filter & pengolahan air','mep','medium','individual',7,NULL,90,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(73,1,'KIT-EQ','Peralatan dapur stainless (meja kerja, sink, rak)','kitchen','low','individual',9,NULL,365,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(74,1,'TOOLS','Peralatan kerja teknik','mep','low','individual',9,NULL,NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(75,1,'AV-ACC','Kabel & aksesori audio-visual','mice','low','quantity',13,NULL,NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(76,1,'DECOR','Dekorasi, lukisan & cermin','lainnya','low','individual',8,NULL,NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(77,1,'APPL','Peralatan elektronik umum (dispenser, kulkas, kipas, vacuum)','lainnya','low','individual',11,NULL,NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(78,1,'GAS-CYL','Tabung gas LPG','kitchen','high','quantity',9,NULL,30,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(79,1,'MED','Peralatan klinik','lainnya','medium','individual',9,NULL,365,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(80,1,'RADIO','Handy talky & radio','it','medium','individual',14,NULL,NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(81,1,'KARAOKE','Sistem karaoke & hiburan','publik','low','individual',13,NULL,180,1,'2026-11-16 04:32:32','2026-11-16 04:32:32');
/*!40000 ALTER TABLE `asset_categories` ENABLE KEYS */;
DROP TABLE IF EXISTS `asset_components`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `asset_components` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `parent_asset_id` bigint unsigned NOT NULL,
  `component_asset_id` bigint unsigned NOT NULL,
  `installed_at` timestamp NOT NULL,
  `removed_at` timestamp NULL DEFAULT NULL,
  `work_order_id` bigint unsigned DEFAULT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `replaced_component_id` bigint unsigned DEFAULT NULL,
  `active_component_key` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_components_active_component_key_unique` (`active_component_key`),
  KEY `asset_components_parent_asset_id_foreign` (`parent_asset_id`),
  KEY `asset_components_component_asset_id_foreign` (`component_asset_id`),
  KEY `asset_components_replaced_component_id_foreign` (`replaced_component_id`),
  KEY `asset_components_created_by_foreign` (`created_by`),
  CONSTRAINT `asset_components_component_asset_id_foreign` FOREIGN KEY (`component_asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asset_components_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `asset_components_parent_asset_id_foreign` FOREIGN KEY (`parent_asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asset_components_replaced_component_id_foreign` FOREIGN KEY (`replaced_component_id`) REFERENCES `assets` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `asset_components` DISABLE KEYS */;
/*!40000 ALTER TABLE `asset_components` ENABLE KEYS */;
DROP TABLE IF EXISTS `asset_document_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `asset_document_links` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `asset_document_id` bigint unsigned NOT NULL,
  `asset_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_document_links_asset_document_id_asset_id_unique` (`asset_document_id`,`asset_id`),
  KEY `asset_document_links_asset_id_index` (`asset_id`),
  CONSTRAINT `asset_document_links_asset_document_id_foreign` FOREIGN KEY (`asset_document_id`) REFERENCES `asset_documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asset_document_links_asset_id_foreign` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `asset_document_links` DISABLE KEYS */;
/*!40000 ALTER TABLE `asset_document_links` ENABLE KEYS */;
DROP TABLE IF EXISTS `asset_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `asset_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `property_id` bigint unsigned NOT NULL,
  `register_no` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'mis. LGL-IZN/2026/0042',
  `document_type_id` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issuer` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'instansi / pihak penerbit',
  `issued_at` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL COMMENT 'null + no_expiry = tanpa batas',
  `no_expiry` tinyint(1) NOT NULL DEFAULT '0',
  `notice_days` smallint unsigned DEFAULT NULL COMMENT 'kontrak: batas pemberitahuan sebelum berakhir',
  `status` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'valid',
  `renewal_submitted_at` date DEFAULT NULL,
  `renewal_ref` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `renewal_cost` decimal(18,2) DEFAULT NULL,
  `replaced_by_id` bigint unsigned DEFAULT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `revoke_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pic_id` bigint unsigned DEFAULT NULL,
  `backup_pic_id` bigint unsigned DEFAULT NULL,
  `original_location` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `original_location_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'nama bank & nomor perjanjian, nama notaris, dsb.',
  `vendor_id` bigint unsigned DEFAULT NULL,
  `location_id` bigint unsigned DEFAULT NULL,
  `contract_value` decimal(18,2) DEFAULT NULL,
  `premium` decimal(18,2) DEFAULT NULL,
  `sum_insured` decimal(18,2) DEFAULT NULL,
  `classification` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'internal',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `version` int unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_documents_property_id_register_no_unique` (`property_id`,`register_no`),
  KEY `asset_documents_document_type_id_foreign` (`document_type_id`),
  KEY `asset_documents_replaced_by_id_foreign` (`replaced_by_id`),
  KEY `asset_documents_pic_id_foreign` (`pic_id`),
  KEY `asset_documents_backup_pic_id_foreign` (`backup_pic_id`),
  KEY `asset_documents_vendor_id_foreign` (`vendor_id`),
  KEY `asset_documents_location_id_foreign` (`location_id`),
  KEY `asset_documents_created_by_foreign` (`created_by`),
  KEY `asset_documents_property_id_valid_until_index` (`property_id`,`valid_until`),
  KEY `asset_documents_status_index` (`status`),
  CONSTRAINT `asset_documents_backup_pic_id_foreign` FOREIGN KEY (`backup_pic_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `asset_documents_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `asset_documents_document_type_id_foreign` FOREIGN KEY (`document_type_id`) REFERENCES `document_types` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `asset_documents_location_id_foreign` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `asset_documents_pic_id_foreign` FOREIGN KEY (`pic_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `asset_documents_property_id_foreign` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `asset_documents_replaced_by_id_foreign` FOREIGN KEY (`replaced_by_id`) REFERENCES `asset_documents` (`id`) ON DELETE SET NULL,
  CONSTRAINT `asset_documents_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `asset_documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `asset_documents` ENABLE KEYS */;
DROP TABLE IF EXISTS `asset_financials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `asset_financials` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `asset_id` bigint unsigned NOT NULL,
  `acquisition_date` date DEFAULT NULL,
  `acquisition_cost` decimal(18,2) DEFAULT NULL,
  `replacement_estimate` decimal(18,2) DEFAULT NULL,
  `depreciation_class_id` bigint unsigned DEFAULT NULL,
  `commercial_life_months` smallint unsigned DEFAULT NULL,
  `residual_value` decimal(18,2) DEFAULT NULL,
  `depreciation_start_date` date DEFAULT NULL COMMENT 'tanggal siap dipakai (komersial)',
  `fiscal_group_code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fiscal_life_months` smallint unsigned DEFAULT NULL,
  `fiscal_start_date` date DEFAULT NULL COMMENT 'bulan pengeluaran (fiskal)',
  `capitalisation` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'capitalised',
  `group_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'referensi pembelian kelompok / proyek',
  `opening_balance_date` date DEFAULT NULL,
  `opening_accum_commercial` decimal(18,2) DEFAULT NULL,
  `opening_accum_fiscal` decimal(18,2) DEFAULT NULL,
  `impairment_loss` decimal(18,2) DEFAULT NULL,
  `disposal_date` date DEFAULT NULL,
  `disposal_proceeds` decimal(18,2) DEFAULT NULL,
  `cost_center` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `accounting_ref` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verification_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unverified',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_financials_asset_id_unique` (`asset_id`),
  KEY `asset_financials_depreciation_class_id_foreign` (`depreciation_class_id`),
  CONSTRAINT `asset_financials_asset_id_foreign` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asset_financials_depreciation_class_id_foreign` FOREIGN KEY (`depreciation_class_id`) REFERENCES `depreciation_classes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `asset_financials` DISABLE KEYS */;
/*!40000 ALTER TABLE `asset_financials` ENABLE KEYS */;
DROP TABLE IF EXISTS `asset_labels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `asset_labels` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `property_id` bigint unsigned NOT NULL,
  `asset_id` bigint unsigned DEFAULT NULL,
  `quantity_group_id` bigint unsigned DEFAULT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token_ciphertext` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'terenkripsi APP_KEY, untuk cetak ulang',
  `status` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `activated_at` timestamp NOT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `revoke_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `revoked_by` bigint unsigned DEFAULT NULL,
  `active_target_key` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_labels_token_hash_unique` (`token_hash`),
  UNIQUE KEY `asset_labels_active_target_key_unique` (`active_target_key`),
  KEY `asset_labels_property_id_foreign` (`property_id`),
  KEY `asset_labels_asset_id_foreign` (`asset_id`),
  KEY `asset_labels_quantity_group_id_foreign` (`quantity_group_id`),
  KEY `asset_labels_revoked_by_foreign` (`revoked_by`),
  CONSTRAINT `asset_labels_asset_id_foreign` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `asset_labels_property_id_foreign` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `asset_labels_quantity_group_id_foreign` FOREIGN KEY (`quantity_group_id`) REFERENCES `asset_quantity_groups` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `asset_labels_revoked_by_foreign` FOREIGN KEY (`revoked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `asset_labels` DISABLE KEYS */;
/*!40000 ALTER TABLE `asset_labels` ENABLE KEYS */;
DROP TABLE IF EXISTS `asset_quantity_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `asset_quantity_groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `property_id` bigint unsigned NOT NULL,
  `group_code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `location_id` bigint unsigned NOT NULL,
  `category_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pcs',
  `on_hand` decimal(14,2) DEFAULT NULL COMMENT 'null = belum dihitung',
  `expected_qty` decimal(14,2) DEFAULT NULL,
  `status` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `generation_batch_id` bigint unsigned DEFAULT NULL,
  `installation_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_quantity_groups_property_id_group_code_unique` (`property_id`,`group_code`),
  KEY `asset_quantity_groups_location_id_foreign` (`location_id`),
  KEY `asset_quantity_groups_category_id_foreign` (`category_id`),
  KEY `asset_quantity_groups_generation_batch_id_index` (`generation_batch_id`),
  CONSTRAINT `asset_quantity_groups_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `asset_categories` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `asset_quantity_groups_location_id_foreign` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `asset_quantity_groups_property_id_foreign` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `asset_quantity_groups` DISABLE KEYS */;
/*!40000 ALTER TABLE `asset_quantity_groups` ENABLE KEYS */;
DROP TABLE IF EXISTS `asset_status_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `asset_status_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `asset_id` bigint unsigned NOT NULL,
  `dimension` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_value` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_value` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_type` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_id` bigint unsigned DEFAULT NULL,
  `actor_id` bigint unsigned DEFAULT NULL,
  `occurred_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `asset_status_events_actor_id_foreign` (`actor_id`),
  KEY `asset_status_events_asset_id_dimension_index` (`asset_id`,`dimension`),
  CONSTRAINT `asset_status_events_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `asset_status_events_asset_id_foreign` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `asset_status_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `asset_status_events` ENABLE KEYS */;
DROP TABLE IF EXISTS `asset_transfer_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `asset_transfer_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `transfer_id` bigint unsigned NOT NULL,
  `asset_id` bigint unsigned NOT NULL,
  `from_location_id` bigint unsigned DEFAULT NULL,
  `condition_out` char(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `condition_in` char(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `received_scan` tinyint(1) NOT NULL DEFAULT '0',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_transfer_items_transfer_id_asset_id_unique` (`transfer_id`,`asset_id`),
  KEY `asset_transfer_items_asset_id_foreign` (`asset_id`),
  KEY `asset_transfer_items_from_location_id_foreign` (`from_location_id`),
  CONSTRAINT `asset_transfer_items_asset_id_foreign` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `asset_transfer_items_from_location_id_foreign` FOREIGN KEY (`from_location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `asset_transfer_items_transfer_id_foreign` FOREIGN KEY (`transfer_id`) REFERENCES `asset_transfers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `asset_transfer_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `asset_transfer_items` ENABLE KEYS */;
DROP TABLE IF EXISTS `asset_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `asset_transfers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `property_id` bigint unsigned NOT NULL,
  `number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_location_id` bigint unsigned DEFAULT NULL,
  `to_location_id` bigint unsigned NOT NULL,
  `to_department_id` bigint unsigned DEFAULT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `requested_by` bigint unsigned NOT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `dispatched_by` bigint unsigned DEFAULT NULL,
  `dispatched_at` timestamp NULL DEFAULT NULL,
  `received_by` bigint unsigned DEFAULT NULL,
  `received_at` timestamp NULL DEFAULT NULL,
  `status_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `version` int unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_transfers_number_unique` (`number`),
  KEY `asset_transfers_property_id_foreign` (`property_id`),
  KEY `asset_transfers_from_location_id_foreign` (`from_location_id`),
  KEY `asset_transfers_to_location_id_foreign` (`to_location_id`),
  KEY `asset_transfers_to_department_id_foreign` (`to_department_id`),
  KEY `asset_transfers_requested_by_foreign` (`requested_by`),
  KEY `asset_transfers_approved_by_foreign` (`approved_by`),
  KEY `asset_transfers_dispatched_by_foreign` (`dispatched_by`),
  KEY `asset_transfers_received_by_foreign` (`received_by`),
  CONSTRAINT `asset_transfers_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `asset_transfers_dispatched_by_foreign` FOREIGN KEY (`dispatched_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `asset_transfers_from_location_id_foreign` FOREIGN KEY (`from_location_id`) REFERENCES `locations` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `asset_transfers_property_id_foreign` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `asset_transfers_received_by_foreign` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `asset_transfers_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `asset_transfers_to_department_id_foreign` FOREIGN KEY (`to_department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `asset_transfers_to_location_id_foreign` FOREIGN KEY (`to_location_id`) REFERENCES `locations` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `asset_transfers` DISABLE KEYS */;
/*!40000 ALTER TABLE `asset_transfers` ENABLE KEYS */;
DROP TABLE IF EXISTS `assets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `property_id` bigint unsigned NOT NULL,
  `asset_code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'permanen, dibuat server, mis. GPCH-AST-000001',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` bigint unsigned NOT NULL,
  `brand` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `serial_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_id` bigint unsigned DEFAULT NULL,
  `department_id` bigint unsigned DEFAULT NULL,
  `custodian_id` bigint unsigned DEFAULT NULL,
  `custodian_unit` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'unit penanggung jawab bila bukan perorangan',
  `parent_id` bigint unsigned DEFAULT NULL,
  `condition_grade` char(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `criticality` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operational_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'serviceable',
  `lifecycle_status` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `in_transit` tinyint(1) NOT NULL DEFAULT '0',
  `last_inspected_at` timestamp NULL DEFAULT NULL,
  `ownership` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `in_service_date` date DEFAULT NULL,
  `vendor_id` bigint unsigned DEFAULT NULL,
  `invoice_ref` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `funding_source` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pm_interval_days` smallint unsigned DEFAULT NULL,
  `specification` text COLLATE utf8mb4_unicode_ci,
  `warranty_until` date DEFAULT NULL,
  `contract_ref` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verification_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unverified',
  `legacy_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `migration_source` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `generation_batch_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `verified_by` bigint unsigned DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `installation_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `version` int unsigned NOT NULL DEFAULT '1',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `assets_property_id_asset_code_unique` (`property_id`,`asset_code`),
  UNIQUE KEY `assets_public_id_unique` (`public_id`),
  KEY `assets_category_id_foreign` (`category_id`),
  KEY `assets_location_id_foreign` (`location_id`),
  KEY `assets_department_id_foreign` (`department_id`),
  KEY `assets_custodian_id_foreign` (`custodian_id`),
  KEY `assets_parent_id_foreign` (`parent_id`),
  KEY `assets_vendor_id_foreign` (`vendor_id`),
  KEY `assets_created_by_foreign` (`created_by`),
  KEY `assets_verified_by_foreign` (`verified_by`),
  KEY `assets_property_id_lifecycle_status_index` (`property_id`,`lifecycle_status`),
  KEY `assets_property_id_location_id_index` (`property_id`,`location_id`),
  KEY `assets_serial_number_index` (`serial_number`),
  KEY `assets_legacy_code_index` (`legacy_code`),
  KEY `assets_generation_batch_id_index` (`generation_batch_id`),
  CONSTRAINT `assets_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `asset_categories` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `assets_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `assets_custodian_id_foreign` FOREIGN KEY (`custodian_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `assets_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `assets_location_id_foreign` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `assets_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `assets` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `assets_property_id_foreign` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `assets_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `assets_verified_by_foreign` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `assets` DISABLE KEYS */;
/*!40000 ALTER TABLE `assets` ENABLE KEYS */;
DROP TABLE IF EXISTS `attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attachments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `attachable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attachable_id` bigint unsigned NOT NULL,
  `kind` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'evidence',
  `disk` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'local',
  `path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mime` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size` bigint unsigned DEFAULT NULL,
  `checksum` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_version` smallint unsigned DEFAULT NULL COMMENT 'versi berkas dokumen register',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `visibility` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'internal',
  `uploaded_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `attachments_attachable_type_attachable_id_index` (`attachable_type`,`attachable_id`),
  KEY `attachments_uploaded_by_foreign` (`uploaded_by`),
  CONSTRAINT `attachments_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `attachments` DISABLE KEYS */;
/*!40000 ALTER TABLE `attachments` ENABLE KEYS */;
DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `property_id` bigint unsigned DEFAULT NULL,
  `event` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `auditable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `auditable_id` bigint unsigned DEFAULT NULL,
  `auditable_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_id` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `audit_logs_user_id_foreign` (`user_id`),
  KEY `audit_logs_property_id_foreign` (`property_id`),
  KEY `audit_logs_auditable_type_auditable_id_index` (`auditable_type`,`auditable_id`),
  CONSTRAINT `audit_logs_property_id_foreign` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE SET NULL,
  CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
DROP TABLE IF EXISTS `client_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `client_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `action` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload_hash` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `result` json DEFAULT NULL,
  `device_time` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `client_events_user_id_action_uuid_unique` (`user_id`,`action`,`uuid`),
  CONSTRAINT `client_events_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `client_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `client_events` ENABLE KEYS */;
DROP TABLE IF EXISTS `comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `comments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `commentable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `commentable_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `comments_commentable_type_commentable_id_index` (`commentable_type`,`commentable_id`),
  KEY `comments_user_id_foreign` (`user_id`),
  CONSTRAINT `comments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `comments` DISABLE KEYS */;
/*!40000 ALTER TABLE `comments` ENABLE KEYS */;
DROP TABLE IF EXISTS `condition_parameters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `condition_parameters` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `property_id` bigint unsigned NOT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_meter` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'nilai kumulatif (jam operasi, km) untuk PM berbasis meter',
  `category_id` bigint unsigned DEFAULT NULL,
  `direction` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'above',
  `warning_value` decimal(14,3) DEFAULT NULL,
  `critical_value` decimal(14,3) DEFAULT NULL,
  `wo_on_warning` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'buat WO sejak batas waspada',
  `critical_priority` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'P2',
  `reading_interval_days` smallint unsigned DEFAULT NULL COMMENT 'jadwal pembacaan rutin',
  `guidance` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `condition_parameters_property_id_code_unique` (`property_id`,`code`),
  KEY `condition_parameters_category_id_foreign` (`category_id`),
  CONSTRAINT `condition_parameters_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `asset_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `condition_parameters_property_id_foreign` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `condition_parameters` DISABLE KEYS */;
INSERT INTO `condition_parameters` VALUES (1,1,'JAM-OPS','Jam operasi (hour meter)','jam',1,NULL,'above',NULL,NULL,0,'P3',7,'Catat angka hour meter; dipakai untuk PM berbasis jam operasi.',1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(2,1,'SUHU-CHILL','Suhu ruang pendingin (chiller)','°C',0,20,'above',5.000,8.000,0,'P2',1,'Cek seal pintu, kondensor & kompresor; pindahkan bahan bila > 8 °C lebih dari 2 jam.',1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(3,1,'SUHU-FREEZ','Suhu freezer','°C',0,21,'above',-15.000,-12.000,0,'P2',1,'Cek defrost, kipas evaporator & seal pintu.',1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(4,1,'TEK-BOILER','Tekanan kerja boiler','bar',0,28,'above',8.000,10.000,0,'P1',1,'Lewat batas kritis: hentikan pemanas, periksa safety valve & pressure switch.',1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(5,1,'GETAR','Getaran mesin berputar (ISO 10816)','mm/s',0,NULL,'above',4.500,7.100,0,'P2',30,'Cek kelurusan (alignment), bantalan (bearing) & baut dudukan.',1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(6,1,'SUHU-PANEL','Suhu titik panas panel (thermal)','°C',0,32,'above',60.000,80.000,0,'P2',90,'Kencangkan terminal, cek beban fasa & kondisi MCB/MCCB.',1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(7,1,'TEK-APAR','Tekanan APAR (manometer)','bar',0,37,'below',12.000,10.000,0,'P2',30,'Isi ulang atau ganti tabung; pastikan pin & segel utuh.',1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(8,1,'AKI-GEN','Tegangan aki starter genset','V',0,31,'below',24.500,23.500,0,'P2',7,'Cas / ganti aki; cek charger & terminal.',1,'2026-11-16 04:32:33','2026-11-16 04:32:33');
/*!40000 ALTER TABLE `condition_parameters` ENABLE KEYS */;
DROP TABLE IF EXISTS `condition_readings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `condition_readings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `asset_id` bigint unsigned NOT NULL,
  `condition_parameter_id` bigint unsigned NOT NULL,
  `value` decimal(14,3) NOT NULL,
  `status` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `read_at` timestamp NOT NULL,
  `recorded_by` bigint unsigned DEFAULT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `work_order_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `condition_readings_condition_parameter_id_foreign` (`condition_parameter_id`),
  KEY `condition_readings_recorded_by_foreign` (`recorded_by`),
  KEY `condition_readings_work_order_id_foreign` (`work_order_id`),
  KEY `cond_read_asset_param_at` (`asset_id`,`condition_parameter_id`,`read_at`),
  CONSTRAINT `condition_readings_asset_id_foreign` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `condition_readings_condition_parameter_id_foreign` FOREIGN KEY (`condition_parameter_id`) REFERENCES `condition_parameters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `condition_readings_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `condition_readings_work_order_id_foreign` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `condition_readings` DISABLE KEYS */;
/*!40000 ALTER TABLE `condition_readings` ENABLE KEYS */;
DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `departments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `property_id` bigint unsigned NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cost_center` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `departments_property_id_code_unique` (`property_id`,`code`),
  CONSTRAINT `departments_property_id_foreign` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `departments` DISABLE KEYS */;
INSERT INTO `departments` VALUES (1,1,'EXE','Executive Office',NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(2,1,'FO','Front Office',NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(3,1,'HK','Housekeeping',NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(4,1,'FB','Food & Beverage Service',NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(5,1,'KIT','Kitchen & Pastry',NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(6,1,'ENG','Engineering',NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(7,1,'SM','Sales & Marketing',NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(8,1,'ACC','Accounting & Finance',NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(9,1,'HR','Human Resources',NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(10,1,'PUR','Purchasing',NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(11,1,'EDP','EDP / IT',NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(12,1,'SEC','Security',NULL,1,'2026-11-16 04:32:32','2026-11-16 04:32:32');
/*!40000 ALTER TABLE `departments` ENABLE KEYS */;
DROP TABLE IF EXISTS `depreciation_classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `depreciation_classes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `property_id` bigint unsigned NOT NULL,
  `code` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `examples` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_depreciable` tinyint(1) NOT NULL DEFAULT '1',
  `is_intangible` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'amortisasi (SAK EP Bab 18)',
  `commercial_life_months` smallint unsigned DEFAULT NULL,
  `residual_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `fiscal_group_code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fiscal_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `policy_ref` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'bagian kebijakan, mis. 3 atau 4.1',
  `sort` smallint unsigned NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `depreciation_classes_property_id_code_unique` (`property_id`,`code`),
  CONSTRAINT `depreciation_classes_property_id_foreign` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `depreciation_classes` DISABLE KEYS */;
INSERT INTO `depreciation_classes` VALUES (1,1,'LND','Tanah','Tanah hotel, lahan parkir',0,0,NULL,0.00,'NONE','Tidak disusutkan; biaya perpanjangan HGB diamortisasi selama masa hak (konfirmasi konsultan pajak)','2.6',1,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(2,1,'BLD','Bangunan, struktur','Struktur gedung, basement',1,0,480,0.00,'BP','Bangunan permanen: 20 tahun, atau 40 tahun bila dipilih umur sesuai buku','3',2,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(3,1,'BLD-F','Bangunan, fasad dan atap','Fasad, atap, waterproofing',1,0,240,0.00,'BP','Bangunan permanen','3',3,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(4,1,'BLD-R','Renovasi dan fit-out','Renovasi kamar, lobi, ballroom',1,0,120,0.00,'BP','Mengikuti bangunan permanen (perlu dikonfirmasi)','3, 4.2',4,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(5,1,'ME-V','Lift dan eskalator','Lift tamu, lift servis',1,0,240,0.00,'K3','Cocokkan dengan Lampiran PMK 72/2023','3',5,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(6,1,'ME-H','Mesin utama','Chiller, AHU, genset, boiler, trafo, pompa',1,0,180,0.00,'K3','Cocokkan dengan Lampiran PMK 72/2023','3',6,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(7,1,'ME-I','Instalasi bangunan','Listrik, plumbing, fire alarm, sprinkler, CCTV kabel',1,0,180,0.00,'K3','Cocokkan dengan Lampiran PMK 72/2023','3',7,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(8,1,'FFE-W','Perabot kayu dan soft furnishing','Tempat tidur, lemari, sofa, meja kamar',1,0,84,0.00,'K1',NULL,'3',8,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(9,1,'FFE-M','Perabot dan peralatan logam','Kursi banquet, troli, rak stainless',1,0,96,0.00,'K2',NULL,'3',9,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(10,1,'FFE-S','Karpet, gorden, kasur','Karpet koridor, blackout, matras',1,0,60,0.00,'K1',NULL,'3',10,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(11,1,'ELC','Peralatan elektronik kamar','AC split, TV, kulkas minibar, brankas',1,0,60,0.00,'K1','AC: Kelompok 2 (atur di kategori AC)','3',11,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(12,1,'KIT','Peralatan dapur dan laundry','Combi oven, cold room, mesin cuci industri, ironer',1,0,120,0.00,'K2',NULL,'3',12,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(13,1,'MICE','Peralatan banquet dan MICE','Sound system, proyektor, LED wall, panggung',1,0,60,0.00,'K1',NULL,'3',13,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(14,1,'ICT','Perangkat TI','Komputer, server, jaringan, Wi-Fi, POS, kunci kamar elektronik',1,0,48,0.00,'K1',NULL,'3',14,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(15,1,'SW','Perangkat lunak','Lisensi, implementasi sistem yang dikapitalisasi',1,1,48,0.00,'K1','Amortisasi harta tak berwujud','3',15,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(16,1,'VEH','Kendaraan','Mobil antar-jemput, mobil operasional, minibus',1,0,96,10.00,'K2',NULL,'3',16,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(17,1,'REC','Fasilitas rekreasi','Alat gym, peralatan kolam renang, spa',1,0,60,0.00,'K1',NULL,'3',17,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(18,1,'CIP','Aset dalam penyelesaian','Proyek renovasi berjalan',0,0,NULL,0.00,'NONE','Tidak disusutkan sampai selesai dan dipindah ke kelas aset','4.4',18,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(19,1,'OE','Operating equipment (stok dasar)','China, glass, silver: stok dasar saat pembukaan / penambahan outlet',1,0,36,0.00,'K1','Penggantian rutin dibebankan, bukan aset','4.1',19,1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(20,1,'OE-L','Linen dan uniform (stok dasar)','Linen kamar & F&B, uniform: stok dasar',1,0,24,0.00,'K1','Penggantian rutin dibebankan, bukan aset','4.1',20,1,'2026-11-16 04:32:32','2026-11-16 04:32:32');
/*!40000 ALTER TABLE `depreciation_classes` ENABLE KEYS */;
DROP TABLE IF EXISTS `document_requirements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_requirements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `depreciation_class_id` bigint unsigned NOT NULL,
  `document_type_id` bigint unsigned NOT NULL,
  `condition` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'mis. "bila ada", "bila angkutan"',
  `is_mandatory` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'false = dianjurkan (bila ada)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `doc_req_class_type_unique` (`depreciation_class_id`,`document_type_id`),
  KEY `document_requirements_document_type_id_foreign` (`document_type_id`),
  CONSTRAINT `document_requirements_depreciation_class_id_foreign` FOREIGN KEY (`depreciation_class_id`) REFERENCES `depreciation_classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `document_requirements_document_type_id_foreign` FOREIGN KEY (`document_type_id`) REFERENCES `document_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=68 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `document_requirements` DISABLE KEYS */;
INSERT INTO `document_requirements` VALUES (1,1,1,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(2,1,2,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(3,1,4,'tahun berjalan',1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(4,2,5,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(5,2,7,'masih berlaku',1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(6,2,8,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(7,2,55,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(8,4,6,'bila mengubah struktur atau fungsi',0,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(9,4,43,'kontrak kontraktor',0,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(10,4,37,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(11,4,8,'gambar as-built baru',1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(12,5,36,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(13,5,37,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(14,5,21,'masih berlaku',1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(15,5,43,'masih berlaku',1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(16,5,39,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(17,6,36,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(18,6,37,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(19,6,39,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(20,6,22,'genset / instalasi listrik',0,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(21,6,23,'boiler / bejana tekan',0,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(22,6,43,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(23,6,57,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(24,7,8,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(25,7,22,'SLO listrik',1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(26,7,25,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(27,7,24,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(28,8,36,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(29,8,37,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(30,8,40,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(31,8,38,'bila ada',0,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(32,9,36,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(33,9,37,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(34,9,40,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(35,9,38,'bila ada',0,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(36,10,36,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(37,10,37,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(38,10,40,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(39,10,38,'bila ada',0,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(40,11,36,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(41,11,37,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(42,11,40,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(43,11,38,'bila ada',0,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(44,13,36,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(45,13,37,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(46,13,40,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(47,13,38,'bila ada',0,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(48,17,36,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(49,17,37,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(50,17,40,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(51,17,38,'bila ada',0,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(52,12,36,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(53,12,37,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(54,12,39,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(55,12,38,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(56,12,43,'bila ada',0,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(57,14,36,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(58,14,37,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(59,14,38,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(60,15,45,'perjanjian lisensi atau langganan',1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(61,15,47,'kontrak implementasi',0,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(62,15,49,'bila menyimpan data pribadi',0,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(63,16,41,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(64,16,59,NULL,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(65,18,43,'kontrak proyek',1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(66,18,6,'bila perlu',0,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(67,18,37,'saat selesai',0,'2026-11-16 04:32:33','2026-11-16 04:32:33');
/*!40000 ALTER TABLE `document_requirements` ENABLE KEYS */;
DROP TABLE IF EXISTS `document_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `archive_category` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `section` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `validity_hint` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'pola masa berlaku; tanggal di dokumen yang dipakai',
  `has_expiry` tinyint(1) NOT NULL DEFAULT '1',
  `first_reminder_days` smallint unsigned NOT NULL DEFAULT '90',
  `is_k3` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'kedaluwarsa → aset "Tidak boleh dioperasikan"',
  `is_insurance` tinyint(1) NOT NULL DEFAULT '0',
  `is_contract` tinyint(1) NOT NULL DEFAULT '0',
  `default_classification` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'internal',
  `default_pic_role` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `retention` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort` smallint unsigned NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `document_types_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `document_types` DISABLE KEYS */;
INSERT INTO `document_types` VALUES (1,'LGL-TNH-01','Sertifikat HGB atau SHM','LGL','Tanah & bangunan','HGB sesuai tanggal di sertifikat; SHM tanpa batas',1,1095,0,0,0,'terbatas','Legal','Permanen',1,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(2,'LGL-TNH-02','Akta jual beli / dasar perolehan tanah','LGL','Tanah & bangunan','Tanpa batas',0,90,0,0,0,'terbatas','Legal','Permanen',2,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(3,'LGL-TNH-03','Sertifikat Hak Tanggungan & perjanjian kredit','LGL','Tanah & bangunan','Sampai kredit lunas',1,90,0,0,0,'rahasia','FC','Permanen',3,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(4,'LGL-TNH-04','SPPT & bukti bayar PBB','LGL','Tanah & bangunan','Tahunan',1,60,0,0,0,'internal','Accounting','Permanen',4,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(5,'LGL-BGN-01','IMB (lama) atau PBG','LGL','Tanah & bangunan','Tanpa batas selama bangunan tidak diubah',0,90,0,0,0,'terbatas','Legal','Permanen',5,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(6,'LGL-BGN-02','PBG perubahan (renovasi / penambahan)','LGL','Tanah & bangunan','Tanpa batas',0,90,0,0,0,'terbatas','Chief Engineering','Permanen',6,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(7,'LGL-BGN-03','Sertifikat Laik Fungsi (SLF)','LGL-IZN','Tanah & bangunan','Umumnya 5 tahun (cek)',1,180,0,0,0,'internal','Chief Engineering','Berlaku + 10 tahun',7,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(8,'LGL-BGN-04','Gambar as-built arsitektur, struktur & M&E','PML','Tanah & bangunan','Tanpa batas; perbarui setiap renovasi',0,90,0,0,0,'internal','Chief Engineering','Umur aset + 5 tahun',8,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(9,'LGL-BGN-05','Laporan pengkajian teknis bangunan (perpanjangan SLF)','LGL-IZN','Tanah & bangunan','Mengikuti SLF',1,180,0,0,0,'internal','Chief Engineering','Berlaku + 10 tahun',9,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(10,'LGL-BU-01','Akta pendirian & perubahan, SK Kemenkumham','LGL','Badan usaha & izin hotel','Tanpa batas',0,90,0,0,0,'terbatas','Legal','Permanen',10,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(11,'LGL-BU-02','NIB & Sertifikat Standar usaha hotel (OSS RBA)','LGL','Badan usaha & izin hotel','Selama usaha berjalan',0,90,0,0,0,'internal','Legal','Permanen',11,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(12,'LGL-BU-03','NPWP & SKT, pengukuhan PKP','LGL','Badan usaha & izin hotel','Tanpa batas',0,90,0,0,0,'internal','Accounting','Permanen',12,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(13,'LGL-BU-04','NPWPD & pengukuhan wajib pajak PBJT','LGL','Badan usaha & izin hotel','Tanpa batas',0,90,0,0,0,'internal','Accounting','Permanen',13,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(14,'LGL-IZN-01','Sertifikat usaha hotel / klasifikasi bintang','LGL-IZN','Badan usaha & izin hotel','Sesuai sertifikat (cek, lazimnya 3 tahun)',1,120,0,0,0,'internal','GM','Berlaku + 10 tahun',14,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(15,'LGL-IZN-02','Sertifikat Laik Higiene Sanitasi (SLHS)','LGL-IZN','Badan usaha & izin hotel','Sesuai sertifikat (cek)',1,120,0,0,0,'internal','Executive Chef','Berlaku + 10 tahun',15,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(16,'LGL-IZN-03','Sertifikat halal per outlet / produk','LGL-IZN','Badan usaha & izin hotel','Sesuai sertifikat & aturan BPJPH (cek)',1,90,0,0,0,'internal','Executive Chef','Berlaku + 10 tahun',16,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(17,'LGL-IZN-04','Izin penjualan minuman beralkohol','LGL-IZN','Badan usaha & izin hotel','Sesuai izin (lazimnya tahunan)',1,90,0,0,0,'internal','Director of F&B','Berlaku + 10 tahun',17,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(18,'LGL-IZN-05','Izin reklame & papan nama','LGL-IZN','Badan usaha & izin hotel','Tahunan',1,90,0,0,0,'internal','Marketing','Berlaku + 10 tahun',18,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(19,'LGL-IZN-06','Izin Stasiun Radio (ISR) untuk HT','LGL-IZN','Badan usaha & izin hotel','Tahunan',1,90,0,0,0,'internal','Chief Security','Berlaku + 10 tahun',19,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(20,'LGL-IZN-07','Lisensi royalti musik & lagu (LMKN)','LGL-IZN','Badan usaha & izin hotel','Tahunan',1,90,0,0,0,'internal','Director of F&B','Berlaku + 10 tahun',20,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(21,'K3-01','Izin pakai & riksa uji lift / eskalator (Disnaker)','LGL-IZN','K3 & kelaikan alat','Riksa uji berkala, lazimnya tahunan (cek)',1,60,1,0,0,'internal','Chief Engineering','Berlaku + 10 tahun',21,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(22,'K3-02','Riksa uji genset & instalasi listrik, SLO','LGL-IZN','K3 & kelaikan alat','Sesuai sertifikat (cek)',1,60,1,0,0,'internal','Chief Engineering','Berlaku + 10 tahun',22,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(23,'K3-03','Riksa uji boiler & bejana tekan','LGL-IZN','K3 & kelaikan alat','Sesuai pengesahan (cek)',1,60,1,0,0,'internal','Chief Engineering','Berlaku + 10 tahun',23,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(24,'K3-04','Riksa uji instalasi penyalur petir','LGL-IZN','K3 & kelaikan alat','Berkala (cek)',1,60,1,0,0,'internal','Chief Engineering','Berlaku + 10 tahun',24,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(25,'K3-05','Sertifikat / rekomendasi sistem proteksi kebakaran (Damkar)','LGL-IZN','K3 & kelaikan alat','Sesuai rekomendasi (cek)',1,60,1,0,0,'internal','Chief Engineering','Berlaku + 10 tahun',25,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(26,'K3-06','Kartu inspeksi & isi ulang APAR','LGL-IZN','K3 & kelaikan alat','Sesuai jadwal, lazimnya tahunan',1,60,1,0,0,'internal','Chief Security','Berlaku + 10 tahun',26,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(27,'K3-07','Tera ulang timbangan & alat ukur (Metrologi)','LGL-IZN','K3 & kelaikan alat','Tahunan',1,60,0,0,0,'internal','Purchasing','Berlaku + 10 tahun',27,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(28,'K3-08','Lisensi K3 operator & teknisi','LGL-IZN','K3 & kelaikan alat','Sesuai lisensi',1,60,0,0,0,'internal','HR','Berlaku + 10 tahun',28,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(29,'LGL-LH-01','Dokumen lingkungan (UKL-UPL / AMDAL) & persetujuan','LGL-IZN','Lingkungan','Tanpa batas selama tidak ada perubahan usaha',0,90,0,0,0,'internal','Chief Engineering','Berlaku + 10 tahun',29,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(30,'LGL-LH-02','Laporan pelaksanaan RKL-RPL / UKL-UPL','LGL-IZN','Lingkungan','Per semester',1,30,0,0,0,'internal','Chief Engineering','Berlaku + 10 tahun',30,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(31,'LGL-LH-03','Persetujuan teknis & SLO pembuangan air limbah (IPAL/STP)','LGL-IZN','Lingkungan','Sesuai persetujuan (cek)',1,90,0,0,0,'internal','Chief Engineering','Berlaku + 10 tahun',31,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(32,'LGL-LH-04','Kontrak pengangkut limbah B3 & manifest','LGL-KTR','Lingkungan','Kontrak tahunan',1,60,0,0,1,'internal','Chief Engineering','Berakhir + 10 tahun',32,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(33,'LGL-LH-05','Izin pengusahaan air tanah (SIPA)','LGL-IZN','Lingkungan','Sesuai izin (cek)',1,90,0,0,0,'internal','Chief Engineering','Berlaku + 10 tahun',33,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(34,'LGL-LH-06','Hasil uji kualitas air bersih, kolam & limbah','LGL-IZN','Lingkungan','Berkala sesuai jadwal',1,30,0,0,0,'internal','Chief Engineering','Berlaku + 10 tahun',34,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(35,'AST-01','Purchase order & persetujuan pembelian','AST','Perolehan aset','Tanpa batas',0,90,0,0,0,'internal','Purchasing','10 tahun setelah aset dilepas',35,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(36,'AST-02','Faktur pemasok & faktur pajak','AST','Perolehan aset','Tanpa batas',0,90,0,0,0,'internal','Accounting','10 tahun setelah aset dilepas',36,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(37,'AST-03','Berita acara serah terima (BAST) & uji coba','AST','Perolehan aset','Tanpa batas',0,90,0,0,0,'internal','Chief Engineering','10 tahun setelah aset dilepas',37,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(38,'AST-04','Kartu garansi','AST','Perolehan aset','Sesuai garansi',1,60,0,0,0,'internal','Chief Engineering','10 tahun setelah aset dilepas',38,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(39,'AST-05','Manual operasi & spesifikasi teknis','PML','Perolehan aset','Tanpa batas',0,90,0,0,0,'internal','Chief Engineering','Umur aset + 5 tahun',39,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(40,'AST-06','Foto aset & label tag','AST','Perolehan aset','Tanpa batas',0,90,0,0,0,'internal','Asset Controller','10 tahun setelah aset dilepas',40,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(41,'AST-07','BPKB, STNK, pajak kendaraan, uji KIR','AST','Perolehan aset','STNK 5 tahun, pajak tahunan, KIR berkala',1,60,0,0,0,'terbatas','GA','10 tahun setelah aset dilepas',41,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(42,'AST-08','Berita acara penghapusan, penjualan & stock opname aset','AST','Perolehan aset','Tanpa batas',0,90,0,0,0,'internal','FC','10 tahun setelah aset dilepas',42,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(43,'KTR-SVC','Kontrak servis / pemeliharaan vendor','LGL-KTR','Kontrak pemeliharaan & vendor','Sesuai kontrak; perhatikan batas pemberitahuan',1,90,0,0,1,'terbatas','Chief Engineering','Berakhir + 10 tahun',43,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(44,'PML-RPT','Laporan servis / kunjungan vendor','PML','Pemeliharaan','Tanpa batas',0,90,0,0,0,'internal','Chief Engineering','Umur aset + 5 tahun',44,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(45,'TI-01','Perjanjian lisensi perangkat lunak','LGL-KTR','Perangkat lunak & TI','Perpetual atau langganan tahunan',1,60,0,0,1,'internal','IT Manager','Berakhir + 10 tahun',45,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(46,'TI-02','Langganan SaaS (channel manager, booking engine, e-mail, WhatsApp API)','LGL-KTR','Perangkat lunak & TI','Bulanan atau tahunan',1,30,0,0,1,'internal','IT Manager','Berakhir + 10 tahun',46,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(47,'TI-03','Kontrak pengembangan & pemeliharaan sistem (termasuk kode sumber)','LGL-KTR','Perangkat lunak & TI','Sesuai kontrak',1,90,0,0,1,'terbatas','IT Manager','Berakhir + 10 tahun',47,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(48,'TI-04','Kontrak hosting / VPS & SLA','LGL-KTR','Perangkat lunak & TI','Tahunan',1,60,0,0,1,'internal','IT Manager','Berakhir + 10 tahun',48,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(49,'TI-05','Perjanjian pemrosesan data pribadi (DPA) sesuai UU PDP','LGL-KTR','Perangkat lunak & TI','Mengikuti kontrak utama',1,60,0,0,1,'terbatas','IT Manager','Berakhir + 10 tahun',49,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(50,'TI-06','Nama domain & sertifikat SSL','LGL-KTR','Perangkat lunak & TI','Tahunan',1,30,0,0,0,'internal','IT Manager','Berakhir + 10 tahun',50,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(51,'TI-07','Kontrak internet ISP utama & cadangan','LGL-KTR','Perangkat lunak & TI','Tahunan',1,60,0,0,1,'internal','IT Manager','Berakhir + 10 tahun',51,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(52,'TI-08','Kontrak dukungan interface (door lock, PABX, IPTV, Wi-Fi)','LGL-KTR','Perangkat lunak & TI','Tahunan',1,60,0,0,1,'internal','IT Manager','Berakhir + 10 tahun',52,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(53,'TI-09','Catatan lisensi sumber terbuka','LGL-KTR','Perangkat lunak & TI','Tanpa batas',0,90,0,0,0,'internal','IT Manager','Berakhir + 10 tahun',53,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(54,'TI-10','Perjanjian kerahasiaan (NDA) vendor TI','LGL-KTR','Perangkat lunak & TI','Sesuai perjanjian',1,60,0,0,0,'terbatas','Legal','Berakhir + 10 tahun',54,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(55,'ASR-01','Polis Property / Industrial All Risk (termasuk gempa bumi)','LGL-KTR','Asuransi','Tahunan',1,60,0,1,0,'terbatas','FC','Berakhir + 10 tahun',55,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(56,'ASR-02','Polis Business Interruption','LGL-KTR','Asuransi','Tahunan',1,60,0,1,0,'terbatas','FC','Berakhir + 10 tahun',56,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(57,'ASR-03','Polis Machinery Breakdown','LGL-KTR','Asuransi','Tahunan',1,60,0,1,0,'terbatas','FC','Berakhir + 10 tahun',57,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(58,'ASR-04','Polis Public Liability','LGL-KTR','Asuransi','Tahunan',1,60,0,1,0,'terbatas','FC','Berakhir + 10 tahun',58,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(59,'ASR-05','Polis asuransi kendaraan','LGL-KTR','Asuransi','Tahunan',1,60,0,1,0,'terbatas','FC','Berakhir + 10 tahun',59,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(60,'ASR-06','Polis Money Insurance','LGL-KTR','Asuransi','Tahunan',1,60,0,1,0,'terbatas','FC','Berakhir + 10 tahun',60,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(61,'ASR-07','Polis Electronic Equipment','LGL-KTR','Asuransi','Tahunan',1,60,0,1,0,'terbatas','FC','Berakhir + 10 tahun',61,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(62,'KTR-SEWA','Kontrak sewa ruang / atap kepada penyewa','LGL-KTR','Kontrak lain','Sesuai kontrak',1,90,0,0,1,'terbatas','Legal','Berakhir + 10 tahun',62,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(63,'KTR-LEASE','Sewa guna usaha (leasing) kendaraan / peralatan','LGL-KTR','Kontrak lain','Sesuai kontrak',1,90,0,0,1,'terbatas','FC','Berakhir + 10 tahun',63,1,'2026-11-16 04:32:33','2026-11-16 04:32:33'),(64,'KTR-KREDIT','Perjanjian kredit bank yang menjaminkan aset','LGL-KTR','Kontrak lain','Sampai lunas',1,90,0,0,1,'rahasia','FC','Berakhir + 10 tahun',64,1,'2026-11-16 04:32:33','2026-11-16 04:32:33');
/*!40000 ALTER TABLE `document_types` ENABLE KEYS */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
DROP TABLE IF EXISTS `fiscal_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fiscal_groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `life_months` smallint unsigned DEFAULT NULL COMMENT 'null = tidak disusutkan',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fiscal_groups_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `fiscal_groups` DISABLE KEYS */;
INSERT INTO `fiscal_groups` VALUES (1,'K1','Kelompok 1 (bukan bangunan)',48,'Garis lurus 25%',1,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(2,'K2','Kelompok 2 (bukan bangunan)',96,'Garis lurus 12,5%',2,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(3,'K3','Kelompok 3 (bukan bangunan)',192,'Garis lurus 6,25%; jenis yang tidak tercantum di Lampiran PMK masuk kelompok ini',3,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(4,'K4','Kelompok 4 (bukan bangunan)',240,'Garis lurus 5%',4,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(5,'BP','Bangunan permanen',240,'20 tahun (5%), atau umur sesuai buku bila > 20 tahun; diatur di Kebijakan aset tetap',5,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(6,'BTP','Bangunan tidak permanen',120,'Garis lurus 10%',6,'2026-11-16 04:32:32','2026-11-16 04:32:32'),(7,'NONE','Tidak disusutkan',NULL,'Tanah, aset dalam penyelesaian',7,'2026-11-16 04:32:32','2026-11-16 04:32:32');
/*!40000 ALTER TABLE `fiscal_groups` ENABLE KEYS */;
DROP TABLE IF EXISTS `fixed_asset_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fixed_asset_policies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `property_id` bigint unsigned NOT NULL,
  `capitalisation_threshold` decimal(18,2) NOT NULL DEFAULT '5000000.00' COMMENT 'per unit; di bawahnya dibebankan',
  `group_purchase_threshold` decimal(18,2) NOT NULL DEFAULT '25000000.00' COMMENT 'pembelian massal satu jenis untuk satu proyek',
  `renovation_threshold` decimal(18,2) NOT NULL DEFAULT '25000000.00' COMMENT 'per proyek renovasi',
  `building_fiscal_life_years` smallint unsigned NOT NULL DEFAULT '40' COMMENT '20 (PMK) atau umur sesuai buku > 20',
  `deferred_tax_rate` decimal(5,2) NOT NULL DEFAULT '22.00' COMMENT 'tarif PPh Badan untuk pajak tangguhan (%)',
  `effective_date` date DEFAULT NULL COMMENT 'tanggal berlaku kebijakan',
  `status` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fixed_asset_policies_property_id_unique` (`property_id`),
  KEY `fixed_asset_policies_approved_by_foreign` (`approved_by`),
  CONSTRAINT `fixed_asset_policies_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fixed_asset_policies_property_id_foreign` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `fixed_asset_policies` DISABLE KEYS */;
INSERT INTO `fixed_asset_policies` VALUES (1,1,5000000.00,25000000.00,25000000.00,40,22.00,NULL,'draft',NULL,NULL,NULL,'2026-11-16 04:32:32','2026-11-16 04:32:32');
/*!40000 ALTER TABLE `fixed_asset_policies` ENABLE KEYS */;
DROP TABLE IF EXISTS `generation_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `generation_batches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `property_id` bigint unsigned NOT NULL,
  `number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `template_id` bigint unsigned NOT NULL,
  `template_version` smallint unsigned NOT NULL,
  `idempotency_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `room_ids` json NOT NULL,
  `preview_snapshot` json DEFAULT NULL,
  `match_decisions` json DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'previewed',
  `created_assets` int unsigned NOT NULL DEFAULT '0',
  `matched_assets` int unsigned NOT NULL DEFAULT '0',
  `created_by` bigint unsigned DEFAULT NULL,
  `committed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `generation_batches_number_unique` (`number`),
  UNIQUE KEY `generation_batches_idempotency_key_unique` (`idempotency_key`),
  KEY `generation_batches_property_id_foreign` (`property_id`),
  KEY `generation_batches_template_id_foreign` (`template_id`),
  KEY `generation_batches_created_by_foreign` (`created_by`),
  CONSTRAINT `generation_batches_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `generation_batches_property_id_foreign` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `generation_batches_template_id_foreign` FOREIGN KEY (`template_id`) REFERENCES `room_asset_templates` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `generation_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `generation_batches` ENABLE KEYS */;
DROP TABLE IF EXISTS `generation_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `generation_results` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `batch_id` bigint unsigned NOT NULL,
  `room_id` bigint unsigned NOT NULL,
  `status` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_count` int unsigned NOT NULL DEFAULT '0',
  `matched_count` int unsigned NOT NULL DEFAULT '0',
  `skipped_count` int unsigned NOT NULL DEFAULT '0',
  `message` text COLLATE utf8mb4_unicode_ci,
  `detail` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `generation_results_batch_id_room_id_unique` (`batch_id`,`room_id`),
  KEY `generation_results_room_id_foreign` (`room_id`),
  CONSTRAINT `generation_results_batch_id_foreign` FOREIGN KEY (`batch_id`) REFERENCES `generation_batches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `generation_results_room_id_foreign` FOREIGN KEY (`room_id`) REFERENCES `locations` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `generation_results` DISABLE KEYS */;
/*!40000 ALTER TABLE `generation_results` ENABLE KEYS */;
DROP TABLE IF EXISTS `installation_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `installation_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `asset_label_id` bigint unsigned NOT NULL,
  `asset_id` bigint unsigned DEFAULT NULL,
  `quantity_group_id` bigint unsigned DEFAULT NULL,
  `location_id` bigint unsigned DEFAULT NULL,
  `print_item_id` bigint unsigned DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actor_id` bigint unsigned DEFAULT NULL,
  `occurred_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `installation_events_asset_id_foreign` (`asset_id`),
  KEY `installation_events_quantity_group_id_foreign` (`quantity_group_id`),
  KEY `installation_events_location_id_foreign` (`location_id`),
  KEY `installation_events_print_item_id_foreign` (`print_item_id`),
  KEY `installation_events_actor_id_foreign` (`actor_id`),
  KEY `installation_events_asset_label_id_occurred_at_index` (`asset_label_id`,`occurred_at`),
  CONSTRAINT `installation_events_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `installation_events_asset_id_foreign` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `installation_events_asset_label_id_foreign` FOREIGN KEY (`asset_label_id`) REFERENCES `asset_labels` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `installation_events_location_id_foreign` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `installation_events_print_item_id_foreign` FOREIGN KEY (`print_item_id`) REFERENCES `label_print_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `installation_events_quantity_group_id_foreign` FOREIGN KEY (`quantity_group_id`) REFERENCES `asset_quantity_groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `installation_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `installation_events` ENABLE KEYS */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
DROP TABLE IF EXISTS `label_print_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `label_print_batches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `property_id` bigint unsigned NOT NULL,
  `number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `template_version` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `media_profile` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_cell` smallint unsigned NOT NULL DEFAULT '1',
  `filters` json DEFAULT NULL,
  `purpose` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `reprint_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `label_count` int unsigned NOT NULL DEFAULT '0',
  `sheet_count` int unsigned NOT NULL DEFAULT '0',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ready',
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `generated_at` timestamp NULL DEFAULT NULL,
  `downloaded_at` timestamp NULL DEFAULT NULL,
  `printed_confirmed_at` timestamp NULL DEFAULT NULL,
  `printed_confirmed_by` bigint unsigned DEFAULT NULL,
  `operator_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `label_print_batches_number_unique` (`number`),
  KEY `label_print_batches_property_id_foreign` (`property_id`),
  KEY `label_print_batches_printed_confirmed_by_foreign` (`printed_confirmed_by`),
  KEY `label_print_batches_operator_id_foreign` (`operator_id`),
  CONSTRAINT `label_print_batches_operator_id_foreign` FOREIGN KEY (`operator_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `label_print_batches_printed_confirmed_by_foreign` FOREIGN KEY (`printed_confirmed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `label_print_batches_property_id_foreign` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `label_print_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `label_print_batches` ENABLE KEYS */;
DROP TABLE IF EXISTS `label_print_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `label_print_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `batch_id` bigint unsigned NOT NULL,
  `asset_label_id` bigint unsigned NOT NULL,
  `asset_id` bigint unsigned DEFAULT NULL,
  `quantity_group_id` bigint unsigned DEFAULT NULL,
  `location_id` bigint unsigned DEFAULT NULL,
  `location_snapshot` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name_snapshot` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code_snapshot` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `template_version` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sheet_no` smallint unsigned NOT NULL DEFAULT '1',
  `cell_no` smallint unsigned NOT NULL DEFAULT '1',
  `copy_no` smallint unsigned NOT NULL DEFAULT '1',
  `reprint_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sequence` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `label_print_items_batch_id_foreign` (`batch_id`),
  KEY `label_print_items_asset_label_id_foreign` (`asset_label_id`),
  KEY `label_print_items_asset_id_foreign` (`asset_id`),
  KEY `label_print_items_quantity_group_id_foreign` (`quantity_group_id`),
  KEY `label_print_items_location_id_foreign` (`location_id`),
  CONSTRAINT `label_print_items_asset_id_foreign` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `label_print_items_asset_label_id_foreign` FOREIGN KEY (`asset_label_id`) REFERENCES `asset_labels` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `label_print_items_batch_id_foreign` FOREIGN KEY (`batch_id`) REFERENCES `label_print_batches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `label_print_items_location_id_foreign` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `label_print_items_quantity_group_id_foreign` FOREIGN KEY (`quantity_group_id`) REFERENCES `asset_quantity_groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `label_print_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `label_print_items` ENABLE KEYS */;
DROP TABLE IF EXISTS `locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `locations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `property_id` bigint unsigned NOT NULL,
  `parent_id` bigint unsigned DEFAULT NULL,
  `code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `location_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `room_type_id` bigint unsigned DEFAULT NULL,
  `room_ref` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'nomor kamar di PMS',
  `floor_label` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_key` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'urutan natural: gedung/lantai/nomor',
  `pms_room_status` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'informasi terpisah dari status aset',
  `full_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `locations_property_id_code_unique` (`property_id`,`code`),
  KEY `locations_parent_id_foreign` (`parent_id`),
  KEY `locations_room_type_id_foreign` (`room_type_id`),
  KEY `locations_property_id_location_type_index` (`property_id`,`location_type`),
  KEY `locations_sort_key_index` (`sort_key`),
  CONSTRAINT `locations_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `locations` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `locations_property_id_foreign` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `locations_room_type_id_foreign` FOREIGN KEY (`room_type_id`) REFERENCES `room_types` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `locations` DISABLE KEYS */;
/*!40000 ALTER TABLE `locations` ENABLE KEYS */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_10_21_000001_create_reference_tables',1),(5,'2026_10_21_000002_create_asset_tables',1),(6,'2026_10_21_000003_create_label_and_room_template_tables',1),(7,'2026_10_21_000004_create_maintenance_tables',1),(8,'2026_11_16_000001_create_fixed_asset_policy_tables',1),(9,'2026_11_16_000002_create_asset_document_tables',1),(10,'2026_11_16_000003_create_planned_maintenance_tables',1),(11,'2026_11_16_000004_create_app_settings_table',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
DROP TABLE IF EXISTS `notification_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dedupe_key` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `channel` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'database',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sent',
  `recipients` int unsigned NOT NULL DEFAULT '0',
  `error` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `notification_logs_dedupe_key_unique` (`dedupe_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `notification_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `notification_logs` ENABLE KEYS */;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_id` bigint unsigned NOT NULL,
  `data` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
DROP TABLE IF EXISTS `permission_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permission_role` (
  `permission_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `permission_role_role_id_foreign` (`role_id`),
  CONSTRAINT `permission_role_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `permission_role_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `permission_role` DISABLE KEYS */;
INSERT INTO `permission_role` VALUES (1,1),(2,1),(3,1),(4,1),(5,1),(6,1),(7,1),(8,1),(9,1),(10,1),(11,1),(12,1),(13,1),(14,1),(15,1),(16,1),(17,1),(18,1),(19,1),(20,1),(21,1),(22,1),(23,1),(24,1),(25,1),(26,1),(27,1),(28,1),(29,1),(30,1),(31,1),(32,1),(33,1),(34,1),(35,1),(36,1),(37,1),(38,1),(39,1),(40,1),(41,1),(42,1),(1,2),(2,2),(3,2),(4,2),(8,2),(20,2),(21,2),(25,2),(27,2),(42,2),(1,3),(2,3),(3,3),(4,3),(8,3),(20,3),(21,3),(23,3),(25,3),(27,3),(42,3),(1,4),(2,4),(3,4),(4,4),(8,4),(11,4),(17,4),(20,4),(21,4),(23,4),(24,4),(25,4),(27,4),(32,4),(33,4),(42,4),(1,5),(2,5),(3,5),(4,5),(5,5),(6,5),(10,5),(15,5),(16,5),(18,5),(20,5),(22,5),(24,5),(25,5),(26,5),(27,5),(28,5),(29,5),(30,5),(31,5),(32,5),(33,5),(35,5),(36,5),(4,6),(15,6),(18,6),(24,6),(27,6),(30,6),(36,6),(2,7),(4,7),(16,7),(17,7),(20,7),(24,7),(27,7),(4,8),(24,8),(4,9),(24,9),(27,9),(37,9),(1,10),(2,10),(3,10),(4,10),(5,10),(6,10),(7,10),(10,10),(12,10),(13,10),(14,10),(15,10),(16,10),(18,10),(20,10),(22,10),(24,10),(35,10),(36,10),(38,10),(1,11),(2,11),(3,11),(4,11),(8,11),(9,11),(17,11),(19,11),(20,11),(21,11),(22,11),(25,11),(27,11),(32,11),(34,11),(4,12),(24,12),(27,12),(4,13),(20,13),(24,13),(27,13),(38,13),(4,14),(20,14),(22,14),(24,14),(38,14),(39,14),(40,14),(41,14),(42,14),(2,15),(3,15),(4,15),(8,15),(20,15),(21,15),(25,15),(27,15),(42,15);
/*!40000 ALTER TABLE `permission_role` ENABLE KEYS */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `group` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'dashboard.view','Beranda manajerial (KPI, risiko, biaya)','Umum','2026-11-16 04:32:32','2026-11-16 04:32:32'),(2,'report.view','Melihat laporan','Umum','2026-11-16 04:32:32','2026-11-16 04:32:32'),(3,'report.export','Ekspor laporan (CSV)','Umum','2026-11-16 04:32:32','2026-11-16 04:32:32'),(4,'asset.view','Melihat register & kartu aset','Aset','2026-11-16 04:32:32','2026-11-16 04:32:32'),(5,'asset.edit','Membuat & mengubah data aset','Aset','2026-11-16 04:32:32','2026-11-16 04:32:32'),(6,'asset.verify','Verifikasi fisik aset','Aset','2026-11-16 04:32:32','2026-11-16 04:32:32'),(7,'asset.activate','Mengaktifkan / menghentikan aset (siklus hidup)','Aset','2026-11-16 04:32:32','2026-11-16 04:32:32'),(8,'asset.view_financial','Melihat harga & nilai aset','Aset','2026-11-16 04:32:32','2026-11-16 04:32:32'),(9,'asset.edit_financial','Mengubah data finansial aset','Aset','2026-11-16 04:32:32','2026-11-16 04:32:32'),(10,'template.manage','Menyusun template aset tipe kamar','Aset','2026-11-16 04:32:32','2026-11-16 04:32:32'),(11,'template.publish','Memeriksa & menerbitkan template','Aset','2026-11-16 04:32:32','2026-11-16 04:32:32'),(12,'generation.run','Pembuatan aset massal untuk kamar','Aset','2026-11-16 04:32:32','2026-11-16 04:32:32'),(13,'label.print','Membuat batch & cetak label QR','Aset','2026-11-16 04:32:32','2026-11-16 04:32:32'),(14,'label.rotate','Rotasi token label (nonaktifkan label lama)','Aset','2026-11-16 04:32:32','2026-11-16 04:32:32'),(15,'label.install','Konfirmasi pemasangan label','Aset','2026-11-16 04:32:32','2026-11-16 04:32:32'),(16,'transfer.request','Mengajukan mutasi aset','Aset','2026-11-16 04:32:32','2026-11-16 04:32:32'),(17,'transfer.approve','Menyetujui mutasi aset','Aset','2026-11-16 04:32:32','2026-11-16 04:32:32'),(18,'transfer.receive','Mengirim / menerima mutasi aset','Aset','2026-11-16 04:32:32','2026-11-16 04:32:32'),(19,'policy.manage','Kebijakan aset tetap: kelas penyusutan, kelompok fiskal, batas kapitalisasi','Aset tetap & dokumen','2026-11-16 04:32:32','2026-11-16 04:32:32'),(20,'document.view','Melihat register dokumen aset (Umum & Internal)','Aset tetap & dokumen','2026-11-16 04:32:32','2026-11-16 04:32:32'),(21,'document.view_restricted','Melihat dokumen Terbatas & Rahasia','Aset tetap & dokumen','2026-11-16 04:32:32','2026-11-16 04:32:32'),(22,'document.manage','Mendaftarkan dokumen, mengunggah berkas, memperpanjang','Aset tetap & dokumen','2026-11-16 04:32:32','2026-11-16 04:32:32'),(23,'document.escalation','Menerima eskalasi dokumen H-30 & kedaluwarsa','Aset tetap & dokumen','2026-11-16 04:32:32','2026-11-16 04:32:32'),(24,'request.create','Membuat laporan kerusakan','Pemeliharaan','2026-11-16 04:32:32','2026-11-16 04:32:32'),(25,'request.view_all','Melihat semua laporan (lintas departemen)','Pemeliharaan','2026-11-16 04:32:32','2026-11-16 04:32:32'),(26,'request.triage','Triase laporan & menetapkan prioritas','Pemeliharaan','2026-11-16 04:32:32','2026-11-16 04:32:32'),(27,'wo.view','Melihat work order','Pemeliharaan','2026-11-16 04:32:32','2026-11-16 04:32:32'),(28,'wo.create','Membuat work order','Pemeliharaan','2026-11-16 04:32:32','2026-11-16 04:32:32'),(29,'wo.assign','Menugaskan & menjadwalkan work order','Pemeliharaan','2026-11-16 04:32:32','2026-11-16 04:32:32'),(30,'wo.execute','Mengerjakan work order (teknisi)','Pemeliharaan','2026-11-16 04:32:32','2026-11-16 04:32:32'),(31,'wo.verify','Memverifikasi hasil pekerjaan','Pemeliharaan','2026-11-16 04:32:32','2026-11-16 04:32:32'),(32,'wo.approve','Menyetujui biaya / lingkup work order','Pemeliharaan','2026-11-16 04:32:32','2026-11-16 04:32:32'),(33,'wo.close','Menutup & membuka ulang work order','Pemeliharaan','2026-11-16 04:32:32','2026-11-16 04:32:32'),(34,'wo.cost_finalize','Finalisasi biaya (Finance)','Pemeliharaan','2026-11-16 04:32:32','2026-11-16 04:32:32'),(35,'pm.manage','Mengelola rencana PM & parameter kondisi','Pemeliharaan','2026-11-16 04:32:32','2026-11-16 04:32:32'),(36,'condition.record','Mencatat pembacaan kondisi / meter aset','Pemeliharaan','2026-11-16 04:32:32','2026-11-16 04:32:32'),(37,'room.block_confirm','Konfirmasi blokir kamar (Front Office)','Pemeliharaan','2026-11-16 04:32:32','2026-11-16 04:32:32'),(38,'reference.manage','Lokasi, kategori, departemen, tipe kamar, vendor','Pengaturan','2026-11-16 04:32:32','2026-11-16 04:32:32'),(39,'settings.manage','SLA & batas persetujuan','Pengaturan','2026-11-16 04:32:32','2026-11-16 04:32:32'),(40,'user.manage','Pengguna & penugasan peran','Pengaturan','2026-11-16 04:32:32','2026-11-16 04:32:32'),(41,'role.manage','Peran & izin','Pengaturan','2026-11-16 04:32:32','2026-11-16 04:32:32'),(42,'audit.view','Log audit','Pengaturan','2026-11-16 04:32:32','2026-11-16 04:32:32');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
DROP TABLE IF EXISTS `pm_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pm_plans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `property_id` bigint unsigned NOT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'asset',
  `asset_id` bigint unsigned DEFAULT NULL,
  `category_id` bigint unsigned DEFAULT NULL,
  `wo_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'preventive',
  `interval_days` smallint unsigned DEFAULT NULL,
  `meter_parameter_id` bigint unsigned DEFAULT NULL,
  `interval_meter` decimal(14,3) DEFAULT NULL,
  `starts_on` date DEFAULT NULL COMMENT 'jatuh tempo pertama',
  `lead_days` smallint unsigned NOT NULL DEFAULT '7' COMMENT 'WO dibuat H-n sebelum jatuh tempo',
  `priority` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'P4',
  `checklist` json DEFAULT NULL,
  `estimated_hours` decimal(6,2) DEFAULT NULL,
  `needs_vendor` tinyint(1) NOT NULL DEFAULT '0',
  `vendor_id` bigint unsigned DEFAULT NULL,
  `pic_id` bigint unsigned DEFAULT NULL,
  `instructions` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pm_plans_property_id_code_unique` (`property_id`,`code`),
  KEY `pm_plans_asset_id_foreign` (`asset_id`),
  KEY `pm_plans_category_id_foreign` (`category_id`),
  KEY `pm_plans_meter_parameter_id_foreign` (`meter_parameter_id`),
  KEY `pm_plans_vendor_id_foreign` (`vendor_id`),
  KEY `pm_plans_pic_id_foreign` (`pic_id`),
  KEY `pm_plans_created_by_foreign` (`created_by`),
  CONSTRAINT `pm_plans_asset_id_foreign` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pm_plans_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `asset_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pm_plans_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pm_plans_meter_parameter_id_foreign` FOREIGN KEY (`meter_parameter_id`) REFERENCES `condition_parameters` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pm_plans_pic_id_foreign` FOREIGN KEY (`pic_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pm_plans_property_id_foreign` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `pm_plans_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `pm_plans` DISABLE KEYS */;
/*!40000 ALTER TABLE `pm_plans` ENABLE KEYS */;
DROP TABLE IF EXISTS `pm_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pm_schedules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pm_plan_id` bigint unsigned NOT NULL,
  `asset_id` bigint unsigned NOT NULL,
  `last_done_at` date DEFAULT NULL,
  `last_meter` decimal(14,3) DEFAULT NULL,
  `next_due_at` date DEFAULT NULL,
  `next_due_meter` decimal(14,3) DEFAULT NULL,
  `open_work_order_id` bigint unsigned DEFAULT NULL,
  `done_count` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pm_schedules_pm_plan_id_asset_id_unique` (`pm_plan_id`,`asset_id`),
  KEY `pm_schedules_asset_id_foreign` (`asset_id`),
  KEY `pm_schedules_open_work_order_id_foreign` (`open_work_order_id`),
  CONSTRAINT `pm_schedules_asset_id_foreign` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pm_schedules_open_work_order_id_foreign` FOREIGN KEY (`open_work_order_id`) REFERENCES `work_orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pm_schedules_pm_plan_id_foreign` FOREIGN KEY (`pm_plan_id`) REFERENCES `pm_plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `pm_schedules` DISABLE KEYS */;
/*!40000 ALTER TABLE `pm_schedules` ENABLE KEYS */;
DROP TABLE IF EXISTS `properties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `properties` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `timezone` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Asia/Jakarta',
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `properties_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `properties` DISABLE KEYS */;
INSERT INTO `properties` VALUES (1,'GPCH','Grand Pasundan Convention Hotel','Asia/Jakarta','Jl. Peta No. 147–149, Bandung',1,'2026-11-16 04:32:32','2026-11-16 04:32:32');
/*!40000 ALTER TABLE `properties` ENABLE KEYS */;
DROP TABLE IF EXISTS `quantity_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quantity_movements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quantity_group_id` bigint unsigned NOT NULL,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `qty_before` decimal(14,2) DEFAULT NULL,
  `qty_after` decimal(14,2) NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actor_id` bigint unsigned DEFAULT NULL,
  `occurred_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `quantity_movements_quantity_group_id_foreign` (`quantity_group_id`),
  KEY `quantity_movements_actor_id_foreign` (`actor_id`),
  CONSTRAINT `quantity_movements_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quantity_movements_quantity_group_id_foreign` FOREIGN KEY (`quantity_group_id`) REFERENCES `asset_quantity_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `quantity_movements` DISABLE KEYS */;
/*!40000 ALTER TABLE `quantity_movements` ENABLE KEYS */;
DROP TABLE IF EXISTS `request_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `request_links` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `service_request_id` bigint unsigned NOT NULL,
  `work_order_id` bigint unsigned NOT NULL,
  `linked_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `request_links_service_request_id_work_order_id_unique` (`service_request_id`,`work_order_id`),
  KEY `request_links_work_order_id_foreign` (`work_order_id`),
  KEY `request_links_linked_by_foreign` (`linked_by`),
  CONSTRAINT `request_links_linked_by_foreign` FOREIGN KEY (`linked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `request_links_service_request_id_foreign` FOREIGN KEY (`service_request_id`) REFERENCES `service_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `request_links_work_order_id_foreign` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `request_links` DISABLE KEYS */;
/*!40000 ALTER TABLE `request_links` ENABLE KEYS */;
DROP TABLE IF EXISTS `role_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_user` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `property_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_user_role_id_user_id_property_id_unique` (`role_id`,`user_id`,`property_id`),
  KEY `role_user_user_id_foreign` (`user_id`),
  KEY `role_user_property_id_foreign` (`property_id`),
  CONSTRAINT `role_user_property_id_foreign` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_user_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `role_user` DISABLE KEYS */;
INSERT INTO `role_user` VALUES (1,1,1,NULL);
/*!40000 ALTER TABLE `role_user` ENABLE KEYS */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'super-admin','Super Admin','Konsultan / vendor aplikasi — semua izin','2026-11-16 04:32:32','2026-11-16 04:32:32'),(2,'owner','Owner','Melihat seluruh kinerja & biaya; memutuskan investasi/penghapusan','2026-11-16 04:32:32','2026-11-16 04:32:32'),(3,'owner-rep','Owner Representative','Memantau risiko aset & anggaran; mengkaji prioritas & usulan penggantian','2026-11-16 04:32:32','2026-11-16 04:32:32'),(4,'gm','General Manager','Mengawasi operasi, backlog, downtime, SLA & persetujuan sesuai batas nilai','2026-11-16 04:32:32','2026-11-16 04:32:32'),(5,'chief-engineering','Chief Engineering','Triase, prioritas, penjadwalan teknisi & verifikasi pekerjaan','2026-11-16 04:32:32','2026-11-16 04:32:32'),(6,'technician','Teknisi','Melihat tugas & aset relevan; mencatat diagnosis, waktu, material & bukti','2026-11-16 04:32:32','2026-11-16 04:32:32'),(7,'hod','HOD','Melapor, melihat laporan departemennya, mengonfirmasi layanan & kondisi aset','2026-11-16 04:32:32','2026-11-16 04:32:32'),(8,'reporter','Pelapor (staf)','Membuat laporan kerusakan & melihat laporan sendiri','2026-11-16 04:32:32','2026-11-16 04:32:32'),(9,'front-office','Front Office','Melapor & mengonfirmasi blokir kamar terkait perbaikan','2026-11-16 04:32:32','2026-11-16 04:32:32'),(10,'asset-controller','Asset Controller','Memelihara register, label & inventarisasi; mengusulkan mutasi & koreksi','2026-11-16 04:32:32','2026-11-16 04:32:32'),(11,'finance','Finance / Chief Accountant','Data finansial, validasi biaya, anggaran & rekonsiliasi','2026-11-16 04:32:32','2026-11-16 04:32:32'),(12,'storekeeper','Storekeeper','Mengelola stok engineering (rilis B)','2026-11-16 04:32:32','2026-11-16 04:32:32'),(13,'purchasing','Purchasing','Vendor & referensi pembelian','2026-11-16 04:32:32','2026-11-16 04:32:32'),(14,'it-admin','IT Administrator','Konfigurasi teknis & akun; tidak otomatis berwenang menyetujui biaya','2026-11-16 04:32:32','2026-11-16 04:32:32'),(15,'auditor','Auditor','Membaca data & jejak audit dalam lingkup yang ditetapkan','2026-11-16 04:32:32','2026-11-16 04:32:32');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
DROP TABLE IF EXISTS `room_asset_slots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `room_asset_slots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `property_id` bigint unsigned NOT NULL,
  `room_id` bigint unsigned NOT NULL,
  `item_key` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit_position` smallint unsigned NOT NULL DEFAULT '1',
  `position_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category_id` bigint unsigned NOT NULL,
  `standard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mode` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'individual',
  `expected_qty` decimal(14,2) DEFAULT NULL COMMENT 'hanya slot kuantitas',
  `template_id` bigint unsigned DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'empty',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `room_slot_key_unique` (`property_id`,`room_id`,`item_key`,`unit_position`),
  KEY `room_asset_slots_room_id_foreign` (`room_id`),
  KEY `room_asset_slots_category_id_foreign` (`category_id`),
  KEY `room_asset_slots_template_id_foreign` (`template_id`),
  CONSTRAINT `room_asset_slots_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `asset_categories` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `room_asset_slots_property_id_foreign` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `room_asset_slots_room_id_foreign` FOREIGN KEY (`room_id`) REFERENCES `locations` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `room_asset_slots_template_id_foreign` FOREIGN KEY (`template_id`) REFERENCES `room_asset_templates` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `room_asset_slots` DISABLE KEYS */;
/*!40000 ALTER TABLE `room_asset_slots` ENABLE KEYS */;
DROP TABLE IF EXISTS `room_asset_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `room_asset_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `property_id` bigint unsigned NOT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `version` smallint unsigned NOT NULL DEFAULT '1',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `room_type_id` bigint unsigned NOT NULL,
  `status` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `prepared_by` bigint unsigned DEFAULT NULL,
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `room_asset_templates_property_id_code_version_unique` (`property_id`,`code`,`version`),
  KEY `room_asset_templates_room_type_id_foreign` (`room_type_id`),
  KEY `room_asset_templates_prepared_by_foreign` (`prepared_by`),
  KEY `room_asset_templates_reviewed_by_foreign` (`reviewed_by`),
  CONSTRAINT `room_asset_templates_prepared_by_foreign` FOREIGN KEY (`prepared_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `room_asset_templates_property_id_foreign` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `room_asset_templates_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `room_asset_templates_room_type_id_foreign` FOREIGN KEY (`room_type_id`) REFERENCES `room_types` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `room_asset_templates` DISABLE KEYS */;
/*!40000 ALTER TABLE `room_asset_templates` ENABLE KEYS */;
DROP TABLE IF EXISTS `room_blocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `room_blocks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `room_id` bigint unsigned NOT NULL,
  `work_order_id` bigint unsigned DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'proposed',
  `started_at` timestamp NULL DEFAULT NULL,
  `ended_at` timestamp NULL DEFAULT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `proposed_by` bigint unsigned DEFAULT NULL,
  `fo_confirmed_by` bigint unsigned DEFAULT NULL,
  `fo_confirmed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `room_blocks_room_id_foreign` (`room_id`),
  KEY `room_blocks_work_order_id_foreign` (`work_order_id`),
  KEY `room_blocks_proposed_by_foreign` (`proposed_by`),
  KEY `room_blocks_fo_confirmed_by_foreign` (`fo_confirmed_by`),
  CONSTRAINT `room_blocks_fo_confirmed_by_foreign` FOREIGN KEY (`fo_confirmed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `room_blocks_proposed_by_foreign` FOREIGN KEY (`proposed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `room_blocks_room_id_foreign` FOREIGN KEY (`room_id`) REFERENCES `locations` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `room_blocks_work_order_id_foreign` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `room_blocks` DISABLE KEYS */;
/*!40000 ALTER TABLE `room_blocks` ENABLE KEYS */;
DROP TABLE IF EXISTS `room_exceptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `room_exceptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `room_id` bigint unsigned NOT NULL,
  `item_key` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` smallint unsigned DEFAULT NULL COMMENT 'jumlah aktual untuk tipe quantity/added',
  `substitute_category_id` bigint unsigned DEFAULT NULL,
  `substitute_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `recorded_by` bigint unsigned DEFAULT NULL,
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `room_exceptions_room_id_item_key_unique` (`room_id`,`item_key`),
  KEY `room_exceptions_substitute_category_id_foreign` (`substitute_category_id`),
  KEY `room_exceptions_recorded_by_foreign` (`recorded_by`),
  KEY `room_exceptions_reviewed_by_foreign` (`reviewed_by`),
  CONSTRAINT `room_exceptions_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `room_exceptions_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `room_exceptions_room_id_foreign` FOREIGN KEY (`room_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `room_exceptions_substitute_category_id_foreign` FOREIGN KEY (`substitute_category_id`) REFERENCES `asset_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `room_exceptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `room_exceptions` ENABLE KEYS */;
DROP TABLE IF EXISTS `room_template_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `room_template_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `template_id` bigint unsigned NOT NULL,
  `item_key` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'stabil lintas versi, mis. tv-utama',
  `category_id` bigint unsigned NOT NULL,
  `standard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` smallint unsigned NOT NULL DEFAULT '1',
  `mode` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'individual',
  `unit` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unit',
  `positions` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'label posisi per unit, pisahkan koma: Kiri, Kanan',
  `default_department_id` bigint unsigned DEFAULT NULL,
  `default_criticality` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `specification` text COLLATE utf8mb4_unicode_ci,
  `pm_interval_days` smallint unsigned DEFAULT NULL COMMENT 'usulan; berlaku setelah aktivasi',
  `sort` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `room_template_items_template_id_item_key_unique` (`template_id`,`item_key`),
  KEY `room_template_items_category_id_foreign` (`category_id`),
  KEY `room_template_items_default_department_id_foreign` (`default_department_id`),
  CONSTRAINT `room_template_items_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `asset_categories` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `room_template_items_default_department_id_foreign` FOREIGN KEY (`default_department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `room_template_items_template_id_foreign` FOREIGN KEY (`template_id`) REFERENCES `room_asset_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `room_template_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `room_template_items` ENABLE KEYS */;
DROP TABLE IF EXISTS `room_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `room_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `property_id` bigint unsigned NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bed_config` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `room_types_property_id_code_unique` (`property_id`,`code`),
  CONSTRAINT `room_types_property_id_foreign` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `room_types` DISABLE KEYS */;
/*!40000 ALTER TABLE `room_types` ENABLE KEYS */;
DROP TABLE IF EXISTS `scan_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `scan_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `asset_label_id` bigint unsigned DEFAULT NULL,
  `asset_id` bigint unsigned DEFAULT NULL,
  `result` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `channel` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'url',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scanned_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `scan_logs_user_id_foreign` (`user_id`),
  KEY `scan_logs_asset_label_id_foreign` (`asset_label_id`),
  KEY `scan_logs_asset_id_foreign` (`asset_id`),
  CONSTRAINT `scan_logs_asset_id_foreign` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `scan_logs_asset_label_id_foreign` FOREIGN KEY (`asset_label_id`) REFERENCES `asset_labels` (`id`) ON DELETE SET NULL,
  CONSTRAINT `scan_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `scan_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `scan_logs` ENABLE KEYS */;
DROP TABLE IF EXISTS `sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sequences` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `property_id` bigint unsigned NOT NULL,
  `prefix` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `year` smallint unsigned NOT NULL DEFAULT '0',
  `last_number` bigint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sequences_property_id_prefix_year_unique` (`property_id`,`prefix`,`year`),
  CONSTRAINT `sequences_property_id_foreign` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `sequences` DISABLE KEYS */;
/*!40000 ALTER TABLE `sequences` ENABLE KEYS */;
DROP TABLE IF EXISTS `service_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `property_id` bigint unsigned NOT NULL,
  `number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `asset_id` bigint unsigned DEFAULT NULL,
  `quantity_group_id` bigint unsigned DEFAULT NULL,
  `location_id` bigint unsigned DEFAULT NULL,
  `reporter_id` bigint unsigned NOT NULL,
  `reporter_department_id` bigint unsigned DEFAULT NULL,
  `reporter_contact` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `symptom` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `impact` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_safety` tinyint(1) NOT NULL DEFAULT '0',
  `priority` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'P3',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'submitted',
  `received_at` timestamp NOT NULL,
  `response_due_at` timestamp NULL DEFAULT NULL,
  `response_at` timestamp NULL DEFAULT NULL,
  `responded_by` bigint unsigned DEFAULT NULL,
  `triaged_at` timestamp NULL DEFAULT NULL,
  `triaged_by` bigint unsigned DEFAULT NULL,
  `status_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duplicate_of_id` bigint unsigned DEFAULT NULL,
  `room_block_proposed` tinyint(1) NOT NULL DEFAULT '0',
  `resolved_at` timestamp NULL DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `client_event_uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `version` int unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `service_requests_number_unique` (`number`),
  UNIQUE KEY `service_requests_reporter_id_client_event_uuid_unique` (`reporter_id`,`client_event_uuid`),
  KEY `service_requests_asset_id_foreign` (`asset_id`),
  KEY `service_requests_quantity_group_id_foreign` (`quantity_group_id`),
  KEY `service_requests_location_id_foreign` (`location_id`),
  KEY `service_requests_reporter_department_id_foreign` (`reporter_department_id`),
  KEY `service_requests_responded_by_foreign` (`responded_by`),
  KEY `service_requests_triaged_by_foreign` (`triaged_by`),
  KEY `service_requests_duplicate_of_id_foreign` (`duplicate_of_id`),
  KEY `service_requests_property_id_status_index` (`property_id`,`status`),
  CONSTRAINT `service_requests_asset_id_foreign` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `service_requests_duplicate_of_id_foreign` FOREIGN KEY (`duplicate_of_id`) REFERENCES `service_requests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `service_requests_location_id_foreign` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `service_requests_property_id_foreign` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `service_requests_quantity_group_id_foreign` FOREIGN KEY (`quantity_group_id`) REFERENCES `asset_quantity_groups` (`id`) ON DELETE SET NULL,
  CONSTRAINT `service_requests_reporter_department_id_foreign` FOREIGN KEY (`reporter_department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `service_requests_reporter_id_foreign` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `service_requests_responded_by_foreign` FOREIGN KEY (`responded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `service_requests_triaged_by_foreign` FOREIGN KEY (`triaged_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `service_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `service_requests` ENABLE KEYS */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
DROP TABLE IF EXISTS `sla_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sla_policies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `property_id` bigint unsigned NOT NULL,
  `priority` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `example` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `response_minutes` int unsigned NOT NULL,
  `recovery_minutes` int unsigned DEFAULT NULL COMMENT 'null = target per kasus',
  `calendar` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '24h',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sla_policies_property_id_priority_unique` (`property_id`,`priority`),
  CONSTRAINT `sla_policies_property_id_foreign` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `sla_policies` DISABLE KEYS */;
INSERT INTO `sla_policies` VALUES (1,1,'P1','P1 Darurat','Bahaya listrik, dugaan kebocoran gas, kegagalan utilitas kritis',5,NULL,'24h','2026-11-16 04:32:32','2026-11-16 04:32:32'),(2,1,'P2','P2 Mendesak','Kamar tidak dapat dijual, chiller gagal, gangguan acara aktif',15,120,'24h','2026-11-16 04:32:32','2026-11-16 04:32:32'),(3,1,'P3','P3 Normal','Kerusakan dengan layanan alternatif yang aman',120,1440,'business','2026-11-16 04:32:32','2026-11-16 04:32:32'),(4,1,'P4','P4 Terencana','Kosmetik atau penyempurnaan tanpa gangguan layanan',540,10080,'business','2026-11-16 04:32:32','2026-11-16 04:32:32');
/*!40000 ALTER TABLE `sla_policies` ENABLE KEYS */;
DROP TABLE IF EXISTS `slot_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `slot_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `slot_id` bigint unsigned NOT NULL,
  `asset_id` bigint unsigned DEFAULT NULL,
  `quantity_group_id` bigint unsigned DEFAULT NULL,
  `assigned_at` timestamp NOT NULL,
  `released_at` timestamp NULL DEFAULT NULL,
  `assign_reason` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'generated',
  `release_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `generation_batch_id` bigint unsigned DEFAULT NULL,
  `actor_id` bigint unsigned DEFAULT NULL,
  `active_slot_key` bigint unsigned DEFAULT NULL,
  `active_asset_key` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slot_assignments_active_slot_key_unique` (`active_slot_key`),
  UNIQUE KEY `slot_assignments_active_asset_key_unique` (`active_asset_key`),
  KEY `slot_assignments_slot_id_foreign` (`slot_id`),
  KEY `slot_assignments_asset_id_foreign` (`asset_id`),
  KEY `slot_assignments_quantity_group_id_foreign` (`quantity_group_id`),
  KEY `slot_assignments_actor_id_foreign` (`actor_id`),
  CONSTRAINT `slot_assignments_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `slot_assignments_asset_id_foreign` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `slot_assignments_quantity_group_id_foreign` FOREIGN KEY (`quantity_group_id`) REFERENCES `asset_quantity_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `slot_assignments_slot_id_foreign` FOREIGN KEY (`slot_id`) REFERENCES `room_asset_slots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `slot_assignments` DISABLE KEYS */;
/*!40000 ALTER TABLE `slot_assignments` ENABLE KEYS */;
DROP TABLE IF EXISTS `status_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `status_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `subject_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_id` bigint unsigned NOT NULL,
  `from_status` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `data` json DEFAULT NULL,
  `actor_id` bigint unsigned DEFAULT NULL,
  `occurred_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `status_events_subject_type_subject_id_index` (`subject_type`,`subject_id`),
  KEY `status_events_actor_id_foreign` (`actor_id`),
  CONSTRAINT `status_events_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `status_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `status_events` ENABLE KEYS */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `property_id` bigint unsigned DEFAULT NULL,
  `department_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `job_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `must_change_password` tinyint(1) NOT NULL DEFAULT '0',
  `password_changed_at` timestamp NULL DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `app_authentication_secret` text COLLATE utf8mb4_unicode_ci,
  `app_authentication_recovery_codes` text COLLATE utf8mb4_unicode_ci,
  `labor_rate` decimal(14,2) DEFAULT NULL COMMENT 'tarif manajerial per jam (internal)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_property_id_foreign` (`property_id`),
  KEY `users_department_id_foreign` (`department_id`),
  CONSTRAINT `users_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_property_id_foreign` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,1,NULL,'Konsultan Asset Care','Super Admin (vendor aplikasi)','admin@assetcare.local',NULL,NULL,'$2y$12$uKYqM5BnxQ/65cKRW2n1/uLNVDL69.DVyQde4Znkj05tVKqmkAkKu',NULL,'2026-11-16 04:32:33','2026-11-16 04:32:33',1,1,NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
DROP TABLE IF EXISTS `vendors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendors` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `property_id` bigint unsigned NOT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `specialization` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_person` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service_hours` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sla_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vendors_property_id_code_unique` (`property_id`,`code`),
  CONSTRAINT `vendors_property_id_foreign` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `vendors` DISABLE KEYS */;
/*!40000 ALTER TABLE `vendors` ENABLE KEYS */;
DROP TABLE IF EXISTS `wo_cost_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wo_cost_lines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `work_order_id` bigint unsigned NOT NULL,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` decimal(14,3) NOT NULL DEFAULT '1.000',
  `unit_cost` decimal(18,2) NOT NULL DEFAULT '0.00',
  `amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `cost_status` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'provisional',
  `is_cash_out` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'false = alokasi biaya internal',
  `source_type` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_id` bigint unsigned DEFAULT NULL,
  `invoice_ref` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reverses_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `finalized_by` bigint unsigned DEFAULT NULL,
  `finalized_at` timestamp NULL DEFAULT NULL,
  `period` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wo_cost_lines_source_type_source_id_unique` (`source_type`,`source_id`),
  KEY `wo_cost_lines_work_order_id_foreign` (`work_order_id`),
  KEY `wo_cost_lines_reverses_id_foreign` (`reverses_id`),
  KEY `wo_cost_lines_created_by_foreign` (`created_by`),
  KEY `wo_cost_lines_finalized_by_foreign` (`finalized_by`),
  CONSTRAINT `wo_cost_lines_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `wo_cost_lines_finalized_by_foreign` FOREIGN KEY (`finalized_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `wo_cost_lines_reverses_id_foreign` FOREIGN KEY (`reverses_id`) REFERENCES `wo_cost_lines` (`id`) ON DELETE SET NULL,
  CONSTRAINT `wo_cost_lines_work_order_id_foreign` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `wo_cost_lines` DISABLE KEYS */;
/*!40000 ALTER TABLE `wo_cost_lines` ENABLE KEYS */;
DROP TABLE IF EXISTS `wo_time_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wo_time_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `work_order_id` bigint unsigned NOT NULL,
  `technician_id` bigint unsigned NOT NULL,
  `started_at` timestamp NOT NULL,
  `ended_at` timestamp NULL DEFAULT NULL,
  `approved_minutes` int unsigned DEFAULT NULL,
  `rate_snapshot` decimal(14,2) DEFAULT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wo_time_logs_work_order_id_foreign` (`work_order_id`),
  KEY `wo_time_logs_technician_id_foreign` (`technician_id`),
  CONSTRAINT `wo_time_logs_technician_id_foreign` FOREIGN KEY (`technician_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `wo_time_logs_work_order_id_foreign` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `wo_time_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `wo_time_logs` ENABLE KEYS */;
DROP TABLE IF EXISTS `work_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `work_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `property_id` bigint unsigned NOT NULL,
  `number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'corrective',
  `source` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `pm_schedule_id` bigint unsigned DEFAULT NULL,
  `condition_reading_id` bigint unsigned DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `asset_id` bigint unsigned DEFAULT NULL,
  `location_id` bigint unsigned DEFAULT NULL,
  `priority` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'P3',
  `is_safety` tinyint(1) NOT NULL DEFAULT '0',
  `is_emergency` tinyint(1) NOT NULL DEFAULT '0',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `received_at` timestamp NOT NULL COMMENT 'waktu laporan pertama diterima server',
  `response_due_at` timestamp NULL DEFAULT NULL,
  `due_at` timestamp NULL DEFAULT NULL COMMENT 'target pemulihan',
  `sla_calendar` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '24h',
  `sla_cycle` int unsigned NOT NULL DEFAULT '1' COMMENT 'reopen memulai siklus baru',
  `hold_minutes` int unsigned NOT NULL DEFAULT '0',
  `hold_started_at` timestamp NULL DEFAULT NULL,
  `hold_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estimated_cost` decimal(18,2) DEFAULT NULL,
  `requires_approval` tinyint(1) NOT NULL DEFAULT '0',
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `approval_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pic_id` bigint unsigned DEFAULT NULL,
  `team_ids` json DEFAULT NULL,
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `planned_due_at` date DEFAULT NULL COMMENT 'jatuh tempo PM',
  `estimated_hours` decimal(6,2) DEFAULT NULL,
  `needs_vendor` tinyint(1) NOT NULL DEFAULT '0',
  `vendor_id` bigint unsigned DEFAULT NULL,
  `warranty_decision` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `permits` json DEFAULT NULL COMMENT 'ptw, loto, hot_work, height, room_access',
  `room_access_needed` tinyint(1) NOT NULL DEFAULT '0',
  `started_at` timestamp NULL DEFAULT NULL,
  `failure_mode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `diagnosis` text COLLATE utf8mb4_unicode_ci,
  `action_taken` text COLLATE utf8mb4_unicode_ci,
  `function_test_result` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `root_cause` text COLLATE utf8mb4_unicode_ci,
  `follow_up` text COLLATE utf8mb4_unicode_ci,
  `checklist` json DEFAULT NULL,
  `pm_checklist` json DEFAULT NULL COMMENT '[{item, done}] dari rencana PM',
  `evidence_exception_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `restored_at` timestamp NULL DEFAULT NULL COMMENT 'layanan kembali aman (termasuk solusi sementara)',
  `completed_at` timestamp NULL DEFAULT NULL,
  `completed_by` bigint unsigned DEFAULT NULL,
  `cost_status` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'provisional',
  `no_cost_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verified_by` bigint unsigned DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `verification_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `asset_status_after` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fo_confirmed` tinyint(1) DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `closed_by` bigint unsigned DEFAULT NULL,
  `reopen_count` int unsigned NOT NULL DEFAULT '0',
  `created_by` bigint unsigned DEFAULT NULL,
  `version` int unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `work_orders_number_unique` (`number`),
  KEY `work_orders_location_id_foreign` (`location_id`),
  KEY `work_orders_approved_by_foreign` (`approved_by`),
  KEY `work_orders_pic_id_foreign` (`pic_id`),
  KEY `work_orders_vendor_id_foreign` (`vendor_id`),
  KEY `work_orders_completed_by_foreign` (`completed_by`),
  KEY `work_orders_verified_by_foreign` (`verified_by`),
  KEY `work_orders_closed_by_foreign` (`closed_by`),
  KEY `work_orders_created_by_foreign` (`created_by`),
  KEY `work_orders_property_id_status_index` (`property_id`,`status`),
  KEY `work_orders_asset_id_failure_mode_index` (`asset_id`,`failure_mode`),
  KEY `work_orders_pm_schedule_id_foreign` (`pm_schedule_id`),
  KEY `work_orders_condition_reading_id_foreign` (`condition_reading_id`),
  CONSTRAINT `work_orders_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `work_orders_asset_id_foreign` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `work_orders_closed_by_foreign` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `work_orders_completed_by_foreign` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `work_orders_condition_reading_id_foreign` FOREIGN KEY (`condition_reading_id`) REFERENCES `condition_readings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `work_orders_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `work_orders_location_id_foreign` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `work_orders_pic_id_foreign` FOREIGN KEY (`pic_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `work_orders_pm_schedule_id_foreign` FOREIGN KEY (`pm_schedule_id`) REFERENCES `pm_schedules` (`id`) ON DELETE SET NULL,
  CONSTRAINT `work_orders_property_id_foreign` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `work_orders_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `work_orders_verified_by_foreign` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `work_orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `work_orders` ENABLE KEYS */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

