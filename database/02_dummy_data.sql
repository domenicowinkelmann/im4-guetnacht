-- ============================================================
-- Seed data for domenico@test.ch (user_id = 4)
-- ============================================================

-- ── Baby profile ──────────────────────────────────────────────────────────────
INSERT INTO babies (user_id, name, birth_date) VALUES
(4, 'Liam', '2024-09-15');

-- ── Use the baby we just created ─────────────────────────────────────────────
SET @baby_id = LAST_INSERT_ID();

-- ── Device status ─────────────────────────────────────────────────────────────
INSERT INTO device_status (baby_id, battery_percent, signal_strength, last_seen_at) VALUES
(@baby_id, 87, 4, NOW() - INTERVAL 2 MINUTE);

-- ── Sleep sessions ────────────────────────────────────────────────────────────
-- Night sleep (18h → 570min ago)
INSERT INTO sleep_sessions (baby_id, started_at, ended_at) VALUES
(@baby_id, NOW() - INTERVAL 1080 MINUTE, NOW() - INTERVAL 570 MINUTE);

-- Morning nap (7h → 345min ago)
INSERT INTO sleep_sessions (baby_id, started_at, ended_at) VALUES
(@baby_id, NOW() - INTERVAL 420 MINUTE, NOW() - INTERVAL 345 MINUTE);

-- Current afternoon nap (ongoing)
INSERT INTO sleep_sessions (baby_id, started_at, ended_at) VALUES
(@baby_id, NOW() - INTERVAL 154 MINUTE, NULL);

-- ── Sensor events ─────────────────────────────────────────────────────────────
INSERT INTO sensor_events (baby_id, event_type, label, value, recorded_at) VALUES
-- Current nap
(@baby_id, 'sleep',    'Schläft ein',           NULL, NOW() - INTERVAL 154 MINUTE),
(@baby_id, 'movement', 'Leichte Bewegung',       NULL, NOW() - INTERVAL 140 MINUTE),
(@baby_id, 'movement', 'Leichte Bewegung',       NULL, NOW() - INTERVAL 115 MINUTE),
(@baby_id, 'movement', 'Leichte Bewegung',       NULL, NOW() - INTERVAL 90 MINUTE),
(@baby_id, 'movement', 'Leichte Bewegung',       NULL, NOW() - INTERVAL 49 MINUTE),
(@baby_id, 'movement', 'Schlaf fortgesetzt',     NULL, NOW() - INTERVAL 45 MINUTE),
(@baby_id, 'movement', 'Kurzes Aufwachen',       NULL, NOW() - INTERVAL 32 MINUTE),
(@baby_id, 'movement', 'Schlaf fortgesetzt',     NULL, NOW() - INTERVAL 29 MINUTE),
(@baby_id, 'movement', 'Leichte Bewegung',       NULL, NOW() - INTERVAL 17 MINUTE),
-- Morning nap
(@baby_id, 'sleep',    'Mittagsschlaf begonnen', NULL, NOW() - INTERVAL 420 MINUTE),
(@baby_id, 'movement', 'Leichte Bewegung',       NULL, NOW() - INTERVAL 405 MINUTE),
(@baby_id, 'wake',     'Aufgewacht',             NULL, NOW() - INTERVAL 345 MINUTE),
-- Nighttime
(@baby_id, 'sleep',    'Nachtschlaf begonnen',   NULL, NOW() - INTERVAL 1080 MINUTE),
(@baby_id, 'movement', 'Leichte Bewegung',       NULL, NOW() - INTERVAL 1020 MINUTE),
(@baby_id, 'movement', 'Leichte Bewegung',       NULL, NOW() - INTERVAL 900 MINUTE),
(@baby_id, 'movement', 'Kurzes Aufwachen',       NULL, NOW() - INTERVAL 840 MINUTE),
(@baby_id, 'movement', 'Schlaf fortgesetzt',     NULL, NOW() - INTERVAL 830 MINUTE),
(@baby_id, 'movement', 'Leichte Bewegung',       NULL, NOW() - INTERVAL 720 MINUTE),
(@baby_id, 'movement', 'Leichte Bewegung',       NULL, NOW() - INTERVAL 660 MINUTE),
(@baby_id, 'movement', 'Leichte Bewegung',       NULL, NOW() - INTERVAL 600 MINUTE),
(@baby_id, 'wake',     'Guten Morgen',           NULL, NOW() - INTERVAL 570 MINUTE);

-- ── Notifications ─────────────────────────────────────────────────────────────
INSERT INTO notifications (baby_id, title, body, icon_type, is_read, created_at) VALUES
(@baby_id, 'Beruhigt ruhen',   'Liam ist friedlich eingeschlafen',             'sleep', 0, NOW() - INTERVAL 154 MINUTE),
(@baby_id, 'Kurzes Aufwachen', 'Liam hat sich kurz bewegt, ruht jetzt wieder', 'wake',  0, NOW() - INTERVAL 32 MINUTE),
(@baby_id, 'Guten Morgen',     'Liam ist nach 8.5 Stunden erholt aufgewacht',  'sun',   1, NOW() - INTERVAL 570 MINUTE),
(@baby_id, 'Friedliche Nacht', 'Liam schläft tief und fest',                   'sleep', 1, NOW() - INTERVAL 900 MINUTE);