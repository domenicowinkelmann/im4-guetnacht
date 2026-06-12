<?php
/**
 * GET  /backend/api/baby.php  — fetch baby profile
 * POST /backend/api/baby.php  — update baby profile
 * Header: Authorization: Bearer <token>
 */

require_once __DIR__ . '/../utils/bootstrap.php';
require_once __DIR__ . '/../middleware/auth.php';

$user = requireAuth();
$pdo  = Database::getConnection();

// ── GET ───────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare('SELECT id, name, birth_date FROM babies WHERE user_id = :uid LIMIT 1');
    $stmt->execute([':uid' => $user['id']]);
    $baby = $stmt->fetch();

    if (!$baby) {
        jsonOk(['baby' => null]);
    }

    jsonOk(['baby' => $baby]);
}

// ── POST ──────────────────────────────────────────────────────────────────────
requireMethod('POST');

$body      = bodyJson();
$name      = trim($body['name'] ?? '');
$birthDate = trim($body['birth_date'] ?? '');

if ($name === '') {
    jsonError('Name is required.');
}

// Validate date format YYYY-MM-DD
if ($birthDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDate)) {
    jsonError('birth_date must be in YYYY-MM-DD format.');
}

// Check if baby exists for this user
$stmt = $pdo->prepare('SELECT id FROM babies WHERE user_id = :uid LIMIT 1');
$stmt->execute([':uid' => $user['id']]);
$baby = $stmt->fetch();

if ($baby) {
    // Update existing
    $pdo->prepare(
        'UPDATE babies SET name = :name, birth_date = :bd WHERE id = :id'
    )->execute([
        ':name' => $name,
        ':bd'   => $birthDate ?: null,
        ':id'   => $baby['id'],
    ]);
} else {
    // Create new (first time setup)
    $pdo->prepare(
        'INSERT INTO babies (user_id, name, birth_date) VALUES (:uid, :name, :bd)'
    )->execute([
        ':uid'  => $user['id'],
        ':name' => $name,
        ':bd'   => $birthDate ?: null,
    ]);
}

jsonOk(['message' => 'Baby profile updated.', 'name' => $name, 'birth_date' => $birthDate]);