-- =============================================================================
-- PRADO Forum Extension — PostgreSQL Schema
-- Namespace: Belisoful\Forum
-- All table names use the "forum_" prefix configurable via TForumManager::tablePrefix
-- Compatible with: PostgreSQL 13+
--
-- FULLTEXT search is handled via tsvector / GIN indexes on threads.title and
-- posts.content_raw for the TForumSearchService fulltext backend.
--
-- Run once during installation inside a transaction.
-- =============================================================================

BEGIN;

-- =============================================================================
-- forum_categories
-- =============================================================================
CREATE TABLE IF NOT EXISTS forum_categories (
    id          SERIAL          PRIMARY KEY,
    name        VARCHAR(120)    NOT NULL,
    description TEXT            DEFAULT NULL,
    sort_order  SMALLINT        NOT NULL DEFAULT 0,
    is_active   BOOLEAN         NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ     NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_categories_sort ON forum_categories (sort_order, is_active);


-- =============================================================================
-- forum_boards
-- =============================================================================
CREATE TABLE IF NOT EXISTS forum_boards (
    id              SERIAL          PRIMARY KEY,
    category_id     INT             NOT NULL REFERENCES forum_categories (id) ON DELETE CASCADE ON UPDATE CASCADE,
    parent_id       INT             DEFAULT NULL,           -- self-referential sub-board
    name            VARCHAR(120)    NOT NULL,
    slug            VARCHAR(100)    NOT NULL,
    description     TEXT            DEFAULT NULL,
    sort_order      SMALLINT        NOT NULL DEFAULT 0,
    is_active       BOOLEAN         NOT NULL DEFAULT TRUE,
    is_locked       BOOLEAN         NOT NULL DEFAULT FALSE,
    thread_count    INT             NOT NULL DEFAULT 0 CHECK (thread_count >= 0),
    post_count      INT             NOT NULL DEFAULT 0 CHECK (post_count >= 0),
    last_post_at    TIMESTAMPTZ     DEFAULT NULL,
    last_post_user_id INT           DEFAULT NULL,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW()
);

ALTER TABLE forum_boards
    ADD CONSTRAINT IF NOT EXISTS fk_boards_parent
    FOREIGN KEY (parent_id) REFERENCES forum_boards (id) ON DELETE SET NULL;

CREATE UNIQUE INDEX IF NOT EXISTS uq_boards_slug      ON forum_boards (slug);
CREATE INDEX        IF NOT EXISTS idx_boards_category  ON forum_boards (category_id, sort_order);
CREATE INDEX        IF NOT EXISTS idx_boards_parent    ON forum_boards (parent_id);


-- =============================================================================
-- forum_user_profiles
-- =============================================================================
CREATE TABLE IF NOT EXISTS forum_user_profiles (
    id                  SERIAL          PRIMARY KEY,
    username            VARCHAR(60)     NOT NULL,
    display_name        VARCHAR(80)     DEFAULT NULL,
    avatar_url          VARCHAR(512)    DEFAULT NULL,
    bio                 TEXT            DEFAULT NULL,
    signature           TEXT            DEFAULT NULL,
    location            VARCHAR(120)    DEFAULT NULL,
    website             VARCHAR(512)    DEFAULT NULL,
    post_count          INT             NOT NULL DEFAULT 0 CHECK (post_count >= 0),
    thread_count        INT             NOT NULL DEFAULT 0 CHECK (thread_count >= 0),
    reputation_points   INT             NOT NULL DEFAULT 0,
    warn_count          SMALLINT        NOT NULL DEFAULT 0 CHECK (warn_count >= 0),
    is_active           BOOLEAN         NOT NULL DEFAULT TRUE,
    is_banned           BOOLEAN         NOT NULL DEFAULT FALSE,
    banned_until        TIMESTAMPTZ     DEFAULT NULL,
    ban_reason          VARCHAR(500)    DEFAULT NULL,
    last_seen_at        TIMESTAMPTZ     DEFAULT NULL,
    created_at          TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ     NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_profiles_username  ON forum_user_profiles (username);
CREATE INDEX        IF NOT EXISTS idx_profiles_banned    ON forum_user_profiles (is_banned);
CREATE INDEX        IF NOT EXISTS idx_profiles_active    ON forum_user_profiles (is_active);
CREATE INDEX        IF NOT EXISTS idx_profiles_last_seen ON forum_user_profiles (last_seen_at DESC);


-- =============================================================================
-- forum_threads
-- =============================================================================
CREATE TABLE IF NOT EXISTS forum_threads (
    id              SERIAL          PRIMARY KEY,
    board_id        INT             NOT NULL REFERENCES forum_boards (id) ON DELETE CASCADE ON UPDATE CASCADE,
    user_id         INT             NOT NULL REFERENCES forum_user_profiles (id) ON DELETE CASCADE ON UPDATE CASCADE,
    title           VARCHAR(250)    NOT NULL,
    slug            VARCHAR(220)    DEFAULT NULL,
    reply_count     INT             NOT NULL DEFAULT 0 CHECK (reply_count >= 0),
    view_count      INT             NOT NULL DEFAULT 0 CHECK (view_count >= 0),
    is_pinned       BOOLEAN         NOT NULL DEFAULT FALSE,
    is_locked       BOOLEAN         NOT NULL DEFAULT FALSE,
    is_approved     BOOLEAN         NOT NULL DEFAULT TRUE,
    is_featured     BOOLEAN         NOT NULL DEFAULT FALSE,
    last_post_at    TIMESTAMPTZ     DEFAULT NULL,
    last_post_user_id INT           DEFAULT NULL,
    first_post_id   INT             DEFAULT NULL,
    deleted_at      TIMESTAMPTZ     DEFAULT NULL,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    -- tsvector column for full-text search (populated by trigger below)
    _fts            TSVECTOR        GENERATED ALWAYS AS (to_tsvector('english', coalesce(title, ''))) STORED
);

CREATE INDEX IF NOT EXISTS idx_threads_board      ON forum_threads (board_id, deleted_at, is_pinned, last_post_at DESC);
CREATE INDEX IF NOT EXISTS idx_threads_user       ON forum_threads (user_id);
CREATE INDEX IF NOT EXISTS idx_threads_approved   ON forum_threads (is_approved, deleted_at);
CREATE INDEX IF NOT EXISTS idx_threads_last_post  ON forum_threads (last_post_at DESC);
CREATE INDEX IF NOT EXISTS ft_threads_title       ON forum_threads USING GIN (_fts);


-- =============================================================================
-- forum_posts
-- =============================================================================
CREATE TABLE IF NOT EXISTS forum_posts (
    id                      SERIAL          PRIMARY KEY,
    thread_id               INT             NOT NULL REFERENCES forum_threads (id) ON DELETE CASCADE ON UPDATE CASCADE,
    board_id                INT             NOT NULL REFERENCES forum_boards  (id) ON DELETE CASCADE ON UPDATE CASCADE,
    user_id                 INT             NOT NULL REFERENCES forum_user_profiles (id) ON DELETE CASCADE ON UPDATE CASCADE,
    content_raw             TEXT            NOT NULL,
    content_html            TEXT            DEFAULT NULL,
    is_approved             BOOLEAN         NOT NULL DEFAULT TRUE,
    is_spam_flagged         BOOLEAN         NOT NULL DEFAULT FALSE,
    spam_score              SMALLINT        NOT NULL DEFAULT 0 CHECK (spam_score >= 0),
    ip_address              VARCHAR(45)     DEFAULT NULL,
    edit_count              SMALLINT        NOT NULL DEFAULT 0 CHECK (edit_count >= 0),
    last_edited_by_user_id  INT             DEFAULT NULL,
    deleted_at              TIMESTAMPTZ     DEFAULT NULL,
    created_at              TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at              TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    -- tsvector for full-text search
    _fts                    TSVECTOR        GENERATED ALWAYS AS (to_tsvector('english', coalesce(content_raw, ''))) STORED
);

CREATE INDEX IF NOT EXISTS idx_posts_thread   ON forum_posts (thread_id, deleted_at, is_approved, created_at);
CREATE INDEX IF NOT EXISTS idx_posts_board    ON forum_posts (board_id, deleted_at, is_approved);
CREATE INDEX IF NOT EXISTS idx_posts_user     ON forum_posts (user_id, deleted_at);
CREATE INDEX IF NOT EXISTS idx_posts_pending  ON forum_posts (is_approved, deleted_at);
CREATE INDEX IF NOT EXISTS idx_posts_spam     ON forum_posts (is_spam_flagged, deleted_at);
CREATE INDEX IF NOT EXISTS ft_posts_content   ON forum_posts USING GIN (_fts);


-- =============================================================================
-- forum_post_history
-- =============================================================================
CREATE TABLE IF NOT EXISTS forum_post_history (
    id                  SERIAL      PRIMARY KEY,
    post_id             INT         NOT NULL REFERENCES forum_posts (id) ON DELETE CASCADE ON UPDATE CASCADE,
    edited_by_user_id   INT         DEFAULT NULL,
    content_raw         TEXT        NOT NULL,
    content_html        TEXT        DEFAULT NULL,
    edit_note           VARCHAR(200) DEFAULT NULL,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_post_history_post ON forum_post_history (post_id, created_at DESC);


-- =============================================================================
-- forum_tags
-- =============================================================================
CREATE TABLE IF NOT EXISTS forum_tags (
    id              SERIAL          PRIMARY KEY,
    name            VARCHAR(80)     NOT NULL,
    slug            VARCHAR(80)     NOT NULL,
    thread_count    INT             NOT NULL DEFAULT 0 CHECK (thread_count >= 0),
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_tags_slug        ON forum_tags (slug);
CREATE INDEX        IF NOT EXISTS idx_tags_thread_count ON forum_tags (thread_count DESC);


-- =============================================================================
-- forum_thread_tags  (pivot)
-- =============================================================================
CREATE TABLE IF NOT EXISTS forum_thread_tags (
    thread_id INT NOT NULL REFERENCES forum_threads (id) ON DELETE CASCADE ON UPDATE CASCADE,
    tag_id    INT NOT NULL REFERENCES forum_tags    (id) ON DELETE CASCADE ON UPDATE CASCADE,
    PRIMARY KEY (thread_id, tag_id)
);

CREATE INDEX IF NOT EXISTS idx_thread_tags_tag ON forum_thread_tags (tag_id);


-- =============================================================================
-- forum_reactions
-- =============================================================================
CREATE TABLE IF NOT EXISTS forum_reactions (
    id              SERIAL          PRIMARY KEY,
    post_id         INT             NOT NULL REFERENCES forum_posts         (id) ON DELETE CASCADE ON UPDATE CASCADE,
    user_id         INT             NOT NULL REFERENCES forum_user_profiles (id) ON DELETE CASCADE ON UPDATE CASCADE,
    reaction_type   VARCHAR(30)     NOT NULL,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_reactions_post_user ON forum_reactions (post_id, user_id);
CREATE INDEX        IF NOT EXISTS idx_reactions_post      ON forum_reactions (post_id, reaction_type);


-- =============================================================================
-- forum_attachments
-- =============================================================================
CREATE TABLE IF NOT EXISTS forum_attachments (
    id              SERIAL          PRIMARY KEY,
    post_id         INT             DEFAULT NULL REFERENCES forum_posts         (id) ON DELETE SET NULL ON UPDATE CASCADE,
    thread_id       INT             DEFAULT NULL REFERENCES forum_threads       (id) ON DELETE SET NULL ON UPDATE CASCADE,
    user_id         INT             NOT NULL REFERENCES forum_user_profiles (id) ON DELETE CASCADE ON UPDATE CASCADE,
    filename        VARCHAR(255)    NOT NULL,
    stored_path     VARCHAR(512)    NOT NULL,
    mime_type       VARCHAR(100)    DEFAULT NULL,
    file_size       INT             NOT NULL DEFAULT 0 CHECK (file_size >= 0),
    download_count  INT             NOT NULL DEFAULT 0 CHECK (download_count >= 0),
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_attachments_post   ON forum_attachments (post_id);
CREATE INDEX IF NOT EXISTS idx_attachments_thread ON forum_attachments (thread_id);
CREATE INDEX IF NOT EXISTS idx_attachments_user   ON forum_attachments (user_id);


-- =============================================================================
-- forum_subscriptions
-- =============================================================================
CREATE TABLE IF NOT EXISTS forum_subscriptions (
    id          SERIAL      PRIMARY KEY,
    user_id     INT         NOT NULL REFERENCES forum_user_profiles (id) ON DELETE CASCADE ON UPDATE CASCADE,
    thread_id   INT         DEFAULT NULL REFERENCES forum_threads   (id) ON DELETE CASCADE ON UPDATE CASCADE,
    board_id    INT         DEFAULT NULL REFERENCES forum_boards    (id) ON DELETE CASCADE ON UPDATE CASCADE,
    notify_on   VARCHAR(10) NOT NULL DEFAULT 'all' CHECK (notify_on IN ('all','mention','digest')),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_subscriptions_user_thread ON forum_subscriptions (user_id, thread_id) WHERE thread_id IS NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS uq_subscriptions_user_board  ON forum_subscriptions (user_id, board_id)  WHERE board_id  IS NOT NULL;
CREATE INDEX        IF NOT EXISTS idx_subscriptions_thread      ON forum_subscriptions (thread_id);
CREATE INDEX        IF NOT EXISTS idx_subscriptions_board       ON forum_subscriptions (board_id);


-- =============================================================================
-- forum_notifications
-- =============================================================================
CREATE TABLE IF NOT EXISTS forum_notifications (
    id              SERIAL          PRIMARY KEY,
    user_id         INT             NOT NULL REFERENCES forum_user_profiles (id) ON DELETE CASCADE ON UPDATE CASCADE,
    type            VARCHAR(40)     NOT NULL,
    actor_user_id   INT             DEFAULT NULL,
    thread_id       INT             DEFAULT NULL,
    post_id         INT             DEFAULT NULL,
    message         VARCHAR(500)    DEFAULT NULL,
    url             VARCHAR(512)    DEFAULT NULL,
    is_read         BOOLEAN         NOT NULL DEFAULT FALSE,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_notifications_user ON forum_notifications (user_id, is_read, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_notifications_type ON forum_notifications (type);


-- =============================================================================
-- forum_polls
-- =============================================================================
CREATE TABLE IF NOT EXISTS forum_polls (
    id          SERIAL          PRIMARY KEY,
    thread_id   INT             NOT NULL UNIQUE REFERENCES forum_threads (id) ON DELETE CASCADE ON UPDATE CASCADE,
    question    VARCHAR(300)    NOT NULL,
    is_multi    BOOLEAN         NOT NULL DEFAULT FALSE,
    max_choices SMALLINT        NOT NULL DEFAULT 1 CHECK (max_choices >= 1),
    closes_at   TIMESTAMPTZ     DEFAULT NULL,
    is_closed   BOOLEAN         NOT NULL DEFAULT FALSE,
    vote_count  INT             NOT NULL DEFAULT 0 CHECK (vote_count >= 0),
    created_at  TIMESTAMPTZ     NOT NULL DEFAULT NOW()
);


-- =============================================================================
-- forum_poll_options
-- =============================================================================
CREATE TABLE IF NOT EXISTS forum_poll_options (
    id          SERIAL          PRIMARY KEY,
    poll_id     INT             NOT NULL REFERENCES forum_polls (id) ON DELETE CASCADE ON UPDATE CASCADE,
    option_text VARCHAR(200)    NOT NULL,
    sort_order  SMALLINT        NOT NULL DEFAULT 0,
    vote_count  INT             NOT NULL DEFAULT 0 CHECK (vote_count >= 0)
);

CREATE INDEX IF NOT EXISTS idx_poll_options_poll ON forum_poll_options (poll_id, sort_order);


-- =============================================================================
-- forum_poll_votes
-- =============================================================================
CREATE TABLE IF NOT EXISTS forum_poll_votes (
    id          SERIAL      PRIMARY KEY,
    poll_id     INT         NOT NULL REFERENCES forum_polls        (id) ON DELETE CASCADE ON UPDATE CASCADE,
    option_id   INT         NOT NULL REFERENCES forum_poll_options (id) ON DELETE CASCADE ON UPDATE CASCADE,
    user_id     INT         NOT NULL REFERENCES forum_user_profiles(id) ON DELETE CASCADE ON UPDATE CASCADE,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_poll_votes_user_option ON forum_poll_votes (poll_id, option_id, user_id);
CREATE INDEX        IF NOT EXISTS idx_poll_votes_user        ON forum_poll_votes (user_id, poll_id);


-- =============================================================================
-- forum_badges
-- =============================================================================
CREATE TABLE IF NOT EXISTS forum_badges (
    id              SERIAL          PRIMARY KEY,
    name            VARCHAR(80)     NOT NULL,
    slug            VARCHAR(80)     NOT NULL,
    description     TEXT            DEFAULT NULL,
    icon_url        VARCHAR(512)    DEFAULT NULL,
    criteria_json   TEXT            DEFAULT NULL,
    is_active       BOOLEAN         NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_badges_slug ON forum_badges (slug);


-- =============================================================================
-- forum_user_badges  (pivot)
-- =============================================================================
CREATE TABLE IF NOT EXISTS forum_user_badges (
    id                  SERIAL      PRIMARY KEY,
    user_id             INT         NOT NULL REFERENCES forum_user_profiles (id) ON DELETE CASCADE ON UPDATE CASCADE,
    badge_id            INT         NOT NULL REFERENCES forum_badges        (id) ON DELETE CASCADE ON UPDATE CASCADE,
    granted_by_user_id  INT         DEFAULT NULL,
    context             VARCHAR(200) DEFAULT NULL,
    awarded_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_user_badges      ON forum_user_badges (user_id, badge_id);
CREATE INDEX        IF NOT EXISTS idx_user_badges_badge ON forum_user_badges (badge_id);


-- =============================================================================
-- forum_moderation_log
-- Immutable audit log — never UPDATE or DELETE rows.
-- =============================================================================
CREATE TABLE IF NOT EXISTS forum_moderation_log (
    id                  SERIAL          PRIMARY KEY,
    moderator_user_id   INT             DEFAULT NULL,
    action_type         VARCHAR(40)     NOT NULL,
    target_type         VARCHAR(20)     NOT NULL,
    target_id           INT             NOT NULL,
    reason              VARCHAR(500)    DEFAULT NULL,
    meta_json           TEXT            DEFAULT NULL,
    created_at          TIMESTAMPTZ     NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_modlog_moderator ON forum_moderation_log (moderator_user_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_modlog_target    ON forum_moderation_log (target_type, target_id);
CREATE INDEX IF NOT EXISTS idx_modlog_action    ON forum_moderation_log (action_type, created_at DESC);


-- =============================================================================
-- updated_at auto-maintenance function + triggers
-- PostgreSQL does not have ON UPDATE CURRENT_TIMESTAMP; a trigger is required.
-- =============================================================================
CREATE OR REPLACE FUNCTION forum_set_updated_at()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    NEW.updated_at := NOW();
    RETURN NEW;
END;
$$;

DO $$
DECLARE tbl TEXT;
BEGIN
  FOREACH tbl IN ARRAY ARRAY[
    'forum_categories','forum_boards','forum_user_profiles','forum_threads','forum_posts'
  ] LOOP
    EXECUTE format(
      'CREATE OR REPLACE TRIGGER trg_%I_updated_at
       BEFORE UPDATE ON %I
       FOR EACH ROW EXECUTE FUNCTION forum_set_updated_at()',
      tbl, tbl
    );
  END LOOP;
END;
$$;


COMMIT;
