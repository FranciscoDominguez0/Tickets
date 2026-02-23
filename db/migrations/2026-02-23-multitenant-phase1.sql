START TRANSACTION;

CREATE TABLE IF NOT EXISTS `empresas` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(255) NOT NULL,
  `estado` ENUM('activa','inactiva') NOT NULL DEFAULT 'activa',
  `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_empresas_nombre` (`nombre`),
  KEY `idx_empresas_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `empresas` (`id`, `nombre`, `estado`, `fecha_creacion`)
VALUES (1, 'Vigitec panama', 'activa', NOW())
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `estado` = VALUES(`estado`);

ALTER TABLE `staff`
  MODIFY `role` ENUM('agent','supervisor','admin','superadmin') DEFAULT 'agent';

ALTER TABLE `staff`
  ADD COLUMN `empresa_id` INT NULL AFTER `dept_id`;

INSERT INTO `staff` (`id`, `username`, `password`, `email`, `firstname`, `lastname`, `dept_id`, `empresa_id`, `role`, `is_active`, `created`, `updated`, `last_login`, `signature`)
VALUES (999, 'vigitec', '$2y$12$3r5NhzoS0NzLKXj6MW/J.eZLbc58ECID205xSylALHcv15jxoVJbq', 'superadmin@vigitec.local', 'Vigitec', 'SuperAdmin', NULL, 1, 'superadmin', 1, NOW(), NOW(), NULL, NULL)
ON DUPLICATE KEY UPDATE
  `password` = VALUES(`password`),
  `empresa_id` = VALUES(`empresa_id`),
  `role` = VALUES(`role`),
  `is_active` = VALUES(`is_active`),
  `updated` = NOW();

ALTER TABLE `users`
  ADD COLUMN `empresa_id` INT NULL AFTER `company`;

ALTER TABLE `tickets`
  ADD COLUMN `empresa_id` INT NULL AFTER `ticket_number`;

ALTER TABLE `threads`
  ADD COLUMN `empresa_id` INT NULL AFTER `ticket_id`;

ALTER TABLE `thread_entries`
  ADD COLUMN `empresa_id` INT NULL AFTER `thread_id`;

ALTER TABLE `attachments`
  ADD COLUMN `empresa_id` INT NULL AFTER `thread_entry_id`;

ALTER TABLE `tasks`
  ADD COLUMN `empresa_id` INT NULL AFTER `dept_id`;

ALTER TABLE `departments`
  ADD COLUMN `empresa_id` INT NULL AFTER `description`;

ALTER TABLE `help_topics`
  ADD COLUMN `empresa_id` INT NULL AFTER `description`;

ALTER TABLE `email_accounts`
  ADD COLUMN `empresa_id` INT NULL AFTER `priority`;

ALTER TABLE `organizations`
  ADD COLUMN `empresa_id` INT NULL AFTER `name`;

ALTER TABLE `config`
  ADD COLUMN `empresa_id` INT NULL AFTER `id`;

ALTER TABLE `user_notes`
  ADD COLUMN `empresa_id` INT NULL AFTER `id`;

ALTER TABLE `user_login_attempts`
  ADD COLUMN `empresa_id` INT NULL AFTER `id`;

ALTER TABLE `ticket_links`
  ADD COLUMN `empresa_id` INT NULL AFTER `linked_ticket_id`;

ALTER TABLE `staff_password_resets`
  ADD COLUMN `empresa_id` INT NULL AFTER `id`;

ALTER TABLE `staff_login_attempts`
  ADD COLUMN `empresa_id` INT NULL AFTER `id`;

ALTER TABLE `sequences`
  ADD COLUMN `empresa_id` INT NULL AFTER `id`;

ALTER TABLE `role_permissions`
  ADD COLUMN `empresa_id` INT NULL AFTER `id`;

ALTER TABLE `roles`
  ADD COLUMN `empresa_id` INT NULL AFTER `id`;

ALTER TABLE `password_resets`
  ADD COLUMN `empresa_id` INT NULL AFTER `id`;

ALTER TABLE `notifications`
  ADD COLUMN `empresa_id` INT NULL AFTER `id`;

ALTER TABLE `logs`
  ADD COLUMN `empresa_id` INT NULL AFTER `id`;

ALTER TABLE `banlist`
  ADD COLUMN `empresa_id` INT NULL AFTER `id`;

ALTER TABLE `app_settings`
  ADD COLUMN `empresa_id` INT NULL AFTER `key`;

UPDATE `staff` SET `empresa_id` = 1 WHERE `empresa_id` IS NULL;
UPDATE `users` SET `empresa_id` = 1 WHERE `empresa_id` IS NULL;
UPDATE `tickets` SET `empresa_id` = 1 WHERE `empresa_id` IS NULL;
UPDATE `threads` SET `empresa_id` = 1 WHERE `empresa_id` IS NULL;
UPDATE `thread_entries` SET `empresa_id` = 1 WHERE `empresa_id` IS NULL;
UPDATE `attachments` SET `empresa_id` = 1 WHERE `empresa_id` IS NULL;
UPDATE `tasks` SET `empresa_id` = 1 WHERE `empresa_id` IS NULL;
UPDATE `departments` SET `empresa_id` = 1 WHERE `empresa_id` IS NULL;
UPDATE `help_topics` SET `empresa_id` = 1 WHERE `empresa_id` IS NULL;
UPDATE `email_accounts` SET `empresa_id` = 1 WHERE `empresa_id` IS NULL;
UPDATE `organizations` SET `empresa_id` = 1 WHERE `empresa_id` IS NULL;
UPDATE `config` SET `empresa_id` = 1 WHERE `empresa_id` IS NULL;
UPDATE `user_notes` SET `empresa_id` = 1 WHERE `empresa_id` IS NULL;
UPDATE `user_login_attempts` SET `empresa_id` = 1 WHERE `empresa_id` IS NULL;
UPDATE `ticket_links` SET `empresa_id` = 1 WHERE `empresa_id` IS NULL;
UPDATE `staff_password_resets` SET `empresa_id` = 1 WHERE `empresa_id` IS NULL;
UPDATE `staff_login_attempts` SET `empresa_id` = 1 WHERE `empresa_id` IS NULL;
UPDATE `sequences` SET `empresa_id` = 1 WHERE `empresa_id` IS NULL;
UPDATE `role_permissions` SET `empresa_id` = 1 WHERE `empresa_id` IS NULL;
UPDATE `roles` SET `empresa_id` = 1 WHERE `empresa_id` IS NULL;
UPDATE `password_resets` SET `empresa_id` = 1 WHERE `empresa_id` IS NULL;
UPDATE `notifications` SET `empresa_id` = 1 WHERE `empresa_id` IS NULL;
UPDATE `logs` SET `empresa_id` = 1 WHERE `empresa_id` IS NULL;
UPDATE `banlist` SET `empresa_id` = 1 WHERE `empresa_id` IS NULL;
UPDATE `app_settings` SET `empresa_id` = 1 WHERE `empresa_id` IS NULL;

ALTER TABLE `staff` MODIFY `empresa_id` INT NOT NULL DEFAULT 1;
ALTER TABLE `users` MODIFY `empresa_id` INT NOT NULL DEFAULT 1;
ALTER TABLE `tickets` MODIFY `empresa_id` INT NOT NULL DEFAULT 1;
ALTER TABLE `threads` MODIFY `empresa_id` INT NOT NULL DEFAULT 1;
ALTER TABLE `thread_entries` MODIFY `empresa_id` INT NOT NULL DEFAULT 1;
ALTER TABLE `attachments` MODIFY `empresa_id` INT NOT NULL DEFAULT 1;
ALTER TABLE `tasks` MODIFY `empresa_id` INT NOT NULL DEFAULT 1;
ALTER TABLE `departments` MODIFY `empresa_id` INT NOT NULL DEFAULT 1;
ALTER TABLE `help_topics` MODIFY `empresa_id` INT NOT NULL DEFAULT 1;
ALTER TABLE `email_accounts` MODIFY `empresa_id` INT NOT NULL DEFAULT 1;
ALTER TABLE `organizations` MODIFY `empresa_id` INT NOT NULL DEFAULT 1;
ALTER TABLE `config` MODIFY `empresa_id` INT NOT NULL DEFAULT 1;
ALTER TABLE `user_notes` MODIFY `empresa_id` INT NOT NULL DEFAULT 1;
ALTER TABLE `user_login_attempts` MODIFY `empresa_id` INT NOT NULL DEFAULT 1;
ALTER TABLE `ticket_links` MODIFY `empresa_id` INT NOT NULL DEFAULT 1;
ALTER TABLE `staff_password_resets` MODIFY `empresa_id` INT NOT NULL DEFAULT 1;
ALTER TABLE `staff_login_attempts` MODIFY `empresa_id` INT NOT NULL DEFAULT 1;
ALTER TABLE `sequences` MODIFY `empresa_id` INT NOT NULL DEFAULT 1;
ALTER TABLE `role_permissions` MODIFY `empresa_id` INT NOT NULL DEFAULT 1;
ALTER TABLE `roles` MODIFY `empresa_id` INT NOT NULL DEFAULT 1;
ALTER TABLE `password_resets` MODIFY `empresa_id` INT NOT NULL DEFAULT 1;
ALTER TABLE `notifications` MODIFY `empresa_id` INT NOT NULL DEFAULT 1;
ALTER TABLE `logs` MODIFY `empresa_id` INT NOT NULL DEFAULT 1;
ALTER TABLE `banlist` MODIFY `empresa_id` INT NOT NULL DEFAULT 1;
ALTER TABLE `app_settings` MODIFY `empresa_id` INT NOT NULL DEFAULT 1;

ALTER TABLE `staff`
  ADD KEY `idx_staff_empresa_id` (`empresa_id`),
  ADD KEY `idx_staff_empresa_active` (`empresa_id`,`is_active`);

ALTER TABLE `users`
  ADD KEY `idx_users_empresa_id` (`empresa_id`),
  ADD KEY `idx_users_empresa_status` (`empresa_id`,`status`);

ALTER TABLE `tickets`
  ADD KEY `idx_tickets_empresa_id` (`empresa_id`),
  ADD KEY `idx_tickets_empresa_status` (`empresa_id`,`status_id`),
  ADD KEY `idx_tickets_empresa_created` (`empresa_id`,`created`);

ALTER TABLE `threads`
  ADD KEY `idx_threads_empresa_id` (`empresa_id`),
  ADD KEY `idx_threads_empresa_created` (`empresa_id`,`created`);

ALTER TABLE `thread_entries`
  ADD KEY `idx_thread_entries_empresa_id` (`empresa_id`),
  ADD KEY `idx_thread_entries_empresa_created` (`empresa_id`,`created`);

ALTER TABLE `attachments`
  ADD KEY `idx_attachments_empresa_id` (`empresa_id`),
  ADD KEY `idx_attachments_empresa_created` (`empresa_id`,`created`);

ALTER TABLE `tasks`
  ADD KEY `idx_tasks_empresa_id` (`empresa_id`),
  ADD KEY `idx_tasks_empresa_status` (`empresa_id`,`status`),
  ADD KEY `idx_tasks_empresa_created` (`empresa_id`,`created`);

ALTER TABLE `departments`
  ADD KEY `idx_departments_empresa_id` (`empresa_id`),
  ADD KEY `idx_departments_empresa_active` (`empresa_id`,`is_active`);

ALTER TABLE `help_topics`
  ADD KEY `idx_help_topics_empresa_id` (`empresa_id`),
  ADD KEY `idx_help_topics_empresa_active` (`empresa_id`,`is_active`);

ALTER TABLE `email_accounts`
  ADD KEY `idx_email_accounts_empresa_id` (`empresa_id`),
  ADD KEY `idx_email_accounts_empresa_default` (`empresa_id`,`is_default`);

ALTER TABLE `organizations`
  ADD KEY `idx_organizations_empresa_id` (`empresa_id`);

ALTER TABLE `config`
  ADD KEY `idx_config_empresa_id` (`empresa_id`);

ALTER TABLE `user_notes`
  ADD KEY `idx_user_notes_empresa_id` (`empresa_id`);

ALTER TABLE `user_login_attempts`
  ADD KEY `idx_user_login_attempts_empresa_id` (`empresa_id`),
  ADD KEY `idx_user_login_attempts_empresa_updated` (`empresa_id`,`updated`);

ALTER TABLE `ticket_links`
  ADD KEY `idx_ticket_links_empresa_id` (`empresa_id`);

ALTER TABLE `staff_password_resets`
  ADD KEY `idx_staff_password_resets_empresa_id` (`empresa_id`),
  ADD KEY `idx_staff_password_resets_empresa_expires` (`empresa_id`,`expires_at`);

ALTER TABLE `staff_login_attempts`
  ADD KEY `idx_staff_login_attempts_empresa_id` (`empresa_id`),
  ADD KEY `idx_staff_login_attempts_empresa_updated` (`empresa_id`,`updated`);

ALTER TABLE `sequences`
  ADD KEY `idx_sequences_empresa_id` (`empresa_id`);

ALTER TABLE `role_permissions`
  ADD KEY `idx_role_permissions_empresa_id` (`empresa_id`),
  ADD KEY `idx_role_permissions_empresa_role` (`empresa_id`,`role_name`);

ALTER TABLE `roles`
  ADD KEY `idx_roles_empresa_id` (`empresa_id`);

ALTER TABLE `password_resets`
  ADD KEY `idx_password_resets_empresa_id` (`empresa_id`);

ALTER TABLE `notifications`
  ADD KEY `idx_notifications_empresa_id` (`empresa_id`);

ALTER TABLE `logs`
  ADD KEY `idx_logs_empresa_id` (`empresa_id`);

ALTER TABLE `banlist`
  ADD KEY `idx_banlist_empresa_id` (`empresa_id`);

ALTER TABLE `app_settings`
  ADD KEY `idx_app_settings_empresa_id` (`empresa_id`);

COMMIT;
