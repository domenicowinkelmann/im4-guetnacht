<?php
/**
 * Bootstrap — loaded at the top of every API endpoint.
 * Handles CORS, JSON headers, and error formatting.
 */

require_once __DIR__ . '/../config/config.public.php';
require_once __DIR__ . '/../config/config.secure.php';
require_once __DIR__ . '/../config/database.php';

// ── CORS ────────────────────────────────────────────────────────────────────
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, ALLOWED_ORIGINS, true)) {
    header("Access-Control-Allow-Origin: $origin");
} elseif (APP_ENV === 'development') {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Error handler ────────────────────────────────────────────────────────────
set_exception_handler(function (Throwable $e) {
    $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error'   => APP_ENV === 'development' ? $e->getMessage() : 'Internal server error.',
    ]);
    exit;
});

// ── Helpers ──────────────────────────────────────────────────────────────────
function jsonOk(array $data): void
{
    echo json_encode(['success' => true, ...$data]);
    exit;
}

function jsonError(string $message, int $status = 400): void
{
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

function requireMethod(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== strtoupper($method)) {
        jsonError('Method not allowed.', 405);
    }
}

function bodyJson(): array
{
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        jsonError('Invalid JSON body.', 400);
    }
    return $data;
}
