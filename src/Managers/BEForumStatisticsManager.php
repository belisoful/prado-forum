<?php

/**
 * BEForumStatisticsManager class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Managers;

use Belisoful\Forum\Data\BEForumMember;
use Belisoful\Forum\Data\BEForumPost;
use Belisoful\Forum\Data\BEForumThread;
use Belisoful\Forum\Util\BEForumTime;

/**
 * BEForumStatisticsManager class.
 *
 * BEForumStatisticsManager computes forum wide figures (threads, posts,
 * members, newest member, members online) for the statistics control, caches
 * them in the application cache when one is configured, and recomputes every
 * denormalised counter on request.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumStatisticsManager extends BEForumManager
{
	/** The application cache key of the summary */
	public const CACHE_KEY = 'beforum:statistics';

	/** @var int seconds the summary is cached */
	private int $_cacheDuration = 60;

	/** @var int the presence window in minutes */
	private int $_onlineMinutes = 15;

	/**
	 * @return int seconds the summary is cached
	 */
	public function getCacheDuration(): int
	{
		return $this->_cacheDuration;
	}

	/**
	 * @param int $seconds seconds the summary is cached, 0 disables caching
	 */
	public function setCacheDuration(int $seconds): void
	{
		$this->_cacheDuration = max(0, $seconds);
	}

	/**
	 * @return int the presence window in minutes
	 */
	public function getOnlineMinutes(): int
	{
		return $this->_onlineMinutes;
	}

	/**
	 * @param int $minutes the presence window in minutes
	 */
	public function setOnlineMinutes(int $minutes): void
	{
		$this->_onlineMinutes = max(1, $minutes);
	}

	/**
	 * @return string the cache key including the table prefix
	 */
	protected function getCacheKey(): string
	{
		return self::CACHE_KEY . ':' . $this->getModule()->getTablePrefix();
	}

	/**
	 * Returns the forum summary.
	 * @param bool $fresh whether to bypass the cache
	 * @return array{threads: int, posts: int, members: int, newest_member: null|string, newest_member_username: null|string, online: int, generated_at: string}
	 */
	public function getSummary(bool $fresh = false): array
	{
		$app = $this->getModule()->getApplication();
		$cache = ($app && $this->_cacheDuration > 0) ? $app->getCache() : null;
		if ($cache !== null && !$fresh) {
			$summary = $cache->get($this->getCacheKey());
			if (is_array($summary)) {
				return $summary;
			}
		}
		$module = $this->getModule();
		$newest = $module->getMembers()->getNewestMembers(1);
		$newest = $newest[0] ?? null;
		$summary = [
			'threads' => $module->getThreads()->countThreads(),
			'posts' => $module->getPosts()->countPosts(),
			'members' => $module->getMembers()->countMembers(),
			'newest_member' => $newest instanceof BEForumMember ? $newest->getDisplayName() : null,
			'newest_member_username' => $newest instanceof BEForumMember ? (string) $newest->username : null,
			'online' => count($module->getMembers()->getOnlineMembers($this->_onlineMinutes)),
			'generated_at' => BEForumTime::now(),
		];
		if ($cache !== null) {
			$cache->set($this->getCacheKey(), $summary, $this->_cacheDuration);
		}
		return $summary;
	}

	/**
	 * Removes the cached summary.
	 */
	public function invalidate(): void
	{
		$app = $this->getModule()->getApplication();
		$cache = $app ? $app->getCache() : null;
		if ($cache !== null) {
			$cache->delete($this->getCacheKey());
		}
	}

	/**
	 * @param int $limit the maximum number of members
	 * @return BEForumMember[] the members with the most posts
	 */
	public function getTopPosters(int $limit = 10): array
	{
		$this->getDbConnection();
		return BEForumMember::finder()->findAll(BEForumMember::criteria('post_count > 0', [], ['post_count' => 'desc', 'username' => 'asc'], max(1, $limit)));
	}

	/**
	 * @param int $limit the maximum number of members
	 * @return BEForumMember[] the members with the highest reputation
	 */
	public function getTopReputation(int $limit = 10): array
	{
		$this->getDbConnection();
		return BEForumMember::finder()->findAll(BEForumMember::criteria('reputation > 0', [], ['reputation' => 'desc', 'username' => 'asc'], max(1, $limit)));
	}

	/**
	 * @param int $limit the maximum number of threads
	 * @return BEForumThread[] the most viewed visible threads
	 */
	public function getMostViewedThreads(int $limit = 10): array
	{
		$this->getDbConnection();
		return BEForumThread::finder()->findAll(BEForumThread::criteria($this->inCondition('board_id', $this->getModule()->getBoards()->getVisibleBoardIds()) . ' AND is_deleted = ? AND is_approved = ?', [false, true], ['view_count' => 'desc', 'id' => 'desc'], max(1, $limit)));
	}

	/**
	 * Recomputes every denormalised counter of the forum.
	 * @return array<string, int> the number of records recounted per area
	 */
	public function recountAll(): array
	{
		$module = $this->getModule();
		$this->getDbConnection();
		$result = [
			'threads' => $module->getThreads()->recountAll(),
			'boards' => $module->getBoards()->recountAll(),
			'tags' => $module->getTags()->recountAll(),
			'reactions' => $module->getReactions()->recountAll(),
			'members' => $this->recountMembers(),
		];
		$this->invalidate();
		return $result;
	}

	/**
	 * Recomputes the post and thread counters of every member.
	 * @return int the number of members recounted
	 */
	public function recountMembers(): int
	{
		$this->getDbConnection();
		$count = 0;
		foreach (BEForumMember::finder()->findAll() as $member) {
			$posts = BEForumPost::countWhere('member_id = ? AND is_deleted = ? AND is_approved = ?', [$member->getId(), false, true]);
			$threads = BEForumThread::countWhere('member_id = ? AND is_deleted = ? AND is_approved = ?', [$member->getId(), false, true]);
			$last = BEForumPost::finder()->find(BEForumPost::criteria('member_id = ? AND is_deleted = ?', [$member->getId(), false], ['created_at' => 'desc'], 1));
			BEForumMember::execute('UPDATE {table} SET post_count = :posts, thread_count = :threads, last_post_at = :last WHERE id = :id', [
				'posts' => $posts,
				'threads' => $threads,
				'last' => $last instanceof BEForumPost ? $last->created_at : null,
				'id' => $member->getId(),
			]);
			$count++;
		}
		return $count;
	}
}
