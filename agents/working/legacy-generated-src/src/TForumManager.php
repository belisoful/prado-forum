<?php

/**
 * TForumManager class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum;

use Prado\TModule;
use Prado\Data\ActiveRecord\TActiveRecordManager;
use Prado\Data\TDataSourceConfig;
use Prado\Data\TDbConnection;
use Belisoful\Forum\Exceptions\TForumConfigurationException;
use Belisoful\Forum\Exceptions\TForumInvalidOperationException;
use Belisoful\Forum\ActiveRecord\TForumBoardRecord;
use Belisoful\Forum\ActiveRecord\TForumUserProfileRecord;
use Belisoful\Forum\Behaviors\TForumUserBehavior;
use Belisoful\Forum\Services\TForumBBCodeParser;
use Belisoful\Forum\Services\TForumSearchService;
use Belisoful\Forum\Services\TForumNotificationService;
use Belisoful\Forum\Services\TForumSpamService;
use Belisoful\Forum\Services\TForumRSSService;

/**
 * TForumManager is the core module for the PRADO Forum extension.
 *
 * It is the bootstrap class registered via composer.json `extra.bootstrap`.
 * TForumManager acts as the single point of configuration for the entire
 * forum system, providing database connectivity (via a ConnectionID like
 * other PRADO modules), lazy-loaded services, intelligent helpers, and
 * the Active Record gateway setup.
 *
 * Configure it in your application.xml:
 * ```xml
 * <module id="forum" class="Belisoful\Forum\TForumManager"
 *     ConnectionID="db"
 *     PostsPerPage="20"
 *     ThreadsPerPage="30"
 *     EnableBBCode="true"
 *     EnablePolls="true"
 *     EnableReactions="true"
 *     EnableTags="true"
 *     EnableNotifications="true"
 *     EnableRSS="true"
 *     ModerationMode="post"
 *     SiteName="My Forum"
 *     SiteEmail="forum@example.com"
 * />
 * ```
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class TForumManager extends TModule
{
    // ===================================================================
    // Database
    // ===================================================================

    /** @var string the application module ID of the DB connection/data source */
    private $_connectionId = 'db';

    /** @var TDbConnection|null resolved DB connection instance */
    private $_connection = null;

    // ===================================================================
    // General Configuration
    // ===================================================================

    /** @var string prefix applied to every forum table name */
    private $_tablePrefix = 'forum_';

    /** @var int posts displayed per page in a thread */
    private $_postsPerPage = 20;

    /** @var int threads displayed per page in a board */
    private $_threadsPerPage = 30;

    /** @var string name displayed in titles, emails, and RSS feeds */
    private $_siteName = 'PRADO Forum';

    /** @var string from-address used for notification emails */
    private $_siteEmail = '';

    /** @var string base URL for forum pages (auto-detected when empty) */
    private $_baseUrl = '';

    // ===================================================================
    // Posting & Content
    // ===================================================================

    /** @var bool whether BBCode parsing is enabled */
    private $_enableBBCode = true;

    /** @var bool whether Markdown parsing is enabled (alternative to BBCode) */
    private $_enableMarkdown = false;

    /** @var int minimum number of characters required in a post body */
    private $_minPostLength = 10;

    /** @var int maximum number of characters allowed in a post body */
    private $_maxPostLength = 65535;

    /** @var int maximum number of characters allowed in a thread title */
    private $_maxTitleLength = 255;

    /** @var bool whether guest (unauthenticated) posting is permitted */
    private $_guestPosting = false;

    /** @var bool whether new users must verify their email before posting */
    private $_requireEmailVerification = false;

    /** @var bool whether users may include a signature on posts */
    private $_enableSignatures = true;

    /** @var int maximum characters allowed in a user signature */
    private $_maxSignatureLength = 500;

    /** @var bool whether @username mentions are processed */
    private $_enableMentions = true;

    // ===================================================================
    // Moderation
    // ===================================================================

    /**
     * @var string moderation mode.
     *   'post' — posts are published immediately and reviewed after.
     *   'pre'  — posts are held in queue until a moderator approves them.
     */
    private $_moderationMode = 'post';

    // ===================================================================
    // Rate Limiting
    // ===================================================================

    /** @var int minimum seconds a user must wait between submitting posts */
    private $_postRateLimit = 60;

    /** @var int minimum seconds a user must wait between creating threads */
    private $_threadRateLimit = 300;

    // ===================================================================
    // Spam Protection
    // ===================================================================

    /** @var bool whether the built-in spam detection heuristics are enabled */
    private $_enableSpamProtection = true;

    /**
     * @var int spam score threshold.
     *   Posts scoring at or above this value are flagged for review.
     */
    private $_spamThreshold = 5;

    // ===================================================================
    // Attachments
    // ===================================================================

    /** @var bool whether file attachments on posts are permitted */
    private $_enableAttachments = true;

    /** @var int maximum file size in bytes for a single attachment (default 10 MB) */
    private $_maxAttachmentSize = 10485760;

    /** @var string comma-separated list of allowed file extensions */
    private $_allowedAttachmentTypes = 'jpg,jpeg,png,gif,webp,pdf,zip,txt,mp4';

    /** @var string server path (relative or absolute) where attachments are stored */
    private $_uploadPath = 'uploads/forum';

    // ===================================================================
    // Advanced Forum Features
    // ===================================================================

    /** @var bool whether the inline poll system is enabled */
    private $_enablePolls = true;

    /** @var bool whether emoji/reaction buttons on posts are enabled */
    private $_enableReactions = true;

    /** @var bool whether thread tagging is enabled */
    private $_enableTags = true;

    /** @var int maximum number of tags that can be attached to a single thread */
    private $_maxTagsPerThread = 5;

    /** @var bool whether email and on-site notifications are delivered */
    private $_enableNotifications = true;

    /** @var bool whether per-board and per-thread RSS feeds are generated */
    private $_enableRSS = true;

    /** @var bool whether the forum search feature is enabled */
    private $_enableSearch = true;

    /**
     * @var string search back-end type.
     *   'native'   — SQL LIKE queries (works everywhere, slower on large data sets).
     *   'fulltext' — MySQL FULLTEXT / PostgreSQL tsvector (requires schema support).
     */
    private $_searchType = 'native';

    // ===================================================================
    // Gamification
    // ===================================================================

    /** @var bool whether the reputation/karma point system is active */
    private $_reputationEnabled = true;

    /** @var bool whether the badge/achievement system is active */
    private $_badgesEnabled = true;

    /** @var string URL to a default avatar image used when a user has none */
    private $_defaultAvatarUrl = '';

    // ===================================================================
    // Application Integration
    // ===================================================================

    /**
     * @var string module ID of the application cache component.
     *   When empty the application's default cache is used.
     */
    private $_cacheId = '';

    // ===================================================================
    // Lazy-loaded Services
    // ===================================================================

    /** @var TForumBBCodeParser|null */
    private $_bbcodeParser = null;

    /** @var TForumSearchService|null */
    private $_searchService = null;

    /** @var TForumNotificationService|null */
    private $_notificationService = null;

    /** @var TForumSpamService|null */
    private $_spamService = null;

    /** @var TForumRSSService|null */
    private $_rssService = null;

    // ===================================================================
    // TModule lifecycle
    // ===================================================================

    /**
     * Initialise the forum manager.
     *
     * Called by PRADO after the module's properties have been set from the
     * application configuration.  Sets up the Active Record gateway and
     * attaches the forum user behaviour to the application user manager.
     *
     * @param \Prado\Xml\TXmlElement|null $config module XML configuration node
     */
    public function init($config)
    {
        parent::init($config);

        // Wire the Active Record gateway to our connection.
        $this->setupActiveRecord();

        // Decorate the application's user manager with forum-aware methods.
        $this->attachUserBehavior();
    }

    /**
     * Register our DB connection with the PRADO Active Record gateway.
     * Only sets the connection when the gateway does not already have one,
     * so applications that configure TActiveRecordConfig separately are not
     * affected.
     */
    protected function setupActiveRecord(): void
    {
        $manager = TActiveRecordManager::getInstance();
        if ($manager->getDbConnection() === null) {
            $manager->setDbConnection($this->getDbConnection());
        }
    }

    /**
     * Attach {@see TForumUserBehavior} to the application user manager if one
     * is present and the behaviour has not already been attached.
     */
    protected function attachUserBehavior(): void
    {
        $userManager = $this->getApplication()->getModule('users');
        if ($userManager !== null && !$userManager->hasBehavior('forumUser')) {
            $behavior = new TForumUserBehavior();
            $behavior->setForumManager($this);
            $userManager->attachBehavior('forumUser', $behavior);
        }
    }

    // ===================================================================
    // Database Connection
    // ===================================================================

    /**
     * @return string the module ID of the DB connection or TDataSourceConfig
     */
    public function getConnectionID(): string
    {
        return $this->_connectionId;
    }

    /**
     * @param string $value module ID of the DB connection or TDataSourceConfig
     */
    public function setConnectionID(string $value): void
    {
        $this->_connectionId = $value;
    }

    /**
     * Return the active database connection.
     *
     * Resolved once from the ConnectionID module and cached.  Mirrors the
     * pattern used by {@see \Prado\Data\ActiveRecord\TActiveRecordConfig}.
     *
     * @return TDbConnection the live database connection
     * @throws TConfigurationException when ConnectionID is empty or invalid
     */
    public function getDbConnection(): TDbConnection
    {
        if ($this->_connection === null) {
            $connId = $this->getConnectionID();
            if ($connId === '') {
                throw new TForumConfigurationException(
                    'forum_no_connection_id',
                    get_class($this)
                );
            }

            $module = $this->getApplication()->getModule($connId);

            if ($module instanceof TDataSourceConfig) {
                $this->_connection = $module->getDbConnection();
            } elseif ($module instanceof TDbConnection) {
                $this->_connection = $module;
            } else {
                throw new TForumConfigurationException(
                    'forum_invalid_connection_id',
                    $connId
                );
            }

            $this->_connection->setActive(true);
        }

        return $this->_connection;
    }

    // ===================================================================
    // Configuration Properties
    // ===================================================================

    public function getTablePrefix(): string { return $this->_tablePrefix; }
    public function setTablePrefix(string $v): void { $this->_tablePrefix = $v; }

    public function getPostsPerPage(): int { return (int) $this->_postsPerPage; }
    public function setPostsPerPage(int $v): void { $this->_postsPerPage = max(1, $v); }

    public function getThreadsPerPage(): int { return (int) $this->_threadsPerPage; }
    public function setThreadsPerPage(int $v): void { $this->_threadsPerPage = max(1, $v); }

    public function getSiteName(): string { return $this->_siteName; }
    public function setSiteName(string $v): void { $this->_siteName = $v; }

    public function getSiteEmail(): string { return $this->_siteEmail; }
    public function setSiteEmail(string $v): void { $this->_siteEmail = $v; }

    public function getBaseUrl(): string { return $this->_baseUrl; }
    public function setBaseUrl(string $v): void { $this->_baseUrl = rtrim($v, '/'); }

    public function getEnableBBCode(): bool { return (bool) $this->_enableBBCode; }
    public function setEnableBBCode(bool $v): void { $this->_enableBBCode = $v; }

    public function getEnableMarkdown(): bool { return (bool) $this->_enableMarkdown; }
    public function setEnableMarkdown(bool $v): void { $this->_enableMarkdown = $v; }

    public function getMinPostLength(): int { return (int) $this->_minPostLength; }
    public function setMinPostLength(int $v): void { $this->_minPostLength = max(0, $v); }

    public function getMaxPostLength(): int { return (int) $this->_maxPostLength; }
    public function setMaxPostLength(int $v): void { $this->_maxPostLength = max(1, $v); }

    public function getMaxTitleLength(): int { return (int) $this->_maxTitleLength; }
    public function setMaxTitleLength(int $v): void { $this->_maxTitleLength = max(10, $v); }

    public function getGuestPosting(): bool { return (bool) $this->_guestPosting; }
    public function setGuestPosting(bool $v): void { $this->_guestPosting = $v; }

    public function getRequireEmailVerification(): bool { return (bool) $this->_requireEmailVerification; }
    public function setRequireEmailVerification(bool $v): void { $this->_requireEmailVerification = $v; }

    public function getEnableSignatures(): bool { return (bool) $this->_enableSignatures; }
    public function setEnableSignatures(bool $v): void { $this->_enableSignatures = $v; }

    public function getMaxSignatureLength(): int { return (int) $this->_maxSignatureLength; }
    public function setMaxSignatureLength(int $v): void { $this->_maxSignatureLength = max(0, $v); }

    public function getEnableMentions(): bool { return (bool) $this->_enableMentions; }
    public function setEnableMentions(bool $v): void { $this->_enableMentions = $v; }

    public function getModerationMode(): string { return $this->_moderationMode; }

    /**
     * @param string $v 'pre' or 'post'
     * @throws TConfigurationException for invalid values
     */
    public function setModerationMode(string $v): void
    {
        if (!in_array($v, ['pre', 'post'], true)) {
            throw new TForumConfigurationException('forum_invalid_moderation_mode', $v);
        }
        $this->_moderationMode = $v;
    }

    public function getPostRateLimit(): int { return (int) $this->_postRateLimit; }
    public function setPostRateLimit(int $v): void { $this->_postRateLimit = max(0, $v); }

    public function getThreadRateLimit(): int { return (int) $this->_threadRateLimit; }
    public function setThreadRateLimit(int $v): void { $this->_threadRateLimit = max(0, $v); }

    public function getEnableSpamProtection(): bool { return (bool) $this->_enableSpamProtection; }
    public function setEnableSpamProtection(bool $v): void { $this->_enableSpamProtection = $v; }

    public function getSpamThreshold(): int { return (int) $this->_spamThreshold; }
    public function setSpamThreshold(int $v): void { $this->_spamThreshold = max(1, $v); }

    public function getEnableAttachments(): bool { return (bool) $this->_enableAttachments; }
    public function setEnableAttachments(bool $v): void { $this->_enableAttachments = $v; }

    public function getMaxAttachmentSize(): int { return (int) $this->_maxAttachmentSize; }
    public function setMaxAttachmentSize(int $v): void { $this->_maxAttachmentSize = max(1, $v); }

    public function getAllowedAttachmentTypes(): string { return $this->_allowedAttachmentTypes; }
    public function setAllowedAttachmentTypes(string $v): void { $this->_allowedAttachmentTypes = $v; }

    public function getUploadPath(): string { return $this->_uploadPath; }
    public function setUploadPath(string $v): void { $this->_uploadPath = rtrim($v, '/'); }

    public function getEnablePolls(): bool { return (bool) $this->_enablePolls; }
    public function setEnablePolls(bool $v): void { $this->_enablePolls = $v; }

    public function getEnableReactions(): bool { return (bool) $this->_enableReactions; }
    public function setEnableReactions(bool $v): void { $this->_enableReactions = $v; }

    public function getEnableTags(): bool { return (bool) $this->_enableTags; }
    public function setEnableTags(bool $v): void { $this->_enableTags = $v; }

    public function getMaxTagsPerThread(): int { return (int) $this->_maxTagsPerThread; }
    public function setMaxTagsPerThread(int $v): void { $this->_maxTagsPerThread = max(1, $v); }

    public function getEnableNotifications(): bool { return (bool) $this->_enableNotifications; }
    public function setEnableNotifications(bool $v): void { $this->_enableNotifications = $v; }

    public function getEnableRSS(): bool { return (bool) $this->_enableRSS; }
    public function setEnableRSS(bool $v): void { $this->_enableRSS = $v; }

    public function getEnableSearch(): bool { return (bool) $this->_enableSearch; }
    public function setEnableSearch(bool $v): void { $this->_enableSearch = $v; }

    public function getSearchType(): string { return $this->_searchType; }
    public function setSearchType(string $v): void { $this->_searchType = $v; }

    public function getReputationEnabled(): bool { return (bool) $this->_reputationEnabled; }
    public function setReputationEnabled(bool $v): void { $this->_reputationEnabled = $v; }

    public function getBadgesEnabled(): bool { return (bool) $this->_badgesEnabled; }
    public function setBadgesEnabled(bool $v): void { $this->_badgesEnabled = $v; }

    public function getDefaultAvatarUrl(): string { return $this->_defaultAvatarUrl; }
    public function setDefaultAvatarUrl(string $v): void { $this->_defaultAvatarUrl = $v; }

    public function getCacheId(): string { return $this->_cacheId; }
    public function setCacheId(string $v): void { $this->_cacheId = $v; }

    // ===================================================================
    // Lazy-loaded Services
    // ===================================================================

    /**
     * @return TForumBBCodeParser the shared BBCode parser instance
     */
    public function getBBCodeParser(): TForumBBCodeParser
    {
        if ($this->_bbcodeParser === null) {
            $this->_bbcodeParser = new TForumBBCodeParser($this);
        }
        return $this->_bbcodeParser;
    }

    /**
     * @return TForumSearchService the forum search service
     */
    public function getSearchService(): TForumSearchService
    {
        if ($this->_searchService === null) {
            $this->_searchService = new TForumSearchService($this);
        }
        return $this->_searchService;
    }

    /**
     * @return TForumNotificationService the notification delivery service
     */
    public function getNotificationService(): TForumNotificationService
    {
        if ($this->_notificationService === null) {
            $this->_notificationService = new TForumNotificationService($this);
        }
        return $this->_notificationService;
    }

    /**
     * @return TForumSpamService the spam detection / scoring service
     */
    public function getSpamService(): TForumSpamService
    {
        if ($this->_spamService === null) {
            $this->_spamService = new TForumSpamService($this);
        }
        return $this->_spamService;
    }

    /**
     * @return TForumRSSService the RSS/Atom feed generation service
     */
    public function getRSSService(): TForumRSSService
    {
        if ($this->_rssService === null) {
            $this->_rssService = new TForumRSSService($this);
        }
        return $this->_rssService;
    }

    // ===================================================================
    // Application Cache Helper
    // ===================================================================

    /**
     * Return the cache component configured for the forum.
     * Falls back to the application's default cache when CacheId is not set.
     *
     * @return \Prado\Caching\ICache|null
     */
    public function getCache()
    {
        if ($this->_cacheId !== '') {
            return $this->getApplication()->getModule($this->_cacheId);
        }
        return $this->getApplication()->getCache();
    }

    // ===================================================================
    // Utility / Intelligent Helpers
    // ===================================================================

    /**
     * Return the fully-qualified table name for a logical table.
     *
     * @param string $table logical name without prefix, e.g. 'threads'
     * @return string full table name, e.g. 'forum_threads'
     */
    public function getTable(string $table): string
    {
        return $this->_tablePrefix . $table;
    }

    /**
     * Parse raw post content through the enabled markup pipeline.
     *
     * When BBCode is enabled the BBCode parser runs first.  Markdown is an
     * alternative to BBCode; both cannot be active simultaneously.
     *
     * @param string $content raw user-supplied content
     * @return string safe HTML ready for rendering
     */
    public function parseContent(string $content): string
    {
        if ($this->getEnableBBCode()) {
            $content = $this->getBBCodeParser()->parse($content);
        }
        // Markdown support hook — sub-classes may override or a service may be injected.
        return $content;
    }

    /**
     * Scan content for @username mentions, linkify them, and queue notifications.
     *
     * @param string $content   the rendered HTML content of the new post
     * @param int    $postId    the newly saved post ID (used in notification links)
     * @return string content with mention links inserted
     */
    public function processMentions(string $content, int $postId): string
    {
        if (!$this->getEnableMentions()) {
            return $content;
        }

        return preg_replace_callback(
            '/@([a-zA-Z0-9_\-]{2,50})/',
            function (array $matches) use ($postId): string {
                $username = $matches[1];

                if ($this->getEnableNotifications()) {
                    $this->getNotificationService()->queueMentionNotification($username, $postId);
                }

                $url = htmlspecialchars(
                    $this->getApplication()->createUrl('forum/UserProfile', ['username' => $username]),
                    ENT_QUOTES
                );
                $safe = htmlspecialchars($username, ENT_QUOTES);

                return '<a href="' . $url . '" class="forum-mention" data-username="' . $safe . '">@' . $safe . '</a>';
            },
            $content
        );
    }

    /**
     * Determine whether a user is currently subject to a posting rate limit.
     *
     * Requires a cache component to be configured.  When no cache is available
     * this method always returns false (rate limiting disabled).
     *
     * @param string $type   'post' | 'thread'
     * @param string $userId opaque user identifier
     * @return bool true when the user must wait before posting again
     */
    public function isRateLimited(string $type, string $userId): bool
    {
        $cache = $this->getCache();
        if ($cache === null) {
            return false;
        }

        $key = $this->buildRateLimitKey($type, $userId);
        return $cache->get($key) !== false;
    }

    /**
     * Record that a user has just posted, starting the rate-limit window.
     *
     * @param string $type   'post' | 'thread'
     * @param string $userId opaque user identifier
     */
    public function setRateLimit(string $type, string $userId): void
    {
        $cache = $this->getCache();
        if ($cache === null) {
            return;
        }

        $limit = $type === 'thread' ? $this->getThreadRateLimit() : $this->getPostRateLimit();
        if ($limit <= 0) {
            return;
        }

        $key = $this->buildRateLimitKey($type, $userId);
        $cache->set($key, 1, $limit);
    }

    /**
     * Build a namespaced cache key for a rate-limit record.
     *
     * @param string $type   'post' | 'thread'
     * @param string $userId opaque user identifier
     * @return string cache key
     */
    protected function buildRateLimitKey(string $type, string $userId): string
    {
        return 'forum_rate_' . $type . '_' . md5($userId);
    }

    /**
     * Award or deduct reputation points for a user profile.
     *
     * Does nothing when {@see $reputationEnabled} is false.
     *
     * @param string $username forum username
     * @param int    $points   positive to award, negative to deduct
     * @param string $reason   short human-readable reason string (for audit log)
     */
    public function awardReputation(string $username, int $points, string $reason = ''): void
    {
        if (!$this->getReputationEnabled() || $points === 0) {
            return;
        }

        $profile = TForumUserProfileRecord::finder()->findByAttributes(['username' => $username]);
        if ($profile !== null) {
            $profile->reputation_points = (int) $profile->reputation_points + $points;
            $profile->save();
        }
    }

    /**
     * Check whether a given file extension is allowed for attachments.
     *
     * @param string $ext file extension, with or without leading dot
     * @return bool
     */
    public function isAllowedAttachmentType(string $ext): bool
    {
        $ext = ltrim(strtolower($ext), '.');
        $allowed = array_map('trim', explode(',', strtolower($this->getAllowedAttachmentTypes())));
        return in_array($ext, $allowed, true);
    }

    /**
     * Return the path to the absolute upload directory on the server.
     *
     * @return string absolute filesystem path
     */
    public function getUploadDirectory(): string
    {
        $path = $this->getUploadPath();
        if ($path === '' || $path[0] !== '/') {
            $path = $this->getApplication()->getBasePath() . DIRECTORY_SEPARATOR . $path;
        }
        return $path;
    }

    /**
     * Ensure the upload directory exists and is writable.
     *
     * @throws TInvalidOperationException if the directory cannot be created or written to
     */
    public function ensureUploadDirectory(): void
    {
        $dir = $this->getUploadDirectory();
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new TForumInvalidOperationException('forum_upload_dir_create_failed', $dir);
            }
        } elseif (!is_writable($dir)) {
            throw new TForumInvalidOperationException('forum_upload_dir_not_writable', $dir);
        }
    }

    /**
     * Determine whether a post is in pre-moderation mode.
     *
     * @return bool true when posts must be approved before appearing publicly
     */
    public function isPreModeration(): bool
    {
        return $this->getModerationMode() === 'pre';
    }

    /**
     * Build a forum URL for a given page service path and parameters.
     *
     * @param string $pageServicePath e.g. 'forum/ThreadView'
     * @param array  $params          query parameters
     * @return string the application URL
     */
    public function createUrl(string $pageServicePath, array $params = []): string
    {
        return $this->getApplication()->createUrl($pageServicePath, $params);
    }
}
