<?php
declare(strict_types=1);

/* Copy to config/secrets.php on the server. Never commit real secrets. */
return [
    'api_key' => 'PUT-API-KEY-HERE',
    'ip_salt' => 'REPLACE_WITH_LONG_RANDOM_SECRET',
    'canary' => '',
];
