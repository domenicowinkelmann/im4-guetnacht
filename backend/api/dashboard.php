<?php
/**
 * GET /api/dashboard.php
 * Header: Authorization: Bearer <token>
 *
 * Returns all data needed for the dashboard view.
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
    jsonOk(['baby' => null, 'stats' => null]);
}

// Use first baby for now (multi-baby support: pass ?baby_id= in future)
$baby   = $babies[0];
$babyId = (int) $baby['id'];

$activeSession   = SleepModel::getActiveSleepSession($pdo, $babyId);
$totalSleepSec   = SleepModel::getTotalSleepLast24h($pdo, $babyId);
$wakeUpCount     = SleepModel::getWakeUpCountLast24h($pdo, $babyId);
$movementCount   = SleepModel::getMovementCountLast24h($pdo, $babyId);
$hourlyActivity  = SleepModel::getHourlyActivity($pdo, $babyId);
$unreadCount     = SleepModel::getUnreadCount($pdo, $user['id']);

// ── ETL: Transform ────────────────────────────────────────────────────────────
$currentDurationSec = $activeSession ? (int) $activeSession['duration_seconds'] : 0;
$hours   = intdiv($currentDurationSec, 3600);
$minutes = intdiv($currentDurationSec % 3600, 60);

$totalHours   = intdiv($totalSleepSec, 3600);
$totalMinutes = intdiv($totalSleepSec % 3600, 60);

// Sleep quality heuristic based on movements per hour of sleep
$sleepHours = max($totalSleepSec / 3600, 1);
$movPh = $movementCount / $sleepHours;
$quality = match(true) {
    $movPh < 3  => 'Ausgezeichnet',
    $movPh < 8  => 'Gut',
    $movPh < 15 => 'Unruhig',
    default     => 'Aktiv',
};

// Build a full 24-slot hourly chart array (0–23), filling gaps with 0
$chartData = array_fill(0, 24, 0);
foreach ($hourlyActivity as $row) {
    $chartData[(int) $row['hour']] = (int) $row['movement_count'];
}

// ── ETL: Load (return) ────────────────────────────────────────────────────────
jsonOk([
    'baby' => [
        'id'         => $baby['id'],
        'name'       => $baby['name'],
        'birth_date' => $baby['birth_date'],
    ],
    'is_sleeping'       => $activeSession !== null,
    'current_sleep'     => ['hours' => $hours, 'minutes' => $minutes],
    'sleep_quality'     => $quality,
    'stats' => [
        'movement_count' => $movementCount,
        'total_sleep'    => sprintf('%dh %02dm', $totalHours, $totalMinutes),
        'wake_up_count'  => $wakeUpCount,
    ],
    'chart_data'         => $chartData,  // 24 values, index = hour
    'unread_notifications' => $unreadCount,
]);
