<?php
/**
 * POST /api/auth/login.php
 * Body: { "email": string, "password": string }
 */

require_once __DIR__ . '/../../utils/bootstrap.php';
require_once __DIR__ . '/../../models/UserModel.php';

requireMethod('POST');

$body     = bodyJson();
$email    = trim(strtolower($body['email'] ?? ''));
$password = $body['password'] ?? '';

if ($email === '' || $password === '') {
    jsonError('email and password are required.');
}

$pdo  = Database::getConnection();
$user = UserModel::findByEmail($pdo, $email);

// Constant-time check to prevent timing attacks
if ($user === null || !password_verify($password, $user['password_hash'])) {
    jsonError('Invalid email or password.', 401);
}

$token = UserModel::createToken($pdo, $user['id']);

jsonOk([
    'token'      => $token,
    'expires_in' => SESSION_LIFETIME,
    'user'       => [
        'id'    => $user['id'],
        'name'  => $user['name'],
        'email' => $user['email'],
    ],
]);
