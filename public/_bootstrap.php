<?php
declare(strict_types=1);

/**
 * Resolve the private application bootstrap.
 *
 * Deployment layout:
 *   /var/www/html/                 public document root
 *   /var/www/smart-shop-private/   private application files
 *
 * Local repository layout remains supported:
 *   project/public/ + project/app/
 */
$privateRoot = getenv('SMART_SHOP_PRIVATE_PATH');
$candidates = [];

if (is_string($privateRoot) && $privateRoot !== '') {
    $candidates[] = rtrim($privateRoot, DIRECTORY_SEPARATOR) . '/app/bootstrap.php';
}

// Default production path for the server deployment documented in docs/deployment.md.
$candidates[] = '/var/www/smart-shop-private/app/bootstrap.php';

// Local/repository layout fallback.
$candidates[] = dirname(__DIR__) . '/app/bootstrap.php';

foreach ($candidates as $bootstrap) {
    if (is_file($bootstrap) && is_readable($bootstrap)) {
        require_once $bootstrap;
        return;
    }
}

http_response_code(500);
if (PHP_SAPI === 'cli') {
    fwrite(STDERR, "Smart Shop bootstrap not found. Set SMART_SHOP_PRIVATE_PATH or check deployment paths.\n");
} else {
    error_log('[deployment] private application bootstrap not found');
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'خطای پیکربندی سرور. لطفاً بعداً دوباره تلاش کنید.';
}
exit(1);
