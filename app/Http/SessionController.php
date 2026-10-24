<?php
declare(strict_types=1);

namespace App\Http;

use App\Gateway\ConversationStore;
use App\Gateway\RateLimiter;
use App\Support\Config;
use App\Support\Logger;

/**
 * POST /api/session.php  ←  برای هر کاربر (یا هر بار «گفتگوی جدید») یک گفتگوی تازه می‌سازد.
 */
final class SessionController
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
            $ipHash = self::ipHash();
            $store = new ConversationStore();
            $limiter = new RateLimiter($store, Config::get('app'));

            if (!$limiter->allowNewConversation($ipHash)) {
                Response::error('rate_limited', 'تعداد گفتگوهای جدید زیاد است. چند دقیقه بعد دوباره تلاش کنید.', 429);
                return;
            }

            $conv = $store->create($ipHash, hash('sha256', Request::userAgent()));

            // با ساخت گفتگوی جدید، شناسه قبلی از Session پاک می‌شود و کاربر به گفتگوی تازه وصل می‌شود.
            session_regenerate_id(true);
            $_SESSION['conv_public_id'] = $conv['public_id'];
            $_SESSION['csrf'] = bin2hex(random_bytes(32));

            Response::json([
                'ok'        => true,
                'csrf'      => $_SESSION['csrf'],
                'greeting'  => 'درود! من دستیار خرید هوشمند هستم. بگویید چه ساعتی با چه بودجه‌ای می‌خواهید تا کمکتان کنم.',
                'max_chars' => (int) Config::get('app')['max_message_chars'],
            ]);
        } catch (\Throwable $e) {
            Logger::error('session_failed', ['error' => $e->getMessage()]);
            Response::error('server_error', 'خطای سرور. لطفاً کمی بعد دوباره تلاش کنید.', 500);
        }
    }

    public static function ipHash(): string
    {
        return hash('sha256', Request::ip() . '|' . Config::secret('ip_salt'));
    }
}
