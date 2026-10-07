<?php

/**
 * BEForumMemberManager class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Managers;

use Belisoful\Forum\Data\BEForumBadge;
use Belisoful\Forum\Data\BEForumBoardModerator;
use Belisoful\Forum\Data\BEForumMember;
use Belisoful\Forum\Data\BEForumMemberBadge;
use Belisoful\Forum\Data\BEForumNotification;
use Belisoful\Forum\Exceptions\BEForumNotFoundException;
use Belisoful\Forum\Exceptions\BEForumValidationException;
use Belisoful\Forum\Security\BEForumPermissions;
use Belisoful\Forum\Util\BEForumPagination;
use Belisoful\Forum\Util\BEForumSlug;
use Belisoful\Forum\Util\BEForumTime;
use Prado\Security\IUser;

/**
 * BEForumMemberManager class.
 *
 * BEForumMemberManager links application users to {@see BEForumMember forum
 * members}, maintains profiles, presence, bans and warnings, board moderator
 * assignments and badges.
 *
 * ```php
 * $member = $forum->getMembers()->getCurrentMember();
 * $forum->getMembers()->updateProfile($member, ['display_name' => 'Alice', 'signature' => '...']);
 * $forum->getMembers()->ban($target, '+7 days', 'spam');
 * ```
 *
 * @method BEForumMember dyCreateMember(BEForumMember $member, IUser $user)
 * @method array dyValidateProfile(array $fields, BEForumMember $member)
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumMemberManager extends BEForumManager
{
	/** Seconds between two `last_seen_at` updates of the same member */
	public const PRESENCE_THROTTLE = 60;

	/** The profile fields members may edit themselves */
	public const PROFILE_FIELDS = ['display_name', 'email', 'avatar_url', 'signature', 'bio', 'location', 'website', 'timezone'];

	/**
	 * @return null|BEForumMember the member of the current user, null for guests
	 */
	public function getCurrentMember(): ?BEForumMember
	{
		$user = $this->getUser();
		if ($user === null || $user->getIsGuest()) {
			return null;
		}
		return $this->getMemberForUser($user, $this->getModule()->getAutoCreateMembers());
	}

	/**
	 * Returns the member of an application user.
	 * @param IUser $user the user
	 * @param bool $create whether to create the profile when missing
	 * @return null|BEForumMember the member
	 */
	public function getMemberForUser(IUser $user, bool $create = true): ?BEForumMember
	{
		if ($user->getIsGuest()) {
			return null;
		}
		$username = (string) $user->getName();
		if ($username === '') {
			return null;
		}
		$key = 'user:' . strtolower($username);
		$member = $this->cached($key, fn () => $this->findByUsername($username));
		if ($member === null && $create) {
			$member = $this->createMember($user);
			$this->flushRequestCache($key);
			$this->cached($key, fn () => $member);
		}
		if ($member !== null) {
			$this->touchPresence($member);
		}
		return $member;
	}

	/**
	 * Creates the member profile of an application user.
	 * @param IUser $user the user
	 * @return BEForumMember the new member
	 */
	public function createMember(IUser $user): BEForumMember
	{
		$member = new BEForumMember();
		$member->username = (string) $user->getName();
		$member->display_name = null;
		$member = $this->dyCreateMember($member, $user);
		$member->save();
		$this->raise('onMemberCreated', $member, [], $member);
		return $member;
	}

	/**
	 * Finds or creates the member of a username (used by shell tools and imports).
	 * @param string $username the username
	 * @throws BEForumValidationException when the username is empty
	 * @return BEForumMember the member
	 */
	public function ensureMember(string $username): BEForumMember
	{
		$username = trim($username);
		if ($username === '') {
			throw new BEForumValidationException('username', 'forum_username_required');
		}
		$member = $this->findByUsername($username);
		if ($member === null) {
			$member = new BEForumMember();
			$member->username = $username;
			$member->save();
			$this->raise('onMemberCreated', $member, [], $member);
		}
		return $member;
	}

	/**
	 * @param string $username the username, case insensitive
	 * @return null|BEForumMember the member
	 */
	public function findByUsername(string $username): ?BEForumMember
	{
		$username = trim($username);
		if ($username === '') {
			return null;
		}
		$this->getDbConnection();
		$member = BEForumMember::finder()->find('LOWER(username) = ?', [mb_strtolower($username)]);
		return $member instanceof BEForumMember ? $member : null;
	}

	/**
	 * @param int $id the member id
	 * @return null|BEForumMember the member
	 */
	public function findById(int $id): ?BEForumMember
	{
		$this->getDbConnection();
		return BEForumMember::findOne($id);
	}

	/**
	 * @param int $id the member id
	 * @throws BEForumNotFoundException when the member does not exist
	 * @return BEForumMember the member
	 */
	public function getMemberById(int $id): BEForumMember
	{
		$member = $this->findById($id);
		if ($member === null) {
			throw new BEForumNotFoundException('forum_member_not_found', $id);
		}
		return $member;
	}

	/**
	 * @param string $username the username
	 * @throws BEForumNotFoundException when the member does not exist
	 * @return BEForumMember the member
	 */
	public function getMemberByUsername(string $username): BEForumMember
	{
		$member = $this->findByUsername($username);
		if ($member === null) {
			throw new BEForumNotFoundException('forum_member_not_found', $username);
		}
		return $member;
	}

	/**
	 * Loads several members by id in one query.
	 * @param int[] $ids the member ids
	 * @return array<int, BEForumMember> the members keyed by id
	 */
	public function getMembersByIds(array $ids): array
	{
		$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
		if (!$ids) {
			return [];
		}
		$this->getDbConnection();
		return $this->indexById(BEForumMember::finder()->findAll(BEForumMember::criteria($this->inCondition('id', $ids), [], ['id' => 'asc'])));
	}

	/**
	 * Updates `last_seen_at`, at most once per {@see PRESENCE_THROTTLE} seconds.
	 * @param BEForumMember $member the member
	 * @return bool whether the record was written
	 */
	public function touchPresence(BEForumMember $member): bool
	{
		if ($member->getIsNew() || ($member->last_seen_at && BEForumTime::age($member->last_seen_at) < self::PRESENCE_THROTTLE)) {
			return false;
		}
		$member->last_seen_at = $this->now();
		BEForumMember::execute('UPDATE {table} SET last_seen_at = :seen WHERE id = :id', ['seen' => $member->last_seen_at, 'id' => $member->getId()]);
		return true;
	}

	/**
	 * Updates the editable profile fields of a member.
	 * @param BEForumMember $member the member
	 * @param array<string, null|string> $fields the fields (see PROFILE_FIELDS)
	 * @throws BEForumValidationException when a value is invalid
	 * @return BEForumMember the updated member
	 */
	public function updateProfile(BEForumMember $member, array $fields): BEForumMember
	{
		$member->refresh();
		$this->authorize(BEForumPermissions::PROFILE_EDIT, ['username' => $member->username]);
		$fields = array_intersect_key($fields, array_flip(self::PROFILE_FIELDS));
		$fields = $this->dyValidateProfile($fields, $member);
		$previous = [];
		foreach ($fields as $name => $value) {
			$value = $value === null ? null : trim((string) $value);
			switch ($name) {
				case 'display_name':
					if ($value !== null && mb_strlen($value) > 120) {
						throw new BEForumValidationException($name, 'forum_field_too_long', 'display name', 120, mb_strlen($value));
					}
					break;
				case 'email':
					if ($value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
						throw new BEForumValidationException($name, 'forum_email_invalid', $value);
					}
					break;
				case 'avatar_url':
				case 'website':
					$value = $this->validateUrl($name, $value);
					if ($value !== null && mb_strlen($value) > 500) {
						throw new BEForumValidationException($name, 'forum_field_too_long', $name, 500, mb_strlen($value));
					}
					break;
				case 'location':
					if ($value !== null && mb_strlen($value) > 120) {
						throw new BEForumValidationException($name, 'forum_field_too_long', $name, 120, mb_strlen($value));
					}
					break;
				case 'timezone':
					if ($value !== null && $value !== '' && !in_array($value, \DateTimeZone::listIdentifiers(), true)) {
						throw new BEForumValidationException($name, 'forum_timezone_invalid', $value);
					}
					break;
				case 'signature':
				case 'bio':
					if ($value !== null && mb_strlen($value) > 2000) {
						throw new BEForumValidationException($name, 'forum_field_too_long', $name, 2000, mb_strlen($value));
					}
					break;
			}
			$previous[$name] = $member->$name;
			$member->$name = ($value === '') ? null : $value;
		}
		$member->save();
		$this->flushRequestCache();
		$this->raise('onMemberUpdated', $member, ['previous' => $previous]);
		return $member;
	}

	/**
	 * Saves one member setting.
	 * @param BEForumMember $member the member
	 * @param string $key the setting
	 * @param mixed $value the value, null removes it
	 */
	public function setSetting(BEForumMember $member, string $key, $value): void
	{
		$this->authorize(BEForumPermissions::PROFILE_EDIT, ['username' => $member->username]);
		$member->setSetting($key, $value);
		$this->saveSettings($member);
	}

	/**
	 * Persists only the settings column of a member so that the counters
	 * maintained by SQL (post count, reputation, presence) are never clobbered
	 * by a stale instance.
	 * @param BEForumMember $member the member
	 */
	public function saveSettings(BEForumMember $member): void
	{
		BEForumMember::execute('UPDATE {table} SET settings = :settings WHERE id = :id', [
			'settings' => $member->getColumnValue('settings'),
			'id' => $member->getId(),
		]);
	}

	/**
	 * Bans a member, permanently or until a time.
	 * @param BEForumMember $member the member
	 * @param null|string $until a storage time or strtotime expression (e.g. `+7 days`), null for permanent
	 * @param null|string $reason the reason
	 * @throws BEForumValidationException when banning yourself or the time is invalid
	 * @return BEForumMember the member
	 */
	public function ban(BEForumMember $member, ?string $until = null, ?string $reason = null): BEForumMember
	{
		$member->refresh();
		$this->authorize(BEForumPermissions::MODERATE);
		$actor = $this->getMember();
		if ($actor !== null && $actor->getId() === $member->getId()) {
			throw new BEForumValidationException('member', 'forum_ban_self');
		}
		$untilTime = null;
		if ($until !== null && trim($until) !== '') {
			$stamp = BEForumTime::parse($until);
			if ($stamp === null) {
				throw new BEForumValidationException('until', 'forum_ban_until_invalid', $until);
			}
			$untilTime = BEForumTime::format($stamp);
		}
		$member->is_banned = true;
		$member->banned_until = $untilTime;
		$member->ban_reason = $reason === null ? null : mb_substr(trim($reason), 0, 500);
		$member->save();
		$this->flushRequestCache();
		$this->getModule()->getModeration()->log('ban', BEForumNotification::TARGET_MEMBER, $member->getId(), ['until' => $untilTime, 'reason' => $member->ban_reason]);
		$this->getModule()->getNotifications()->notify($member, BEForumNotification::TYPE_MODERATION, $actor, BEForumNotification::TARGET_MEMBER, $member->getId(), ['action' => 'ban', 'until' => $untilTime, 'reason' => $member->ban_reason], false);
		$this->raise('onMemberBanned', $member, ['until' => $untilTime, 'reason' => $member->ban_reason]);
		return $member;
	}

	/**
	 * Lifts the ban of a member.
	 * @param BEForumMember $member the member
	 * @return BEForumMember the member
	 */
	public function unban(BEForumMember $member): BEForumMember
	{
		$member->refresh();
		$this->authorize(BEForumPermissions::MODERATE);
		$member->is_banned = false;
		$member->banned_until = null;
		$member->ban_reason = null;
		$member->save();
		$this->flushRequestCache();
		$this->getModule()->getModeration()->log('unban', BEForumNotification::TARGET_MEMBER, $member->getId());
		$this->raise('onMemberUnbanned', $member);
		return $member;
	}

	/**
	 * Warns a member: increments the warning counter and notifies the member.
	 * @param BEForumMember $member the member
	 * @param string $reason the reason
	 * @throws BEForumValidationException when the reason is empty
	 * @return BEForumMember the member
	 */
	public function warn(BEForumMember $member, string $reason): BEForumMember
	{
		$member->refresh();
		$this->authorize(BEForumPermissions::MODERATE);
		$reason = $this->validateText('reason', $reason, 1, 500, 'forum_reason_required', 'forum_field_too_long');
		$member->warning_count = (int) $member->warning_count + 1;
		$member->save();
		$this->getModule()->getModeration()->log('warn', BEForumNotification::TARGET_MEMBER, $member->getId(), ['reason' => $reason]);
		$this->getModule()->getNotifications()->notify($member, BEForumNotification::TYPE_MODERATION, $this->getMember(), BEForumNotification::TARGET_MEMBER, $member->getId(), ['action' => 'warn', 'reason' => $reason], false);
		$this->raise('onMemberWarned', $member, ['reason' => $reason]);
		return $member;
	}

	/**
	 * Clears expired temporary bans.
	 * @return int the number of members unbanned
	 */
	public function expireBans(): int
	{
		$this->getDbConnection();
		return BEForumMember::execute('UPDATE {table} SET is_banned = :off, banned_until = NULL, ban_reason = NULL WHERE is_banned = :on AND banned_until IS NOT NULL AND banned_until <= :now', ['off' => false, 'on' => true, 'now' => $this->now()]);
	}

	/**
	 * Adds reputation points to a member.
	 * @param BEForumMember $member the member
	 * @param int $delta the points, may be negative
	 */
	public function adjustReputation(BEForumMember $member, int $delta): void
	{
		if ($delta === 0) {
			return;
		}
		BEForumMember::execute('UPDATE {table} SET reputation = reputation + :delta WHERE id = :id', ['delta' => $delta, 'id' => $member->getId()]);
		$member->reputation = (int) $member->reputation + $delta;
	}

	/**
	 * Adjusts the post and thread counters of a member.
	 * @param null|int $memberId the member id
	 * @param int $posts the post delta
	 * @param int $threads the thread delta
	 * @param null|string $lastPostAt the time of the newest post, null leaves it
	 */
	public function adjustCounters(?int $memberId, int $posts, int $threads = 0, ?string $lastPostAt = null): void
	{
		if (!$memberId) {
			return;
		}
		$sql = 'UPDATE {table} SET post_count = post_count + :posts, thread_count = thread_count + :threads';
		$params = ['posts' => $posts, 'threads' => $threads, 'id' => $memberId];
		if ($lastPostAt !== null) {
			$sql .= ', last_post_at = :last';
			$params['last'] = $lastPostAt;
		}
		BEForumMember::execute($sql . ' WHERE id = :id', $params);
		$this->flushRequestCache();
	}

	/**
	 * Lists members.
	 * @param int $page the 1-based page
	 * @param null|string $search a username or display name fragment
	 * @param string $orderBy `username`, `reputation`, `post_count` or `joined_at`
	 * @param null|int $pageSize the page size, null for the module default
	 * @return array{0: BEForumMember[], 1: BEForumPagination} the members and the pagination
	 */
	public function listMembers(int $page = 1, ?string $search = null, string $orderBy = 'username', ?int $pageSize = null): array
	{
		$this->getDbConnection();
		$pageSize ??= $this->getModule()->getItemsPerPage();
		$condition = '1=1';
		$params = [];
		$search = trim((string) $search);
		if ($search !== '') {
			$condition = '(LOWER(username) LIKE :q' . self::LIKE_ESCAPE_CLAUSE . ' OR LOWER(display_name) LIKE :q' . self::LIKE_ESCAPE_CLAUSE . ')';
			$params[':q'] = '%' . mb_strtolower($this->escapeLike($search)) . '%';
		}
		$order = match ($orderBy) {
			'reputation' => ['reputation' => 'desc', 'username' => 'asc'],
			'post_count', 'posts' => ['post_count' => 'desc', 'username' => 'asc'],
			'joined_at', 'newest' => ['joined_at' => 'desc', 'id' => 'desc'],
			default => ['username' => 'asc'],
		};
		$pagination = $this->paginate($page, $pageSize, BEForumMember::countWhere($condition, $params));
		$members = BEForumMember::findAllPaged($condition, $params, $order, $pageSize, $pagination->getPage());
		return [$members, $pagination];
	}

	/**
	 * @param int $limit the maximum number of members
	 * @return BEForumMember[] the newest members
	 */
	public function getNewestMembers(int $limit = 5): array
	{
		$this->getDbConnection();
		return BEForumMember::finder()->findAll(BEForumMember::criteria(null, [], ['joined_at' => 'desc', 'id' => 'desc'], max(1, $limit)));
	}

	/**
	 * @param int $minutes the presence window
	 * @return BEForumMember[] the members seen within the window
	 */
	public function getOnlineMembers(int $minutes = 15): array
	{
		$this->getDbConnection();
		return BEForumMember::finder()->findAll(BEForumMember::criteria('last_seen_at >= ?', [BEForumTime::fromNow(-60 * max(1, $minutes))], ['last_seen_at' => 'desc']));
	}

	/**
	 * @return int the number of members
	 */
	public function countMembers(): int
	{
		$this->getDbConnection();
		return BEForumMember::countWhere();
	}

	// ------------------------------------------------------------------
	// Board moderators
	// ------------------------------------------------------------------

	/**
	 * @param int $boardId the board id
	 * @return string[] the usernames moderating the board
	 */
	public function getBoardModeratorUsernames(int $boardId): array
	{
		if ($boardId <= 0) {
			return [];
		}
		return $this->cached('moderators:' . $boardId, function () use ($boardId): array {
			$this->getDbConnection();
			$names = [];
			foreach (BEForumBoardModerator::finder()->findAll('board_id = ?', [$boardId]) as $assignment) {
				$member = $assignment->member;
				if ($member instanceof BEForumMember) {
					$names[] = (string) $member->username;
				}
			}
			return $names;
		});
	}

	/**
	 * @param int $boardId the board id
	 * @return BEForumMember[] the moderators of the board
	 */
	public function getBoardModerators(int $boardId): array
	{
		$this->getDbConnection();
		$members = [];
		foreach (BEForumBoardModerator::finder()->findAll('board_id = ?', [$boardId]) as $assignment) {
			$member = $assignment->member;
			if ($member instanceof BEForumMember) {
				$members[] = $member;
			}
		}
		return $members;
	}

	/**
	 * @param BEForumMember $member the member
	 * @return int[] the ids of the boards the member moderates
	 */
	public function getModeratedBoardIds(BEForumMember $member): array
	{
		$this->getDbConnection();
		$ids = [];
		foreach (BEForumBoardModerator::finder()->findAll('member_id = ?', [$member->getId()]) as $assignment) {
			$ids[] = (int) $assignment->board_id;
		}
		return $ids;
	}

	/**
	 * Assigns a member as moderator of a board.
	 * @param int $boardId the board id
	 * @param BEForumMember $member the member
	 * @return BEForumBoardModerator the assignment
	 */
	public function addBoardModerator(int $boardId, BEForumMember $member): BEForumBoardModerator
	{
		$this->authorize(BEForumPermissions::ADMIN);
		$this->getModule()->getBoards()->getBoard($boardId);
		$existing = BEForumBoardModerator::finder()->find('board_id = ? AND member_id = ?', [$boardId, $member->getId()]);
		if ($existing instanceof BEForumBoardModerator) {
			return $existing;
		}
		$assignment = new BEForumBoardModerator();
		$assignment->board_id = $boardId;
		$assignment->member_id = $member->getId();
		$assignment->save();
		$this->flushRequestCache('moderators:' . $boardId);
		$this->getModule()->getModeration()->log('add_moderator', BEForumNotification::TARGET_BOARD, $boardId, ['member_id' => $member->getId()]);
		return $assignment;
	}

	/**
	 * Removes a member from the moderators of a board.
	 * @param int $boardId the board id
	 * @param BEForumMember $member the member
	 * @return bool whether an assignment was removed
	 */
	public function removeBoardModerator(int $boardId, BEForumMember $member): bool
	{
		$this->authorize(BEForumPermissions::ADMIN);
		$this->getDbConnection();
		$removed = BEForumBoardModerator::finder()->deleteAll('board_id = ? AND member_id = ?', [$boardId, $member->getId()]) > 0;
		$this->flushRequestCache('moderators:' . $boardId);
		if ($removed) {
			$this->getModule()->getModeration()->log('remove_moderator', BEForumNotification::TARGET_BOARD, $boardId, ['member_id' => $member->getId()]);
		}
		return $removed;
	}

	// ------------------------------------------------------------------
	// Badges
	// ------------------------------------------------------------------

	/**
	 * Creates or updates a badge definition.
	 * @param string $name the badge name
	 * @param null|string $description the description
	 * @param null|string $icon an icon URL or CSS class
	 * @param null|string $slug the slug, derived from the name when null
	 * @return BEForumBadge the badge
	 */
	public function defineBadge(string $name, ?string $description = null, ?string $icon = null, ?string $slug = null): BEForumBadge
	{
		$this->authorize(BEForumPermissions::ADMIN);
		$name = $this->validateText('name', $name, 1, 120, 'forum_name_required', 'forum_field_too_long');
		if ($description !== null && mb_strlen($description) > 500) {
			throw new BEForumValidationException('description', 'forum_field_too_long', 'description', 500, mb_strlen($description));
		}
		$slug = BEForumSlug::create($slug ?? $name, 64);
		$badge = BEForumBadge::finder()->find('slug = ?', [$slug]);
		if (!($badge instanceof BEForumBadge)) {
			$badge = new BEForumBadge();
			$badge->slug = $slug;
		}
		$badge->name = $name;
		$badge->description = $description;
		$badge->icon = $icon;
		$badge->save();
		return $badge;
	}

	/**
	 * @param string $slug the badge slug
	 * @return null|BEForumBadge the badge
	 */
	public function findBadge(string $slug): ?BEForumBadge
	{
		$this->getDbConnection();
		$badge = BEForumBadge::finder()->find('slug = ?', [BEForumSlug::create($slug, 64)]);
		return $badge instanceof BEForumBadge ? $badge : null;
	}

	/**
	 * @return BEForumBadge[] all badges
	 */
	public function getBadges(): array
	{
		$this->getDbConnection();
		return BEForumBadge::finder()->findAll(BEForumBadge::criteria(null, [], ['name' => 'asc']));
	}

	/**
	 * Awards a badge to a member (idempotent).
	 * @param BEForumMember $member the member
	 * @param BEForumBadge $badge the badge
	 * @param bool $authorize whether to require the moderate permission (false for automatic awards)
	 * @return BEForumMemberBadge the award
	 */
	public function awardBadge(BEForumMember $member, BEForumBadge $badge, bool $authorize = true): BEForumMemberBadge
	{
		if ($authorize) {
			$this->authorize(BEForumPermissions::MODERATE);
		}
		$existing = BEForumMemberBadge::finder()->find('member_id = ? AND badge_id = ?', [$member->getId(), $badge->getId()]);
		if ($existing instanceof BEForumMemberBadge) {
			return $existing;
		}
		$award = new BEForumMemberBadge();
		$award->member_id = $member->getId();
		$award->badge_id = $badge->getId();
		$actor = $this->getMember();
		$award->awarded_by_member_id = $actor?->getId();
		$award->save();
		$this->getModule()->getNotifications()->notify($member, BEForumNotification::TYPE_BADGE, $actor, BEForumNotification::TARGET_MEMBER, $member->getId(), ['badge' => $badge->name, 'slug' => $badge->slug], false);
		$this->raise('onBadgeAwarded', $award, ['badge' => $badge->name]);
		return $award;
	}

	/**
	 * Revokes a badge from a member.
	 * @param BEForumMember $member the member
	 * @param BEForumBadge $badge the badge
	 * @return bool whether an award was removed
	 */
	public function revokeBadge(BEForumMember $member, BEForumBadge $badge): bool
	{
		$this->authorize(BEForumPermissions::MODERATE);
		$this->getDbConnection();
		return BEForumMemberBadge::finder()->deleteAll('member_id = ? AND badge_id = ?', [$member->getId(), $badge->getId()]) > 0;
	}

	/**
	 * @param BEForumMember $member the member
	 * @return BEForumBadge[] the badges of the member
	 */
	public function getMemberBadges(BEForumMember $member): array
	{
		$this->getDbConnection();
		$badges = [];
		foreach (BEForumMemberBadge::finder()->findAll(BEForumMemberBadge::criteria('member_id = ?', [$member->getId()], ['awarded_at' => 'desc'])) as $award) {
			$badge = $award->badge;
			if ($badge instanceof BEForumBadge) {
				$badges[] = $badge;
			}
		}
		return $badges;
	}
}
