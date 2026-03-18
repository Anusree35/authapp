-- ============================================================
--  setup.sql
--  Run once to create the database and users table.
--
--  Usage:
--    mysql -u root -p < setup.sql
--
--  NOTE: PHP will also auto-create this on first registration
--        via the bootMySQL() function in config.php
-- ============================================================

CREATE DATABASE IF NOT EXISTS `authapp`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `authapp`;

CREATE TABLE IF NOT EXISTS `users` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `first_name` VARCHAR(100)  NOT NULL,
    `last_name`  VARCHAR(100)  NOT NULL,
    `username`   VARCHAR(60)   NOT NULL,
    `email`      VARCHAR(255)  NOT NULL,
    `password`   VARCHAR(255)  NOT NULL,
    `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_email`    (`email`),
    UNIQUE KEY `uq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SELECT 'Database and table created successfully.' AS status;
