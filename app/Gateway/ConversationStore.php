<?php
declare(strict_types=1);

namespace App\Gateway;

use App\Support\Db;
use PDO;

/**
 * نگهداری گفتگوها در MySQL. هر کاربر گفتگوی جدا (conversation) دارد و
 * تاریخچه فقط از اینجا خوانده می‌شود؛ هرگز از مرورگر نمی‌آید.
 */
class ConversationStore
{
    /** @return array{id:int,public_id:string} */
    public function create(string $ipHash, string $uaHash): array
    {
        $publicId = bin2hex(random_bytes(16));
        $stmt = Db::pdo()->prepare(
            'INSERT INTO conversations (public_id, ip_hash, ua_hash, status, message_count, created_at, last_active_at)
             VALUES (:public_id, :ip_hash, :ua_hash, \'open\', 0, NOW(), NOW())'
        );
        $stmt->execute([':public_id' => $publicId, ':ip_hash' => $ipHash, ':ua_hash' => $uaHash]);
        return ['id' => (int) Db::pdo()->lastInsertId(), 'public_id' => $publicId];
    }

    /** @return array{id:int,public_id:string,status:string,message_count:int,ip_hash:string}|null */
    public function findByPublicId(string $publicId): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $publicId)) {
            return null;
        }
        $stmt = Db::pdo()->prepare(
            'SELECT id, public_id, status, message_count, ip_hash FROM conversations WHERE public_id = :p LIMIT 1'
        );
        $stmt->execute([':p' => $publicId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $row['id'] = (int) $row['id'];
        $row['message_count'] = (int) $row['message_count'];
        return $row;
    }

    /**
     * آخرین پیام‌های یک گفتگو (قدیمی‌ترین اول).
     * @return array<int,array{role:string,content:string}>
     */
    public function recentMessages(int $conversationId, int $limit): array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT role, content FROM messages WHERE conversation_id = :c ORDER BY id DESC LIMIT :l'
        );
        $stmt->bindValue(':c', $conversationId, PDO::PARAM_INT);
        $stmt->bindValue(':l', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        $rows = array_reverse($stmt->fetchAll());
        return array_map(static fn(array $r): array => [
            'role'    => (string) $r['role'],
            'content' => (string) $r['content'],
        ], $rows);
    }

    public function addMessage(int $conversationId, string $role, string $content, bool $flagged = false): void
    {
        $pdo = Db::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO messages (conversation_id, role, content, flagged, created_at)
             VALUES (:c, :r, :t, :f, NOW())'
        );
        $stmt->execute([
            ':c' => $conversationId,
            ':r' => $role,
            ':t' => $content,
            ':f' => $flagged ? 1 : 0,
        ]);
        if ($role === 'user') {
            $upd = $pdo->prepare(
                'UPDATE conversations SET message_count = message_count + 1, last_active_at = NOW() WHERE id = :c'
            );
            $upd->execute([':c' => $conversationId]);
        }
    }

    public function countUserMessagesSince(int $conversationId, int $seconds): int
    {
        $stmt = Db::pdo()->prepare(
            'SELECT COUNT(*) FROM messages
             WHERE conversation_id = :c AND role = \'user\' AND created_at >= (NOW() - INTERVAL :s SECOND)'
        );
        $stmt->bindValue(':c', $conversationId, PDO::PARAM_INT);
        $stmt->bindValue(':s', $seconds, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public function countUserMessagesByIpSince(string $ipHash, int $seconds): int
    {
        $stmt = Db::pdo()->prepare(
            'SELECT COUNT(*) FROM messages m
             JOIN conversations c ON c.id = m.conversation_id
             WHERE c.ip_hash = :ip AND m.role = \'user\' AND m.created_at >= (NOW() - INTERVAL :s SECOND)'
        );
        $stmt->bindValue(':ip', $ipHash, PDO::PARAM_STR);
        $stmt->bindValue(':s', $seconds, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public function countNewConversationsByIpSince(string $ipHash, int $seconds): int
    {
        $stmt = Db::pdo()->prepare(
            'SELECT COUNT(*) FROM conversations WHERE ip_hash = :ip AND created_at >= (NOW() - INTERVAL :s SECOND)'
        );
        $stmt->bindValue(':ip', $ipHash, PDO::PARAM_STR);
        $stmt->bindValue(':s', $seconds, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /** فقط برای اسکریپت‌های تست. */
    public function deleteByPublicId(string $publicId): void
    {
        $stmt = Db::pdo()->prepare('DELETE FROM conversations WHERE public_id = :p');
        $stmt->execute([':p' => $publicId]);
    }
}
