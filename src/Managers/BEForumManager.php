<?php

/**
 * BEForumManager class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Managers;

use Belisoful\Forum\BEForumEventParameter;
use Belisoful\Forum\BEForumModule;
use Belisoful\Forum\Data\BEForumBoard;
use Belisoful\Forum\Data\BEForumMember;
use Belisoful\Forum\Data\BEForumRecord;
use Belisoful\Forum\Exceptions\BEForumForbiddenException;
use Belisoful\Forum\Exceptions\BEForumValidationException;
use Belisoful\Forum\Security\BEForumPermissions;
use Belisoful\Forum\Util\BEForumPagination;
use Belisoful\Forum\Util\BEForumTime;
use Prado\Data\ActiveRecord\TActiveRecordCriteria;
use Prado\Data\TDbConnection;
use Prado\Security\IUser;
use Prado\TComponent;

/**
 * BEForumManager class.
 *
 * BEForumManager is the base class of the domain managers.  A manager owns
 * one area of the forum (threads, posts, members, ...) and exposes every
 * operation of that area as a method that validates input, authorizes the
 * current user, changes the records inside a transaction and raises the
 * corresponding module event.  Template controls and pages only ever call
 * managers; they never touch records directly for writes.
 *
 * The base class provides access to the module, the current user and member,
 * authorization shortcuts, transactions, validation helpers, a per request
 * cache and event raising.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
abstract class BEForumManager extends TComponent
{
	/** @var \WeakReference<BEForumModule> the module */
	private \WeakReference $_module;

	/** @var array<string, mixed> the per request cache */
	private array $_cache = [];

	/**
	 * @param BEForumModule $module the module
	 */
	public function __construct(BEForumModule $module)
	{
		$this->_module = \WeakReference::create($module);
		parent::__construct();
	}

	/**
	 * @return BEForumModule the module
	 */
	public function getModule(): BEForumModule
	{
		return $this->_module->get();
	}

	/**
	 * @return TDbConnection the forum database connection with the schema ensured
	 */
	public function getDbConnection(): TDbConnection
	{
		$module = $this->getModule();
		$module->ensureSchema();
		return $module->getDbConnection();
	}

	/**
	 * @return null|IUser the current application user
	 */
	public function getUser(): ?IUser
	{
		return $this->getModule()->getUser();
	}

	/**
	 * @return null|BEForumMember the member of the current user, null for guests
	 */
	public function getMember(): ?BEForumMember
	{
		return $this->getModule()->getMembers()->getCurrentMember();
	}

	/**
	 * @return bool whether the current user is a guest (or there is no user)
	 */
	public function getIsGuest(): bool
	{
		$user = $this->getUser();
		return $user === null || $user->getIsGuest();
	}

	/**
	 * @return string the current user name, empty for guests
	 */
	public function getUsername(): string
	{
		$user = $this->getUser();
		return ($user === null || $user->getIsGuest()) ? '' : (string) $user->getName();
	}

	/**
	 * Returns the current member or throws when the user is a guest.
	 * @param string $permission the permission reported when denied
	 * @throws BEForumForbiddenException when the current user is a guest or banned
	 * @return BEForumMember the member
	 */
	public function requireMember(string $permission = BEForumPermissions::VIEW): BEForumMember
	{
		$member = $this->getMember();
		if ($member === null) {
			throw new BEForumForbiddenException($permission, 'forum_login_required');
		}
		if ($member->getIsBanned()) {
			throw new BEForumForbiddenException($permission, 'forum_member_banned', $member->getDisplayName());
		}
		return $member;
	}

	/**
	 * Throws when the current member is banned.
	 * @param string $permission the permission reported when denied
	 * @throws BEForumForbiddenException when the current member is banned
	 */
	protected function ensureNotBanned(string $permission): void
	{
		$member = $this->getMember();
		if ($member !== null && $member->getIsBanned()) {
			throw new BEForumForbiddenException($permission, 'forum_member_banned', $member->getDisplayName());
		}
	}

	/**
	 * Authorizes the current user for a permission (throws when denied).
	 * @param string $permission the permission name
	 * @param null|array $extra extra rule data
	 * @throws BEForumForbiddenException when denied
	 * @return bool true
	 */
	protected function authorize(string $permission, ?array $extra = null): bool
	{
		return $this->getModule()->authorize($permission, $extra, true);
	}

	/**
	 * @param string $permission the permission name
	 * @param null|array $extra extra rule data
	 * @return bool whether the current user holds the permission
	 */
	public function can(string $permission, ?array $extra = null): bool
	{
		return $this->getModule()->authorize($permission, $extra, false);
	}

	/**
	 * Builds the extra rule data for an action on content of a board.
	 * @param null|BEForumBoard|int $board the board or its id
	 * @param null|string $ownerUsername the username of the content owner
	 * @return array the extra data with `moderators` and optionally `username`
	 */
	protected function extraFor($board, ?string $ownerUsername = null): array
	{
		$extra = ['moderators' => $board === null ? [] : $this->getModule()->getMembers()->getBoardModeratorUsernames($board instanceof BEForumBoard ? $board->getId() : (int) $board)];
		if ($ownerUsername !== null && $ownerUsername !== '') {
			$extra['username'] = $ownerUsername;
		}
		return $extra;
	}

	/**
	 * @param null|BEForumBoard|int $board the board or its id
	 * @return bool whether the current user may moderate the board (or globally when null)
	 */
	public function isModerator($board = null): bool
	{
		return $this->can(BEForumPermissions::MODERATE, $this->extraFor($board));
	}

	/**
	 * Runs a callable inside a database transaction; nested calls join the
	 * running transaction.
	 * @template T
	 * @param callable(): T $callable the work
	 * @throws \Throwable rethrows after rolling back
	 * @return T the callable result
	 */
	public function transaction(callable $callable)
	{
		$connection = $this->getDbConnection();
		$current = $connection->getCurrentTransaction();
		if ($current !== null && $current->getActive()) {
			return $callable();
		}
		$transaction = $connection->beginTransaction();
		try {
			$result = $callable();
			$transaction->commit();
			return $result;
		} catch (\Throwable $e) {
			if ($transaction->getActive()) {
				$transaction->rollback();
			}
			throw $e;
		}
	}

	/**
	 * Raises a module event.
	 * @param string $event the event name
	 * @param null|BEForumRecord $record the subject record
	 * @param array $data extra event data
	 * @param null|BEForumMember $actor the acting member, defaults to the current member
	 * @return BEForumEventParameter the raised parameter
	 */
	protected function raise(string $event, ?BEForumRecord $record, array $data = [], ?BEForumMember $actor = null): BEForumEventParameter
	{
		$param = new BEForumEventParameter($record, $actor ?? $this->getMember(), $data);
		$this->getModule()->raiseForumEvent($event, $param);
		return $param;
	}

	/**
	 * @return string the current UTC time in storage format
	 */
	protected function now(): string
	{
		return BEForumTime::now();
	}

	/**
	 * Creates the pagination of a list.
	 * @param int $page the requested 1-based page
	 * @param int $pageSize the page size
	 * @param int $itemCount the total items
	 * @return BEForumPagination the pagination
	 */
	protected function paginate(int $page, int $pageSize, int $itemCount): BEForumPagination
	{
		return new BEForumPagination($page, $pageSize, $itemCount);
	}

	/**
	 * Validates a required, length limited text field.
	 * @param string $field the field name
	 * @param null|string $value the value
	 * @param int $min the minimum length in characters
	 * @param int $max the maximum length in characters
	 * @param string $requiredKey the message key when empty
	 * @param string $tooLongKey the message key when too long
	 * @throws BEForumValidationException when invalid
	 * @return string the trimmed value
	 */
	protected function validateText(string $field, ?string $value, int $min, int $max, string $requiredKey, string $tooLongKey): string
	{
		$value = trim((string) $value);
		$length = mb_strlen($value);
		if ($length < max(1, $min)) {
			throw new BEForumValidationException($field, $requiredKey, $min);
		}
		if ($length > $max) {
			throw new BEForumValidationException($field, $tooLongKey, $field, $max, $length);
		}
		return $value;
	}

	/**
	 * Validates an optional URL.
	 * @param string $field the field name
	 * @param null|string $value the value
	 * @throws BEForumValidationException when the URL is malformed or not http(s)
	 * @return null|string the URL or null when empty
	 */
	protected function validateUrl(string $field, ?string $value): ?string
	{
		$value = trim((string) $value);
		if ($value === '') {
			return null;
		}
		if (!preg_match('#^https?://#i', $value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
			throw new BEForumValidationException($field, 'forum_url_invalid', $value);
		}
		return $value;
	}

	/**
	 * Reads a per request cache entry.
	 * @param string $key the cache key
	 * @param callable $producer `function(): mixed` producing the value on a miss
	 * @return mixed the cached value
	 */
	protected function cached(string $key, callable $producer)
	{
		if (!array_key_exists($key, $this->_cache)) {
			$this->_cache[$key] = $producer();
		}
		return $this->_cache[$key];
	}

	/**
	 * Removes per request cache entries.
	 * @param null|string $key the cache key, null clears everything
	 */
	public function flushRequestCache(?string $key = null): void
	{
		if ($key === null) {
			$this->_cache = [];
		} else {
			unset($this->_cache[$key]);
		}
	}

	/**
	 * The escape character of LIKE patterns built with {@see escapeLike}; the
	 * clause {@see LIKE_ESCAPE_CLAUSE} must follow the pattern parameter.
	 */
	public const LIKE_ESCAPE = '!';

	/**
	 * The SQL escape clause to append after a LIKE pattern built with {@see escapeLike}.
	 */
	public const LIKE_ESCAPE_CLAUSE = " ESCAPE '!'";

	/**
	 * Escapes LIKE wildcards in a search term.  The escape character is `!`
	 * (portable across SQLite, MySQL and PostgreSQL) and the LIKE expression
	 * must carry {@see LIKE_ESCAPE_CLAUSE}.
	 * @param string $term the term
	 * @return string the escaped term
	 */
	protected function escapeLike(string $term): string
	{
		return str_replace([self::LIKE_ESCAPE, '%', '_'], [self::LIKE_ESCAPE . self::LIKE_ESCAPE, self::LIKE_ESCAPE . '%', self::LIKE_ESCAPE . '_'], $term);
	}

	/**
	 * Derives the criteria used to count the rows matching a query: the same
	 * condition and parameters without ordering (an `ORDER BY` inside a
	 * `COUNT(*)` query is rejected by PostgreSQL and strict MySQL).
	 * @param TActiveRecordCriteria $criteria the query criteria
	 * @return TActiveRecordCriteria the count criteria
	 */
	protected function countCriteria(TActiveRecordCriteria $criteria): TActiveRecordCriteria
	{
		$count = clone $criteria;
		$count->setOrdersBy([]);
		$count->setLimit(-1);
		$count->setOffset(-1);
		return $count;
	}

	/**
	 * Groups records by the value of a column.
	 * @param BEForumRecord[] $records the records
	 * @param string $column the column name
	 * @return array<int|string, BEForumRecord[]> the records keyed by column value
	 */
	protected function groupBy(array $records, string $column): array
	{
		$groups = [];
		foreach ($records as $record) {
			$groups[$record->getColumnValue($column)][] = $record;
		}
		return $groups;
	}

	/**
	 * Indexes records by id.
	 * @param BEForumRecord[] $records the records
	 * @return array<int, BEForumRecord> the records keyed by id
	 */
	protected function indexById(array $records): array
	{
		$index = [];
		foreach ($records as $record) {
			$index[(int) $record->getId()] = $record;
		}
		return $index;
	}

	/**
	 * Builds an `IN (...)` condition for integer ids.
	 * @param string $column the column name
	 * @param int[] $ids the ids
	 * @return string the condition, `1=0` when there are no ids
	 */
	protected function inCondition(string $column, array $ids): string
	{
		$ids = array_values(array_unique(array_map('intval', $ids)));
		if (!$ids) {
			return '1=0';
		}
		return $column . ' IN (' . implode(',', $ids) . ')';
	}
}
