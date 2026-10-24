<?php
declare(strict_types=1);

// نقطه شروع مشترک همه فایل‌های برنامه (public/api/*.php و scripts/*.php)

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

mb_internal_encoding('UTF-8');
date_default_timezone_set('Asia/Tehran');

// خطاها هرگز به کاربر نشان داده نمی‌شوند؛ فقط در لاگ نوشته می‌شوند.
ini_set('display_errors', PHP_SAPI === 'cli' ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

/**
 * شروع Session امن (کوکی HttpOnly و SameSite).
 */
function app_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $cfg = \App\Support\Config::get('app');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name((string) $cfg['session_name']);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
