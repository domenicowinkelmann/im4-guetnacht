<?php

require_once __DIR__ . '/../config/config.public.php';
require_once __DIR__ . '/../config/config.secure.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/UserModel.php';

/**
 * Auth middleware.
 * Call requireAuth() at the top of any protected endpoint.
 * Returns the authenticated user row on success.
 */
function requireAuth(): array
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if (!str_starts_with($header, 'Bearer ')) {
        jsonError('Missing or invalid Authorization header.', 401);
    }

    $token = substr($header, 7);
    $pdo   = Database::getConnection();
    $user  = UserModel::findByToken($pdo, $token);

    if ($user === null) {
        jsonError('Token invalid or expired.', 401);
    }

    return $user;
}
