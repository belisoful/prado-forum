<?php

/**
 * BEForumTestCase class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

use Belisoful\Forum\BEForumModule;
use Belisoful\Forum\Data\BEForumBoard;
use Belisoful\Forum\Data\BEForumCategory;
use Belisoful\Forum\Data\BEForumMember;
use Belisoful\Forum\Data\BEForumPost;
use Belisoful\Forum\Data\BEForumRecord;
use Belisoful\Forum\Data\BEForumSchema;
use Belisoful\Forum\Data\BEForumThread;
use Belisoful\Forum\Util\BEForumTime;
use PHPUnit\Framework\TestCase;
use Prado\Data\TDbConnection;
use Prado\Prado;
use Prado\Security\TUser;
use Prado\Security\TUserManager;
use Prado\TApplication;

/**
 * BEForumTestCase class.
 *
 * BEForumTestCase is the base class of the forum unit tests.  Every test gets
 * a fresh in-memory SQLite database with the schema installed, an initialized
 * {@see BEForumModule} registered in the test application, and helpers to
 * log in users, create structure and content.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
abstract class BEForumTestCase extends TestCase
{
	/** The module id prefix used in the test application; each test registers a unique id */
	public const MODULE_ID = 'forum';

	/** @var string the module id of the current test */
	protected string $moduleId = self::MODULE_ID;

	/** @var BEForumModule the module under test */
	protected BEForumModule $forum;

	/** @var TDbConnection the in-memory connection */
	protected TDbConnection $db;

	/** @var TUserManager the user manager providing test users */
	protected TUserManager $userManager;

	/** @var string the temporary SQLite file of the current test */
	protected string $dbFile = '';

	/** @var null|string the external DSN whose schema has been installed by this process */
	private static ?string $externalSchemaReady = null;

	/**
	 * @var null|TDbConnection the connection shared by every test of an external DSN: PRADO's
	 * Active Record gateway caches its command builder per connection string together with the
	 * first connection object it saw, so a new connection per test would make records and raw
	 * manager commands use two different database sessions (and deadlock on row locks)
	 */
	private static ?TDbConnection $externalConnection = null;

	/**
	 * Creates the database, schema and module.
	 */
	protected function setUp(): void
	{
		parent::setUp();
		if (session_status() === PHP_SESSION_ACTIVE) {
			$_SESSION = [];
		}
		$dsn = (string) getenv('BEFORUM_TEST_DSN');
		if ($dsn !== '') {
			// integration run against MySQL or PostgreSQL: the schema is created once per process
			// (DDL is slow there) and the tables are emptied before every test
			if (self::$externalConnection === null || self::$externalSchemaReady !== $dsn) {
				self::$externalConnection = new TDbConnection($dsn, (string) getenv('BEFORUM_TEST_USER'), (string) getenv('BEFORUM_TEST_PASSWORD'));
			}
			$this->db = self::$externalConnection;
			$this->db->setActive(true);
			$schema = new BEForumSchema($this->db, 'forum_');
			if (self::$externalSchemaReady !== $dsn || !$schema->getIsInstalled() || count($schema->findExistingTables()) < count($schema->getTableNames())) {
				$schema->drop();
				$schema->install();
				self::$externalSchemaReady = $dsn;
			} else {
				$schema->clear();
			}
		} else {
			// TActiveRecordGateway caches metadata per connection string, so every test needs its own file
			$this->dbFile = tempnam(sys_get_temp_dir(), 'beforum-test-');
			$this->db = new TDbConnection('sqlite:' . $this->dbFile);
			$this->db->setActive(true);
			(new BEForumSchema($this->db, 'forum_'))->install();
		}

		$this->userManager = new TUserManager();

		$app = $this->getApp();
		$app->setGlobalState(BEForumModule::STATE_SCHEMA_VERSION . ':forum_', null);
		$this->moduleId = self::MODULE_ID . '_' . substr(md5(uniqid('', true)), 0, 8);
		$this->forum = new BEForumModule();
		$this->forum->setID($this->moduleId);
		$this->forum->setDbConnection($this->db);
		$this->forum->setFloodInterval(0);
		$this->configureModule($this->forum);
		$app->setModule($this->moduleId, $this->forum);
		$this->forum->init(null);
		$this->logout();
	}

	/**
	 * Removes the module from the application and resets the record statics.
	 */
	protected function tearDown(): void
	{
		$app = $this->getApp();
		$this->forum->flushRequestState($app, null);
		$this->forum->__destruct();
		unset($this->forum);
		BEForumRecord::setForumDbConnection(null);
		BEForumRecord::setTablePrefix('forum_');
		BEForumTime::freeze(null);
		$app->setGlobalState(BEForumModule::STATE_SCHEMA_VERSION . ':forum_', null);
		$this->logout();
		if ($this->dbFile !== '') {
			// the external connection stays open for the next test, see $externalConnection
			$this->db->setActive(false);
			if (is_file($this->dbFile)) {
				@unlink($this->dbFile);
			}
		}
		parent::tearDown();
	}

	/**
	 * Hook to configure the module before init.
	 * @param BEForumModule $forum the module
	 */
	protected function configureModule(BEForumModule $forum): void
	{
		$forum->setAdminUsers('admin');
		$forum->setModeratorUsers('mod');
	}

	/**
	 * @return TApplication the test application
	 */
	protected function getApp(): TApplication
	{
		$app = Prado::getApplication();
		self::assertInstanceOf(TApplication::class, $app);
		return $app;
	}

	/**
	 * Sets the application user.
	 * @param string $username the user name
	 * @param string[] $roles the roles
	 * @return TUser the user
	 */
	protected function loginAs(string $username, array $roles = []): TUser
	{
		$user = new TUser($this->userManager);
		$user->setName($username);
		$user->setIsGuest(false);
		$user->setRoles($roles);
		$this->getApp()->setUser($user);
		$this->forum->flushRequestState($this->getApp(), null);
		return $user;
	}

	/**
	 * Sets a guest application user.
	 * @return TUser the guest user
	 */
	protected function logout(): TUser
	{
		$user = new TUser($this->userManager);
		$user->setName($this->userManager->getGuestName());
		$user->setIsGuest(true);
		$this->getApp()->setUser($user);
		if (isset($this->forum)) {
			$this->forum->flushRequestState($this->getApp(), null);
		}
		return $user;
	}

	/**
	 * Creates a category and a board as the administrator, keeping the previous user.
	 * @param string $boardName the board name
	 * @param array $options board options
	 * @return BEForumBoard the board
	 */
	protected function createBoard(string $boardName = 'General', array $options = []): BEForumBoard
	{
		$previous = $this->getApp()->getUser();
		$this->loginAs('admin');
		$category = $this->forum->getBoards()->getCategories(true)[0] ?? $this->forum->getBoards()->createCategory('Category');
		$board = $this->forum->getBoards()->createBoard($category, $boardName, 'A board', $options);
		$this->getApp()->setUser($previous);
		$this->forum->flushRequestState($this->getApp(), null);
		return $board;
	}

	/**
	 * @return BEForumCategory the first category, created when missing
	 */
	protected function getCategory(): BEForumCategory
	{
		$previous = $this->getApp()->getUser();
		$this->loginAs('admin');
		$category = $this->forum->getBoards()->getCategories(true)[0] ?? $this->forum->getBoards()->createCategory('Category');
		$this->getApp()->setUser($previous);
		$this->forum->flushRequestState($this->getApp(), null);
		return $category;
	}

	/**
	 * Creates a thread as a user, keeping the previous user.
	 * @param BEForumBoard $board the board
	 * @param string $username the author
	 * @param string $title the title
	 * @param string $content the content
	 * @param array $options thread options
	 * @return BEForumThread the thread
	 */
	protected function createThreadAs(BEForumBoard $board, string $username, string $title = 'Hello', string $content = 'Hello **world**', array $options = []): BEForumThread
	{
		$previous = $this->getApp()->getUser();
		$this->loginAs($username);
		$thread = $this->forum->getThreads()->createThread($board, $title, $content, $options);
		$this->getApp()->setUser($previous);
		$this->forum->flushRequestState($this->getApp(), null);
		return $thread;
	}

	/**
	 * Creates a reply as a user, keeping the previous user.
	 * @param BEForumThread $thread the thread
	 * @param string $username the author
	 * @param string $content the content
	 * @param array $options post options
	 * @return BEForumPost the post
	 */
	protected function replyAs(BEForumThread $thread, string $username, string $content = 'A reply', array $options = []): BEForumPost
	{
		$previous = $this->getApp()->getUser();
		$this->loginAs($username);
		$post = $this->forum->getPosts()->createPost($thread, $content, $options);
		$this->getApp()->setUser($previous);
		$this->forum->flushRequestState($this->getApp(), null);
		return $post;
	}

	/**
	 * @param string $username the user name
	 * @return BEForumMember the member, created when missing
	 */
	protected function member(string $username): BEForumMember
	{
		return $this->forum->getMembers()->ensureMember($username);
	}
}
