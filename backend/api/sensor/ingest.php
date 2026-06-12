<?php
/**
 * POST /backend/api/sensor/ingest.php
 *
 * Endpoint for the ESP32 sensor.
 * Expects JSON body: { "baby_id": int, "bewegung": 0|1 }
 *
 * Writes to sensor_events, manages sleep_sessions, and updates device_status.
 */

require_once __DIR__ . '/../../utils/bootstrap.php';

requireMethod('POST');

$pdo  = Database::getConnection();
$body = bodyJson();

// ── Validate input ────────────────────────────────────────────────────────────
if (!isset($body['baby_id'], $body['bewegung'])) {
    jsonError('baby_id and bewegung are required.');
}

$babyId   = (int) $body['baby_id'];
$bewegung = (int) $body['bewegung']; // 1 = movement, 0 = still/sleeping

// Verify baby exists
$stmt = $pdo->prepare('SELECT id FROM babies WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $babyId]);
if (!$stmt->fetch()) {
    jsonError("baby_id $babyId not found.", 404);
}

// ── 1. Log raw sensor event ───────────────────────────────────────────────────
$eventType = $bewegung === 1 ? 'movement' : 'sleep';
$label     = $bewegung === 1 ? 'Leichte Bewegung' : 'Ruhig / Schläft';

$pdo->prepare(
    'INSERT INTO sensor_events (baby_id, event_type, label, value, recorded_at)
     VALUES (:bid, :type, :label, NULL, NOW())'
)->execute([':bid' => $babyId, ':type' => $eventType, ':label' => $label]);

// ── 2. Manage sleep sessions ──────────────────────────────────────────────────
// Check if there is an active (open) sleep session for this baby
$stmt = $pdo->prepare(
    'SELECT id FROM sleep_sessions WHERE baby_id = :bid AND ended_at IS NULL LIMIT 1'
);
$stmt->execute([':bid' => $babyId]);
$activeSession = $stmt->fetch();

if ($bewegung === 0 && !$activeSession) {
    // Baby is still and no session is open → start a new sleep session
    $pdo->prepare(
        'INSERT INTO sleep_sessions (baby_id, started_at) VALUES (:bid, NOW())'
    )->execute([':bid' => $babyId]);

} elseif ($bewegung === 1 && $activeSession) {
    // Baby is moving and a session is open → close it (baby woke up)
    $pdo->prepare(
        'UPDATE sleep_sessions SET ended_at = NOW() WHERE id = :id'
    )->execute([':id' => $activeSession['id']]);

    // Overwrite the generic movement event with a wake event
    $pdo->prepare(
        'UPDATE sensor_events SET event_type = :type, label = :label
         WHERE baby_id = :bid ORDER BY recorded_at DESC LIMIT 1'
    )->execute([':type' => 'wake', ':label' => 'Aufgewacht', ':bid' => $babyId]);

    // Add a wake notification
    $pdo->prepare(
        'INSERT INTO notifications (baby_id, title, body, icon_type, is_read, created_at)
         VALUES (:bid, :title, :body, :icon, 0, NOW())'
    )->execute([
        ':bid'   => $babyId,
        ':title' => 'Aufgewacht',
        ':body'  => 'Bewegung erkannt — Baby ist aufgewacht',
        ':icon'  => 'wake',
    ]);
}

// ── 3. Update device heartbeat ────────────────────────────────────────────────
// Battery/signal optional — sensor can send them if available
$battery = isset($body['battery']) ? (int) $body['battery'] : null;
$signal  = isset($body['signal'])  ? (int) $body['signal']  : null;

$existing = $pdo->prepare('SELECT id FROM device_status WHERE baby_id = :bid LIMIT 1');
$existing->execute([':bid' => $babyId]);

if ($existing->fetch()) {
    $pdo->prepare(
        'UPDATE device_status SET
            battery_percent  = COALESCE(:bat, battery_percent),
            signal_strength  = COALESCE(:sig, signal_strength),
            last_seen_at     = NOW()
         WHERE baby_id = :bid'
    )->execute([':bat' => $battery, ':sig' => $signal, ':bid' => $babyId]);
} else {
    $pdo->prepare(
        'INSERT INTO device_status (baby_id, battery_percent, signal_strength, last_seen_at)
         VALUES (:bid, COALESCE(:bat, 100), COALESCE(:sig, 5), NOW())'
    )->execute([':bat' => $battery, ':sig' => $signal, ':bid' => $babyId]);
}

// ── Respond ───────────────────────────────────────────────────────────────────
jsonOk([
    'message'  => 'Data saved successfully.',
    'bewegung' => $bewegung,
    'baby_id'  => $babyId,
]);