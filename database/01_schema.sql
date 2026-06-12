-- ============================================================
-- GuetNacht — Database Schema
-- Run as a DB admin user (CREATE TABLE permissions required).
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ── Users & Auth ─────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    email         VARCHAR(255)     NOT NULL,
    password_hash VARCHAR(255)     NOT NULL,
    name          VARCHAR(100)     NOT NULL,
    created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS session_tokens (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    token      VARCHAR(150) NOT NULL,
    expires_at DATETIME     NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_token (token),
    KEY idx_user_id (user_id),
    CONSTRAINT fk_st_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ── Baby Profiles ─────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS babies (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    name       VARCHAR(100) NOT NULL,
    birth_date DATE         NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_babies_user (user_id),
    CONSTRAINT fk_babies_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ── Sleep Sessions ────────────────────────────────────────────────────────────
-- One row = one continuous sleep block (ended_at NULL = currently sleeping).

CREATE TABLE IF NOT EXISTS sleep_sessions (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    baby_id    INT UNSIGNED NOT NULL,
    started_at DATETIME     NOT NULL,
    ended_at   DATETIME     NULL,

    PRIMARY KEY (id),
    KEY idx_ss_baby (baby_id),
    KEY idx_ss_started (started_at),
    CONSTRAINT fk_ss_baby FOREIGN KEY (baby_id) REFERENCES babies (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ── Sensor Events ─────────────────────────────────────────────────────────────
-- Written by the physical sensor / IoT device.
-- event_type: 'movement' | 'sound' | 'temp_alert' | 'wake' | 'sleep'

CREATE TABLE IF NOT EXISTS sensor_events (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    baby_id     INT UNSIGNED  NOT NULL,
    event_type  VARCHAR(50)   NOT NULL,
    label       VARCHAR(150)  NOT NULL,
    value       FLOAT         NULL,     -- optional numeric payload (e.g. temp in °C)
    recorded_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_se_baby_time (baby_id, recorded_at),
    CONSTRAINT fk_se_baby FOREIGN KEY (baby_id) REFERENCES babies (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ── Device Status ─────────────────────────────────────────────────────────────
-- Sensor device heartbeat. Upsert by baby_id in production.

CREATE TABLE IF NOT EXISTS device_status (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    baby_id          INT UNSIGNED NOT NULL,
    battery_percent  TINYINT UNSIGNED NOT NULL DEFAULT 100,
    signal_strength  TINYINT UNSIGNED NOT NULL DEFAULT 5,  -- 1–5 scale
    last_seen_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_ds_baby (baby_id),
    CONSTRAINT fk_ds_baby FOREIGN KEY (baby_id) REFERENCES babies (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ── Notifications ─────────────────────────────────────────────────────────────
-- icon_type: 'sleep' | 'wake' | 'sun' | 'alert'

CREATE TABLE IF NOT EXISTS notifications (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    baby_id    INT UNSIGNED NOT NULL,
    title      VARCHAR(150) NOT NULL,
    body       VARCHAR(500) NOT NULL,
    icon_type  VARCHAR(50)  NOT NULL DEFAULT 'sleep',
    is_read    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_notif_baby (baby_id),
    KEY idx_notif_read (is_read),
    CONSTRAINT fk_notif_baby FOREIGN KEY (baby_id) REFERENCES babies (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
