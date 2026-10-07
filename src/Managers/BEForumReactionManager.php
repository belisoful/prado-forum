<?php

/**
 * BEForumReactionManager class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Managers;

use Belisoful\Forum\Data\BEForumMember;
use Belisoful\Forum\Data\BEForumNotification;
use Belisoful\Forum\Data\BEForumPost;
use Belisoful\Forum\Data\BEForumReaction;
use Belisoful\Forum\Exceptions\BEForumValidationException;
use Belisoful\Forum\Security\BEForumPermissions;

/**
 * BEForumReactionManager class.
 *
 * BEForumReactionManager records reactions (like, love, ...) of members on
 * posts.  A member has at most one reaction per post; reacting again with a
 * different type replaces it and reacting with the same type removes it.
 * Reactions adjust the reputation of the post author by
 * {@see getReputationPerReaction} (filtered by `dyReactionReputation`).
 *
 * ```php
 * $forum->getReactions()->react($post, 'like');
 * $summary = $forum->getReactions()->getSummary($post);   // ['like' => 3, 'love' => 1]
 * ```
 *
 * @method int dyReactionReputation(int $points, string $type, BEForumPost $post)
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumReactionManager extends BEForumManager
{
	/** @var int reputation points per reaction */
	private int $_reputationPerReaction = 1;

	/**
	 * @return int reputation points the post author gains per reaction
	 */
	public function getReputationPerReaction(): int
	{
		return $this->_reputationPerReaction;
	}

	/**
	 * @param int $points reputation points the post author gains per reaction
	 */
	public function setReputationPerReaction(int $points): void
	{
		$this->_reputationPerReaction = $points;
	}

	/**
	 * @return string[] the configured reaction types
	 */
	public function getTypes(): array
	{
		return $this->getModule()->getReactionTypes();
	}

	/**
	 * @param string $type a reaction type
	 * @throws BEForumValidationException when the type is not configured
	 * @return string the validated type
	 */
	public function validateType(string $type): string
	{
		$type = strtolower(trim($type));
		if (!in_array($type, $this->getTypes(), true)) {
			throw new BEForumValidationException('type', 'forum_reaction_type_invalid', $type);
		}
		return $type;
	}

	/**
	 * Toggles the reaction of the current member on a post.
	 * @param BEForumPost $post the post
	 * @param string $type the reaction type
	 * @throws BEForumValidationException when reacting to an own post or the type is invalid
	 * @return null|BEForumReaction the reaction after the call, null when removed
	 */
	public function react(BEForumPost $post, string $type): ?BEForumReaction
	{
		$module = $this->getModule();
		if (!$module->getEnableReactions()) {
			throw new BEForumValidationException('type', 'forum_reactions_disabled');
		}
		$type = $this->validateType($type);
		$this->authorize(BEForumPermissions::REACT, $this->extraFor((int) $post->board_id));
		$member = $this->requireMember(BEForumPermissions::REACT);
		if ($post->getIsDeleted()) {
			throw new BEForumValidationException('post', 'forum_post_not_found', $post->getId());
		}
		if ((int) $post->member_id === $member->getId()) {
			throw new BEForumValidationException('post', 'forum_react_own_post');
		}
		return $this->transaction(function () use ($post, $type, $member, $module): ?BEForumReaction {
			$existing = $this->getMemberReaction($post, $member);
			if ($existing !== null) {
				$sameType = $existing->type === $type;
				$existing->delete();
				$this->adjustPost($post, -1, (string) $existing->type);
				$this->flushRequestCache();
				$this->raise('onReaction', $existing, ['post' => $post, 'added' => false]);
				if ($sameType) {
					return null;
				}
			}
			$reaction = new BEForumReaction();
			$reaction->post_id = $post->getId();
			$reaction->member_id = $member->getId();
			$reaction->type = $type;
			$reaction->save();
			$this->adjustPost($post, 1, $type);
			$this->flushRequestCache();
			if ($post->member_id) {
				$thread = $module->getThreads()->findThread((int) $post->thread_id);
				$module->getNotifications()->notify((int) $post->member_id, BEForumNotification::TYPE_REACTION, $member, BEForumNotification::TARGET_POST, $post->getId(), ['type' => $type, 'thread_id' => (int) $post->thread_id, 'title' => $thread?->title]);
			}
			$this->raise('onReaction', $reaction, ['post' => $post, 'added' => true]);
			return $reaction;
		});
	}

	/**
	 * Removes the reaction of the current member on a post.
	 * @param BEForumPost $post the post
	 * @return bool whether a reaction was removed
	 */
	public function unreact(BEForumPost $post): bool
	{
		$member = $this->requireMember(BEForumPermissions::REACT);
		$existing = $this->getMemberReaction($post, $member);
		if ($existing === null) {
			return false;
		}
		$existing->delete();
		$this->adjustPost($post, -1, (string) $existing->type);
		$this->flushRequestCache();
		$this->raise('onReaction', $existing, ['post' => $post, 'added' => false]);
		return true;
	}

	/**
	 * Adjusts the reaction counter of a post and the reputation of its author.
	 * @param BEForumPost $post the post
	 * @param int $delta +1 or -1
	 * @param string $type the reaction type
	 */
	protected function adjustPost(BEForumPost $post, int $delta, string $type): void
	{
		BEForumPost::execute('UPDATE {table} SET reaction_count = reaction_count + :delta WHERE id = :id', ['delta' => $delta, 'id' => $post->getId()]);
		BEForumPost::execute('UPDATE {table} SET reaction_count = 0 WHERE id = :id AND reaction_count < 0', ['id' => $post->getId()]);
		$post->reaction_count = max(0, (int) $post->reaction_count + $delta);
		if ($post->member_id) {
			$points = (int) $this->dyReactionReputation($this->_reputationPerReaction, $type, $post);
			if ($points !== 0) {
				$author = $this->getModule()->getMembers()->findById((int) $post->member_id);
				if ($author !== null) {
					$this->getModule()->getMembers()->adjustReputation($author, $points * $delta);
				}
			}
		}
	}

	/**
	 * @param BEForumPost $post the post
	 * @param null|BEForumMember $member the member, null for the current member
	 * @return null|BEForumReaction the reaction of the member on the post
	 */
	public function getMemberReaction(BEForumPost $post, ?BEForumMember $member = null): ?BEForumReaction
	{
		$member ??= $this->getMember();
		if ($member === null) {
			return null;
		}
		$this->getDbConnection();
		$reaction = BEForumReaction::finder()->find('post_id = ? AND member_id = ?', [$post->getId(), $member->getId()]);
		return $reaction instanceof BEForumReaction ? $reaction : null;
	}

	/**
	 * @param BEForumPost $post the post
	 * @return array<string, int> the reaction counts keyed by type
	 */
	public function getSummary(BEForumPost $post): array
	{
		return $this->getSummaries([(int) $post->getId()])[(int) $post->getId()] ?? [];
	}

	/**
	 * Loads the reaction counts of several posts in one query.
	 * @param int[] $postIds the post ids
	 * @return array<int, array<string, int>> the counts keyed by post id then type
	 */
	public function getSummaries(array $postIds): array
	{
		$postIds = array_values(array_unique(array_filter(array_map('intval', $postIds))));
		if (!$postIds) {
			return [];
		}
		$connection = $this->getDbConnection();
		$sql = 'SELECT post_id, type, COUNT(*) AS total FROM ' . $connection->quoteTableName(BEForumReaction::finder()->table())
			. ' WHERE ' . $this->inCondition('post_id', $postIds) . ' GROUP BY post_id, type';
		$result = [];
		foreach ($connection->createCommand($sql)->query() as $row) {
			$row = array_change_key_case($row, CASE_LOWER);
			$result[(int) $row['post_id']][(string) $row['type']] = (int) $row['total'];
		}
		foreach ($result as &$counts) {
			arsort($counts);
		}
		return $result;
	}

	/**
	 * Loads the reaction types of the current member on several posts.
	 * @param int[] $postIds the post ids
	 * @return array<int, string> the reaction type keyed by post id
	 */
	public function getMemberReactions(array $postIds): array
	{
		$member = $this->getMember();
		$postIds = array_values(array_unique(array_filter(array_map('intval', $postIds))));
		if ($member === null || !$postIds) {
			return [];
		}
		$this->getDbConnection();
		$result = [];
		foreach (BEForumReaction::finder()->findAll($this->inCondition('post_id', $postIds) . ' AND member_id = ?', [$member->getId()]) as $reaction) {
			$result[(int) $reaction->post_id] = (string) $reaction->type;
		}
		return $result;
	}

	/**
	 * @param BEForumPost $post the post
	 * @param int $limit the maximum number of reactions
	 * @return BEForumReaction[] the newest reactions on the post
	 */
	public function getReactions(BEForumPost $post, int $limit = 50): array
	{
		$this->getDbConnection();
		return BEForumReaction::finder()->findAll(BEForumReaction::criteria('post_id = ?', [$post->getId()], ['created_at' => 'desc'], max(1, $limit)));
	}

	/**
	 * Recomputes the reaction counters of every post.
	 * @return int the number of posts updated
	 */
	public function recountAll(): int
	{
		$connection = $this->getDbConnection();
		$reactions = $connection->quoteTableName(BEForumReaction::finder()->table());
		$sql = 'SELECT post_id, COUNT(*) AS total FROM ' . $reactions . ' GROUP BY post_id';
		$counts = [];
		foreach ($connection->createCommand($sql)->query() as $row) {
			$row = array_change_key_case($row, CASE_LOWER);
			$counts[(int) $row['post_id']] = (int) $row['total'];
		}
		BEForumPost::execute('UPDATE {table} SET reaction_count = 0');
		foreach ($counts as $postId => $total) {
			BEForumPost::execute('UPDATE {table} SET reaction_count = :count WHERE id = :id', ['count' => $total, 'id' => $postId]);
		}
		return count($counts);
	}
}
