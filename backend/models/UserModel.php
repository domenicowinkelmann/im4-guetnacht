<?php

/**
 * UserModel — all DB interactions for users & session tokens.
 */
class UserModel
{
    // ── Registration ─────────────────────────────────────────────────────────

    public static function create(PDO $pdo, string $email, string $passwordHash, string $name): int
    {
        $stmt = $pdo->prepare(
            'INSERT INTO users (email, password_hash, name, created_at)
             VALUES (:email, :hash, :name, NOW())'
        );
        $stmt->execute([
            ':email' => $email,
            ':hash'  => $passwordHash,
            ':name'  => $name,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function findByEmail(PDO $pdo, string $email): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // ── Session tokens ────────────────────────────────────────────────────────

    public static function createToken(PDO $pdo, int $userId): string
    {
        $pdo->prepare('DELETE FROM session_tokens WHERE user_id = :uid')
            ->execute([':uid' => $userId]);

        $token     = self::generateToken();
        $expiresAt = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);

        $pdo->prepare(
            'INSERT INTO session_tokens (user_id, token, expires_at)
             VALUES (:uid, :token, :exp)'
        )->execute([
            ':uid'   => $userId,
            ':token' => $token,
            ':exp'   => $expiresAt,
        ]);

        return $token;
    }

    public static function findByToken(PDO $pdo, string $token): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT u.id, u.email, u.name
             FROM session_tokens st
             JOIN users u ON u.id = st.user_id
             WHERE st.token = :token
               AND st.expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function revokeToken(PDO $pdo, string $token): void
    {
        $pdo->prepare('DELETE FROM session_tokens WHERE token = :token')
            ->execute([':token' => $token]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function generateToken(): string
    {
        // HMAC-signed token: payload.signature
        $payload   = bin2hex(random_bytes(32));
        $signature = hash_hmac('sha256', $payload, TOKEN_SECRET);
        return "$payload.$signature";
    }
}
