<?php
/**
 * GET /api/live.php
 * Header: Authorization: Bearer <token>
 *
 * Returns live monitoring data for the Live page.
 */

require_once __DIR__ . '/../utils/bootstrap.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../models/SleepModel.php';

requireMethod('GET');

$user = requireAuth();
$pdo  = Database::getConnection();

// ── ETL: Extract ──────────────────────────────────────────────────────────────
$babies = SleepModel::getBabiesForUser($pdo, $user['id']);

if (empty($babies)) {
    jsonOk(['device' => null, 'events' => []]);
}

$babyId  = (int) $babies[0]['id'];
$device  = SleepModel::getDeviceStatus($pdo, $babyId);
$events  = SleepModel::getRecentEvents($pdo, $babyId, 10);
$session = SleepModel::getActiveSleepSession($pdo, $babyId);

// ── ETL: Transform ────────────────────────────────────────────────────────────
$isOnline = false;
$batteryPercent = 0;
$signalLabel = 'Unbekannt';

if ($device !== null) {
    $lastSeen  = strtotime($device['last_seen_at']);
    $isOnline  = (time() - $lastSeen) < DATA_FRESHNESS_THRESHOLD;
    $batteryPercent = $device['battery_percent'];
    $strength = (int) $device['signal_strength']; // e.g. dBm or 1-5 scale
    $signalLabel = match(true) {
        $strength >= 4 => 'Stark',
        $strength >= 2 => 'Mittel',
        default        => 'Schwach',
    };
}

// Determine current status from latest event
$latestEvent = $events[0] ?? null;
$statusLabel = 'Unbekannt';
$statusDetail = '';
if ($latestEvent !== null) {
    $statusLabel  = ucfirst($latestEvent['label']);
    $statusDetail = $latestEvent['event_type'] === 'movement'
        ? 'Bewegung erkannt'
        : 'Ruhiger Schlaf';
}

// Format events for display
$formattedEvents = array_map(function ($e) {
    return [
        'time'  => date('H:i', strtotime($e['recorded_at'])),
        'label' => $e['label'],
        'type'  => $e['event_type'],
    ];
}, $events);

// ── ETL: Load (return) ────────────────────────────────────────────────────────
jsonOk([
    'baby_name'    => $babies[0]['name'],
    'is_sleeping'  => $session !== null,
    'status_label' => $session ? ($statusLabel ?: 'Friedlich') : 'Wach',
    'status_detail'=> $statusDetail ?: 'Wenig Bewegung erkannt',
    'device' => [
        'is_online'       => $isOnline,
        'battery_percent' => $batteryPercent,
        'signal_strength' => $signalLabel,
    ],
    'events' => $formattedEvents,
]);
