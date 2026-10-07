<?php

/**
 * BEForumModule class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum;

use Belisoful\Forum\Behaviors\BEForumUserBehavior;
use Belisoful\Forum\Content\BEForumContentRenderer;
use Belisoful\Forum\Data\BEForumMember;
use Belisoful\Forum\Data\BEForumRecord;
use Belisoful\Forum\Data\BEForumSchema;
use Belisoful\Forum\Exceptions\BEForumConfigurationException;
use Belisoful\Forum\Exceptions\BEForumForbiddenException;
use Belisoful\Forum\Managers\BEForumAttachmentManager;
use Belisoful\Forum\Managers\BEForumBoardManager;
use Belisoful\Forum\Managers\BEForumBookmarkManager;
use Belisoful\Forum\Managers\BEForumManager;
use Belisoful\Forum\Managers\BEForumMemberManager;
use Belisoful\Forum\Managers\BEForumModerationManager;
use Belisoful\Forum\Managers\BEForumNotificationManager;
use Belisoful\Forum\Managers\BEForumPollManager;
use Belisoful\Forum\Managers\BEForumPostManager;
use Belisoful\Forum\Managers\BEForumReactionManager;
use Belisoful\Forum\Managers\BEForumReadTracker;
use Belisoful\Forum\Managers\BEForumSearchManager;
use Belisoful\Forum\Managers\BEForumStatisticsManager;
use Belisoful\Forum\Managers\BEForumSubscriptionManager;
use Belisoful\Forum\Managers\BEForumTagManager;
use Belisoful\Forum\Managers\BEForumThreadManager;
use Belisoful\Forum\Security\BEForumGuestUser;
use Belisoful\Forum\Security\BEForumPermissions;
use Belisoful\Forum\Security\BEForumRoleRule;
use Belisoful\Forum\Shell\BEForumShellAction;
use Belisoful\Forum\Web\BEForumUrlBuilder;
use Prado\Data\TDbConnection;
use Prado\Exceptions\TInvalidDataValueException;
use Prado\Exceptions\TInvalidOperationException;
use Prado\Prado;
use Prado\Security\IUser;
use Prado\Security\Permissions\IPermissions;
use Prado\Security\Permissions\TPermissionEvent;
use Prado\Security\Permissions\TPermissionsManager;
use Prado\Security\TAuthorizationRule;
use Prado\Security\TAuthorizationRuleCollection;
use Prado\Shell\TShellApplication;
use Prado\TComponent;
use Prado\TPropertyValue;
use Prado\Util\Cron\TCronTaskInfo;
use Prado\Util\TDbPluginModule;
use Prado\Util\TLogger;

/**
 * BEForumModule class.
 *
 * BEForumModule is the bootstrap module of the PRADO Forum extension.  It is
 * declared by `composer.json` (`extra.bootstrap`) so a host application enables
 * the forum by adding one module to its configuration:
 * ```xml
 * <modules>
 *   <module id="forum" class="Belisoful\Forum\BEForumModule" ConnectionID="db" Title="Community" />
 * </modules>
 * <parameters>
 *   <parameter id="PluginContentId" value="Main" />
 * </parameters>
 * ```
 * Without `ConnectionID` a SQLite database is created in the runtime path.
 * With `AutoInstall` (the default) the tables are created and upgraded on
 * first use; `prado-cli forum/install` does the same from the shell.
 *
 * The module is the single point of configuration and the facade of the
 * domain: it owns the database connection, the {@see getSchema schema}, the
 * {@see getUrls URL builder}, the {@see getRenderer content renderer}, one
 * manager per area ({@see getThreads}, {@see getPosts}, {@see getMembers},
 * ...) and the {@see authorize authorization} that binds the forum permissions
 * to PRADO's {@see \Prado\Security\Permissions\TPermissionsManager}.  It also
 * attaches {@see \Belisoful\Forum\Behaviors\BEForumUserBehavior} to every
 * application user, registers the `forum` shell action and advertises its cron
 * maintenance tasks.
 *
 * Every domain change raises an `on*` event with a {@see BEForumEventParameter}
 * and every decision point calls a `dy*` dynamic event so behaviors attached to
 * the module can extend or replace forum behavior.
 *
 * @method bool dyViewForum(bool $handled, array $extra)
 * @method bool dyCreateThread(bool $handled, array $extra)
 * @method bool dyEditThread(bool $handled, array $extra)
 * @method bool dyDeleteThread(bool $handled, array $extra)
 * @method bool dyCreatePost(bool $handled, array $extra)
 * @method bool dyEditPost(bool $handled, array $extra)
 * @method bool dyDeletePost(bool $handled, array $extra)
 * @method bool dyReact(bool $handled, array $extra)
 * @method bool dyCreatePoll(bool $handled, array $extra)
 * @method bool dyVotePoll(bool $handled, array $extra)
 * @method bool dyAttachFile(bool $handled, array $extra)
 * @method bool dyReportPost(bool $handled, array $extra)
 * @method bool dySubscribe(bool $handled, array $extra)
 * @method bool dyBookmark(bool $handled, array $extra)
 * @method bool dyEditProfile(bool $handled, array $extra)
 * @method bool dyModerate(bool $handled, array $extra)
 * @method bool dyAdministrate(bool $handled, array $extra)
 * @method bool dyRegisterShellAction(bool $handled)
 * @method bool dyAuthorize(bool $allowed, string $permission, null|array $extra, null|IUser $user)
 * @method null|BEForumManager dyCreateManager(null|BEForumManager $manager, string $class)
 * @method null|BEForumContentRenderer dyCreateRenderer(null|BEForumContentRenderer $renderer)
 * @method null|BEForumUrlBuilder dyCreateUrlBuilder(null|BEForumUrlBuilder $urls)
 * @method TCronTaskInfo dyCronTaskInfos(TCronTaskInfo $info)
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumModule extends TDbPluginModule implements IPermissions
{
	/** The extension version */
	public const VERSION = '0.1.0';

	/** The name of the class behavior attached to IUser */
	public const USER_BEHAVIOR = 'beforum';

	/** The application global state key remembering the verified schema version */
	public const STATE_SCHEMA_VERSION = 'beforum:schema:version';

	/** The default SQLite database file name */
	public const SQLITE_DATABASE = 'beforum.db';

	/** @var string the table prefix */
	private string $_tablePrefix = 'forum_';
	/** @var string the forum title */
	private string $_title = 'Forum';
	/** @var string the page path prefix of the forum pages */
	private string $_pagePathPrefix = 'Forum';
	/** @var int threads per board page */
	private int $_threadsPerPage = 25;
	/** @var int posts per thread page */
	private int $_postsPerPage = 20;
	/** @var int items per page of lists such as search results, notifications and members */
	private int $_itemsPerPage = 20;
	/** @var string the default content format */
	private string $_contentFormat = BEForumContentRenderer::FORMAT_MARKDOWN;
	/** @var int seconds an owner may edit a post, 0 for unlimited */
	private int $_editWindow = 0;
	/** @var int minimum seconds between two posts of a member, 0 disables flood control */
	private int $_floodInterval = 30;
	/** @var int maximum title length */
	private int $_maxTitleLength = 255;
	/** @var int minimum post length in characters */
	private int $_minPostLength = 1;
	/** @var int maximum post length in characters */
	private int $_maxPostLength = 65535;
	/** @var int maximum tags per thread */
	private int $_maxTagsPerThread = 5;
	/** @var bool whether polls are enabled */
	private bool $_enablePolls = true;
	/** @var bool whether attachments are enabled */
	private bool $_enableAttachments = true;
	/** @var bool whether reactions are enabled */
	private bool $_enableReactions = true;
	/** @var bool whether tags are enabled */
	private bool $_enableTags = true;
	/** @var bool whether subscriptions and notifications are enabled */
	private bool $_enableSubscriptions = true;
	/** @var bool whether signatures are shown */
	private bool $_enableSignatures = true;
	/** @var bool whether bookmarks are enabled */
	private bool $_enableBookmarks = true;
	/** @var bool whether badges are enabled */
	private bool $_enableBadges = true;
	/** @var bool whether new threads and posts of non-moderators need approval */
	private bool $_requireApproval = false;
	/** @var bool whether member profiles are created automatically for authenticated users */
	private bool $_autoCreateMembers = true;
	/** @var bool whether the schema is installed and upgraded automatically */
	private bool $_autoInstall = true;
	/** @var string[] the reaction types */
	private array $_reactionTypes = ['like', 'love', 'laugh', 'wow', 'sad'];
	/** @var null|string the attachment storage path */
	private ?string $_attachmentPath = null;
	/** @var int the maximum attachment size in bytes */
	private int $_attachmentMaxSize = 2097152;
	/** @var string[] the allowed attachment extensions */
	private array $_attachmentTypes = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'pdf', 'txt', 'zip'];
	/** @var int days to keep read notifications */
	private int $_notificationRetentionDays = 90;
	/** @var int minimum search query length */
	private int $_searchMinLength = 3;
	/** @var string the CSS class prefix of the controls */
	private string $_cssClassPrefix = 'beforum';
	/** @var bool whether the bundled stylesheet is published */
	private bool $_enableDefaultStyles = true;
	/** @var string the PHP date format used for absolute times */
	private string $_dateFormat = 'M j, Y H:i';
	/** @var null|string the display timezone, null for UTC or the member timezone */
	private ?string $_timezone = null;
	/** @var string the name shown for guests */
	private string $_guestName = 'Guest';
	/** @var string[] usernames holding every forum permission */
	private array $_adminUsers = [];
	/** @var string[] roles holding every forum permission */
	private array $_adminRoles = [];
	/** @var string[] usernames holding the moderation permission everywhere */
	private array $_moderatorUsers = [];
	/** @var string[] roles holding the moderation permission everywhere */
	private array $_moderatorRoles = [];
	/** @var null|TDbConnection an explicitly injected connection */
	private ?TDbConnection $_injectedConnection = null;
	/** @var string the content renderer class */
	private string $_rendererClass = BEForumContentRenderer::class;
	/** @var string the URL builder class */
	private string $_urlBuilderClass = BEForumUrlBuilder::class;
	/** @var string the shell action class */
	private string $_shellActionClass = BEForumShellAction::class;
	/** @var bool whether init has completed */
	private bool $_initialized = false;
	/** @var bool whether the schema has been verified in this request */
	private bool $_schemaVerified = false;
	/** @var array<string, BEForumManager> the managers keyed by class */
	private array $_managers = [];
	/** @var null|BEForumContentRenderer the renderer */
	private ?BEForumContentRenderer $_renderer = null;
	/** @var null|BEForumUrlBuilder the URL builder */
	private ?BEForumUrlBuilder $_urls = null;
	/** @var null|BEForumSchema the schema */
	private ?BEForumSchema $_schema = null;
	/** @var array<string, TAuthorizationRuleCollection> preset rules keyed by permission */
	private array $_presetRules = [];

	/**
	 * Initializes the module: validates configuration, wires the records to the
	 * module connection, attaches the user behavior and registers the shell
	 * action hook.
	 * @param null|array|\Prado\Xml\TXmlElement $config the module configuration
	 * @throws TInvalidOperationException when initialized twice
	 */
	public function init($config)
	{
		if ($this->_initialized) {
			throw new TInvalidOperationException('forum_module_init_once');
		}
		$this->_initialized = true;
		BEForumRecord::setTablePrefix($this->_tablePrefix);
		$reference = \WeakReference::create($this);
		BEForumRecord::setForumDbConnection(fn () => $reference->get()?->getDbConnection());

		TComponent::attachClassBehavior(self::USER_BEHAVIOR, ['class' => BEForumUserBehavior::class, 'module' => $reference], IUser::class, -5);

		$app = $this->getApplication();
		$app->attachEventHandler('onAuthenticationComplete', [$this, 'registerShellAction']);
		$app->attachEventHandler('onEndRequest', [$this, 'flushRequestState']);

		parent::init($config);
	}

	/**
	 * Detaches the user class behavior.
	 */
	public function __destruct()
	{
		if ($this->_initialized) {
			try {
				TComponent::detachClassBehavior(self::USER_BEHAVIOR, IUser::class);
			} catch (\Throwable $e) {
				// the behavior may already be gone during shutdown
			}
		}
		parent::__destruct();
	}

	/**
	 * @return bool whether {@see init} has completed
	 */
	public function getIsInitialized(): bool
	{
		return $this->_initialized;
	}

	/**
	 * Throws when a property that must be set before initialization is changed later.
	 * @param string $property the property name
	 * @throws TInvalidOperationException when the module is initialized
	 */
	protected function ensureNotInitialized(string $property): void
	{
		if ($this->_initialized) {
			throw new TInvalidOperationException('forum_property_unchangeable', $property);
		}
	}

	/**
	 * @return null|string the SQLite database file used without a ConnectionID
	 */
	protected function getSqliteDatabaseName()
	{
		return self::SQLITE_DATABASE;
	}

	// ------------------------------------------------------------------
	// Configuration properties
	// ------------------------------------------------------------------

	/**
	 * @return string the table prefix
	 */
	public function getTablePrefix(): string
	{
		return $this->_tablePrefix;
	}

	/**
	 * @param string $prefix the table prefix; letters, digits and underscores only
	 * @throws TInvalidDataValueException when the prefix contains other characters
	 */
	public function setTablePrefix($prefix): void
	{
		$this->ensureNotInitialized('TablePrefix');
		$prefix = TPropertyValue::ensureString($prefix);
		if (!preg_match('/^[A-Za-z0-9_]*$/', $prefix)) {
			throw new TInvalidDataValueException('forum_table_prefix_invalid', $prefix);
		}
		$this->_tablePrefix = $prefix;
	}

	/**
	 * @return string the forum title
	 */
	public function getTitle(): string
	{
		return $this->_title;
	}

	/**
	 * @param string $title the forum title
	 */
	public function setTitle($title): void
	{
		$this->_title = TPropertyValue::ensureString($title);
	}

	/**
	 * @return string the page path prefix of the forum pages
	 */
	public function getPagePathPrefix(): string
	{
		return $this->_pagePathPrefix;
	}

	/**
	 * @param string $prefix the page path prefix, e.g. `Forum` for `Forum.Index`
	 */
	public function setPagePathPrefix($prefix): void
	{
		$this->_pagePathPrefix = trim(TPropertyValue::ensureString($prefix), '.');
	}

	/**
	 * @return int threads per board page
	 */
	public function getThreadsPerPage(): int
	{
		return $this->_threadsPerPage;
	}

	/**
	 * @param int $count threads per board page, positive
	 */
	public function setThreadsPerPage($count): void
	{
		$this->_threadsPerPage = $this->ensurePositive('ThreadsPerPage', $count);
	}

	/**
	 * @return int posts per thread page
	 */
	public function getPostsPerPage(): int
	{
		return $this->_postsPerPage;
	}

	/**
	 * @param int $count posts per thread page, positive
	 */
	public function setPostsPerPage($count): void
	{
		$this->_postsPerPage = $this->ensurePositive('PostsPerPage', $count);
	}

	/**
	 * @return int items per page of generic lists
	 */
	public function getItemsPerPage(): int
	{
		return $this->_itemsPerPage;
	}

	/**
	 * @param int $count items per page of generic lists, positive
	 */
	public function setItemsPerPage($count): void
	{
		$this->_itemsPerPage = $this->ensurePositive('ItemsPerPage', $count);
	}

	/**
	 * @return string the default content format of new posts
	 */
	public function getContentFormat(): string
	{
		return $this->_contentFormat;
	}

	/**
	 * @param string $format the default content format: markdown or text
	 * @throws TInvalidDataValueException when the format is unknown
	 */
	public function setContentFormat($format): void
	{
		$format = strtolower(TPropertyValue::ensureString($format));
		if (!in_array($format, [BEForumContentRenderer::FORMAT_MARKDOWN, BEForumContentRenderer::FORMAT_TEXT], true) && !$this->getRenderer()->isFormatSupported($format)) {
			throw new TInvalidDataValueException('forum_content_format_invalid', $format);
		}
		$this->_contentFormat = $format;
	}

	/**
	 * @return int seconds an owner may edit a post, 0 for unlimited
	 */
	public function getEditWindow(): int
	{
		return $this->_editWindow;
	}

	/**
	 * @param int $seconds seconds an owner may edit a post, 0 for unlimited
	 */
	public function setEditWindow($seconds): void
	{
		$this->_editWindow = $this->ensureNonNegative('EditWindow', $seconds);
	}

	/**
	 * @return int minimum seconds between two posts of a member, 0 disables flood control
	 */
	public function getFloodInterval(): int
	{
		return $this->_floodInterval;
	}

	/**
	 * @param int $seconds minimum seconds between two posts of a member
	 */
	public function setFloodInterval($seconds): void
	{
		$this->_floodInterval = $this->ensureNonNegative('FloodInterval', $seconds);
	}

	/**
	 * @return int maximum title length
	 */
	public function getMaxTitleLength(): int
	{
		return $this->_maxTitleLength;
	}

	/**
	 * @param int $length maximum title length, between 1 and 255
	 */
	public function setMaxTitleLength($length): void
	{
		$this->_maxTitleLength = min(255, $this->ensurePositive('MaxTitleLength', $length));
	}

	/**
	 * @return int minimum post length in characters
	 */
	public function getMinPostLength(): int
	{
		return $this->_minPostLength;
	}

	/**
	 * @param int $length minimum post length in characters
	 */
	public function setMinPostLength($length): void
	{
		$this->_minPostLength = $this->ensurePositive('MinPostLength', $length);
	}

	/**
	 * @return int maximum post length in characters
	 */
	public function getMaxPostLength(): int
	{
		return $this->_maxPostLength;
	}

	/**
	 * @param int $length maximum post length in characters
	 */
	public function setMaxPostLength($length): void
	{
		$this->_maxPostLength = $this->ensurePositive('MaxPostLength', $length);
	}

	/**
	 * @return int maximum tags per thread
	 */
	public function getMaxTagsPerThread(): int
	{
		return $this->_maxTagsPerThread;
	}

	/**
	 * @param int $count maximum tags per thread
	 */
	public function setMaxTagsPerThread($count): void
	{
		$this->_maxTagsPerThread = $this->ensureNonNegative('MaxTagsPerThread', $count);
	}

	/**
	 * @return bool whether polls are enabled
	 */
	public function getEnablePolls(): bool
	{
		return $this->_enablePolls;
	}

	/**
	 * @param bool $enabled whether polls are enabled
	 */
	public function setEnablePolls($enabled): void
	{
		$this->_enablePolls = TPropertyValue::ensureBoolean($enabled);
	}

	/**
	 * @return bool whether attachments are enabled
	 */
	public function getEnableAttachments(): bool
	{
		return $this->_enableAttachments;
	}

	/**
	 * @param bool $enabled whether attachments are enabled
	 */
	public function setEnableAttachments($enabled): void
	{
		$this->_enableAttachments = TPropertyValue::ensureBoolean($enabled);
	}

	/**
	 * @return bool whether reactions are enabled
	 */
	public function getEnableReactions(): bool
	{
		return $this->_enableReactions;
	}

	/**
	 * @param bool $enabled whether reactions are enabled
	 */
	public function setEnableReactions($enabled): void
	{
		$this->_enableReactions = TPropertyValue::ensureBoolean($enabled);
	}

	/**
	 * @return bool whether tags are enabled
	 */
	public function getEnableTags(): bool
	{
		return $this->_enableTags;
	}

	/**
	 * @param bool $enabled whether tags are enabled
	 */
	public function setEnableTags($enabled): void
	{
		$this->_enableTags = TPropertyValue::ensureBoolean($enabled);
	}

	/**
	 * @return bool whether subscriptions and notifications are enabled
	 */
	public function getEnableSubscriptions(): bool
	{
		return $this->_enableSubscriptions;
	}

	/**
	 * @param bool $enabled whether subscriptions and notifications are enabled
	 */
	public function setEnableSubscriptions($enabled): void
	{
		$this->_enableSubscriptions = TPropertyValue::ensureBoolean($enabled);
	}

	/**
	 * @return bool whether signatures are shown
	 */
	public function getEnableSignatures(): bool
	{
		return $this->_enableSignatures;
	}

	/**
	 * @param bool $enabled whether signatures are shown
	 */
	public function setEnableSignatures($enabled): void
	{
		$this->_enableSignatures = TPropertyValue::ensureBoolean($enabled);
	}

	/**
	 * @return bool whether bookmarks are enabled
	 */
	public function getEnableBookmarks(): bool
	{
		return $this->_enableBookmarks;
	}

	/**
	 * @param bool $enabled whether bookmarks are enabled
	 */
	public function setEnableBookmarks($enabled): void
	{
		$this->_enableBookmarks = TPropertyValue::ensureBoolean($enabled);
	}

	/**
	 * @return bool whether badges are enabled
	 */
	public function getEnableBadges(): bool
	{
		return $this->_enableBadges;
	}

	/**
	 * @param bool $enabled whether badges are enabled
	 */
	public function setEnableBadges($enabled): void
	{
		$this->_enableBadges = TPropertyValue::ensureBoolean($enabled);
	}

	/**
	 * @return bool whether new threads and posts of non-moderators need approval
	 */
	public function getRequireApproval(): bool
	{
		return $this->_requireApproval;
	}

	/**
	 * @param bool $required whether new threads and posts of non-moderators need approval
	 */
	public function setRequireApproval($required): void
	{
		$this->_requireApproval = TPropertyValue::ensureBoolean($required);
	}

	/**
	 * @return bool whether member profiles are created automatically for authenticated users
	 */
	public function getAutoCreateMembers(): bool
	{
		return $this->_autoCreateMembers;
	}

	/**
	 * @param bool $auto whether member profiles are created automatically for authenticated users
	 */
	public function setAutoCreateMembers($auto): void
	{
		$this->_autoCreateMembers = TPropertyValue::ensureBoolean($auto);
	}

	/**
	 * @return bool whether the schema is installed and upgraded automatically on first use
	 */
	public function getAutoInstall(): bool
	{
		return $this->_autoInstall;
	}

	/**
	 * @param bool $auto whether the schema is installed and upgraded automatically on first use
	 */
	public function setAutoInstall($auto): void
	{
		$this->_autoInstall = TPropertyValue::ensureBoolean($auto);
	}

	/**
	 * @return string[] the reaction types
	 */
	public function getReactionTypes(): array
	{
		return $this->_reactionTypes;
	}

	/**
	 * @param array|string $types the reaction types, comma separated or an array
	 * @throws TInvalidDataValueException when a type is not a word
	 */
	public function setReactionTypes($types): void
	{
		$this->_reactionTypes = $this->ensureWordList('ReactionTypes', $types);
	}

	/**
	 * @return string the attachment storage path, defaults to `<runtime>/beforum-attachments`
	 */
	public function getAttachmentPath(): string
	{
		if ($this->_attachmentPath === null) {
			$this->_attachmentPath = $this->getApplication()->getRuntimePath() . DIRECTORY_SEPARATOR . 'beforum-attachments';
		}
		return $this->_attachmentPath;
	}

	/**
	 * @param string $path the attachment storage path
	 */
	public function setAttachmentPath($path): void
	{
		$path = TPropertyValue::ensureString($path);
		$this->_attachmentPath = $path === '' ? null : $path;
	}

	/**
	 * @return int the maximum attachment size in bytes
	 */
	public function getAttachmentMaxSize(): int
	{
		return $this->_attachmentMaxSize;
	}

	/**
	 * @param int $bytes the maximum attachment size in bytes
	 */
	public function setAttachmentMaxSize($bytes): void
	{
		$this->_attachmentMaxSize = $this->ensurePositive('AttachmentMaxSize', $bytes);
	}

	/**
	 * @return string[] the allowed attachment extensions, lower case
	 */
	public function getAttachmentTypes(): array
	{
		return $this->_attachmentTypes;
	}

	/**
	 * @param array|string $types the allowed attachment extensions, comma separated or an array
	 */
	public function setAttachmentTypes($types): void
	{
		$this->_attachmentTypes = $this->ensureWordList('AttachmentTypes', $types);
	}

	/**
	 * @return int days to keep read notifications
	 */
	public function getNotificationRetentionDays(): int
	{
		return $this->_notificationRetentionDays;
	}

	/**
	 * @param int $days days to keep read notifications
	 */
	public function setNotificationRetentionDays($days): void
	{
		$this->_notificationRetentionDays = $this->ensurePositive('NotificationRetentionDays', $days);
	}

	/**
	 * @return int minimum search query length
	 */
	public function getSearchMinLength(): int
	{
		return $this->_searchMinLength;
	}

	/**
	 * @param int $length minimum search query length
	 */
	public function setSearchMinLength($length): void
	{
		$this->_searchMinLength = $this->ensurePositive('SearchMinLength', $length);
	}

	/**
	 * @return string the CSS class prefix of the controls
	 */
	public function getCssClassPrefix(): string
	{
		return $this->_cssClassPrefix;
	}

	/**
	 * @param string $prefix the CSS class prefix of the controls
	 */
	public function setCssClassPrefix($prefix): void
	{
		$this->_cssClassPrefix = trim(TPropertyValue::ensureString($prefix));
	}

	/**
	 * @return bool whether the bundled stylesheet is published
	 */
	public function getEnableDefaultStyles(): bool
	{
		return $this->_enableDefaultStyles;
	}

	/**
	 * @param bool $enabled whether the bundled stylesheet is published
	 */
	public function setEnableDefaultStyles($enabled): void
	{
		$this->_enableDefaultStyles = TPropertyValue::ensureBoolean($enabled);
	}

	/**
	 * @return string the PHP date format used for absolute times
	 */
	public function getDateFormat(): string
	{
		return $this->_dateFormat;
	}

	/**
	 * @param string $format the PHP date format used for absolute times
	 */
	public function setDateFormat($format): void
	{
		$this->_dateFormat = TPropertyValue::ensureString($format);
	}

	/**
	 * @return null|string the display timezone identifier, null for UTC
	 */
	public function getTimezone(): ?string
	{
		return $this->_timezone;
	}

	/**
	 * @param null|string $timezone the display timezone identifier
	 * @throws TInvalidDataValueException when the identifier is unknown
	 */
	public function setTimezone($timezone): void
	{
		$timezone = TPropertyValue::ensureString($timezone);
		if ($timezone !== '' && !in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
			throw new TInvalidDataValueException('forum_timezone_invalid', $timezone);
		}
		$this->_timezone = $timezone === '' ? null : $timezone;
	}

	/**
	 * @return string the name shown for guests
	 */
	public function getGuestName(): string
	{
		return $this->_guestName;
	}

	/**
	 * @param string $name the name shown for guests
	 */
	public function setGuestName($name): void
	{
		$this->_guestName = TPropertyValue::ensureString($name);
	}

	/**
	 * @return string[] usernames holding every forum permission
	 */
	public function getAdminUsers(): array
	{
		return $this->_adminUsers;
	}

	/**
	 * @param array|string $users usernames holding every forum permission, comma separated or an array
	 */
	public function setAdminUsers($users): void
	{
		$this->_adminUsers = $this->ensureNameList($users);
	}

	/**
	 * @return string[] roles holding every forum permission
	 */
	public function getAdminRoles(): array
	{
		return $this->_adminRoles;
	}

	/**
	 * @param array|string $roles roles holding every forum permission, comma separated or an array
	 */
	public function setAdminRoles($roles): void
	{
		$this->_adminRoles = $this->ensureNameList($roles);
	}

	/**
	 * @return string[] usernames holding the moderation permission everywhere
	 */
	public function getModeratorUsers(): array
	{
		return $this->_moderatorUsers;
	}

	/**
	 * @param array|string $users usernames holding the moderation permission everywhere, comma separated or an array
	 */
	public function setModeratorUsers($users): void
	{
		$this->_moderatorUsers = $this->ensureNameList($users);
	}

	/**
	 * @return string[] roles holding the moderation permission everywhere
	 */
	public function getModeratorRoles(): array
	{
		return $this->_moderatorRoles;
	}

	/**
	 * @param array|string $roles roles holding the moderation permission everywhere, comma separated or an array
	 */
	public function setModeratorRoles($roles): void
	{
		$this->_moderatorRoles = $this->ensureNameList($roles);
	}

	/**
	 * @param array|string $value comma separated names or an array
	 * @return string[] the trimmed, non-empty names
	 */
	protected function ensureNameList($value): array
	{
		$items = is_array($value) ? $value : explode(',', TPropertyValue::ensureString($value));
		$names = [];
		foreach ($items as $item) {
			$name = trim((string) $item);
			if ($name !== '') {
				$names[strtolower($name)] = $name;
			}
		}
		return array_values($names);
	}

	/**
	 * Returns the module connection: an injected one, or the TDbPluginModule
	 * connection resolved from ConnectionID / the default SQLite database.
	 * @return TDbConnection the connection
	 */
	public function getDbConnection()
	{
		if ($this->_injectedConnection !== null) {
			$this->_injectedConnection->setActive(true);
			return $this->_injectedConnection;
		}
		return parent::getDbConnection();
	}

	/**
	 * Injects the database connection, bypassing ConnectionID (used by tests
	 * and programmatic setups).
	 * @param null|TDbConnection $connection the connection
	 */
	public function setDbConnection(?TDbConnection $connection): void
	{
		$this->_injectedConnection = $connection;
		$this->_schema = null;
		$this->_schemaVerified = false;
		if ($this->_initialized) {
			$reference = \WeakReference::create($this);
			BEForumRecord::setForumDbConnection(fn () => $reference->get()?->getDbConnection());
		}
	}

	/**
	 * @return string the content renderer class
	 */
	public function getRendererClass(): string
	{
		return $this->_rendererClass;
	}

	/**
	 * @param string $class a subclass of BEForumContentRenderer
	 * @throws TInvalidDataValueException when the class is not a renderer
	 */
	public function setRendererClass($class): void
	{
		$class = TPropertyValue::ensureString($class);
		if (!is_a($class, BEForumContentRenderer::class, true)) {
			throw new TInvalidDataValueException('forum_class_invalid', 'RendererClass', $class, BEForumContentRenderer::class);
		}
		$this->_rendererClass = $class;
		$this->_renderer = null;
	}

	/**
	 * @return string the URL builder class
	 */
	public function getUrlBuilderClass(): string
	{
		return $this->_urlBuilderClass;
	}

	/**
	 * @param string $class a subclass of BEForumUrlBuilder
	 * @throws TInvalidDataValueException when the class is not a URL builder
	 */
	public function setUrlBuilderClass($class): void
	{
		$class = TPropertyValue::ensureString($class);
		if (!is_a($class, BEForumUrlBuilder::class, true)) {
			throw new TInvalidDataValueException('forum_class_invalid', 'UrlBuilderClass', $class, BEForumUrlBuilder::class);
		}
		$this->_urlBuilderClass = $class;
		$this->_urls = null;
	}

	/**
	 * @return string the shell action class
	 */
	public function getShellActionClass(): string
	{
		return $this->_shellActionClass;
	}

	/**
	 * @param string $class a subclass of BEForumShellAction
	 * @throws TInvalidDataValueException when the class is not a forum shell action
	 */
	public function setShellActionClass($class): void
	{
		$class = TPropertyValue::ensureString($class);
		if (!is_a($class, BEForumShellAction::class, true)) {
			throw new TInvalidDataValueException('forum_class_invalid', 'ShellActionClass', $class, BEForumShellAction::class);
		}
		$this->_shellActionClass = $class;
	}

	/**
	 * @param string $property the property name for error messages
	 * @param mixed $value the value
	 * @throws TInvalidDataValueException when the value is not positive
	 * @return int the positive integer
	 */
	protected function ensurePositive(string $property, $value): int
	{
		$value = TPropertyValue::ensureInteger($value);
		if ($value < 1) {
			throw new TInvalidDataValueException('forum_property_positive', $property, $value);
		}
		return $value;
	}

	/**
	 * @param string $property the property name for error messages
	 * @param mixed $value the value
	 * @throws TInvalidDataValueException when the value is negative
	 * @return int the non-negative integer
	 */
	protected function ensureNonNegative(string $property, $value): int
	{
		$value = TPropertyValue::ensureInteger($value);
		if ($value < 0) {
			throw new TInvalidDataValueException('forum_property_non_negative', $property, $value);
		}
		return $value;
	}

	/**
	 * @param string $property the property name for error messages
	 * @param array|string $value comma separated words or an array
	 * @throws TInvalidDataValueException when a word contains other than letters, digits, underscores or hyphens
	 * @return string[] the lower case words
	 */
	protected function ensureWordList(string $property, $value): array
	{
		$items = is_array($value) ? $value : explode(',', TPropertyValue::ensureString($value));
		$words = [];
		foreach ($items as $item) {
			$word = strtolower(trim((string) $item));
			if ($word === '') {
				continue;
			}
			if (!preg_match('/^[a-z0-9_\-]+$/', $word)) {
				throw new TInvalidDataValueException('forum_property_word_invalid', $property, $word);
			}
			$words[$word] = $word;
		}
		return array_values($words);
	}

	// ------------------------------------------------------------------
	// Schema
	// ------------------------------------------------------------------

	/**
	 * @return BEForumSchema the schema bound to the module connection and prefix
	 */
	public function getSchema(): BEForumSchema
	{
		if ($this->_schema === null) {
			$this->_schema = new BEForumSchema($this->getDbConnection(), $this->_tablePrefix);
		}
		return $this->_schema;
	}

	/**
	 * Installs or upgrades the schema when {@see getAutoInstall} is enabled.
	 * The check runs once per request and is remembered in the application
	 * global state so the database metadata is not queried on every request.
	 * @param bool $force whether to verify the schema even when the global state says it is current
	 * @return bool whether the schema is current after the call
	 */
	public function ensureSchema(bool $force = false): bool
	{
		if ($this->_schemaVerified && !$force) {
			return true;
		}
		$app = $this->getApplication();
		$stateKey = self::STATE_SCHEMA_VERSION . ':' . $this->_tablePrefix;
		if (!$force && (int) $app->getGlobalState($stateKey, 0) === BEForumSchema::VERSION) {
			$this->_schemaVerified = true;
			return true;
		}
		$schema = $this->getSchema();
		$version = $schema->getInstalledVersion();
		if ($version < BEForumSchema::VERSION) {
			if (!$this->_autoInstall) {
				return false;
			}
			$version = $schema->upgrade();
			Prado::log('Forum schema installed/upgraded to version ' . $version, TLogger::INFO, self::class);
		}
		$app->setGlobalState($stateKey, $version, 0);
		$this->_schemaVerified = true;
		return true;
	}

	/**
	 * Clears per request caches of the managers at the end of the request.
	 * @param mixed $sender the application
	 * @param mixed $param the event parameter
	 */
	public function flushRequestState($sender, $param): void
	{
		foreach ($this->_managers as $manager) {
			$manager->flushRequestCache();
		}
	}

	// ------------------------------------------------------------------
	// Components
	// ------------------------------------------------------------------

	/**
	 * Returns a manager, creating it on first use.  A behavior may supply its
	 * own instance through `dyCreateManager`.
	 * @template T of BEForumManager
	 * @param class-string<T> $class the manager class
	 * @throws BEForumConfigurationException when the class is not a manager
	 * @return T the manager
	 */
	public function getManager(string $class): BEForumManager
	{
		if (!isset($this->_managers[$class])) {
			$manager = $this->dyCreateManager(null, $class);
			if (!($manager instanceof BEForumManager)) {
				if (!is_a($class, BEForumManager::class, true)) {
					throw new BEForumConfigurationException('forum_manager_class_invalid', $class);
				}
				$manager = new $class($this);
			}
			$this->_managers[$class] = $manager;
		}
		return $this->_managers[$class];
	}

	/**
	 * Replaces a manager instance, for example with a subclass.
	 * @param string $class the manager class being replaced
	 * @param BEForumManager $manager the replacement
	 */
	public function setManager(string $class, BEForumManager $manager): void
	{
		$this->_managers[$class] = $manager;
	}

	/**
	 * @return BEForumMemberManager the member manager
	 */
	public function getMembers(): BEForumMemberManager
	{
		return $this->getManager(BEForumMemberManager::class);
	}

	/**
	 * @return BEForumBoardManager the category and board manager
	 */
	public function getBoards(): BEForumBoardManager
	{
		return $this->getManager(BEForumBoardManager::class);
	}

	/**
	 * @return BEForumThreadManager the thread manager
	 */
	public function getThreads(): BEForumThreadManager
	{
		return $this->getManager(BEForumThreadManager::class);
	}

	/**
	 * @return BEForumPostManager the post manager
	 */
	public function getPosts(): BEForumPostManager
	{
		return $this->getManager(BEForumPostManager::class);
	}

	/**
	 * @return BEForumReactionManager the reaction manager
	 */
	public function getReactions(): BEForumReactionManager
	{
		return $this->getManager(BEForumReactionManager::class);
	}

	/**
	 * @return BEForumPollManager the poll manager
	 */
	public function getPolls(): BEForumPollManager
	{
		return $this->getManager(BEForumPollManager::class);
	}

	/**
	 * @return BEForumTagManager the tag manager
	 */
	public function getTags(): BEForumTagManager
	{
		return $this->getManager(BEForumTagManager::class);
	}

	/**
	 * @return BEForumAttachmentManager the attachment manager
	 */
	public function getAttachments(): BEForumAttachmentManager
	{
		return $this->getManager(BEForumAttachmentManager::class);
	}

	/**
	 * @return BEForumSubscriptionManager the subscription manager
	 */
	public function getSubscriptions(): BEForumSubscriptionManager
	{
		return $this->getManager(BEForumSubscriptionManager::class);
	}

	/**
	 * @return BEForumNotificationManager the notification manager
	 */
	public function getNotifications(): BEForumNotificationManager
	{
		return $this->getManager(BEForumNotificationManager::class);
	}

	/**
	 * @return BEForumSearchManager the search manager
	 */
	public function getSearch(): BEForumSearchManager
	{
		return $this->getManager(BEForumSearchManager::class);
	}

	/**
	 * @return BEForumModerationManager the moderation manager
	 */
	public function getModeration(): BEForumModerationManager
	{
		return $this->getManager(BEForumModerationManager::class);
	}

	/**
	 * @return BEForumReadTracker the read tracker
	 */
	public function getReadTracker(): BEForumReadTracker
	{
		return $this->getManager(BEForumReadTracker::class);
	}

	/**
	 * @return BEForumBookmarkManager the bookmark manager
	 */
	public function getBookmarks(): BEForumBookmarkManager
	{
		return $this->getManager(BEForumBookmarkManager::class);
	}

	/**
	 * @return BEForumStatisticsManager the statistics manager
	 */
	public function getStatistics(): BEForumStatisticsManager
	{
		return $this->getManager(BEForumStatisticsManager::class);
	}

	/**
	 * @return BEForumContentRenderer the content renderer
	 */
	public function getRenderer(): BEForumContentRenderer
	{
		if ($this->_renderer === null) {
			$renderer = $this->dyCreateRenderer(null);
			if (!($renderer instanceof BEForumContentRenderer)) {
				$class = $this->_rendererClass;
				$renderer = new $class($this);
			}
			$renderer->setMentionResolver(function (string $username): ?string {
				$member = $this->getMembers()->findByUsername($username);
				return $member === null ? null : $this->getUrls()->member($member);
			});
			$this->_renderer = $renderer;
		}
		return $this->_renderer;
	}

	/**
	 * Renders raw content with the module renderer.
	 * @param null|string $raw the raw content
	 * @param null|string $format the format, null for the module default
	 * @return string sanitised HTML
	 */
	public function renderContent(?string $raw, ?string $format = null): string
	{
		return $this->getRenderer()->render($raw, $format ?? $this->_contentFormat);
	}

	/**
	 * @return BEForumUrlBuilder the URL builder
	 */
	public function getUrls(): BEForumUrlBuilder
	{
		if ($this->_urls === null) {
			$urls = $this->dyCreateUrlBuilder(null);
			if (!($urls instanceof BEForumUrlBuilder)) {
				$class = $this->_urlBuilderClass;
				$urls = new $class($this);
			}
			$this->_urls = $urls;
		}
		return $this->_urls;
	}

	// ------------------------------------------------------------------
	// Users and authorization
	// ------------------------------------------------------------------

	/**
	 * @return null|IUser the current application user, null outside a request
	 */
	public function getUser(): ?IUser
	{
		$app = $this->getApplication();
		if ($app === null) {
			return null;
		}
		try {
			$user = $app->getUser();
		} catch (\Throwable $e) {
			return null;
		}
		return $user instanceof IUser ? $user : null;
	}

	/**
	 * @return null|BEForumMember the member of the current user, null for guests
	 */
	public function getMember(): ?BEForumMember
	{
		return $this->getMembers()->getCurrentMember();
	}

	/**
	 * @return bool whether the module runs under prado-cli
	 */
	public function getIsShellApplication(): bool
	{
		return $this->getApplication() instanceof TShellApplication;
	}

	/**
	 * @return bool whether a TPermissionsManager is installed in the application
	 */
	public function getHasPermissionsManager(): bool
	{
		return TPermissionsManager::getManager() !== null;
	}

	/**
	 * Registers the forum permissions with the permissions manager.
	 * @param TPermissionsManager $manager the permissions manager
	 * @return TPermissionEvent[] the permission events
	 */
	public function getPermissions($manager)
	{
		$events = [];
		foreach (BEForumPermissions::getDefinitions() as $name => $definition) {
			if ($manager instanceof TPermissionsManager && $manager->getPermissionRules($name) !== null) {
				// another forum module instance has registered the permission already
				continue;
			}
			$events[] = new TPermissionEvent($name, $definition['description'], [$definition['event']], $this->createPresetRules($name));
		}
		return $events;
	}

	/**
	 * Creates the preset rules of a permission: the static rules of
	 * {@see BEForumPermissions} plus the configured administrator (every
	 * permission) and moderator (moderation and content permissions) rules.
	 * @param string $permission the permission name
	 * @return TAuthorizationRule[] the rules in evaluation order
	 */
	public function createPresetRules(string $permission): array
	{
		$permission = strtolower($permission);
		$rules = [new BEForumRoleRule($this, BEForumRoleRule::KIND_ADMIN)];
		if ($permission !== BEForumPermissions::ADMIN && $permission !== BEForumPermissions::SHELL) {
			$rules[] = new BEForumRoleRule($this, BEForumRoleRule::KIND_MODERATOR);
		}
		foreach (BEForumPermissions::getPresetRules($permission) as $rule) {
			$rules[] = $rule;
		}
		return $rules;
	}

	/**
	 * @param string $permission the permission name
	 * @return TAuthorizationRuleCollection the preset rules used without a permissions manager
	 */
	public function getPresetRules(string $permission): TAuthorizationRuleCollection
	{
		$permission = strtolower($permission);
		if (!isset($this->_presetRules[$permission])) {
			$collection = new TAuthorizationRuleCollection();
			foreach ($this->createPresetRules($permission) as $rule) {
				$collection->add($rule);
			}
			// without a permissions manager nothing adds the final deny-all rule, so add it here
			$collection->add(new TAuthorizationRule('deny', '*', '*', '*', '*', 1000));
			$this->_presetRules[$permission] = $collection;
		}
		return $this->_presetRules[$permission];
	}

	/**
	 * Decides whether a user holds a forum permission.
	 *
	 * With a {@see TPermissionsManager} the decision comes from the registered
	 * permission rules (`$user->can()`), otherwise from the preset rules of
	 * {@see BEForumPermissions}.  In the shell every permission is granted (the
	 * shell action is gated by `forum_shell`); a web request without a user
	 * module is evaluated as a guest.  `dyAuthorize` filters the result.
	 * @param null|IUser $user the user
	 * @param string $permission the permission name
	 * @param null|array $extra extra data for the rules: username (owner), moderators (board moderator usernames)
	 * @return bool whether the user is allowed
	 */
	public function canUser(?IUser $user, string $permission, ?array $extra = null): bool
	{
		$permission = strtolower($permission);
		if ($this->getIsShellApplication()) {
			// shell commands are gated by the forum_shell permission when registered
			$allowed = true;
		} elseif ($user instanceof TComponent && $user->asa(TPermissionsManager::USER_PERMISSIONS_BEHAVIOR) !== null && $this->getHasPermissionsManager()) {
			$allowed = (bool) call_user_func([$user, 'can'], $permission, $extra);
		} else {
			$app = $this->getApplication();
			$request = $app ? $app->getRequest() : null;
			$verb = $request ? $request->getRequestType() : 'GET';
			$ip = $request ? (string) $request->getUserHostAddress() : '';
			$allowed = $this->getPresetRules($permission)->isUserAllowed($user ?? new BEForumGuestUser($this->getGuestName()), $verb, $ip, $extra);
		}
		return (bool) $this->dyAuthorize($allowed, $permission, $extra, $user);
	}

	/**
	 * @param string $permission the permission name
	 * @param null|array $extra extra data for the rules
	 * @return bool whether the current user holds the permission
	 */
	public function can(string $permission, ?array $extra = null): bool
	{
		return $this->authorize($permission, $extra, false);
	}

	/**
	 * Authorizes the current user for a permission.  The dynamic event guarding
	 * the permission is raised first so {@see \Prado\Security\Permissions\TPermissionsBehavior}
	 * and any attached behavior may deny the action; then {@see canUser} decides.
	 * @param string $permission the permission name
	 * @param null|array $extra extra data for the rules
	 * @param bool $throw whether to throw instead of returning false
	 * @throws BEForumForbiddenException when denied and $throw is true
	 * @return bool whether the current user is allowed
	 */
	public function authorize(string $permission, ?array $extra = null, bool $throw = true): bool
	{
		$permission = strtolower($permission);
		$allowed = true;
		$event = BEForumPermissions::getEvent($permission);
		if ($event !== null && $this->$event(false, ['extra' => $extra]) === true) {
			$allowed = false;
		}
		if ($allowed) {
			$allowed = $this->canUser($this->getUser(), $permission, $extra);
		}
		if (!$allowed && $throw) {
			Prado::log('Permission "' . $permission . '" denied', TLogger::NOTICE, self::class);
			throw new BEForumForbiddenException($permission);
		}
		return $allowed;
	}

	// ------------------------------------------------------------------
	// Shell and cron
	// ------------------------------------------------------------------

	/**
	 * Adds the `forum` shell action when running under prado-cli.
	 * @param object $sender the application
	 * @param mixed $param the event parameter
	 */
	public function registerShellAction($sender, $param): void
	{
		if ($this->dyRegisterShellAction(false) !== true && ($app = $this->getApplication()) instanceof TShellApplication) {
			$app->addShellActionClass(['class' => $this->_shellActionClass, 'ForumModule' => $this]);
		}
	}

	/**
	 * Advertises the maintenance task to {@see \Prado\Util\Cron\TCronModule}.
	 * The cron module expects exactly one task info per handler; the recount
	 * (`<id>->recountStatistics`) can be scheduled as an additional method task.
	 * @param object $sender the cron module
	 * @param mixed $param the event parameter
	 * @return TCronTaskInfo the task info
	 */
	public function fxGetCronTaskInfos($sender, $param): TCronTaskInfo
	{
		$id = $this->getID();
		$info = new TCronTaskInfo('forum_maintenance', $id . '->runMaintenance', $id, Prado::localize('Forum Maintenance'), Prado::localize('Expires bans and pins, prunes old notifications and orphaned tags.'));
		return $this->dyCronTaskInfos($info);
	}

	/**
	 * Cron entry point: expires bans and pins, prunes notifications and unused tags.
	 * @return array<string, int> counts of what was changed
	 */
	public function runMaintenance(): array
	{
		$this->ensureSchema();
		return [
			'bans_expired' => $this->getMembers()->expireBans(),
			'pins_expired' => $this->getThreads()->expirePins(),
			'notifications_pruned' => $this->getNotifications()->prune($this->_notificationRetentionDays),
			'tags_removed' => $this->getTags()->removeUnused(),
		];
	}

	/**
	 * Cron entry point: recomputes every denormalised counter.
	 * @return array<string, int> counts of what was recounted
	 */
	public function recountStatistics(): array
	{
		$this->ensureSchema();
		return $this->getStatistics()->recountAll();
	}

	// ------------------------------------------------------------------
	// Events
	// ------------------------------------------------------------------

	/**
	 * Raises a forum event by name.
	 * @param string $name the event name, e.g. `onPostCreated`
	 * @param BEForumEventParameter $param the event parameter
	 */
	public function raiseForumEvent(string $name, BEForumEventParameter $param): void
	{
		$this->raiseEvent($name, $this, $param);
	}

	/** @param BEForumEventParameter $param */
	public function onMemberCreated($param)
	{
		$this->raiseEvent('onMemberCreated', $this, $param);
	}

	/** @param BEForumEventParameter $param */
	public function onMemberUpdated($param)
	{
		$this->raiseEvent('onMemberUpdated', $this, $param);
	}

	/** @param BEForumEventParameter $param */
	public function onMemberBanned($param)
	{
		$this->raiseEvent('onMemberBanned', $this, $param);
	}

	/** @param BEForumEventParameter $param */
	public function onMemberUnbanned($param)
	{
		$this->raiseEvent('onMemberUnbanned', $this, $param);
	}

	/** @param BEForumEventParameter $param */
	public function onMemberWarned($param)
	{
		$this->raiseEvent('onMemberWarned', $this, $param);
	}

	/** @param BEForumEventParameter $param */
	public function onBadgeAwarded($param)
	{
		$this->raiseEvent('onBadgeAwarded', $this, $param);
	}

	/** @param BEForumEventParameter $param */
	public function onCategoryChanged($param)
	{
		$this->raiseEvent('onCategoryChanged', $this, $param);
	}

	/** @param BEForumEventParameter $param */
	public function onBoardChanged($param)
	{
		$this->raiseEvent('onBoardChanged', $this, $param);
	}

	/** @param BEForumEventParameter $param */
	public function onThreadCreated($param)
	{
		$this->raiseEvent('onThreadCreated', $this, $param);
	}

	/** @param BEForumEventParameter $param */
	public function onThreadUpdated($param)
	{
		$this->raiseEvent('onThreadUpdated', $this, $param);
	}

	/** @param BEForumEventParameter $param */
	public function onThreadDeleted($param)
	{
		$this->raiseEvent('onThreadDeleted', $this, $param);
	}

	/** @param BEForumEventParameter $param */
	public function onThreadRestored($param)
	{
		$this->raiseEvent('onThreadRestored', $this, $param);
	}

	/** @param BEForumEventParameter $param */
	public function onThreadMoved($param)
	{
		$this->raiseEvent('onThreadMoved', $this, $param);
	}

	/** @param BEForumEventParameter $param */
	public function onThreadApproved($param)
	{
		$this->raiseEvent('onThreadApproved', $this, $param);
	}

	/** @param BEForumEventParameter $param */
	public function onThreadSolved($param)
	{
		$this->raiseEvent('onThreadSolved', $this, $param);
	}

	/** @param BEForumEventParameter $param */
	public function onPostCreated($param)
	{
		$this->raiseEvent('onPostCreated', $this, $param);
	}

	/** @param BEForumEventParameter $param */
	public function onPostUpdated($param)
	{
		$this->raiseEvent('onPostUpdated', $this, $param);
	}

	/** @param BEForumEventParameter $param */
	public function onPostDeleted($param)
	{
		$this->raiseEvent('onPostDeleted', $this, $param);
	}

	/** @param BEForumEventParameter $param */
	public function onPostRestored($param)
	{
		$this->raiseEvent('onPostRestored', $this, $param);
	}

	/** @param BEForumEventParameter $param */
	public function onPostApproved($param)
	{
		$this->raiseEvent('onPostApproved', $this, $param);
	}

	/** @param BEForumEventParameter $param */
	public function onReaction($param)
	{
		$this->raiseEvent('onReaction', $this, $param);
	}

	/** @param BEForumEventParameter $param */
	public function onPollCreated($param)
	{
		$this->raiseEvent('onPollCreated', $this, $param);
	}

	/** @param BEForumEventParameter $param */
	public function onPollVoted($param)
	{
		$this->raiseEvent('onPollVoted', $this, $param);
	}

	/** @param BEForumEventParameter $param */
	public function onReportCreated($param)
	{
		$this->raiseEvent('onReportCreated', $this, $param);
	}

	/** @param BEForumEventParameter $param */
	public function onReportHandled($param)
	{
		$this->raiseEvent('onReportHandled', $this, $param);
	}

	/** @param BEForumEventParameter $param */
	public function onNotification($param)
	{
		$this->raiseEvent('onNotification', $this, $param);
	}

	/** @param BEForumEventParameter $param */
	public function onSubscriptionChanged($param)
	{
		$this->raiseEvent('onSubscriptionChanged', $this, $param);
	}

	/** @param BEForumEventParameter $param */
	public function onAttachmentAdded($param)
	{
		$this->raiseEvent('onAttachmentAdded', $this, $param);
	}

	/** @param BEForumEventParameter $param */
	public function onModerationAction($param)
	{
		$this->raiseEvent('onModerationAction', $this, $param);
	}
}
