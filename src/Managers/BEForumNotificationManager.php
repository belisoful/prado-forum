<?php

/**
 * BEForumNotificationManager class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Managers;

use Belisoful\Forum\Data\BEForumMember;
use Belisoful\Forum\Data\BEForumNotification;
use Belisoful\Forum\Exceptions\BEForumForbiddenException;
use Belisoful\Forum\Security\BEForumPermissions;
use Belisoful\Forum\Util\BEForumPagination;
use Belisoful\Forum\Util\BEForumTime;

/**
 * BEForumNotificationManager class.
 *
 * BEForumNotificationManager creates, lists and prunes the in-forum
 * {@see BEForumNotification notifications}.  Duplicate unread notifications
 * for the same recipient, type and target are folded into one.  Members may
 * mute notification types through their settings (`notify_<type> = false`).
 * The `dyNotify` dynamic event filters (or suppresses with null) every
 * notification and the module `onNotification` event lets the host deliver it
 * by other channels such as e-mail.
 *
 * @method null|BEForumNotification dyNotify(null|BEForumNotification $notification, BEForumMember $recipient)
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumNotificationManager extends BEForumManager
{
	/**
	 * Creates a notification for a member.
	 * @param BEForumMember|int $recipient the recipient or its id
	 * @param string $type the notification type
	 * @param null|BEForumMember $actor the acting member
	 * @param null|string $targetType the target type
	 * @param null|int $targetId the target id
	 * @param array $data extra details
	 * @param bool $fold whether an unread notification of the same type and target is updated instead of adding a second one
	 * @return null|BEForumNotification the notification, null when suppressed
	 */
	public function notify($recipient, string $type, ?BEForumMember $actor = null, ?string $targetType = null, ?int $targetId = null, array $data = [], bool $fold = true): ?BEForumNotification
	{
		$module = $this->getModule();
		$recipient = $recipient instanceof BEForumMember ? $recipient : $module->getMembers()->findById((int) $recipient);
		if ($recipient === null) {
			return null;
		}
		if ($actor !== null && $actor->getId() === $recipient->getId()) {
			return null;
		}
		if ($recipient->getSetting('notify_' . $type, true) === false) {
			return null;
		}
		$this->getDbConnection();
		$notification = null;
		if ($fold && $targetType !== null && $targetId !== null) {
			$existing = BEForumNotification::finder()->find('member_id = ? AND type = ? AND target_type = ? AND target_id = ? AND is_read = ?', [$recipient->getId(), $type, $targetType, $targetId, false]);
			if ($existing instanceof BEForumNotification) {
				$notification = $existing;
			}
		}
		if ($notification === null) {
			$notification = new BEForumNotification();
			$notification->member_id = $recipient->getId();
			$notification->type = $type;
			$notification->target_type = $targetType;
			$notification->target_id = $targetId;
		}
		$notification->actor_member_id = $actor?->getId();
		$notification->setJsonColumn('data', $data);
		$notification->is_read = false;
		$notification->read_at = null;
		$notification->created_at = $this->now();
		$notification = $this->dyNotify($notification, $recipient);
		if (!($notification instanceof BEForumNotification)) {
			return null;
		}
		$notification->save();
		$this->flushRequestCache('unread:' . $recipient->getId());
		$this->raise('onNotification', $notification, ['recipient' => $recipient], $actor);
		return $notification;
	}

	/**
	 * Notifies several members.
	 * @param BEForumMember[] $recipients the recipients
	 * @param string $type the notification type
	 * @param null|BEForumMember $actor the acting member
	 * @param null|string $targetType the target type
	 * @param null|int $targetId the target id
	 * @param array $data extra details
	 * @param null|int $excludeMemberId a member to skip (usually the author)
	 * @return int the number of notifications created
	 */
	public function notifyMany(array $recipients, string $type, ?BEForumMember $actor, ?string $targetType, ?int $targetId, array $data = [], ?int $excludeMemberId = null): int
	{
		$count = 0;
		foreach ($recipients as $recipient) {
			if (!($recipient instanceof BEForumMember) || ($excludeMemberId !== null && $recipient->getId() === $excludeMemberId)) {
				continue;
			}
			if ($this->notify($recipient, $type, $actor, $targetType, $targetId, $data) !== null) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Lists the notifications of the current member, newest first.
	 * @param int $page the 1-based page
	 * @param bool $unreadOnly whether to list unread notifications only
	 * @param null|BEForumMember $member the member, null for the current member
	 * @param null|int $pageSize the page size
	 * @return array{0: BEForumNotification[], 1: BEForumPagination} the notifications and the pagination
	 */
	public function listNotifications(int $page = 1, bool $unreadOnly = false, ?BEForumMember $member = null, ?int $pageSize = null): array
	{
		$member ??= $this->requireMember();
		$this->getDbConnection();
		$pageSize ??= $this->getModule()->getItemsPerPage();
		$condition = 'member_id = :member';
		$params = [':member' => $member->getId()];
		if ($unreadOnly) {
			$condition .= ' AND is_read = :unread';
			$params[':unread'] = false;
		}
		$pagination = $this->paginate($page, $pageSize, BEForumNotification::countWhere($condition, $params));
		return [BEForumNotification::findAllPaged($condition, $params, ['created_at' => 'desc', 'id' => 'desc'], $pageSize, $pagination->getPage()), $pagination];
	}

	/**
	 * @param null|BEForumMember $member the member, null for the current member
	 * @return int the number of unread notifications
	 */
	public function countUnread(?BEForumMember $member = null): int
	{
		$member ??= $this->getMember();
		if ($member === null) {
			return 0;
		}
		return $this->cached('unread:' . $member->getId(), function () use ($member): int {
			$this->getDbConnection();
			return BEForumNotification::countWhere('member_id = ? AND is_read = ?', [$member->getId(), false]);
		});
	}

	/**
	 * @param int $id the notification id
	 * @throws BEForumForbiddenException when the notification belongs to someone else
	 * @return BEForumNotification the notification of the current member
	 */
	public function getNotification(int $id): BEForumNotification
	{
		$member = $this->requireMember();
		$this->getDbConnection();
		$notification = BEForumNotification::findOne($id);
		if ($notification === null || (int) $notification->member_id !== $member->getId()) {
			throw new BEForumForbiddenException(BEForumPermissions::VIEW, 'forum_notification_not_owned');
		}
		return $notification;
	}

	/**
	 * Marks a notification as read.
	 * @param BEForumNotification|int $notification the notification or its id
	 * @return BEForumNotification the notification
	 */
	public function markRead($notification): BEForumNotification
	{
		$notification = $notification instanceof BEForumNotification ? $notification : $this->getNotification((int) $notification);
		if (!$notification->getIsRead()) {
			$notification->is_read = true;
			$notification->read_at = $this->now();
			$notification->save();
			$this->flushRequestCache('unread:' . (int) $notification->member_id);
		}
		return $notification;
	}

	/**
	 * Marks every notification of a member as read.
	 * @param null|BEForumMember $member the member, null for the current member
	 * @return int the number of notifications marked
	 */
	public function markAllRead(?BEForumMember $member = null): int
	{
		$member ??= $this->requireMember();
		$this->getDbConnection();
		$count = BEForumNotification::execute('UPDATE {table} SET is_read = :on, read_at = :now WHERE member_id = :member AND is_read = :off', ['on' => true, 'now' => $this->now(), 'member' => $member->getId(), 'off' => false]);
		$this->flushRequestCache('unread:' . $member->getId());
		return $count;
	}

	/**
	 * Deletes a notification of the current member.
	 * @param BEForumNotification|int $notification the notification or its id
	 */
	public function delete($notification): void
	{
		$notification = $notification instanceof BEForumNotification ? $notification : $this->getNotification((int) $notification);
		$memberId = (int) $notification->member_id;
		$notification->delete();
		$this->flushRequestCache('unread:' . $memberId);
	}

	/**
	 * Deletes read notifications older than a number of days.
	 * @param int $days the retention in days
	 * @return int the number of notifications deleted
	 */
	public function prune(int $days): int
	{
		$this->getDbConnection();
		$cutoff = BEForumTime::fromNow(-86400 * max(1, $days));
		$count = (int) BEForumNotification::finder()->deleteAll('is_read = ? AND created_at < ?', [true, $cutoff]);
		$this->flushRequestCache();
		return $count;
	}
}
