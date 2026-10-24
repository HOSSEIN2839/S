<?php
declare(strict_types=1);

/* Copy to config/ai.php on the server; choose mock for local UI tests or live for API. */
return [
    'mode' => 'mock',
    'base_url' => 'https://api.example.com/v1',
    'chat_path' => 'chat/completions',
    'model' => 'REPLACE_WITH_PROVIDER_MODEL',
    'temperature' => 0.3,
    'max_output_tokens' => 700,
    'max_tokens_param' => 'max_tokens',
    'timeout' => 30,
    'connect_timeout' => 10,
    'retries' => 1,
];
