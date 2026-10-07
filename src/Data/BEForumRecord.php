<?php

/**
 * BEForumRecord class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Data;

use Belisoful\Forum\Util\BEForumTime;
use Prado\Data\ActiveRecord\TActiveRecord;
use Prado\Data\ActiveRecord\TActiveRecordCriteria;
use Prado\Data\Common\TDbCommandBuilder;
use Prado\Data\TDbConnection;
use Prado\Exceptions\TInvalidDataValueException;

/**
 * BEForumRecord class.
 *
 * BEForumRecord is the base Active Record of the forum.  It adds to
 * {@see \Prado\Data\ActiveRecord\TActiveRecord}:
 *  - prefix aware table names: subclasses declare `TABLE_NAME` (without prefix)
 *    and {@see table} prepends {@see getTablePrefix};
 *  - a forum wide database connection ({@see setForumDbConnection}) so the host
 *    application's `TActiveRecordManager` configuration is not touched;
 *  - automatic `created_at`/`updated_at` timestamps in UTC;
 *  - normalisation of boolean columns (`BOOLEAN_COLUMNS`) before saving so every
 *    driver receives real booleans, and boolean accessors through {@see flag};
 *  - JSON column helpers ({@see getJsonColumn}, {@see setJsonColumn});
 *  - a late static binding {@see finder} so `BEForumThread::finder()` works
 *    without each subclass overriding it;
 *  - query helpers ({@see criteria}, {@see findAllPaged}, {@see refresh}).
 *
 * Relation properties declared in `$RELATIONS` are read lazily as
 * `$record->relationName`; they are intentionally not declared as public
 * properties or accessor methods so `TActiveRecord::__get` performs the fetch.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
abstract class BEForumRecord extends TActiveRecord
{
	/** The unprefixed table name; subclasses override */
	public const TABLE_NAME = '';

	/** @var string[] the boolean columns of the table */
	public const BOOLEAN_COLUMNS = [];

	/** @var string[] the JSON encoded text columns of the table */
	public const JSON_COLUMNS = [];

	/** @var string the shared table prefix */
	private static string $_tablePrefix = 'forum_';

	/** @var null|callable|TDbConnection the shared forum connection or a callable returning it */
	private static $_forumConnection;

	/**
	 * @return string the prefixed table name
	 */
	public function table(): string
	{
		return self::$_tablePrefix . static::TABLE_NAME;
	}

	/**
	 * @return string the shared table prefix
	 */
	public static function getTablePrefix(): string
	{
		return self::$_tablePrefix;
	}

	/**
	 * @param string $prefix the shared table prefix; letters, digits and underscores only
	 * @throws TInvalidDataValueException when the prefix contains other characters
	 */
	public static function setTablePrefix(string $prefix): void
	{
		if (!preg_match('/^[A-Za-z0-9_]*$/', $prefix)) {
			throw new TInvalidDataValueException('forum_table_prefix_invalid', $prefix);
		}
		self::$_tablePrefix = $prefix;
	}

	/**
	 * Resolves the shared forum connection; a callable provider is invoked on
	 * first use so the database is only opened when a record needs it.
	 * @return null|TDbConnection the shared forum connection
	 */
	public static function getForumDbConnection(): ?TDbConnection
	{
		if (self::$_forumConnection !== null && !(self::$_forumConnection instanceof TDbConnection)) {
			$connection = call_user_func(self::$_forumConnection);
			self::$_forumConnection = $connection instanceof TDbConnection ? $connection : null;
		}
		return self::$_forumConnection;
	}

	/**
	 * @param null|callable|TDbConnection $connection the shared forum connection (or a callable returning it) used by every record without an explicit connection
	 */
	public static function setForumDbConnection($connection): void
	{
		self::$_forumConnection = $connection;
	}

	/**
	 * Returns the record connection: the explicit one, then the shared forum
	 * connection, then the TActiveRecordManager connection.
	 * @return TDbConnection the connection
	 */
	public function getDbConnection()
	{
		if ($this->_connection === null && ($connection = self::getForumDbConnection()) !== null) {
			$connection->setActive(true);
			return $connection;
		}
		return parent::getDbConnection();
	}

	/**
	 * Returns the shared finder of the called class.
	 * @param null|string $className the record class, defaults to the called class
	 * @return static the finder instance
	 */
	public static function finder($className = null)
	{
		return parent::finder($className ?? static::class);
	}

	/**
	 * Creates a criteria object.
	 * @param null|string $condition the SQL condition
	 * @param array $parameters the bound parameters
	 * @param null|array|string $orderBy the ordering, e.g. `['created_at' => 'desc']` or `"created_at desc"`
	 * @param null|int $limit the maximum rows
	 * @param null|int $offset the row offset
	 * @return TActiveRecordCriteria the criteria
	 */
	public static function criteria(?string $condition = null, array $parameters = [], $orderBy = null, ?int $limit = null, ?int $offset = null): TActiveRecordCriteria
	{
		$criteria = new TActiveRecordCriteria($condition, $parameters);
		if ($orderBy !== null) {
			$criteria->setOrdersBy($orderBy);
		}
		if ($limit !== null) {
			$criteria->setLimit($limit);
		}
		if ($offset !== null) {
			$criteria->setOffset($offset);
		}
		return $criteria;
	}

	/**
	 * Finds a page of records.
	 * @param null|string $condition the SQL condition
	 * @param array $parameters the bound parameters
	 * @param null|array|string $orderBy the ordering
	 * @param int $pageSize the page size
	 * @param int $page the 1-based page
	 * @return static[] the records
	 */
	public static function findAllPaged(?string $condition, array $parameters, $orderBy, int $pageSize, int $page = 1): array
	{
		$pageSize = max(1, $pageSize);
		$page = max(1, $page);
		return static::finder()->findAll(static::criteria($condition, $parameters, $orderBy, $pageSize, ($page - 1) * $pageSize));
	}

	/**
	 * @return null|int the primary key value, null for unsaved records
	 */
	public function getId(): ?int
	{
		$id = $this->getColumnValue('id');
		return $id === null ? null : (int) $id;
	}

	/**
	 * @return bool whether the record has not been saved yet
	 */
	public function getIsNew(): bool
	{
		return $this->_recordState === self::STATE_NEW;
	}

	/**
	 * @return bool whether the record has been deleted
	 */
	public function getIsDeletedRecord(): bool
	{
		return $this->_recordState === self::STATE_DELETED;
	}

	/**
	 * Reads a boolean column.
	 * @param string $column the column name
	 * @return bool the value
	 */
	protected function flag(string $column): bool
	{
		$value = $this->getColumnValue($column);
		if (is_string($value)) {
			return !in_array(strtolower($value), ['', '0', 'f', 'false', 'n', 'no'], true);
		}
		return (bool) $value;
	}

	/**
	 * Reads a JSON column as an array.
	 * @param string $column the column name
	 * @return array the decoded value, empty when null or invalid
	 */
	public function getJsonColumn(string $column): array
	{
		$value = $this->getColumnValue($column);
		if (is_array($value)) {
			return $value;
		}
		if (!is_string($value) || $value === '') {
			return [];
		}
		$decoded = json_decode($value, true);
		return is_array($decoded) ? $decoded : [];
	}

	/**
	 * Writes an array to a JSON column.
	 * @param string $column the column name
	 * @param null|array $value the value, null clears the column
	 */
	public function setJsonColumn(string $column, ?array $value): void
	{
		$this->setColumnValue($column, $value === null ? null : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	}

	/**
	 * Casts boolean and JSON columns to their storage representation.
	 */
	protected function normalizeColumns(): void
	{
		// PostgreSQL BOOLEAN columns need real booleans; SQLite and MySQL store 0/1
		$asBoolean = str_starts_with(strtolower((string) $this->getDbConnection()->getDriverName()), 'pgsql');
		foreach (static::BOOLEAN_COLUMNS as $column) {
			$value = $this->flag($column);
			$this->setColumnValue($column, $asBoolean ? $value : ($value ? 1 : 0));
		}
		foreach (static::JSON_COLUMNS as $column) {
			$value = $this->getColumnValue($column);
			if (is_array($value)) {
				$this->setJsonColumn($column, $value);
			}
		}
	}

	/**
	 * @return bool whether the table has a `created_at` column
	 */
	protected function hasCreatedAt(): bool
	{
		return property_exists($this, 'created_at');
	}

	/**
	 * @return bool whether the table has an `updated_at` column
	 */
	protected function hasUpdatedAt(): bool
	{
		return property_exists($this, 'updated_at');
	}

	/**
	 * Stamps `created_at` and normalizes columns before insertion.
	 * @param \Prado\Data\ActiveRecord\TActiveRecordChangeEventParameter $param the event parameter
	 */
	public function onInsert($param)
	{
		if ($this->hasCreatedAt() && !$this->getColumnValue('created_at')) {
			$this->setColumnValue('created_at', BEForumTime::now());
		}
		$this->normalizeColumns();
		parent::onInsert($param);
	}

	/**
	 * Stamps `updated_at` and normalizes columns before an update.
	 * @param \Prado\Data\ActiveRecord\TActiveRecordChangeEventParameter $param the event parameter
	 */
	public function onUpdate($param)
	{
		if ($this->hasUpdatedAt()) {
			$this->setColumnValue('updated_at', BEForumTime::now());
		}
		$this->normalizeColumns();
		parent::onUpdate($param);
	}

	/**
	 * Reloads every column from the database.
	 * @return static the record
	 */
	public function refresh(): static
	{
		$fresh = static::finder()->findByPk($this->getId());
		if ($fresh instanceof static) {
			$this->copyFrom($fresh->toArray());
		}
		return $this;
	}

	/**
	 * Finds a record by primary key or returns null.
	 * @param null|int|string $id the primary key
	 * @return null|static the record
	 */
	public static function findOne($id): ?static
	{
		if ($id === null || $id === '' || (int) $id <= 0) {
			return null;
		}
		$record = static::finder()->findByPk((int) $id);
		return $record instanceof static ? $record : null;
	}

	/**
	 * Counts the records matching a condition.
	 * @param null|string $condition the SQL condition
	 * @param array $parameters the bound parameters
	 * @return int the count
	 */
	public static function countWhere(?string $condition = null, array $parameters = []): int
	{
		return (int) static::finder()->count($condition, $parameters);
	}

	/**
	 * Executes a raw SQL statement against the record table using the record connection.
	 * @param string $sql the SQL with `{table}` replaced by the prefixed table name
	 * @param array $parameters the bound parameters
	 * @return int the number of affected rows
	 */
	public static function execute(string $sql, array $parameters = []): int
	{
		$record = static::finder();
		$connection = $record->getDbConnection();
		$asBoolean = str_starts_with(strtolower((string) $connection->getDriverName()), 'pgsql');
		$command = $connection->createCommand(str_replace('{table}', $connection->quoteTableName($record->table()), $sql));
		foreach ($parameters as $name => $value) {
			if (is_bool($value) && !$asBoolean) {
				$value = $value ? 1 : 0;
			}
			$command->bindValue(is_int($name) ? $name + 1 : (str_starts_with($name, ':') ? $name : ':' . $name), $value, TDbCommandBuilder::getPdoType($value));
		}
		return (int) $command->execute();
	}
}
