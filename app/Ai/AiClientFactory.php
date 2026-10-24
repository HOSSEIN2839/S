<?php
declare(strict_types=1);

namespace App\Ai;

use App\Support\Config;

final class AiClientFactory
{
    public static function create(): AiClientInterface
    {
        $mode = (string) (Config::get('ai')['mode'] ?? 'mock');
        return $mode === 'live' ? new OpenAiCompatibleClient() : new MockAiClient();
    }
}
