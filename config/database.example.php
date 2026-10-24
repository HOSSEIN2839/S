<?php
declare(strict_types=1);

/* Copy to config/database.php on the server and fill credentials from hosting panel. */
return [
    'host' => '127.0.0.1',
    'port' => 3306,
    'name' => 'smart_shop',
    'user' => 'REPLACE_WITH_DATABASE_USER',
    'password' => 'REPLACE_WITH_DATABASE_PASSWORD',
    'charset' => 'utf8mb4',
];
