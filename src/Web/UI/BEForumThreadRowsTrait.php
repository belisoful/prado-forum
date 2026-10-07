<?php

/**
 * BEForumThreadRowsTrait trait file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Web\UI;

use Belisoful\Forum\Data\BEForumMember;
use Belisoful\Forum\Data\BEForumPost;
use Belisoful\Forum\Data\BEForumTag;
use Belisoful\Forum\Data\BEForumThread;

/**
 * BEForumThreadRowsTrait trait.
 *
 * BEForumThreadRowsTrait builds the view model rows consumed by
 * {@see BEForumThreadRow} from a list of threads, loading authors, tags and
 * unread markers in batches.  It is shared by the thread list, the search
 * results and the member profile.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
trait BEForumThreadRowsTrait
{
	/**
	 * Builds the view model rows (HTML escaped).
	 * @param BEForumThread[] $threads the threads
	 * @param bool $showBoard
	 * @return array the rows
	 */
	public function buildThreadRows(array $threads, bool $showBoard = false): array
	{
		$forum = $this->getForum();
		$memberIds = [];
		foreach ($threads as $thread) {
			$memberIds[] = (int) $thread->member_id;
			$memberIds[] = (int) $thread->last_poster_member_id;
		}
		$members = $forum->getMembers()->getMembersByIds($memberIds);
		$tags = $forum->getEnableTags() ? $forum->getThreads()->getTagsFor($threads) : [];
		$unread = $forum->getReadTracker()->getUnreadMap($threads);
		// the last posts of guest posters are loaded for their guest names
		$guestLastPostIds = [];
		foreach ($threads as $thread) {
			if ($thread->last_post_id && !$thread->last_poster_member_id) {
				$guestLastPostIds[] = (int) $thread->last_post_id;
			}
		}
		$lastPosts = $guestLastPostIds ? $forum->getPosts()->getPostsByIds($guestLastPostIds) : [];
		$rows = [];
		foreach ($threads as $thread) {
			$rows[] = $this->threadRow($thread, $members, $tags[(int) $thread->getId()] ?? [], $unread[(int) $thread->getId()] ?? false, $showBoard, $lastPosts);
		}
		return $rows;
	}

	/**
	 * Builds the view model of one thread.
	 * @param BEForumThread $thread the thread
	 * @param array<int, BEForumMember> $members members keyed by id
	 * @param BEForumTag[] $tags the tags of the thread
	 * @param bool $unread whether the thread has unread posts
	 * @param bool $showBoard whether to include the board name and link
	 * @param array<int, BEForumPost> $lastPosts the last posts of guest posters keyed by id
	 * @return array the row
	 */
	public function threadRow(BEForumThread $thread, array $members, array $tags, bool $unread, bool $showBoard = false, array $lastPosts = []): array
	{
		$forum = $this->getForum();
		$urls = $this->getUrls();
		$author = $thread->member_id ? ($members[(int) $thread->member_id] ?? null) : null;
		$lastPoster = $thread->last_poster_member_id ? ($members[(int) $thread->last_poster_member_id] ?? null) : null;
		$tagRows = [];
		foreach ($tags as $tag) {
			$tagRows[] = ['name' => $this->e((string) $tag->name), 'url' => $this->e($urls->tag($tag))];
		}
		$unreadUrl = '';
		if ($unread) {
			$first = $forum->getReadTracker()->getFirstUnreadPost($thread);
			$unreadUrl = $first ? $urls->post($first) : $urls->thread($thread);
		}
		$lastPage = max(1, (int) ceil($thread->getPostCount() / $forum->getPostsPerPage()));
		$board = $showBoard ? $forum->getBoards()->findBoard((int) $thread->board_id) : null;
		$typeLabel = match ($thread->type) {
			BEForumThread::TYPE_QUESTION => $this->t('Question'),
			BEForumThread::TYPE_ANNOUNCEMENT => $this->t('Announcement'),
			default => '',
		};
		return [
			'id' => (int) $thread->getId(),
			'url' => $this->e($urls->thread($thread)),
			'title' => $this->e((string) $thread->title),
			'type' => (string) $thread->type,
			'typeLabel' => $this->e($typeLabel),
			'pinned' => $thread->getIsPinned(),
			'locked' => $thread->getIsLocked(),
			'solved' => $thread->getIsSolved(),
			'deleted' => $thread->getIsDeleted(),
			'pending' => !$thread->getIsApproved(),
			'unread' => $unread,
			'unreadUrl' => $this->e($unreadUrl),
			'author' => $this->memberLink($author, $thread->guest_name),
			'created' => $this->timeTag($thread->created_at),
			'replies' => (int) $thread->reply_count,
			'views' => (int) $thread->view_count,
			'tags' => $tagRows,
			'lastPoster' => $thread->last_post_at ? $this->memberLink($lastPoster, $lastPosts[(int) $thread->last_post_id]->guest_name ?? $thread->guest_name) : '',
			'lastTime' => $this->timeTag($thread->last_post_at),
			'lastUrl' => $this->e($urls->thread($thread, $lastPage, $thread->last_post_id ? (int) $thread->last_post_id : null)),
			'boardName' => $board ? $this->e((string) $board->name) : '',
			'boardUrl' => $board ? $this->e($urls->board($board)) : '',
		];
	}
}
