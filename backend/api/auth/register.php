<?php
/**
 * POST /api/auth/register.php
 * Body: { "name": string, "email": string, "password": string }
 */

require_once __DIR__ . '/../../utils/bootstrap.php';
require_once __DIR__ . '/../../models/UserModel.php';

requireMethod('POST');

$body     = bodyJson();
$name     = trim($body['name'] ?? '');
$email    = trim(strtolower($body['email'] ?? ''));
$password = $body['password'] ?? '';

// ── Validation ────────────────────────────────────────────────────────────────
if ($name === '' || $email === '' || $password === '') {
    jsonError('name, email, and password are required.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonError('Invalid email address.');
}

if (strlen($password) < 8) {
    jsonError('Password must be at least 8 characters.');
}

// ── DB ────────────────────────────────────────────────────────────────────────
$pdo = Database::getConnection();

if (UserModel::findByEmail($pdo, $email) !== null) {
    jsonError('An account with this email already exists.', 409);
}

$hash   = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
$userId = UserModel::create($pdo, $email, $hash, $name);
$token  = UserModel::createToken($pdo, $userId);

jsonOk([
    'token'      => $token,
    'expires_in' => SESSION_LIFETIME,
    'user'       => ['id' => $userId, 'name' => $name, 'email' => $email],
]);
