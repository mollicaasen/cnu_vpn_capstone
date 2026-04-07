-- CNU VPN portal — full MySQL layout for this project
-- Run as: mysql -u root -p < schema.sql
-- Or paste into phpMyAdmin / MySQL Workbench.

CREATE DATABASE IF NOT EXISTS cnu_vpn
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE cnu_vpn;

-- -----------------------------------------------------------------------------
-- users — used by login.php (MD5 password check)
-- index.php still uses hardcoded credentials; add matching rows here if you
-- later point the portal at the database, or use login.php + session sync.
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS vpn_clients;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id      VARCHAR(50)  NOT NULL,
  password        CHAR(32)     NOT NULL COMMENT 'MD5 hex digest (32 chars), as built by login.php',
  created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_student_id (student_id)
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- notifications — login.php INSERT, fetch_notifications.php SELECT,
-- mark_read.php UPDATE is_read
-- -----------------------------------------------------------------------------
CREATE TABLE notifications (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         VARCHAR(50)  NOT NULL COMMENT 'Same identifier as users.student_id',
  message         TEXT         NOT NULL,
  is_read         TINYINT(1)   NOT NULL DEFAULT 0,
  created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_notifications_user_time (user_id, created_at)
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- vpn_clients — generate_vpn.php SELECT / INSERT
-- username must match $_SESSION["user_id"] from the portal (e.g. student_id).
-- -----------------------------------------------------------------------------
CREATE TABLE vpn_clients (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username        VARCHAR(50)  NOT NULL COMMENT 'Portal user id / student_id',
  public_key      TEXT         NOT NULL,
  private_key     TEXT         NOT NULL,
  vpn_ip          VARCHAR(15)  NOT NULL COMMENT 'e.g. 10.0.0.2',
  created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_vpn_clients_username (username),
  KEY idx_vpn_clients_ip (vpn_ip)
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- Seed: matches index.php demo login (student_id / password capstone2026)
-- -----------------------------------------------------------------------------
INSERT INTO users (student_id, password)
VALUES ('00923451', MD5('capstone2026'));
