<?php

/**
 * BEForumSchema class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Data;

use Belisoful\Forum\Exceptions\BEForumConfigurationException;
use Belisoful\Forum\Exceptions\BEForumException;
use Belisoful\Forum\Util\BEForumTime;
use Prado\Data\TDbConnection;
use Prado\Exceptions\TException;
use Prado\Exceptions\TInvalidDataValueException;
use Prado\TComponent;

/**
 * BEForumSchema class.
 *
 * BEForumSchema is the single, driver neutral declaration of every forum table.
 * It generates DDL for SQLite, MySQL/MariaDB and PostgreSQL, installs the tables
 * that are missing, records the installed schema version in the `meta` table and
 * applies versioned migrations with {@see upgrade}.
 *
 * Table definitions use a small DSL:
 * ```php
 * 'columns' => [
 *     'id' => ['pk'],                                  // auto increment integer primary key
 *     'name' => ['string', 'length' => 120],           // VARCHAR(120) NOT NULL
 *     'body' => ['longtext', 'null' => true],          // nullable large text
 *     'position' => ['int', 'default' => 0],
 *     'is_locked' => ['bool', 'default' => false],
 *     'created_at' => ['datetime'],
 *     'settings' => ['json', 'null' => true],
 * ],
 * 'indexes' => ['slug' => ['unique' => true, 'columns' => ['slug']]],
 * 'foreign' => [['columns' => ['board_id'], 'references' => 'boards', 'on' => ['id'], 'delete' => 'CASCADE']],
 * ```
 * Table and index names are prefixed with {@see getTablePrefix} so several forums
 * may share one database.
 *
 * ```php
 * $schema = new BEForumSchema($connection, 'forum_');
 * if (!$schema->getIsInstalled()) {
 *     $schema->install();
 * }
 * $schema->upgrade();
 * ```
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumSchema extends TComponent
{
	/** The current schema version */
	public const VERSION = 1;

	/** The meta key holding the installed schema version */
	public const META_SCHEMA_VERSION = 'schema_version';

	/** The meta key holding the installation time */
	public const META_INSTALLED_AT = 'installed_at';

	public const DRIVER_SQLITE = 'sqlite';
	public const DRIVER_MYSQL = 'mysql';
	public const DRIVER_PGSQL = 'pgsql';

	/** @var null|TDbConnection the connection to operate on */
	private ?TDbConnection $_connection;

	/** @var string the table prefix */
	private string $_prefix;

	/** @var bool whether the forum message file has been registered with TException */
	private static bool $_messagesRegistered = false;

	/**
	 * Registers the forum error messages with {@see TException} so that the
	 * framework exceptions thrown here are readable before a forum module
	 * has been initialized (installers, shell, tests).
	 */
	public static function registerMessageFile(): void
	{
		if (!self::$_messagesRegistered) {
			self::$_messagesRegistered = true;
			TException::addMessageFile(BEForumException::getForumMessageFile());
		}
	}

	/**
	 * @param null|TDbConnection $connection the database connection
	 * @param string $prefix the table prefix
	 */
	public function __construct(?TDbConnection $connection = null, string $prefix = 'forum_')
	{
		static::registerMessageFile();
		$this->_connection = $connection;
		$this->setTablePrefix($prefix);
		parent::__construct();
	}

	/**
	 * @return null|TDbConnection the database connection
	 */
	public function getDbConnection(): ?TDbConnection
	{
		return $this->_connection;
	}

	/**
	 * @param null|TDbConnection $connection the database connection
	 */
	public function setDbConnection(?TDbConnection $connection): void
	{
		$this->_connection = $connection;
	}

	/**
	 * @return string the table prefix
	 */
	public function getTablePrefix(): string
	{
		return $this->_prefix;
	}

	/**
	 * @param string $prefix the table prefix; letters, digits and underscores only
	 * @throws TInvalidDataValueException when the prefix contains other characters
	 */
	public function setTablePrefix(string $prefix): void
	{
		if (!preg_match('/^[A-Za-z0-9_]*$/', $prefix)) {
			throw new TInvalidDataValueException('forum_table_prefix_invalid', $prefix);
		}
		$this->_prefix = $prefix;
	}

	/**
	 * @param string $name the unprefixed table name
	 * @return string the prefixed table name
	 */
	public function getTableName(string $name): string
	{
		return $this->_prefix . $name;
	}

	/**
	 * @return string[] the unprefixed table names in creation order
	 */
	public function getTableNames(): array
	{
		return array_keys(static::getTables());
	}

	/**
	 * @throws BEForumConfigurationException when there is no connection
	 * @return TDbConnection the active connection
	 */
	protected function ensureConnection(): TDbConnection
	{
		if ($this->_connection === null) {
			throw new BEForumConfigurationException('forum_schema_connection_required');
		}
		$this->_connection->setActive(true);
		return $this->_connection;
	}

	/**
	 * @throws BEForumConfigurationException when the driver is unsupported
	 * @return string the normalized driver name: sqlite, mysql or pgsql
	 */
	public function getDriverName(): string
	{
		return static::normalizeDriver($this->ensureConnection()->getDriverName());
	}

	/**
	 * @param string $driver a PDO driver name
	 * @throws BEForumConfigurationException when the driver is unsupported
	 * @return string the normalized driver name
	 */
	public static function normalizeDriver(string $driver): string
	{
		$driver = strtolower($driver);
		if (in_array($driver, ['sqlite', 'sqlite2', 'sqlite3'], true)) {
			return self::DRIVER_SQLITE;
		}
		if (in_array($driver, ['mysql', 'mysqli', 'mariadb'], true)) {
			return self::DRIVER_MYSQL;
		}
		if (in_array($driver, ['pgsql', 'postgres', 'postgresql'], true)) {
			return self::DRIVER_PGSQL;
		}
		throw new BEForumConfigurationException('forum_schema_driver_unsupported', $driver);
	}

	/**
	 * The declaration of every forum table, in creation order.
	 * @return array<string, array{columns: array<string, array>, indexes?: array<string, array>, foreign?: array<int, array>, primary?: string[]}>
	 */
	public static function getTables(): array
	{
		$id = ['pk'];
		$fk = fn (bool $null = false) => ['int', 'null' => $null];
		$counter = ['int', 'default' => 0];
		$flag = fn (bool $default = false) => ['bool', 'default' => $default];
		$created = ['datetime'];
		$updated = ['datetime', 'null' => true];
		$foreign = fn (string $column, string $table, string $delete = 'CASCADE') => ['columns' => [$column], 'references' => $table, 'on' => ['id'], 'delete' => $delete];

		return [
			'meta' => [
				'columns' => [
					'key' => ['string', 'length' => 64],
					'value' => ['text', 'null' => true],
				],
				'primary' => ['key'],
			],
			'categories' => [
				'columns' => [
					'id' => $id,
					'slug' => ['string', 'length' => 100],
					'name' => ['string', 'length' => 120],
					'description' => ['text', 'null' => true],
					'position' => $counter,
					'is_hidden' => $flag(),
					'board_count' => $counter,
					'created_at' => $created,
					'updated_at' => $updated,
				],
				'indexes' => [
					'slug' => ['unique' => true, 'columns' => ['slug']],
					'position' => ['columns' => ['position']],
				],
			],
			'members' => [
				'columns' => [
					'id' => $id,
					'username' => ['string', 'length' => 120],
					'display_name' => ['string', 'length' => 120, 'null' => true],
					'email' => ['string', 'length' => 254, 'null' => true],
					'avatar_url' => ['string', 'length' => 500, 'null' => true],
					'signature' => ['text', 'null' => true],
					'bio' => ['text', 'null' => true],
					'location' => ['string', 'length' => 120, 'null' => true],
					'website' => ['string', 'length' => 500, 'null' => true],
					'timezone' => ['string', 'length' => 64, 'null' => true],
					'post_count' => $counter,
					'thread_count' => $counter,
					'reputation' => $counter,
					'joined_at' => $created,
					'last_seen_at' => $updated,
					'last_post_at' => $updated,
					'is_banned' => $flag(),
					'banned_until' => $updated,
					'ban_reason' => ['string', 'length' => 500, 'null' => true],
					'warning_count' => $counter,
					'settings' => ['json', 'null' => true],
					'created_at' => $created,
					'updated_at' => $updated,
				],
				'indexes' => [
					'username' => ['unique' => true, 'columns' => ['username']],
					'reputation' => ['columns' => ['reputation']],
					'last_seen' => ['columns' => ['last_seen_at']],
				],
			],
			'boards' => [
				'columns' => [
					'id' => $id,
					'category_id' => $fk(),
					'parent_id' => $fk(true),
					'slug' => ['string', 'length' => 100],
					'name' => ['string', 'length' => 120],
					'description' => ['text', 'null' => true],
					'position' => $counter,
					'is_locked' => $flag(),
					'is_hidden' => $flag(),
					'is_private' => $flag(),
					'thread_count' => $counter,
					'post_count' => $counter,
					'last_thread_id' => $fk(true),
					'last_post_id' => $fk(true),
					'last_post_at' => $updated,
					'last_poster_member_id' => $fk(true),
					'created_at' => $created,
					'updated_at' => $updated,
				],
				'indexes' => [
					'slug' => ['unique' => true, 'columns' => ['slug']],
					'category' => ['columns' => ['category_id', 'position']],
					'parent' => ['columns' => ['parent_id']],
				],
				'foreign' => [
					$foreign('category_id', 'categories', 'RESTRICT'),
					$foreign('parent_id', 'boards', 'SET NULL'),
				],
			],
			'board_moderators' => [
				'columns' => [
					'id' => $id,
					'board_id' => $fk(),
					'member_id' => $fk(),
					'created_at' => $created,
				],
				'indexes' => [
					'board_member' => ['unique' => true, 'columns' => ['board_id', 'member_id']],
					'member' => ['columns' => ['member_id']],
				],
				'foreign' => [
					$foreign('board_id', 'boards'),
					$foreign('member_id', 'members'),
				],
			],
			'threads' => [
				'columns' => [
					'id' => $id,
					'board_id' => $fk(),
					'member_id' => $fk(true),
					'guest_name' => ['string', 'length' => 120, 'null' => true],
					'slug' => ['string', 'length' => 120],
					'title' => ['string', 'length' => 255],
					'type' => ['string', 'length' => 32, 'default' => 'discussion'],
					'is_pinned' => $flag(),
					'is_locked' => $flag(),
					'is_approved' => $flag(true),
					'is_deleted' => $flag(),
					'accepted_post_id' => $fk(true),
					'view_count' => $counter,
					'reply_count' => $counter,
					'first_post_id' => $fk(true),
					'last_post_id' => $fk(true),
					'last_post_at' => $updated,
					'last_poster_member_id' => $fk(true),
					'pinned_until' => $updated,
					'deleted_at' => $updated,
					'deleted_by_member_id' => $fk(true),
					'created_at' => $created,
					'updated_at' => $updated,
				],
				'indexes' => [
					'board_slug' => ['unique' => true, 'columns' => ['board_id', 'slug']],
					'board_listing' => ['columns' => ['board_id', 'is_deleted', 'is_pinned', 'last_post_at']],
					'member' => ['columns' => ['member_id']],
					'last_post' => ['columns' => ['last_post_at']],
				],
				'foreign' => [
					$foreign('board_id', 'boards'),
					$foreign('member_id', 'members', 'SET NULL'),
				],
			],
			'posts' => [
				'columns' => [
					'id' => $id,
					'thread_id' => $fk(),
					'board_id' => $fk(),
					'member_id' => $fk(true),
					'guest_name' => ['string', 'length' => 120, 'null' => true],
					'reply_to_post_id' => $fk(true),
					'position' => $counter,
					'content' => ['longtext'],
					'content_format' => ['string', 'length' => 16, 'default' => 'markdown'],
					'content_html' => ['longtext', 'null' => true],
					'ip_address' => ['string', 'length' => 64, 'null' => true],
					'is_approved' => $flag(true),
					'is_deleted' => $flag(),
					'deleted_at' => $updated,
					'deleted_by_member_id' => $fk(true),
					'edit_count' => $counter,
					'edited_at' => $updated,
					'edited_by_member_id' => $fk(true),
					'reaction_count' => $counter,
					'created_at' => $created,
					'updated_at' => $updated,
				],
				'indexes' => [
					'thread_position' => ['columns' => ['thread_id', 'position']],
					'member' => ['columns' => ['member_id', 'created_at']],
					'board_created' => ['columns' => ['board_id', 'created_at']],
					'created' => ['columns' => ['created_at']],
				],
				'foreign' => [
					$foreign('thread_id', 'threads'),
					$foreign('board_id', 'boards'),
					$foreign('member_id', 'members', 'SET NULL'),
				],
			],
			'post_revisions' => [
				'columns' => [
					'id' => $id,
					'post_id' => $fk(),
					'member_id' => $fk(true),
					'content' => ['longtext'],
					'content_format' => ['string', 'length' => 16, 'default' => 'markdown'],
					'reason' => ['string', 'length' => 255, 'null' => true],
					'created_at' => $created,
				],
				'indexes' => [
					'post' => ['columns' => ['post_id', 'created_at']],
				],
				'foreign' => [
					$foreign('post_id', 'posts'),
				],
			],
			'tags' => [
				'columns' => [
					'id' => $id,
					'slug' => ['string', 'length' => 64],
					'name' => ['string', 'length' => 64],
					'thread_count' => $counter,
					'created_at' => $created,
				],
				'indexes' => [
					'slug' => ['unique' => true, 'columns' => ['slug']],
				],
			],
			'thread_tags' => [
				'columns' => [
					'id' => $id,
					'thread_id' => $fk(),
					'tag_id' => $fk(),
					'created_at' => $created,
				],
				'indexes' => [
					'thread_tag' => ['unique' => true, 'columns' => ['thread_id', 'tag_id']],
					'tag' => ['columns' => ['tag_id']],
				],
				'foreign' => [
					$foreign('thread_id', 'threads'),
					$foreign('tag_id', 'tags'),
				],
			],
			'attachments' => [
				'columns' => [
					'id' => $id,
					'post_id' => $fk(),
					'member_id' => $fk(true),
					'file_name' => ['string', 'length' => 255],
					'stored_name' => ['string', 'length' => 255],
					'mime_type' => ['string', 'length' => 128],
					'size' => ['bigint', 'default' => 0],
					'download_count' => $counter,
					'created_at' => $created,
				],
				'indexes' => [
					'post' => ['columns' => ['post_id']],
				],
				'foreign' => [
					$foreign('post_id', 'posts'),
				],
			],
			'polls' => [
				'columns' => [
					'id' => $id,
					'thread_id' => $fk(),
					'question' => ['string', 'length' => 255],
					'max_choices' => ['int', 'default' => 1],
					'closes_at' => $updated,
					'is_closed' => $flag(),
					'allow_revote' => $flag(),
					'vote_count' => $counter,
					'created_at' => $created,
					'updated_at' => $updated,
				],
				'indexes' => [
					'thread' => ['unique' => true, 'columns' => ['thread_id']],
				],
				'foreign' => [
					$foreign('thread_id', 'threads'),
				],
			],
			'poll_options' => [
				'columns' => [
					'id' => $id,
					'poll_id' => $fk(),
					'position' => $counter,
					'label' => ['string', 'length' => 255],
					'vote_count' => $counter,
				],
				'indexes' => [
					'poll' => ['columns' => ['poll_id', 'position']],
				],
				'foreign' => [
					$foreign('poll_id', 'polls'),
				],
			],
			'poll_votes' => [
				'columns' => [
					'id' => $id,
					'poll_id' => $fk(),
					'option_id' => $fk(),
					'member_id' => $fk(),
					'created_at' => $created,
				],
				'indexes' => [
					'vote' => ['unique' => true, 'columns' => ['poll_id', 'option_id', 'member_id']],
					'member' => ['columns' => ['poll_id', 'member_id']],
				],
				'foreign' => [
					$foreign('poll_id', 'polls'),
					$foreign('option_id', 'poll_options'),
					$foreign('member_id', 'members'),
				],
			],
			'reactions' => [
				'columns' => [
					'id' => $id,
					'post_id' => $fk(),
					'member_id' => $fk(),
					'type' => ['string', 'length' => 32],
					'created_at' => $created,
				],
				'indexes' => [
					'reaction' => ['unique' => true, 'columns' => ['post_id', 'member_id', 'type']],
					'member' => ['columns' => ['member_id']],
				],
				'foreign' => [
					$foreign('post_id', 'posts'),
					$foreign('member_id', 'members'),
				],
			],
			'subscriptions' => [
				'columns' => [
					'id' => $id,
					'member_id' => $fk(),
					'target_type' => ['string', 'length' => 16],
					'target_id' => $fk(),
					'created_at' => $created,
				],
				'indexes' => [
					'subscription' => ['unique' => true, 'columns' => ['member_id', 'target_type', 'target_id']],
					'target' => ['columns' => ['target_type', 'target_id']],
				],
				'foreign' => [
					$foreign('member_id', 'members'),
				],
			],
			'notifications' => [
				'columns' => [
					'id' => $id,
					'member_id' => $fk(),
					'type' => ['string', 'length' => 32],
					'actor_member_id' => $fk(true),
					'target_type' => ['string', 'length' => 16, 'null' => true],
					'target_id' => $fk(true),
					'data' => ['json', 'null' => true],
					'is_read' => $flag(),
					'read_at' => $updated,
					'created_at' => $created,
				],
				'indexes' => [
					'member' => ['columns' => ['member_id', 'is_read', 'created_at']],
				],
				'foreign' => [
					$foreign('member_id', 'members'),
				],
			],
			'reports' => [
				'columns' => [
					'id' => $id,
					'post_id' => $fk(),
					'reporter_member_id' => $fk(true),
					'reason' => ['text'],
					'status' => ['string', 'length' => 16, 'default' => 'open'],
					'handled_by_member_id' => $fk(true),
					'handled_at' => $updated,
					'resolution' => ['text', 'null' => true],
					'created_at' => $created,
					'updated_at' => $updated,
				],
				'indexes' => [
					'status' => ['columns' => ['status', 'created_at']],
					'post' => ['columns' => ['post_id']],
				],
				'foreign' => [
					$foreign('post_id', 'posts'),
				],
			],
			'thread_reads' => [
				'columns' => [
					'id' => $id,
					'member_id' => $fk(),
					'thread_id' => $fk(),
					'last_read_post_id' => $fk(true),
					'read_at' => $created,
				],
				'indexes' => [
					'member_thread' => ['unique' => true, 'columns' => ['member_id', 'thread_id']],
				],
				'foreign' => [
					$foreign('member_id', 'members'),
					$foreign('thread_id', 'threads'),
				],
			],
			'moderation_log' => [
				'columns' => [
					'id' => $id,
					'member_id' => $fk(true),
					'action' => ['string', 'length' => 64],
					'target_type' => ['string', 'length' => 16],
					'target_id' => $fk(),
					'details' => ['json', 'null' => true],
					'created_at' => $created,
				],
				'indexes' => [
					'target' => ['columns' => ['target_type', 'target_id']],
					'created' => ['columns' => ['created_at']],
				],
			],
			'bookmarks' => [
				'columns' => [
					'id' => $id,
					'member_id' => $fk(),
					'post_id' => $fk(),
					'created_at' => $created,
				],
				'indexes' => [
					'bookmark' => ['unique' => true, 'columns' => ['member_id', 'post_id']],
				],
				'foreign' => [
					$foreign('member_id', 'members'),
					$foreign('post_id', 'posts'),
				],
			],
			'badges' => [
				'columns' => [
					'id' => $id,
					'slug' => ['string', 'length' => 64],
					'name' => ['string', 'length' => 120],
					'description' => ['string', 'length' => 500, 'null' => true],
					'icon' => ['string', 'length' => 255, 'null' => true],
					'created_at' => $created,
				],
				'indexes' => [
					'slug' => ['unique' => true, 'columns' => ['slug']],
				],
			],
			'member_badges' => [
				'columns' => [
					'id' => $id,
					'member_id' => $fk(),
					'badge_id' => $fk(),
					'awarded_by_member_id' => $fk(true),
					'awarded_at' => $created,
				],
				'indexes' => [
					'member_badge' => ['unique' => true, 'columns' => ['member_id', 'badge_id']],
				],
				'foreign' => [
					$foreign('member_id', 'members'),
					$foreign('badge_id', 'badges'),
				],
			],
		];
	}

	/**
	 * Quotes an identifier for a driver.
	 * @param string $name the identifier
	 * @param string $driver the normalized driver
	 * @return string the quoted identifier
	 */
	public static function quoteIdentifier(string $name, string $driver): string
	{
		return $driver === self::DRIVER_MYSQL ? '`' . $name . '`' : '"' . $name . '"';
	}

	/**
	 * Maps a DSL column type to a driver column type.
	 * @param array $column the column definition
	 * @param string $driver the normalized driver
	 * @throws TInvalidDataValueException on an unknown DSL type
	 * @return string the SQL type
	 */
	public static function getColumnType(array $column, string $driver): string
	{
		$type = $column[0] ?? 'string';
		$length = (int) ($column['length'] ?? 255);
		switch ($type) {
			case 'pk':
				return match ($driver) {
					self::DRIVER_SQLITE => 'INTEGER PRIMARY KEY AUTOINCREMENT',
					self::DRIVER_MYSQL => 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY',
					default => 'SERIAL PRIMARY KEY',
				};
			case 'int':
				return $driver === self::DRIVER_MYSQL ? 'INT' : 'INTEGER';
			case 'bigint':
				return $driver === self::DRIVER_SQLITE ? 'INTEGER' : 'BIGINT';
			case 'float':
				return match ($driver) {
					self::DRIVER_SQLITE => 'REAL',
					self::DRIVER_MYSQL => 'DOUBLE',
					default => 'DOUBLE PRECISION',
				};
			case 'bool':
				return match ($driver) {
					self::DRIVER_SQLITE => 'INTEGER',
					self::DRIVER_MYSQL => 'TINYINT(1)',
					default => 'BOOLEAN',
				};
			case 'string':
				return 'VARCHAR(' . max(1, $length) . ')';
			case 'text':
			case 'json':
				return 'TEXT';
			case 'longtext':
				return $driver === self::DRIVER_MYSQL ? 'MEDIUMTEXT' : 'TEXT';
			case 'datetime':
				return match ($driver) {
					self::DRIVER_SQLITE => 'DATETIME',
					self::DRIVER_MYSQL => 'DATETIME',
					default => 'TIMESTAMP',
				};
		}
		throw new TInvalidDataValueException('forum_schema_column_type_unknown', $type);
	}

	/**
	 * Formats a DSL default value as an SQL literal.
	 * @param array $column the column definition
	 * @param string $driver the normalized driver
	 * @return null|string the SQL literal, null when the column has no default
	 */
	public static function getColumnDefault(array $column, string $driver): ?string
	{
		if (!array_key_exists('default', $column)) {
			return null;
		}
		$default = $column['default'];
		if ($default === null) {
			return 'NULL';
		}
		if (is_bool($default)) {
			if ($driver === self::DRIVER_PGSQL) {
				return $default ? 'TRUE' : 'FALSE';
			}
			return $default ? '1' : '0';
		}
		if (is_int($default) || is_float($default)) {
			return (string) $default;
		}
		return "'" . str_replace("'", "''", (string) $default) . "'";
	}

	/**
	 * Builds the column clause of a CREATE TABLE statement.
	 * @param string $name the column name
	 * @param array $column the column definition
	 * @param string $driver the normalized driver
	 * @return string the column clause
	 */
	public static function getColumnDefinition(string $name, array $column, string $driver): string
	{
		$sql = static::quoteIdentifier($name, $driver) . ' ' . static::getColumnType($column, $driver);
		if (($column[0] ?? '') === 'pk') {
			return $sql;
		}
		$sql .= ($column['null'] ?? false) ? ' NULL' : ' NOT NULL';
		if (($default = static::getColumnDefault($column, $driver)) !== null) {
			$sql .= ' DEFAULT ' . $default;
		}
		return $sql;
	}

	/**
	 * Builds the CREATE TABLE and CREATE INDEX statements of one table.
	 * @param string $table the unprefixed table name
	 * @param string $driver the normalized driver
	 * @throws TInvalidDataValueException when the table is unknown
	 * @return string[] the statements
	 */
	public function getCreateStatementsFor(string $table, string $driver): array
	{
		$definition = static::getTables()[$table] ?? null;
		if ($definition === null) {
			throw new TInvalidDataValueException('forum_schema_table_unknown', $table);
		}
		$q = fn (string $name) => static::quoteIdentifier($name, $driver);
		$fullName = $this->getTableName($table);
		$lines = [];
		foreach ($definition['columns'] as $name => $column) {
			$lines[] = "\t" . static::getColumnDefinition($name, $column, $driver);
		}
		if (!empty($definition['primary'])) {
			$lines[] = "\tPRIMARY KEY (" . implode(', ', array_map($q, $definition['primary'])) . ')';
		}
		foreach ($definition['foreign'] ?? [] as $i => $foreign) {
			$constraint = $q('fk_' . $fullName . '_' . implode('_', $foreign['columns']));
			$line = "\tCONSTRAINT " . $constraint . ' FOREIGN KEY (' . implode(', ', array_map($q, $foreign['columns'])) . ')'
				. ' REFERENCES ' . $q($this->getTableName($foreign['references'])) . ' (' . implode(', ', array_map($q, $foreign['on'])) . ')';
			if (!empty($foreign['delete'])) {
				$line .= ' ON DELETE ' . $foreign['delete'];
			}
			$lines[] = $line;
		}
		$sql = 'CREATE TABLE ' . $q($fullName) . " (\n" . implode(",\n", $lines) . "\n)";
		if ($driver === self::DRIVER_MYSQL) {
			$sql .= ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
		}
		$statements = [$sql];
		foreach ($definition['indexes'] ?? [] as $indexName => $index) {
			$statements[] = 'CREATE ' . (!empty($index['unique']) ? 'UNIQUE ' : '') . 'INDEX '
				. $q('ix_' . $fullName . '_' . $indexName) . ' ON ' . $q($fullName)
				. ' (' . implode(', ', array_map($q, $index['columns'])) . ')';
		}
		return $statements;
	}

	/**
	 * Builds the DDL of every table.
	 * @param null|string $driver the driver, null uses the connection driver
	 * @return string[] the statements in execution order
	 */
	public function getCreateStatements(?string $driver = null): array
	{
		$driver = $driver === null ? $this->getDriverName() : static::normalizeDriver($driver);
		$statements = [];
		foreach ($this->getTableNames() as $table) {
			foreach ($this->getCreateStatementsFor($table, $driver) as $statement) {
				$statements[] = $statement;
			}
		}
		return $statements;
	}

	/**
	 * Builds the DROP TABLE statements of every table, children first.
	 * @param null|string $driver the driver, null uses the connection driver
	 * @return string[] the statements in execution order
	 */
	public function getDropStatements(?string $driver = null): array
	{
		$driver = $driver === null ? $this->getDriverName() : static::normalizeDriver($driver);
		$statements = [];
		foreach (array_reverse($this->getTableNames()) as $table) {
			$statements[] = 'DROP TABLE IF EXISTS ' . static::quoteIdentifier($this->getTableName($table), $driver);
		}
		return $statements;
	}

	/**
	 * @return string[] the prefixed names of the tables existing in the database
	 */
	public function findExistingTables(): array
	{
		$connection = $this->ensureConnection();
		$existing = array_map('strtolower', $connection->getDbMetaData()->findTableNames());
		$found = [];
		foreach ($this->getTableNames() as $table) {
			$name = $this->getTableName($table);
			if (in_array(strtolower($name), $existing, true)) {
				$found[] = $name;
			}
		}
		return $found;
	}

	/**
	 * @param string $table the unprefixed table name
	 * @return bool whether the table exists in the database
	 */
	public function tableExists(string $table): bool
	{
		return in_array($this->getTableName($table), $this->findExistingTables(), true);
	}

	/**
	 * @return bool whether the meta table exists and records a schema version
	 */
	public function getIsInstalled(): bool
	{
		return $this->getInstalledVersion() > 0;
	}

	/**
	 * @return int the installed schema version, 0 when not installed
	 */
	public function getInstalledVersion(): int
	{
		if (!$this->tableExists('meta')) {
			return 0;
		}
		return (int) $this->getMeta(self::META_SCHEMA_VERSION, '0');
	}

	/**
	 * @return bool whether the installed schema is older than {@see VERSION}
	 */
	public function getNeedsUpgrade(): bool
	{
		$version = $this->getInstalledVersion();
		return $version > 0 && $version < static::VERSION;
	}

	/**
	 * Reads a meta value.
	 * @param string $key the meta key
	 * @param null|string $default the value when the key is missing
	 * @return null|string the value
	 */
	public function getMeta(string $key, ?string $default = null): ?string
	{
		$connection = $this->ensureConnection();
		$driver = $this->getDriverName();
		$command = $connection->createCommand('SELECT ' . static::quoteIdentifier('value', $driver) . ' FROM ' . static::quoteIdentifier($this->getTableName('meta'), $driver) . ' WHERE ' . static::quoteIdentifier('key', $driver) . ' = :key');
		$command->bindValue(':key', $key);
		$value = $command->queryScalar();
		return ($value === false || $value === null) ? $default : (string) $value;
	}

	/**
	 * Writes a meta value.
	 * @param string $key the meta key
	 * @param null|string $value the value
	 */
	public function setMeta(string $key, ?string $value): void
	{
		$connection = $this->ensureConnection();
		$driver = $this->getDriverName();
		$table = static::quoteIdentifier($this->getTableName('meta'), $driver);
		$keyColumn = static::quoteIdentifier('key', $driver);
		$valueColumn = static::quoteIdentifier('value', $driver);
		// the existence is tested explicitly: MySQL reports changed rather than matched rows for UPDATE
		$exists = $connection->createCommand('SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $keyColumn . ' = :key');
		$exists->bindValue(':key', $key);
		$sql = ((int) $exists->queryScalar()) > 0
			? 'UPDATE ' . $table . ' SET ' . $valueColumn . ' = :value WHERE ' . $keyColumn . ' = :key'
			: 'INSERT INTO ' . $table . ' (' . $keyColumn . ', ' . $valueColumn . ') VALUES (:key, :value)';
		$command = $connection->createCommand($sql);
		$command->bindValue(':key', $key);
		$command->bindValue(':value', $value);
		$command->execute();
	}

	/**
	 * Creates every table that does not exist yet and records the schema version.
	 * Existing tables are left untouched; use {@see upgrade} to migrate them.
	 * @return string[] the prefixed names of the tables that were created
	 */
	public function install(): array
	{
		$connection = $this->ensureConnection();
		$driver = $this->getDriverName();
		$existing = $this->findExistingTables();
		$created = [];
		foreach ($this->getTableNames() as $table) {
			$name = $this->getTableName($table);
			if (in_array($name, $existing, true)) {
				continue;
			}
			foreach ($this->getCreateStatementsFor($table, $driver) as $statement) {
				$connection->createCommand($statement)->execute();
			}
			$created[] = $name;
		}
		if ($this->getInstalledVersion() === 0) {
			$this->setMeta(self::META_SCHEMA_VERSION, (string) static::VERSION);
			$this->setMeta(self::META_INSTALLED_AT, BEForumTime::now());
		}
		return $created;
	}

	/**
	 * The migrations keyed by the version they upgrade to.  Version 1 is the
	 * initial installation.  Each callable receives the schema and connection.
	 * @return array<int, callable(BEForumSchema, TDbConnection): void>
	 */
	public function getMigrations(): array
	{
		return [
			1 => function (BEForumSchema $schema, TDbConnection $connection): void {
				$schema->install();
			},
		];
	}

	/**
	 * Applies every migration newer than the installed version.  A fresh
	 * database is installed at the current version directly, because
	 * {@see install} creates the current shape of every table.
	 * @return int the schema version after upgrading
	 */
	public function upgrade(): int
	{
		$connection = $this->ensureConnection();
		$version = $this->getInstalledVersion();
		if ($version === 0) {
			$this->install();
			return $this->getInstalledVersion();
		}
		foreach ($this->getMigrations() as $target => $migration) {
			if ($target <= $version) {
				continue;
			}
			$migration($this, $connection);
			$this->setMeta(self::META_SCHEMA_VERSION, (string) $target);
			$version = $target;
		}
		return $version;
	}

	/**
	 * Drops every forum table.
	 */
	public function drop(): void
	{
		$connection = $this->ensureConnection();
		foreach ($this->getDropStatements() as $statement) {
			$connection->createCommand($statement)->execute();
		}
	}
}
