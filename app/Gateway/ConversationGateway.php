<?php
declare(strict_types=1);

namespace App\Gateway;

use App\Ai\AiClientInterface;
use App\Prompt\PromptGenerator;
use App\Support\Logger;
use App\Tools\ToolRegistry;

/**
 * «سیستم واسط» ما. تنها مسیر بین کاربر و هوش مصنوعی از همین کلاس می‌گذرد:
 *
 *   ChatController → ConversationGateway → PromptGenerator → AiClient → (هوش مصنوعی)
 *
 * وظیفه‌ها به ترتیب: پیدا کردن گفتگوی همان کاربر، محدودیت نرخ، بررسی ورودی، ذخیره پیام،
 * ساخت پرامپت، حلقه ابزارها (Function Calling)، بررسی خروجی، ساخت کارت‌ها، ذخیره جواب.
 */
final class ConversationGateway
{
    private const REFUSAL_INJECTION = 'این درخواست در حوزه کار من نیست. می‌توانم در انتخاب ساعت و مقایسه محصولات کمکتان کنم؛ بودجه و سلیقه‌تان را بگویید.';
    private const FALLBACK_NO_ANSWER = 'متأسفم، الان نتوانستم جواب مناسبی آماده کنم. لطفاً سؤالتان را کمی دقیق‌تر بپرسید.';

    private ConversationStore $store;
    private InputGuard $inputGuard;
    private OutputGuard $outputGuard;
    private RateLimiter $limiter;
    private PromptGenerator $prompts;
    private AiClientInterface $ai;
    private ToolRegistry $tools;
    private array $cfg;

    public function __construct(
        ConversationStore $store,
        InputGuard $inputGuard,
        OutputGuard $outputGuard,
        RateLimiter $limiter,
        PromptGenerator $prompts,
        AiClientInterface $ai,
        ToolRegistry $tools,
        array $appConfig
    ) {
        $this->store = $store;
        $this->inputGuard = $inputGuard;
        $this->outputGuard = $outputGuard;
        $this->limiter = $limiter;
        $this->prompts = $prompts;
        $this->ai = $ai;
        $this->tools = $tools;
        $this->cfg = $appConfig;
    }

    /**
     * @return array{reply:string,products:array<int,array<string,mixed>>,remaining:int}
     * @throws GatewayException
     * @throws \App\Ai\AiException
     */
    public function handle(string $publicId, string $rawMessage, string $ipHash): array
    {
        // ۱) گفتگوی همین کاربر
        $conv = $this->store->findByPublicId($publicId);
        if ($conv === null || $conv['status'] !== 'open') {
            throw new GatewayException('no_session', 'گفتگو پیدا نشد. گفتگوی جدید شروع کنید.', 409);
        }
        $maxMessages = (int) $this->cfg['max_messages_per_conversation'];
        if ($conv['message_count'] >= $maxMessages) {
            throw new GatewayException('conversation_full', 'این گفتگو به سقف پیام رسید. لطفاً گفتگوی جدید شروع کنید.', 429);
        }

        // ۲) محدودیت نرخ
        $this->limiter->check($conv['id'], $ipHash);

        // ۳) بررسی ورودی
        $in = $this->inputGuard->inspect($rawMessage);
        if (!$in['ok']) {
            throw new GatewayException('invalid_message', (string) $in['error'], 400);
        }
        $text = $in['text'];

        // پیام مشکوک به تزریق: جواب ثابت می‌دهیم، به هوش مصنوعی نمی‌فرستیم، هزینه‌ای هم نمی‌شود.
        if ($in['flagged']) {
            Logger::security('input_flagged', ['conversation' => $conv['id'], 'reason' => $in['reason']]);
            $this->store->addMessage($conv['id'], 'user', $text, true);
            $this->store->addMessage($conv['id'], 'assistant', self::REFUSAL_INJECTION, false);
            return [
                'reply'     => self::REFUSAL_INJECTION,
                'products'  => [],
                'remaining' => max(0, $maxMessages - $conv['message_count'] - 1),
            ];
        }

        // ۴) تاریخچه از دیتابیس (قبل از ذخیره پیام جدید) و ذخیره پیام کاربر
        $history = $this->store->recentMessages($conv['id'], (int) $this->cfg['history_messages']);
        $this->store->addMessage($conv['id'], 'user', $text, false);

        // ۵) ساخت پرامپت نهایی
        $built = $this->prompts->build($history, $text);
        $messages = $built['messages'];

        // ۶) حلقه ابزارها: مدل ابزار بخواهد، اجرا می‌کنیم و نتیجه را برمی‌گردانیم (حداکثر چند دور)
        $finalText = null;
        $productIds = [];
        $rounds = (int) $this->cfg['max_tool_rounds'];
        for ($round = 0; $round <= $rounds; $round++) {
            $withTools = $round < $rounds ? $built['tools'] : [];   // آخرین دور بدون ابزار تا جواب متنی بدهد
            $resp = $this->ai->chat($messages, $withTools);

            if ($resp['tool_calls'] === []) {
                $finalText = $resp['content'];
                break;
            }

            $messages[] = $resp['raw_message'];
            foreach ($resp['tool_calls'] as $call) {
                $result = $this->tools->run($call['name'], $call['arguments']);
                foreach ($result['product_ids'] as $pid) {
                    $productIds[] = $pid;
                }
                $messages[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $call['id'],
                    'content'      => (string) json_encode($result['payload'], JSON_UNESCAPED_UNICODE),
                ];
            }
        }

        if ($finalText === null || trim($finalText) === '') {
            $finalText = self::FALLBACK_NO_ANSWER;
            $productIds = [];
        }

        // ۷) بررسی خروجی
        $out = $this->outputGuard->filter($finalText);
        if ($out['blocked']) {
            Logger::security('output_blocked', ['conversation' => $conv['id'], 'reason' => $out['reason']]);
            $productIds = [];
        }
        $reply = $out['text'];

        // ۸) کارت‌ها از دیتابیس (نه از مدل)
        $cards = $this->tools->cards($productIds, $reply);

        // ۹) ذخیره جواب
        $this->store->addMessage($conv['id'], 'assistant', $reply, $out['blocked']);

        return [
            'reply'     => $reply,
            'products'  => $cards,
            'remaining' => max(0, $maxMessages - $conv['message_count'] - 1),
        ];
    }
}
