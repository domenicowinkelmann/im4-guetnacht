<?php
/**
 * POST /api/auth/logout.php
 * Header: Authorization: Bearer <token>
 */

require_once __DIR__ . '/../../utils/bootstrap.php';
require_once __DIR__ . '/../../models/UserModel.php';

requireMethod('POST');

$header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (str_starts_with($header, 'Bearer ')) {
    $token = substr($header, 7);
    $pdo   = Database::getConnection();
    UserModel::revokeToken($pdo, $token);
}

jsonOk(['message' => 'Logged out successfully.']);
