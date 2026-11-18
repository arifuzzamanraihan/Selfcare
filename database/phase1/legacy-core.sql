-- Selfcare phase 1: the part of the existing schema (database/selfcare.sql) that the new core reads and writes.
-- Use this on an empty database. If you imported the full database/selfcare.sql already, skip this file.
-- Foreign keys to tables outside this list are left out. Safe to run more than once (CREATE IF NOT EXISTS, data inserted only into empty tables).

CREATE TABLE IF NOT EXISTS `tenants` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_username` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hospital_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `data` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenants_tenant_username_unique` (`tenant_username`),
  UNIQUE KEY `tenants_hospital_name_unique` (`hospital_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `department_id` bigint UNSIGNED DEFAULT NULL,
  `first_name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `city` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `designation` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `region_code` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_super_admin_default` tinyint(1) NOT NULL DEFAULT '0',
  `gender` int NOT NULL,
  `is_admin_default` tinyint(1) NOT NULL DEFAULT '0',
  `qualification` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `blood_group` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `owner_id` int DEFAULT NULL,
  `owner_type` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL,
  `language` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `username` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hospital_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `facebook_url` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `twitter_url` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `instagram_url` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `linkedIn_url` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `theme_mode` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `hospital_type_id` bigint UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_username_unique` (`username`),
  KEY `users_tenant_id_foreign` (`tenant_id`),
  KEY `users_first_name_index` (`first_name`),
  KEY `users_hospital_type_id_foreign` (`hospital_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `front_settings` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `front_settings_tenant_id_foreign` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `doctor_departments` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `tenant_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `doctor_departments_tenant_id_foreign` (`tenant_id`),
  KEY `doctor_departments_title_index` (`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `doctors` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `doctor_department_id` bigint UNSIGNED NOT NULL,
  `specialist` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `tenant_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `appointment_charge` double NOT NULL DEFAULT '0',
  `google_json_file_path` longtext COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `doctors_user_id_foreign` (`user_id`),
  KEY `doctors_tenant_id_foreign` (`tenant_id`),
  KEY `doctors_doctor_department_id_foreign` (`doctor_department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `patients` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `tenant_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `custom_field` longtext COLLATE utf8mb4_unicode_ci,
  `template_id` int UNSIGNED DEFAULT NULL,
  `patient_unique_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `patients_patient_unique_id_unique` (`patient_unique_id`),
  KEY `patients_tenant_id_foreign` (`tenant_id`),
  KEY `patients_user_id_foreign` (`user_id`),
  KEY `patients_template_id_foreign` (`template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `nurses` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `tenant_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `nurses_tenant_id_foreign` (`tenant_id`),
  KEY `nurses_user_id_foreign` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `receptionists` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `tenant_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `receptionists_tenant_id_foreign` (`tenant_id`),
  KEY `receptionists_user_id_foreign` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pharmacists` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `tenant_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pharmacists_tenant_id_foreign` (`tenant_id`),
  KEY `pharmacists_user_id_foreign` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `accountants` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `tenant_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `accountants_tenant_id_foreign` (`tenant_id`),
  KEY `accountants_user_id_foreign` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lab_technicians` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `tenant_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lab_technicians_tenant_id_foreign` (`tenant_id`),
  KEY `lab_technicians_user_id_foreign` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `case_handlers` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `tenant_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `case_handlers_tenant_id_foreign` (`tenant_id`),
  KEY `case_handlers_user_id_foreign` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `departments` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `guard_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permissions` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_has_permissions` (
  `permission_id` bigint UNSIGNED NOT NULL,
  `role_id` bigint UNSIGNED NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `model_has_roles` (
  `role_id` bigint UNSIGNED NOT NULL,
  `model_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `model_has_permissions` (
  `permission_id` bigint UNSIGNED NOT NULL,
  `model_type` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint UNSIGNED NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  UNIQUE KEY `model_has_permissions_model_type_unique` (`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `departments` (`id`, `name`, `is_active`, `guard_name`, `created_at`, `updated_at`) VALUES
(1, 'Admin', 1, 'web', '2024-07-25 01:13:26', '2024-07-25 01:13:26'),
(2, 'Doctor', 1, 'web', '2024-07-25 01:13:26', '2024-07-25 01:13:26'),
(3, 'Patient', 1, 'web', '2024-07-25 01:13:26', '2024-07-25 01:13:26'),
(4, 'Nurse', 1, 'web', '2024-07-25 01:13:26', '2024-07-25 01:13:26'),
(5, 'Receptionist', 1, 'web', '2024-07-25 01:13:26', '2024-07-25 01:13:26'),
(6, 'Pharmacist', 1, 'web', '2024-07-25 01:13:26', '2024-07-25 01:13:26'),
(7, 'Accountant', 1, 'web', '2024-07-25 01:13:26', '2024-07-25 01:13:26'),
(8, 'Case Manager', 1, 'web', '2024-07-25 01:13:26', '2024-07-25 01:13:26'),
(9, 'Lab Technician', 1, 'web', '2024-07-25 01:13:26', '2024-07-25 01:13:26'),
(10, 'Super Admin', 1, 'web', '2024-07-25 01:13:26', '2024-07-25 01:13:26');

INSERT IGNORE INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES
(1, 'manage_users', 'web', '2024-07-25 01:13:26', '2024-07-25 01:13:26'),
(2, 'manage_beds', 'web', '2024-07-25 01:13:26', '2024-07-25 01:13:26'),
(3, 'manage_wards', 'web', '2024-07-25 01:13:26', '2024-07-25 01:13:26'),
(4, 'manage_appointments', 'web', '2024-07-25 01:13:26', '2024-07-25 01:13:26'),
(5, 'manage_prescriptions', 'web', '2024-07-25 01:13:26', '2024-07-25 01:13:26'),
(6, 'manage_patients', 'web', '2024-07-25 01:13:26', '2024-07-25 01:13:26'),
(7, 'manage_blood_bank', 'web', '2024-07-25 01:13:26', '2024-07-25 01:13:26'),
(8, 'manage_reports', 'web', '2024-07-25 01:13:26', '2024-07-25 01:13:26'),
(9, 'manage_payrolls', 'web', '2024-07-25 01:13:26', '2024-07-25 01:13:26'),
(10, 'manage_settings', 'web', '2024-07-25 01:13:26', '2024-07-25 01:13:26'),
(11, 'manage_notice_board', 'web', '2024-07-25 01:13:26', '2024-07-25 01:13:26'),
(12, 'manage_doctors', 'web', '2024-07-25 01:13:26', '2024-07-25 01:13:26'),
(13, 'manage_nurses', 'web', '2024-07-25 01:13:26', '2024-07-25 01:13:26'),
(14, 'manage_receptionists', 'web', '2024-07-25 01:13:26', '2024-07-25 01:13:26'),
(15, 'manage_pharmacists', 'web', '2024-07-25 01:13:26', '2024-07-25 01:13:26'),
(16, 'manage_accountants', 'web', '2024-07-25 01:13:26', '2024-07-25 01:13:26'),
(17, 'manage_invoices', 'web', '2024-07-25 01:13:26', '2024-07-25 01:13:26'),
(18, 'manage_operations_history', 'web', '2024-07-25 01:13:26', '2024-07-25 01:13:26'),
(19, 'manage_admit_history', 'web', '2024-07-25 01:13:26', '2024-07-25 01:13:26'),
(20, 'manage_blood_donor', 'web', '2024-07-25 01:13:26', '2024-07-25 01:13:26'),
(21, 'manage_medicines', 'web', '2024-07-25 01:13:26', '2024-07-25 01:13:26'),
(22, 'manage_department', 'web', '2024-07-25 01:13:26', '2024-07-25 01:13:26'),
(23, 'manage_doctor_departments', 'web', '2024-07-25 01:13:26', '2024-07-25 01:13:26'),
(24, 'manage_lab_technicians', 'web', '2024-07-25 01:13:27', '2024-07-25 01:13:27');

INSERT IGNORE INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES
(1, 1),
(2, 1),
(3, 1),
(4, 1),
(5, 1),
(6, 1),
(7, 1),
(8, 1),
(9, 1),
(10, 1),
(11, 1),
(12, 1),
(13, 1),
(14, 1),
(15, 1),
(16, 1),
(17, 1),
(18, 1),
(19, 1),
(20, 1),
(21, 1),
(22, 1),
(23, 1),
(24, 1),
(4, 2),
(5, 2),
(6, 2),
(7, 2),
(8, 2),
(11, 2),
(12, 2),
(13, 2),
(14, 2),
(15, 2),
(18, 2),
(20, 2),
(21, 2),
(22, 2),
(4, 3),
(5, 3),
(7, 3),
(8, 3),
(11, 3),
(12, 3),
(13, 3),
(14, 3),
(15, 3),
(19, 3),
(21, 3),
(2, 4),
(3, 4),
(4, 4),
(5, 4),
(6, 4),
(7, 4),
(8, 4),
(11, 4),
(12, 4),
(13, 4),
(14, 4),
(15, 4),
(18, 4),
(20, 4),
(21, 4),
(22, 4),
(2, 5),
(3, 5),
(4, 5),
(6, 5),
(7, 5),
(8, 5),
(11, 5),
(12, 5),
(13, 5),
(14, 5),
(15, 5),
(18, 5),
(19, 5),
(20, 5),
(22, 5),
(24, 5),
(11, 6),
(15, 6),
(21, 6),
(11, 7),
(15, 7),
(21, 7),
(1, 10),
(2, 10),
(3, 10),
(4, 10),
(5, 10),
(6, 10),
(7, 10),
(8, 10),
(9, 10),
(10, 10),
(11, 10),
(12, 10),
(13, 10),
(14, 10),
(15, 10),
(16, 10),
(17, 10),
(18, 10),
(19, 10),
(20, 10),
(21, 10),
(22, 10),
(23, 10);

CREATE TABLE IF NOT EXISTS `settings` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `key` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tenant_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
