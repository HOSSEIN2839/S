-- Add persistent chat history tables required by ConversationStore.
-- Safe to apply to an existing smart_shop database; does not alter catalog tables.
USE smart_shop;

CREATE TABLE IF NOT EXISTS conversations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(32) NOT NULL,
  ip_hash CHAR(64) NOT NULL,
  ua_hash CHAR(64) NOT NULL,
  status ENUM('open','closed') NOT NULL DEFAULT 'open',
  message_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  last_active_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_conversations_public_id (public_id),
  KEY idx_conversations_ip_created (ip_hash, created_at),
  KEY idx_conversations_last_active (last_active_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  conversation_id BIGINT UNSIGNED NOT NULL,
  role ENUM('user','assistant','system','tool') NOT NULL,
  content MEDIUMTEXT NOT NULL,
  flagged TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_messages_conversation_id (conversation_id, id),
  KEY idx_messages_role_created (role, created_at),
  CONSTRAINT fk_messages_conversation
    FOREIGN KEY (conversation_id) REFERENCES conversations(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
