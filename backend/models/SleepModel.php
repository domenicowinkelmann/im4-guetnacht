<?php

/**
 * SleepModel — all DB interactions for sleep sessions and sensor events.
 */
class SleepModel
{
    // ── Dashboard summary ────────────────────────────────────────────────────

    /**
     * Returns the active (ongoing) sleep session for a baby, or null.
     */
    public static function getActiveSleepSession(PDO $pdo, int $babyId): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT id, started_at,
                    TIMESTAMPDIFF(SECOND, started_at, NOW()) AS duration_seconds
             FROM sleep_sessions
             WHERE baby_id = :bid AND ended_at IS NULL
             ORDER BY started_at DESC
             LIMIT 1'
        );
        $stmt->execute([':bid' => $babyId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Total sleep (seconds) within the last 24 hours.
     */
    public static function getTotalSleepLast24h(PDO $pdo, int $babyId): int
    {
        $stmt = $pdo->prepare(
            'SELECT COALESCE(SUM(
                TIMESTAMPDIFF(SECOND,
                    GREATEST(started_at, DATE_SUB(NOW(), INTERVAL 24 HOUR)),
                    COALESCE(ended_at, NOW())
                )
             ), 0) AS total_seconds
             FROM sleep_sessions
             WHERE baby_id = :bid
               AND started_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)'
        );
        $stmt->execute([':bid' => $babyId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Number of wake-ups in the last 24 hours.
     */
    public static function getWakeUpCountLast24h(PDO $pdo, int $babyId): int
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM sleep_sessions
             WHERE baby_id = :bid
               AND ended_at IS NOT NULL
               AND ended_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)'
        );
        $stmt->execute([':bid' => $babyId]);
        return (int) $stmt->fetchColumn();
    }

    // ── Sensor events ────────────────────────────────────────────────────────

    /**
     * Movement count in the last 24 hours.
     */
    public static function getMovementCountLast24h(PDO $pdo, int $babyId): int
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM sensor_events
             WHERE baby_id = :bid
               AND event_type = "movement"
               AND recorded_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)'
        );
        $stmt->execute([':bid' => $babyId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Aggregated hourly movement for the activity chart (last 24h, bucketed by hour).
     * Returns array of {hour, movement_count}.
     */
    public static function getHourlyActivity(PDO $pdo, int $babyId): array
    {
        $stmt = $pdo->prepare(
            'SELECT
                HOUR(recorded_at) AS hour,
                COUNT(*) AS movement_count
             FROM sensor_events
             WHERE baby_id = :bid
               AND event_type = "movement"
               AND recorded_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             GROUP BY HOUR(recorded_at)
             ORDER BY hour ASC'
        );
        $stmt->execute([':bid' => $babyId]);
        return $stmt->fetchAll();
    }

    // ── Live page ────────────────────────────────────────────────────────────

    /**
     * Latest N sensor events for the live activity list.
     */
    public static function getRecentEvents(PDO $pdo, int $babyId, int $limit = 10): array
    {
        $stmt = $pdo->prepare(
            'SELECT event_type, label, recorded_at
             FROM sensor_events
             WHERE baby_id = :bid
             ORDER BY recorded_at DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':bid', $babyId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Latest device status for a baby.
     */
    public static function getDeviceStatus(PDO $pdo, int $babyId): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT battery_percent, signal_strength, last_seen_at
             FROM device_status
             WHERE baby_id = :bid
             ORDER BY last_seen_at DESC
             LIMIT 1'
        );
        $stmt->execute([':bid' => $babyId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // ── Notifications ────────────────────────────────────────────────────────

    public static function getNotifications(PDO $pdo, int $userId, int $limit = 20): array
    {
        $stmt = $pdo->prepare(
            'SELECT n.id, n.title, n.body, n.icon_type, n.is_read, n.created_at
             FROM notifications n
             JOIN babies b ON b.id = n.baby_id
             WHERE b.user_id = :uid
             ORDER BY n.created_at DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function getUnreadCount(PDO $pdo, int $userId): int
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM notifications n
             JOIN babies b ON b.id = n.baby_id
             WHERE b.user_id = :uid AND n.is_read = 0'
        );
        $stmt->execute([':uid' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    // ── Baby profile ─────────────────────────────────────────────────────────

    public static function getBabiesForUser(PDO $pdo, int $userId): array
    {
        $stmt = $pdo->prepare(
            'SELECT id, name, birth_date FROM babies WHERE user_id = :uid ORDER BY id ASC'
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }
}
