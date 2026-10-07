<?php

/**
 * BEForumSubscriptionManager class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Managers;

use Belisoful\Forum\Data\BEForumMember;
use Belisoful\Forum\Data\BEForumSubscription;
use Belisoful\Forum\Exceptions\BEForumValidationException;
use Belisoful\Forum\Security\BEForumPermissions;
use Belisoful\Forum\Util\BEForumPagination;

/**
 * BEForumSubscriptionManager class.
 *
 * BEForumSubscriptionManager lets members follow boards (new threads) and
 * threads (new replies) and resolves the recipients of activity
 * notifications.
 *
 * ```php
 * $forum->getSubscriptions()->subscribe(BEForumSubscription::TYPE_THREAD, $thread->getId());
 * $forum->getSubscriptions()->toggle(BEForumSubscription::TYPE_BOARD, $board->getId());
 * ```
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumSubscriptionManager extends BEForumManager
{
	/**
	 * @param string $type a target type
	 * @throws BEForumValidationException when the type is unknown
	 * @return string the validated type
	 */
	protected function validateType(string $type): string
	{
		$type = strtolower(trim($type));
		if (!in_array($type, BEForumSubscription::getTypes(), true)) {
			throw new BEForumValidationException('type', 'forum_subscription_type_invalid', $type);
		}
		return $type;
	}

	/**
	 * Subscribes a member to a target (idempotent).
	 * @param string $type the target type: board or thread
	 * @param int $targetId the target id
	 * @param null|BEForumMember $member the member, null for the current member
	 * @param bool $authorize whether to check the subscribe permission
	 * @return BEForumSubscription the subscription
	 */
	public function subscribe(string $type, int $targetId, ?BEForumMember $member = null, bool $authorize = true): BEForumSubscription
	{
		$type = $this->validateType($type);
		if ($authorize) {
			// the target must exist and be visible to the current user
			if ($type === BEForumSubscription::TYPE_BOARD) {
				$board = $this->getModule()->getBoards()->getViewableBoard($targetId);
				$this->authorize(BEForumPermissions::SUBSCRIBE, $this->extraFor($board));
			} else {
				$thread = $this->getModule()->getThreads()->getThread($targetId);
				$this->authorize(BEForumPermissions::SUBSCRIBE, $this->extraFor((int) $thread->board_id));
			}
		}
		$member ??= $this->requireMember(BEForumPermissions::SUBSCRIBE);
		$this->getDbConnection();
		$existing = $this->find($type, $targetId, $member);
		if ($existing !== null) {
			return $existing;
		}
		$subscription = new BEForumSubscription();
		$subscription->member_id = $member->getId();
		$subscription->target_type = $type;
		$subscription->target_id = $targetId;
		$subscription->save();
		$this->flushRequestCache();
		$this->raise('onSubscriptionChanged', $subscription, ['subscribed' => true], $member);
		return $subscription;
	}

	/**
	 * Removes the subscription of a member to a target.
	 * @param string $type the target type
	 * @param int $targetId the target id
	 * @param null|BEForumMember $member the member, null for the current member
	 * @return bool whether a subscription was removed
	 */
	public function unsubscribe(string $type, int $targetId, ?BEForumMember $member = null): bool
	{
		$type = $this->validateType($type);
		$member ??= $this->requireMember(BEForumPermissions::SUBSCRIBE);
		$subscription = $this->find($type, $targetId, $member);
		if ($subscription === null) {
			return false;
		}
		$subscription->delete();
		$this->flushRequestCache();
		$this->raise('onSubscriptionChanged', $subscription, ['subscribed' => false], $member);
		return true;
	}

	/**
	 * Subscribes or unsubscribes the current member.
	 * @param string $type the target type
	 * @param int $targetId the target id
	 * @return bool whether the member is subscribed after the call
	 */
	public function toggle(string $type, int $targetId): bool
	{
		if ($this->isSubscribed($type, $targetId)) {
			$this->unsubscribe($type, $targetId);
			return false;
		}
		$this->subscribe($type, $targetId);
		return true;
	}

	/**
	 * @param string $type the target type
	 * @param int $targetId the target id
	 * @param BEForumMember $member the member
	 * @return null|BEForumSubscription the subscription
	 */
	public function find(string $type, int $targetId, BEForumMember $member): ?BEForumSubscription
	{
		$this->getDbConnection();
		$subscription = BEForumSubscription::finder()->find('member_id = ? AND target_type = ? AND target_id = ?', [$member->getId(), $this->validateType($type), $targetId]);
		return $subscription instanceof BEForumSubscription ? $subscription : null;
	}

	/**
	 * @param string $type the target type
	 * @param int $targetId the target id
	 * @param null|BEForumMember $member the member, null for the current member
	 * @return bool whether the member is subscribed
	 */
	public function isSubscribed(string $type, int $targetId, ?BEForumMember $member = null): bool
	{
		$member ??= $this->getMember();
		if ($member === null) {
			return false;
		}
		$key = 'subscribed:' . $member->getId() . ':' . $type . ':' . $targetId;
		return $this->cached($key, fn () => $this->find($type, $targetId, $member) !== null);
	}

	/**
	 * @param string $type the target type
	 * @param int $targetId the target id
	 * @return BEForumMember[] the subscribed members
	 */
	public function getSubscribers(string $type, int $targetId): array
	{
		$type = $this->validateType($type);
		$this->getDbConnection();
		$memberIds = array_map(fn (BEForumSubscription $subscription) => (int) $subscription->member_id, BEForumSubscription::finder()->findAll('target_type = ? AND target_id = ?', [$type, $targetId]));
		return array_values($this->getModule()->getMembers()->getMembersByIds($memberIds));
	}

	/**
	 * Lists the subscriptions of a member.
	 * @param null|BEForumMember $member the member, null for the current member
	 * @param int $page the 1-based page
	 * @param null|string $type an optional target type filter
	 * @param null|int $pageSize the page size
	 * @return array{0: BEForumSubscription[], 1: BEForumPagination} the subscriptions and the pagination
	 */
	public function listSubscriptions(?BEForumMember $member = null, int $page = 1, ?string $type = null, ?int $pageSize = null): array
	{
		$member ??= $this->requireMember(BEForumPermissions::SUBSCRIBE);
		$this->getDbConnection();
		$pageSize ??= $this->getModule()->getItemsPerPage();
		$condition = 'member_id = :member';
		$params = [':member' => $member->getId()];
		if ($type !== null) {
			$condition .= ' AND target_type = :type';
			$params[':type'] = $this->validateType($type);
		}
		$pagination = $this->paginate($page, $pageSize, BEForumSubscription::countWhere($condition, $params));
		return [BEForumSubscription::findAllPaged($condition, $params, ['created_at' => 'desc'], $pageSize, $pagination->getPage()), $pagination];
	}

	/**
	 * Removes every subscription to a target (when the target is purged).
	 * @param string $type the target type
	 * @param int $targetId the target id
	 * @return int the number of subscriptions removed
	 */
	public function removeTarget(string $type, int $targetId): int
	{
		$this->getDbConnection();
		$removed = (int) BEForumSubscription::finder()->deleteAll('target_type = ? AND target_id = ?', [$this->validateType($type), $targetId]);
		$this->flushRequestCache();
		return $removed;
	}
}
