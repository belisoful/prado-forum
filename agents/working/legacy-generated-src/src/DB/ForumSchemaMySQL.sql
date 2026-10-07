-- =============================================================================
-- PRADO Forum Extension — MySQL / MariaDB Schema
-- Namespace: Belisoful\Forum
-- All table names use the "forum_" prefix configurable via TForumManager::tablePrefix
-- Compatible with: MySQL 5.7+ / MariaDB 10.3+
--
-- FULLTEXT indexes on threads.title, posts.content_raw are created for the
-- TForumSearchService fulltext backend. If you prefer the LIKE backend,
-- these indexes are unused but harmless.
--
-- Run once during installation. Safe to wrap in a transaction.
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_ENGINE_SUBSTITUTION';

-- =============================================================================
-- forum_categories
-- =============================================================================
CREATE TABLE IF NOT EXISTS `forum_categories` (
    `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(120)    NOT NULL,
    `description` TEXT            DEFAULT NULL,
    `sort_order`  SMALLINT        NOT NULL DEFAULT 0,
    `is_active`   TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_categories_sort` (`sort_order`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- forum_boards
-- =============================================================================
CREATE TABLE IF NOT EXISTS `forum_boards` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `category_id`   INT UNSIGNED    NOT NULL,
    `parent_id`     INT UNSIGNED    DEFAULT NULL COMMENT 'NULL = top-level board; non-null = sub-board',
    `name`          VARCHAR(120)    NOT NULL,
    `slug`          VARCHAR(100)    NOT NULL,
    `description`   TEXT            DEFAULT NULL,
    `sort_order`    SMALLINT        NOT NULL DEFAULT 0,
    `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
    `is_locked`     TINYINT(1)      NOT NULL DEFAULT 0,
    `thread_count`  INT UNSIGNED    NOT NULL DEFAULT 0,
    `post_count`    INT UNSIGNED    NOT NULL DEFAULT 0,
    `last_post_at`  DATETIME        DEFAULT NULL,
    `last_post_user_id` INT UNSIGNED DEFAULT NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_boards_slug` (`slug`),
    KEY `idx_boards_category` (`category_id`, `sort_order`),
    KEY `idx_boards_parent`   (`parent_id`),
    CONSTRAINT `fk_boards_category` FOREIGN KEY (`category_id`) REFERENCES `forum_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- forum_user_profiles
-- Separate from application auth; linked by username string only.
-- =============================================================================
CREATE TABLE IF NOT EXISTS `forum_user_profiles` (
    `id`                    INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `username`              VARCHAR(60)     NOT NULL,
    `display_name`          VARCHAR(80)     DEFAULT NULL,
    `avatar_url`            VARCHAR(512)    DEFAULT NULL,
    `bio`                   TEXT            DEFAULT NULL,
    `signature`             TEXT            DEFAULT NULL,
    `location`              VARCHAR(120)    DEFAULT NULL,
    `website`               VARCHAR(512)    DEFAULT NULL,
    `post_count`            INT UNSIGNED    NOT NULL DEFAULT 0,
    `thread_count`          INT UNSIGNED    NOT NULL DEFAULT 0,
    `reputation_points`     INT             NOT NULL DEFAULT 0,
    `warn_count`            SMALLINT        NOT NULL DEFAULT 0,
    `is_active`             TINYINT(1)      NOT NULL DEFAULT 1,
    `is_banned`             TINYINT(1)      NOT NULL DEFAULT 0,
    `banned_until`          DATETIME        DEFAULT NULL COMMENT 'NULL = permanent ban when is_banned=1',
    `ban_reason`            VARCHAR(500)    DEFAULT NULL,
    `last_seen_at`          DATETIME        DEFAULT NULL,
    `created_at`            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_profiles_username` (`username`),
    KEY `idx_profiles_banned`    (`is_banned`),
    KEY `idx_profiles_active`    (`is_active`),
    KEY `idx_profiles_last_seen` (`last_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- forum_threads
-- =============================================================================
CREATE TABLE IF NOT EXISTS `forum_threads` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `board_id`      INT UNSIGNED    NOT NULL,
    `user_id`       INT UNSIGNED    NOT NULL COMMENT 'forum_user_profiles.id',
    `title`         VARCHAR(250)    NOT NULL,
    `slug`          VARCHAR(220)    DEFAULT NULL,
    `reply_count`   INT UNSIGNED    NOT NULL DEFAULT 0,
    `view_count`    INT UNSIGNED    NOT NULL DEFAULT 0,
    `is_pinned`     TINYINT(1)      NOT NULL DEFAULT 0,
    `is_locked`     TINYINT(1)      NOT NULL DEFAULT 0,
    `is_approved`   TINYINT(1)      NOT NULL DEFAULT 1,
    `is_featured`   TINYINT(1)      NOT NULL DEFAULT 0,
    `last_post_at`  DATETIME        DEFAULT NULL,
    `last_post_user_id` INT UNSIGNED DEFAULT NULL,
    `first_post_id` INT UNSIGNED    DEFAULT NULL,
    `deleted_at`    DATETIME        DEFAULT NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_threads_board`       (`board_id`, `deleted_at`, `is_pinned`, `last_post_at`),
    KEY `idx_threads_user`        (`user_id`),
    KEY `idx_threads_approved`    (`is_approved`, `deleted_at`),
    KEY `idx_threads_last_post`   (`last_post_at`),
    FULLTEXT KEY `ft_threads_title` (`title`),
    CONSTRAINT `fk_threads_board` FOREIGN KEY (`board_id`) REFERENCES `forum_boards` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_threads_user`  FOREIGN KEY (`user_id`)  REFERENCES `forum_user_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- forum_posts
-- =============================================================================
CREATE TABLE IF NOT EXISTS `forum_posts` (
    `id`                    INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `thread_id`             INT UNSIGNED    NOT NULL,
    `board_id`              INT UNSIGNED    NOT NULL COMMENT 'Denormalised for board-scoped queries',
    `user_id`               INT UNSIGNED    NOT NULL COMMENT 'forum_user_profiles.id',
    `content_raw`           MEDIUMTEXT      NOT NULL COMMENT 'Original BBCode source',
    `content_html`          MEDIUMTEXT      DEFAULT NULL COMMENT 'Pre-rendered HTML; NULL = not yet rendered',
    `is_approved`           TINYINT(1)      NOT NULL DEFAULT 1,
    `is_spam_flagged`       TINYINT(1)      NOT NULL DEFAULT 0,
    `spam_score`            TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `ip_address`            VARCHAR(45)     DEFAULT NULL,
    `edit_count`            SMALLINT        NOT NULL DEFAULT 0,
    `last_edited_by_user_id` INT UNSIGNED   DEFAULT NULL,
    `deleted_at`            DATETIME        DEFAULT NULL,
    `created_at`            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_posts_thread`      (`thread_id`, `deleted_at`, `is_approved`, `created_at`),
    KEY `idx_posts_board`       (`board_id`, `deleted_at`, `is_approved`),
    KEY `idx_posts_user`        (`user_id`, `deleted_at`),
    KEY `idx_posts_pending`     (`is_approved`, `deleted_at`),
    KEY `idx_posts_spam`        (`is_spam_flagged`, `deleted_at`),
    FULLTEXT KEY `ft_posts_content` (`content_raw`),
    CONSTRAINT `fk_posts_thread` FOREIGN KEY (`thread_id`) REFERENCES `forum_threads` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_posts_board`  FOREIGN KEY (`board_id`)  REFERENCES `forum_boards`  (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_posts_user`   FOREIGN KEY (`user_id`)   REFERENCES `forum_user_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- forum_post_history
-- Edit audit trail; one row per revision outside the grace period.
-- =============================================================================
CREATE TABLE IF NOT EXISTS `forum_post_history` (
    `id`                    INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `post_id`               INT UNSIGNED    NOT NULL,
    `edited_by_user_id`     INT UNSIGNED    DEFAULT NULL,
    `content_raw`           MEDIUMTEXT      NOT NULL,
    `content_html`          MEDIUMTEXT      DEFAULT NULL,
    `edit_note`             VARCHAR(200)    DEFAULT NULL,
    `created_at`            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_post_history_post` (`post_id`, `created_at`),
    CONSTRAINT `fk_post_history_post` FOREIGN KEY (`post_id`) REFERENCES `forum_posts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- forum_tags
-- =============================================================================
CREATE TABLE IF NOT EXISTS `forum_tags` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(80)     NOT NULL,
    `slug`          VARCHAR(80)     NOT NULL,
    `thread_count`  INT UNSIGNED    NOT NULL DEFAULT 0,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tags_slug` (`slug`),
    KEY `idx_tags_thread_count` (`thread_count` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- forum_thread_tags  (pivot)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `forum_thread_tags` (
    `thread_id` INT UNSIGNED NOT NULL,
    `tag_id`    INT UNSIGNED NOT NULL,
    PRIMARY KEY (`thread_id`, `tag_id`),
    KEY `idx_thread_tags_tag` (`tag_id`),
    CONSTRAINT `fk_thread_tags_thread` FOREIGN KEY (`thread_id`) REFERENCES `forum_threads` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_thread_tags_tag`    FOREIGN KEY (`tag_id`)    REFERENCES `forum_tags`    (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- forum_reactions
-- =============================================================================
CREATE TABLE IF NOT EXISTS `forum_reactions` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `post_id`       INT UNSIGNED    NOT NULL,
    `user_id`       INT UNSIGNED    NOT NULL,
    `reaction_type` VARCHAR(30)     NOT NULL COMMENT 'like|love|laugh|wow|sad|angry',
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_reactions_post_user` (`post_id`, `user_id`),
    KEY `idx_reactions_post` (`post_id`, `reaction_type`),
    CONSTRAINT `fk_reactions_post` FOREIGN KEY (`post_id`) REFERENCES `forum_posts`         (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_reactions_user` FOREIGN KEY (`user_id`) REFERENCES `forum_user_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- forum_attachments
-- =============================================================================
CREATE TABLE IF NOT EXISTS `forum_attachments` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `post_id`       INT UNSIGNED    DEFAULT NULL,
    `thread_id`     INT UNSIGNED    DEFAULT NULL COMMENT 'Set for opening-post attachments',
    `user_id`       INT UNSIGNED    NOT NULL,
    `filename`      VARCHAR(255)    NOT NULL,
    `stored_path`   VARCHAR(512)    NOT NULL,
    `mime_type`     VARCHAR(100)    DEFAULT NULL,
    `file_size`     INT UNSIGNED    NOT NULL DEFAULT 0,
    `download_count` INT UNSIGNED   NOT NULL DEFAULT 0,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_attachments_post`   (`post_id`),
    KEY `idx_attachments_thread` (`thread_id`),
    KEY `idx_attachments_user`   (`user_id`),
    CONSTRAINT `fk_attachments_post`   FOREIGN KEY (`post_id`)   REFERENCES `forum_posts`         (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_attachments_thread` FOREIGN KEY (`thread_id`) REFERENCES `forum_threads`       (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_attachments_user`   FOREIGN KEY (`user_id`)   REFERENCES `forum_user_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- forum_subscriptions
-- =============================================================================
CREATE TABLE IF NOT EXISTS `forum_subscriptions` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `user_id`       INT UNSIGNED    NOT NULL,
    `thread_id`     INT UNSIGNED    DEFAULT NULL,
    `board_id`      INT UNSIGNED    DEFAULT NULL,
    `notify_on`     ENUM('all','mention','digest') NOT NULL DEFAULT 'all',
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_subscriptions_user_thread` (`user_id`, `thread_id`),
    UNIQUE KEY `uq_subscriptions_user_board`  (`user_id`, `board_id`),
    KEY `idx_subscriptions_thread` (`thread_id`),
    KEY `idx_subscriptions_board`  (`board_id`),
    CONSTRAINT `fk_subscriptions_user`   FOREIGN KEY (`user_id`)   REFERENCES `forum_user_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_subscriptions_thread` FOREIGN KEY (`thread_id`) REFERENCES `forum_threads`       (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_subscriptions_board`  FOREIGN KEY (`board_id`)  REFERENCES `forum_boards`        (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- forum_notifications
-- =============================================================================
CREATE TABLE IF NOT EXISTS `forum_notifications` (
    `id`                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `user_id`           INT UNSIGNED    NOT NULL,
    `type`              VARCHAR(40)     NOT NULL COMMENT 'mention|reply|reaction|badge|mod_action|digest',
    `actor_user_id`     INT UNSIGNED    DEFAULT NULL,
    `thread_id`         INT UNSIGNED    DEFAULT NULL,
    `post_id`           INT UNSIGNED    DEFAULT NULL,
    `message`           VARCHAR(500)    DEFAULT NULL,
    `url`               VARCHAR(512)    DEFAULT NULL,
    `is_read`           TINYINT(1)      NOT NULL DEFAULT 0,
    `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notifications_user`  (`user_id`, `is_read`, `created_at` DESC),
    KEY `idx_notifications_type`  (`type`),
    CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `forum_user_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- forum_polls
-- =============================================================================
CREATE TABLE IF NOT EXISTS `forum_polls` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `thread_id`     INT UNSIGNED    NOT NULL,
    `question`      VARCHAR(300)    NOT NULL,
    `is_multi`      TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '1 = multiple choice',
    `max_choices`   TINYINT         NOT NULL DEFAULT 1,
    `closes_at`     DATETIME        DEFAULT NULL,
    `is_closed`     TINYINT(1)      NOT NULL DEFAULT 0,
    `vote_count`    INT UNSIGNED    NOT NULL DEFAULT 0,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_polls_thread` (`thread_id`),
    CONSTRAINT `fk_polls_thread` FOREIGN KEY (`thread_id`) REFERENCES `forum_threads` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- forum_poll_options
-- =============================================================================
CREATE TABLE IF NOT EXISTS `forum_poll_options` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `poll_id`       INT UNSIGNED    NOT NULL,
    `option_text`   VARCHAR(200)    NOT NULL,
    `sort_order`    TINYINT         NOT NULL DEFAULT 0,
    `vote_count`    INT UNSIGNED    NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_poll_options_poll` (`poll_id`, `sort_order`),
    CONSTRAINT `fk_poll_options_poll` FOREIGN KEY (`poll_id`) REFERENCES `forum_polls` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- forum_poll_votes
-- =============================================================================
CREATE TABLE IF NOT EXISTS `forum_poll_votes` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `poll_id`       INT UNSIGNED    NOT NULL,
    `option_id`     INT UNSIGNED    NOT NULL,
    `user_id`       INT UNSIGNED    NOT NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_poll_votes_user_option` (`poll_id`, `option_id`, `user_id`),
    KEY `idx_poll_votes_user` (`user_id`, `poll_id`),
    CONSTRAINT `fk_poll_votes_poll`   FOREIGN KEY (`poll_id`)   REFERENCES `forum_polls`        (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_poll_votes_option` FOREIGN KEY (`option_id`) REFERENCES `forum_poll_options` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_poll_votes_user`   FOREIGN KEY (`user_id`)   REFERENCES `forum_user_profiles`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- forum_badges
-- =============================================================================
CREATE TABLE IF NOT EXISTS `forum_badges` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(80)     NOT NULL,
    `slug`          VARCHAR(80)     NOT NULL,
    `description`   TEXT            DEFAULT NULL,
    `icon_url`      VARCHAR(512)    DEFAULT NULL,
    `criteria_json` TEXT            DEFAULT NULL COMMENT 'JSON rules for auto-awarding',
    `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_badges_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- forum_user_badges  (pivot)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `forum_user_badges` (
    `id`                    INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `user_id`               INT UNSIGNED    NOT NULL,
    `badge_id`              INT UNSIGNED    NOT NULL,
    `granted_by_user_id`    INT UNSIGNED    DEFAULT NULL COMMENT 'NULL = auto-awarded',
    `context`               VARCHAR(200)    DEFAULT NULL COMMENT 'Human-readable reason',
    `awarded_at`            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_badges` (`user_id`, `badge_id`),
    KEY `idx_user_badges_badge` (`badge_id`),
    CONSTRAINT `fk_user_badges_user`  FOREIGN KEY (`user_id`)  REFERENCES `forum_user_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_user_badges_badge` FOREIGN KEY (`badge_id`) REFERENCES `forum_badges`         (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- forum_moderation_log
-- Immutable audit log — never UPDATE or DELETE rows.
-- =============================================================================
CREATE TABLE IF NOT EXISTS `forum_moderation_log` (
    `id`                    INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `moderator_user_id`     INT UNSIGNED    DEFAULT NULL,
    `action_type`           VARCHAR(40)     NOT NULL COMMENT 'approve|delete|spam|clear_spam|warn|ban|unban|edit_post|move|lock|pin|report|delete_thread',
    `target_type`           VARCHAR(20)     NOT NULL COMMENT 'post|thread|user|board',
    `target_id`             INT UNSIGNED    NOT NULL,
    `reason`                VARCHAR(500)    DEFAULT NULL,
    `meta_json`             TEXT            DEFAULT NULL COMMENT 'Extra context (e.g. old/new board for move)',
    `created_at`            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_modlog_moderator`  (`moderator_user_id`, `created_at` DESC),
    KEY `idx_modlog_target`     (`target_type`, `target_id`),
    KEY `idx_modlog_action`     (`action_type`, `created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- Re-enable FK checks
-- =============================================================================
SET foreign_key_checks = 1;
