<?php
declare(strict_types=1);

/*
 * Copy to config/app.php on the server and keep that real file out of Git.
 * Never place API keys or passwords in this file.
 */
return [
    'shop_name' => 'بازار',
    'prompt_file' => dirname(__DIR__) . '/prompts/system_prompt.fa.txt',
    'session_name' => 'smart_shop_session',
    'max_message_chars' => 500,
    'max_messages_per_conversation' => 40,
    'history_messages' => 12,
    'max_tool_rounds' => 3,
    'rate_limit_per_minute' => 8,
    'rate_limit_ip_per_minute' => 20,
    'log_file' => dirname(__DIR__) . '/logs/app.log',
];
