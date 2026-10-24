<?php
declare(strict_types=1);

namespace App\Gateway;

/** محدودیت تعداد پیام تا هزینه API و سوءاستفاده کنترل شود. */
final class RateLimiter
{
    private ConversationStore $store;
    private array $cfg;

    public function __construct(ConversationStore $store, array $appConfig)
    {
        $this->store = $store;
        $this->cfg = $appConfig;
    }

    /** @throws GatewayException */
    public function check(int $conversationId, string $ipHash): void
    {
        $perConv = (int) ($this->cfg['rate_limit_per_minute'] ?? 8);
        $perIp = (int) ($this->cfg['rate_limit_ip_per_minute'] ?? 20);

        if ($this->store->countUserMessagesSince($conversationId, 60) >= $perConv
            || $this->store->countUserMessagesByIpSince($ipHash, 60) >= $perIp) {
            throw new GatewayException('rate_limited', 'پیام‌ها خیلی سریع ارسال می‌شوند. چند لحظه صبر کنید.', 429);
        }
    }

    public function allowNewConversation(string $ipHash): bool
    {
        return $this->store->countNewConversationsByIpSince($ipHash, 600) < 10;
    }
}
