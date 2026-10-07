<?php

/**
 * BEForumReadTracker class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Managers;

use Belisoful\Forum\Data\BEForumBoard;
use Belisoful\Forum\Data\BEForumMember;
use Belisoful\Forum\Data\BEForumPost;
use Belisoful\Forum\Data\BEForumThread;
use Belisoful\Forum\Data\BEForumThreadRead;
use Belisoful\Forum\Util\BEForumTime;

/**
 * BEForumReadTracker class.
 *
 * BEForumReadTracker remembers which posts a member has read so listings can
 * show unread markers and "first unread" links.  Per thread read positions
 * are stored in `thread_reads`; marking a whole board or the whole forum read
 * stores a timestamp in the member settings (`read_board_<id>`, `read_all`).
 * Activity older than {@see getUnreadHorizonDays} is always considered read.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumReadTracker extends BEForumManager
{
	/** The member setting holding the "mark all read" time */
	public const SETTING_READ_ALL = 'read_all';

	/** The prefix of the member settings holding the "mark board read" times */
	public const SETTING_READ_BOARD = 'read_board_';

	/** @var int days after which unread activity is ignored */
	private int $_unreadHorizonDays = 30;

	/**
	 * @return int days after which unread activity is ignored
	 */
	public function getUnreadHorizonDays(): int
	{
		return $this->_unreadHorizonDays;
	}

	/**
	 * @param int $days days after which unread activity is ignored, at least 1
	 */
	public function setUnreadHorizonDays(int $days): void
	{
		$this->_unreadHorizonDays = max(1, $days);
	}

	/**
	 * Records that the current member has read a thread up to a post.
	 * @param BEForumThread $thread the thread
	 * @param null|int $lastPostId the last read post id, null for the thread's last post
	 * @param null|BEForumMember $member the member, null for the current member
	 * @return null|BEForumThreadRead the read record, null for guests
	 */
	public function markThreadRead(BEForumThread $thread, ?int $lastPostId = null, ?BEForumMember $member = null): ?BEForumThreadRead
	{
		$member ??= $this->getMember();
		if ($member === null) {
			return null;
		}
		$lastPostId = $lastPostId ?? ($thread->last_post_id ? (int) $thread->last_post_id : null);
		$this->getDbConnection();
		$read = BEForumThreadRead::finder()->find('member_id = ? AND thread_id = ?', [$member->getId(), $thread->getId()]);
		if (!($read instanceof BEForumThreadRead)) {
			$read = new BEForumThreadRead();
			$read->member_id = $member->getId();
			$read->thread_id = $thread->getId();
		} elseif ($lastPostId !== null && $read->last_read_post_id !== null && (int) $read->last_read_post_id >= $lastPostId) {
			return $read;
		}
		$read->last_read_post_id = $lastPostId;
		$read->read_at = $this->now();
		$read->save();
		$this->flushRequestCache();
		return $read;
	}

	/**
	 * Marks every thread of a board as read for the current member.
	 * @param BEForumBoard $board the board
	 */
	public function markBoardRead(BEForumBoard $board): void
	{
		$member = $this->getMember();
		if ($member === null) {
			return;
		}
		$member->setSetting(self::SETTING_READ_BOARD . $board->getId(), $this->now());
		$this->getModule()->getMembers()->saveSettings($member);
		$this->flushRequestCache();
	}

	/**
	 * Marks the whole forum as read for the current member.
	 */
	public function markAllRead(): void
	{
		$member = $this->getMember();
		if ($member === null) {
			return;
		}
		$settings = $member->getJsonColumn('settings');
		foreach (array_keys($settings) as $key) {
			if (str_starts_with((string) $key, self::SETTING_READ_BOARD)) {
				unset($settings[$key]);
			}
		}
		$settings[self::SETTING_READ_ALL] = $this->now();
		$member->setJsonColumn('settings', $settings);
		$this->getModule()->getMembers()->saveSettings($member);
		$this->flushRequestCache();
	}

	/**
	 * @param BEForumMember $member the member
	 * @param int $boardId the board id
	 * @return int the unix time before which everything in the board counts as read
	 */
	protected function getReadHorizon(BEForumMember $member, int $boardId): int
	{
		$horizon = BEForumTime::timestamp() - 86400 * $this->_unreadHorizonDays;
		foreach ([self::SETTING_READ_ALL, self::SETTING_READ_BOARD . $boardId] as $key) {
			$stamp = BEForumTime::parse($member->getSetting($key));
			if ($stamp !== null && $stamp > $horizon) {
				$horizon = $stamp;
			}
		}
		return $horizon;
	}

	/**
	 * @param BEForumThread $thread the thread
	 * @return bool whether the thread has unread posts for the current member
	 */
	public function isThreadUnread(BEForumThread $thread): bool
	{
		return $this->getUnreadMap([$thread])[(int) $thread->getId()] ?? false;
	}

	/**
	 * Computes the unread state of several threads in one query.
	 * @param BEForumThread[] $threads the threads
	 * @return array<int, bool> whether each thread (keyed by id) has unread posts
	 */
	public function getUnreadMap(array $threads): array
	{
		$member = $this->getMember();
		$result = [];
		if ($member === null || !$threads) {
			foreach ($threads as $thread) {
				$result[(int) $thread->getId()] = false;
			}
			return $result;
		}
		$this->getDbConnection();
		$reads = [];
		$ids = array_map(fn (BEForumThread $thread) => (int) $thread->getId(), $threads);
		foreach (BEForumThreadRead::finder()->findAll($this->inCondition('thread_id', $ids) . ' AND member_id = ?', [$member->getId()]) as $read) {
			$reads[(int) $read->thread_id] = $read;
		}
		foreach ($threads as $thread) {
			$id = (int) $thread->getId();
			$lastAt = BEForumTime::parse($thread->last_post_at);
			if ($lastAt === null || $lastAt <= $this->getReadHorizon($member, (int) $thread->board_id)) {
				$result[$id] = false;
				continue;
			}
			$read = $reads[$id] ?? null;
			if ($read === null) {
				$result[$id] = true;
				continue;
			}
			$result[$id] = $thread->last_post_id !== null && (int) $read->last_read_post_id < (int) $thread->last_post_id;
		}
		return $result;
	}

	/**
	 * Finds the first unread post of a thread for the current member.
	 * @param BEForumThread $thread the thread
	 * @return null|BEForumPost the first unread post, null when everything is read
	 */
	public function getFirstUnreadPost(BEForumThread $thread): ?BEForumPost
	{
		$member = $this->getMember();
		if ($member === null || !$this->isThreadUnread($thread)) {
			return null;
		}
		$this->getDbConnection();
		$read = BEForumThreadRead::finder()->find('member_id = ? AND thread_id = ?', [$member->getId(), $thread->getId()]);
		$lastRead = $read instanceof BEForumThreadRead ? (int) $read->last_read_post_id : 0;
		$horizon = BEForumTime::format($this->getReadHorizon($member, (int) $thread->board_id));
		$post = BEForumPost::finder()->find(BEForumPost::criteria('thread_id = ? AND id > ? AND created_at > ? AND is_deleted = ? AND is_approved = ?', [$thread->getId(), $lastRead, $horizon, false, true], ['position' => 'asc'], 1));
		return $post instanceof BEForumPost ? $post : null;
	}

	/**
	 * Computes the unread state of boards from their last post time.
	 * @param BEForumBoard[] $boards the boards
	 * @return array<int, bool> whether each board (keyed by id) has unread activity
	 */
	public function getBoardUnreadMap(array $boards): array
	{
		$member = $this->getMember();
		$result = [];
		foreach ($boards as $board) {
			$id = (int) $board->getId();
			if ($member === null) {
				$result[$id] = false;
				continue;
			}
			$lastAt = BEForumTime::parse($board->last_post_at);
			$result[$id] = $lastAt !== null && $lastAt > $this->getReadHorizon($member, $id);
		}
		if ($member !== null) {
			$candidates = array_filter($boards, fn (BEForumBoard $board) => $result[(int) $board->getId()]);
			if ($candidates) {
				$this->getDbConnection();
				foreach ($candidates as $board) {
					$threads = BEForumThread::finder()->findAll(BEForumThread::criteria('board_id = ? AND is_deleted = ? AND is_approved = ? AND last_post_at > ?', [$board->getId(), false, true, BEForumTime::format($this->getReadHorizon($member, (int) $board->getId()))], ['last_post_at' => 'desc'], 50));
					$unread = $this->getUnreadMap($threads);
					$result[(int) $board->getId()] = in_array(true, $unread, true);
				}
			}
		}
		return $result;
	}
}
