<?php
declare(strict_types=1);

namespace App\Ai;

interface AiClientInterface
{
    /**
     * @param array<int,array<string,mixed>> $messages پیام‌ها در قالب Chat Completions
     * @param array<int,array<string,mixed>> $tools    تعریف ابزارها (می‌تواند خالی باشد)
     * @return array{content:?string,tool_calls:array<int,array{id:string,name:string,arguments:array}>,raw_message:array,usage:array}
     * @throws AiException
     */
    public function chat(array $messages, array $tools = []): array;
}
