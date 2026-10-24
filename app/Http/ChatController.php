<?php
declare(strict_types=1);

namespace App\Http;

use App\Ai\AiClientFactory;
use App\Ai\AiException;
use App\Gateway\ConversationGateway;
use App\Gateway\ConversationStore;
use App\Gateway\GatewayException;
use App\Gateway\InputGuard;
use App\Gateway\OutputGuard;
use App\Gateway\RateLimiter;
use App\Models\ProductRepository;
use App\Prompt\PromptGenerator;
use App\Support\Config;
use App\Support\Logger;
use App\Tools\ToolRegistry;

/**
 * POST /api/chat.php  ←  تنها نقطه ورود پیام‌های کاربر.
 * این کلاس فقط درخواست را می‌گیرد و بررسی اولیه می‌کند؛ کار اصلی را ConversationGateway انجام می‌دهد.
 * مرورگر هرگز مستقیم با هوش مصنوعی حرف نمی‌زند.
 */
final class ChatController
{
    public static function handle(): void
    {
        if (Request::method() !== 'POST') {
            header('Allow: POST');
            Response::error('method_not_allowed', 'فقط درخواست POST پذیرفته می‌شود.', 405);
            return;
        }

        try {
            app_start_session();

            $publicId = (string) ($_SESSION['conv_public_id'] ?? '');
            $csrf = (string) ($_SESSION['csrf'] ?? '');
            if ($publicId === '' || $csrf === '') {
                Response::error('no_session', 'گفتگو پیدا نشد. صفحه را دوباره باز کنید یا گفتگوی جدید بسازید.', 409);
                return;
            }
            if (!hash_equals($csrf, Request::header('X-CSRF-Token'))) {
                Logger::security('csrf_mismatch', ['ip' => substr(SessionController::ipHash(), 0, 12)]);
                Response::error('forbidden', 'درخواست نامعتبر است.', 403);
                return;
            }

            $body = Request::jsonBody();
            // فقط فیلد message خوانده می‌شود. هر فیلد دیگر (مثل history یا role) نادیده گرفته می‌شود.
            if ($body === null || !isset($body['message']) || !is_string($body['message'])) {
                Response::error('invalid_message', 'پیام نامعتبر است.', 400);
                return;
            }

            $gateway = self::buildGateway();
            $result = $gateway->handle($publicId, $body['message'], SessionController::ipHash());

            Response::json([
                'ok'        => true,
                'reply'     => $result['reply'],
                'products'  => $result['products'],
                'remaining' => $result['remaining'],
            ]);
        } catch (GatewayException $e) {
            Response::error($e->errorCode(), $e->getMessage(), $e->httpStatus());
        } catch (AiException $e) {
            Logger::error('ai_failed', ['kind' => $e->kind(), 'error' => $e->getMessage()]);
            Response::error('ai_unavailable', 'دستیار فعلاً در دسترس نیست. چند لحظه بعد دوباره تلاش کنید.', 502);
        } catch (\Throwable $e) {
            Logger::error('chat_failed', ['error' => $e->getMessage(), 'file' => basename($e->getFile()), 'line' => $e->getLine()]);
            Response::error('server_error', 'خطای سرور. لطفاً کمی بعد دوباره تلاش کنید.', 500);
        }
    }

    public static function buildGateway(): ConversationGateway
    {
        $appCfg = Config::get('app');
        $store = new ConversationStore();
        $products = new ProductRepository();
        $tools = new ToolRegistry($products);

        return new ConversationGateway(
            $store,
            new InputGuard((int) $appCfg['max_message_chars']),
            new OutputGuard(Config::secret('canary')),
            new RateLimiter($store, $appCfg),
            new PromptGenerator($tools),
            AiClientFactory::create(),
            $tools,
            $appCfg
        );
    }
}
