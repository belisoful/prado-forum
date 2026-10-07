<?php

/**
 * BEForumThreadManager class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Managers;

use Belisoful\Forum\Data\BEForumBoard;
use Belisoful\Forum\Data\BEForumMember;
use Belisoful\Forum\Data\BEForumNotification;
use Belisoful\Forum\Data\BEForumPost;
use Belisoful\Forum\Data\BEForumSubscription;
use Belisoful\Forum\Data\BEForumTag;
use Belisoful\Forum\Data\BEForumThread;
use Belisoful\Forum\Data\BEForumThreadRead;
use Belisoful\Forum\Data\BEForumThreadTag;
use Belisoful\Forum\Exceptions\BEForumFloodException;
use Belisoful\Forum\Exceptions\BEForumForbiddenException;
use Belisoful\Forum\Exceptions\BEForumNotFoundException;
use Belisoful\Forum\Exceptions\BEForumValidationException;
use Belisoful\Forum\Security\BEForumPermissions;
use Belisoful\Forum\Util\BEForumPagination;
use Belisoful\Forum\Util\BEForumSlug;
use Belisoful\Forum\Util\BEForumTime;

/**
 * BEForumThreadManager class.
 *
 * BEForumThreadManager creates, lists and moderates {@see BEForumThread
 * threads}: creation with the opening post, tags and an optional poll;
 * paged board listings with pinned threads first; editing; locking, pinning,
 * moving, approving, marking solved; soft deletion and restoration; view
 * counting.  Every change keeps the denormalised counters of boards and
 * members in sync and raises the matching module event.
 *
 * ```php
 * $thread = $forum->getThreads()->createThread($board, 'Title', 'Body **markdown**', [
 *     'type' => BEForumThread::TYPE_QUESTION,
 *     'tags' => ['php', 'prado'],
 *     'poll' => ['question' => 'Yes?', 'options' => ['Yes', 'No']],
 * ]);
 * [$threads, $pagination] = $forum->getThreads()->listThreads($board, 2);
 * ```
 *
 * @method array dyValidateThread(array $data, null|BEForumThread $thread)
 * @method bool dyIsThreadVisible(bool $visible, BEForumThread $thread)
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumThreadManager extends BEForumManager
{
	/** The reputation awarded to the author of an accepted answer */
	public const ACCEPTED_REPUTATION = 5;

	/**
	 * Creates a thread with its opening post.
	 * @param BEForumBoard|int $board the board or its id
	 * @param string $title the title
	 * @param string $content the raw content of the opening post
	 * @param array<string, mixed> $options type, tags (string[]), poll (question, options, max_choices, closes_at, allow_revote), guest_name, format, ip_address, subscribe (bool)
	 * @throws BEForumValidationException when input is invalid
	 * @throws BEForumForbiddenException when not allowed
	 * @throws BEForumFloodException when posting too fast
	 * @return BEForumThread the thread
	 */
	public function createThread($board, string $title, string $content, array $options = []): BEForumThread
	{
		$board = $board instanceof BEForumBoard ? $board : $this->getModule()->getBoards()->getBoard((int) $board);
		$boards = $this->getModule()->getBoards();
		$boards->ensureViewable($board);
		$extra = $this->extraFor($board);
		$this->authorize(BEForumPermissions::THREAD_CREATE, $extra);
		$this->ensureNotBanned(BEForumPermissions::THREAD_CREATE);
		$moderator = $this->isModerator($board);
		if ($board->getIsLocked() && !$moderator) {
			throw new BEForumValidationException('board', 'forum_board_locked', $board->name);
		}
		$member = $this->getMember();
		$posts = $this->getModule()->getPosts();
		$posts->checkFlood($member);

		$module = $this->getModule();
		$data = $this->dyValidateThread([
			'title' => $this->validateText('title', $title, 1, $module->getMaxTitleLength(), 'forum_thread_title_required', 'forum_thread_title_too_long'),
			'content' => $posts->validateContent($content),
			'type' => $this->validateType($options['type'] ?? BEForumThread::TYPE_DISCUSSION),
			'tags' => $options['tags'] ?? [],
			'poll' => $options['poll'] ?? null,
			'guest_name' => $posts->validateGuestName($member, $options['guest_name'] ?? null),
			'format' => $posts->validateFormat($options['format'] ?? null),
		], null);
		if ($data['type'] === BEForumThread::TYPE_ANNOUNCEMENT && !$moderator) {
			throw new BEForumValidationException('type', 'forum_thread_type_moderator', $data['type']);
		}

		return $this->transaction(function () use ($board, $member, $data, $options, $module, $posts, $moderator): BEForumThread {
			$thread = new BEForumThread();
			$thread->board_id = $board->getId();
			$thread->member_id = $member?->getId();
			$thread->guest_name = $data['guest_name'];
			$thread->title = $data['title'];
			$thread->type = $data['type'];
			$thread->slug = BEForumSlug::unique($data['title'], fn (string $slug) => BEForumThread::countWhere('board_id = ? AND slug = ?', [$board->getId(), $slug]) > 0, 120);
			$thread->is_approved = $moderator || !$module->getRequireApproval();
			$thread->save();

			$post = $posts->createPostRecord($thread, $data['content'], [
				'format' => $data['format'],
				'guest_name' => $data['guest_name'],
				'ip_address' => $options['ip_address'] ?? null,
				'is_approved' => $thread->getIsApproved(),
			]);
			$thread->first_post_id = $post->getId();
			$thread->last_post_id = $post->getId();
			$thread->last_post_at = $post->created_at;
			$thread->last_poster_member_id = $post->member_id;
			$thread->reply_count = 0;
			$thread->save();

			if ($module->getEnableTags() && $data['tags']) {
				$module->getTags()->setThreadTags($thread, (array) $data['tags'], false);
			}
			if ($data['poll'] !== null && $module->getEnablePolls()) {
				$module->getPolls()->createPoll($thread, $data['poll']);
			}
			if ($thread->getIsApproved()) {
				$module->getBoards()->adjustCounters($board->getId(), 1, 1);
				$module->getBoards()->setLastPost($board->getId(), $post);
				$module->getMembers()->adjustCounters($thread->member_id, 1, 1, $post->created_at);
			}
			if ($member !== null && $module->getEnableSubscriptions() && ($options['subscribe'] ?? $member->getSetting('subscribe_own_threads', true))) {
				$module->getSubscriptions()->subscribe(BEForumSubscription::TYPE_THREAD, $thread->getId(), $member, false);
			}
			if ($member !== null) {
				$module->getReadTracker()->markThreadRead($thread, $post->getId());
			}
			if (!$thread->getIsApproved()) {
				$module->getModeration()->flushRequestCache('pending');
			}
			$this->raise('onPostCreated', $post, ['thread' => $thread, 'first' => true]);
			$this->raise('onThreadCreated', $thread, ['post' => $post]);
			if ($thread->getIsApproved()) {
				$this->notifyBoardSubscribers($thread, $post);
				$posts->notifyMentions($post);
			}
			return $thread;
		});
	}

	/**
	 * Notifies the subscribers of a board about a new thread.
	 * @param BEForumThread $thread the thread
	 * @param BEForumPost $post the opening post
	 */
	protected function notifyBoardSubscribers(BEForumThread $thread, BEForumPost $post): void
	{
		$module = $this->getModule();
		if (!$module->getEnableSubscriptions()) {
			return;
		}
		$recipients = $module->getSubscriptions()->getSubscribers(BEForumSubscription::TYPE_BOARD, (int) $thread->board_id);
		$module->getNotifications()->notifyMany($recipients, BEForumNotification::TYPE_THREAD, $this->getMember(), BEForumNotification::TARGET_THREAD, $thread->getId(), [
			'title' => $thread->title,
			'board_id' => (int) $thread->board_id,
			'post_id' => $post->getId(),
		], $thread->member_id);
	}

	/**
	 * @param mixed $type a thread type
	 * @throws BEForumValidationException when the type is unknown
	 * @return string the validated type
	 */
	public function validateType($type): string
	{
		$type = strtolower(trim((string) $type));
		if ($type === '') {
			return BEForumThread::TYPE_DISCUSSION;
		}
		if (!in_array($type, BEForumThread::getTypes(), true)) {
			throw new BEForumValidationException('type', 'forum_thread_type_invalid', $type);
		}
		return $type;
	}

	/**
	 * @param int $id the thread id
	 * @return null|BEForumThread the thread, deleted threads included
	 */
	public function findThread(int $id): ?BEForumThread
	{
		if ($id <= 0) {
			return null;
		}
		return $this->cached('thread:' . $id, function () use ($id): ?BEForumThread {
			$this->getDbConnection();
			return BEForumThread::findOne($id);
		});
	}

	/**
	 * Returns a thread the current user may view.
	 * @param int $id the thread id
	 * @throws BEForumNotFoundException when the thread does not exist or is not visible
	 * @return BEForumThread the thread
	 */
	public function getThread(int $id): BEForumThread
	{
		$thread = $this->findThread($id);
		if ($thread === null || !$this->isVisible($thread)) {
			throw new BEForumNotFoundException('forum_thread_not_found', $id);
		}
		$this->getModule()->getBoards()->ensureViewable($this->getBoardOf($thread));
		return $thread;
	}

	/**
	 * @param BEForumThread $thread the thread
	 * @throws BEForumNotFoundException when the board is gone
	 * @return BEForumBoard the board of the thread
	 */
	public function getBoardOf(BEForumThread $thread): BEForumBoard
	{
		return $this->getModule()->getBoards()->getBoard((int) $thread->board_id);
	}

	/**
	 * @param BEForumThread $thread the thread
	 * @return bool whether the current user may see the thread (deleted and unapproved threads are moderator only)
	 */
	public function isVisible(BEForumThread $thread): bool
	{
		$visible = true;
		if ($thread->getIsDeleted() || !$thread->getIsApproved()) {
			$visible = $this->isModerator((int) $thread->board_id) || (!$thread->getIsDeleted() && $this->isOwner($thread));
		}
		return (bool) $this->dyIsThreadVisible($visible, $thread);
	}

	/**
	 * @param BEForumThread $thread the thread
	 * @return bool whether the current member started the thread
	 */
	public function isOwner(BEForumThread $thread): bool
	{
		$member = $this->getMember();
		return $member !== null && $thread->member_id !== null && (int) $thread->member_id === $member->getId();
	}

	/**
	 * @param BEForumThread $thread the thread
	 * @return null|string the username of the thread author
	 */
	protected function ownerUsername(BEForumThread $thread): ?string
	{
		if (!$thread->member_id) {
			return null;
		}
		$author = $this->getModule()->getMembers()->findById((int) $thread->member_id);
		return $author?->username;
	}

	/**
	 * @param BEForumThread $thread the thread
	 * @return bool whether the current user may edit the thread
	 */
	public function canEdit(BEForumThread $thread): bool
	{
		return $this->can(BEForumPermissions::THREAD_EDIT, $this->extraFor((int) $thread->board_id, $this->ownerUsername($thread)));
	}

	/**
	 * @param BEForumThread $thread the thread
	 * @return bool whether the current user may delete the thread
	 */
	public function canDelete(BEForumThread $thread): bool
	{
		return $this->can(BEForumPermissions::THREAD_DELETE, $this->extraFor((int) $thread->board_id, $this->ownerUsername($thread)));
	}

	/**
	 * @param BEForumThread $thread the thread
	 * @return bool whether the current user may reply
	 */
	public function canReply(BEForumThread $thread): bool
	{
		if ($thread->getIsDeleted()) {
			return false;
		}
		if ($thread->getIsLocked() && !$this->isModerator((int) $thread->board_id)) {
			return false;
		}
		return $this->can(BEForumPermissions::POST_CREATE, $this->extraFor((int) $thread->board_id));
	}

	/**
	 * Lists the threads of a board, pinned first then by last activity.
	 * @param BEForumBoard|int $board the board or its id
	 * @param int $page the 1-based page
	 * @param array<string, mixed> $filters type, tag (slug), member_id, include_subboards (bool)
	 * @param null|int $pageSize the page size, null for the module default
	 * @return array{0: BEForumThread[], 1: BEForumPagination} the threads and the pagination
	 */
	public function listThreads($board, int $page = 1, array $filters = [], ?int $pageSize = null): array
	{
		$board = $board instanceof BEForumBoard ? $board : $this->getModule()->getBoards()->getBoard((int) $board);
		$this->getDbConnection();
		$pageSize ??= $this->getModule()->getThreadsPerPage();
		$boardIds = !empty($filters['include_subboards']) ? $this->getModule()->getBoards()->getDescendantIds($board) : [$board->getId()];
		$conditions = [$this->inCondition('board_id', $boardIds)];
		$params = [];
		$this->applyVisibilityConditions($conditions, $params, (int) $board->getId());
		if (!empty($filters['type'])) {
			$conditions[] = 'type = :type';
			$params[':type'] = $this->validateType($filters['type']);
		}
		if (!empty($filters['member_id'])) {
			$conditions[] = 'member_id = :member';
			$params[':member'] = (int) $filters['member_id'];
		}
		if (!empty($filters['tag'])) {
			$tag = $this->getModule()->getTags()->findBySlug((string) $filters['tag']);
			if ($tag === null) {
				return [[], $this->paginate(1, $pageSize, 0)];
			}
			$conditions[] = $this->inCondition('id', $this->getModule()->getTags()->getThreadIdsForTag($tag));
		}
		$condition = implode(' AND ', $conditions);
		$pagination = $this->paginate($page, $pageSize, BEForumThread::countWhere($condition, $params));
		$threads = BEForumThread::findAllPaged($condition, $params, ['is_pinned' => 'desc', 'last_post_at' => 'desc', 'id' => 'desc'], $pageSize, $pagination->getPage());
		return [$this->filterExpiredPins($threads), $pagination];
	}

	/**
	 * Adds the deleted/approved conditions for the current user.
	 * @param string[] $conditions by reference, the conditions
	 * @param array $params by reference, the parameters
	 * @param null|int $boardId the board deciding moderator status, null for global
	 */
	protected function applyVisibilityConditions(array &$conditions, array &$params, ?int $boardId): void
	{
		if ($this->isModerator($boardId)) {
			return;
		}
		$conditions[] = 'is_deleted = :notdeleted';
		$params[':notdeleted'] = false;
		$member = $this->getMember();
		if ($member !== null) {
			$conditions[] = '(is_approved = :approved OR member_id = :self)';
			$params[':self'] = $member->getId();
		} else {
			$conditions[] = 'is_approved = :approved';
		}
		$params[':approved'] = true;
	}

	/**
	 * Re-sorts a page so threads whose pin expired fall below still pinned threads.
	 * @param BEForumThread[] $threads the threads
	 * @return BEForumThread[] the threads
	 */
	protected function filterExpiredPins(array $threads): array
	{
		usort($threads, function (BEForumThread $a, BEForumThread $b): int {
			$pin = (int) $b->getIsPinned() <=> (int) $a->getIsPinned();
			if ($pin !== 0) {
				return $pin;
			}
			return strcmp((string) $b->last_post_at, (string) $a->last_post_at) ?: ($b->getId() <=> $a->getId());
		});
		return $threads;
	}

	/**
	 * Lists the most recently active visible threads.
	 * @param int $limit the maximum number of threads
	 * @param null|BEForumBoard|int $board an optional board
	 * @return BEForumThread[] the threads
	 */
	public function getRecentThreads(int $limit = 10, $board = null): array
	{
		$this->getDbConnection();
		$conditions = [$this->inCondition('board_id', $board === null ? $this->getModule()->getBoards()->getVisibleBoardIds() : [$board instanceof BEForumBoard ? $board->getId() : (int) $board])];
		$params = [];
		$this->applyVisibilityConditions($conditions, $params, $board === null ? null : ($board instanceof BEForumBoard ? $board->getId() : (int) $board));
		return BEForumThread::finder()->findAll(BEForumThread::criteria(implode(' AND ', $conditions), $params, ['last_post_at' => 'desc', 'id' => 'desc'], max(1, $limit)));
	}

	/**
	 * Lists the threads started by a member.
	 * @param BEForumMember $member the member
	 * @param int $page the 1-based page
	 * @param null|int $pageSize the page size
	 * @return array{0: BEForumThread[], 1: BEForumPagination} the threads and the pagination
	 */
	public function getThreadsByMember(BEForumMember $member, int $page = 1, ?int $pageSize = null): array
	{
		$this->getDbConnection();
		$pageSize ??= $this->getModule()->getItemsPerPage();
		$conditions = ['member_id = :member', $this->inCondition('board_id', $this->getModule()->getBoards()->getVisibleBoardIds())];
		$params = [':member' => $member->getId()];
		$this->applyVisibilityConditions($conditions, $params, null);
		$condition = implode(' AND ', $conditions);
		$pagination = $this->paginate($page, $pageSize, BEForumThread::countWhere($condition, $params));
		return [BEForumThread::findAllPaged($condition, $params, ['created_at' => 'desc'], $pageSize, $pagination->getPage()), $pagination];
	}

	/**
	 * Lists the threads carrying a tag.
	 * @param BEForumTag $tag the tag
	 * @param int $page the 1-based page
	 * @param null|int $pageSize the page size
	 * @return array{0: BEForumThread[], 1: BEForumPagination} the threads and the pagination
	 */
	public function getThreadsByTag(BEForumTag $tag, int $page = 1, ?int $pageSize = null): array
	{
		$this->getDbConnection();
		$pageSize ??= $this->getModule()->getThreadsPerPage();
		$conditions = [$this->inCondition('id', $this->getModule()->getTags()->getThreadIdsForTag($tag)), $this->inCondition('board_id', $this->getModule()->getBoards()->getVisibleBoardIds())];
		$params = [];
		$this->applyVisibilityConditions($conditions, $params, null);
		$condition = implode(' AND ', $conditions);
		$pagination = $this->paginate($page, $pageSize, BEForumThread::countWhere($condition, $params));
		return [BEForumThread::findAllPaged($condition, $params, ['last_post_at' => 'desc'], $pageSize, $pagination->getPage()), $pagination];
	}

	/**
	 * Loads several threads by id.
	 * @param int[] $ids the thread ids
	 * @return array<int, BEForumThread> the threads keyed by id
	 */
	public function getThreadsByIds(array $ids): array
	{
		$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
		if (!$ids) {
			return [];
		}
		$this->getDbConnection();
		return $this->indexById(BEForumThread::finder()->findAll(BEForumThread::criteria($this->inCondition('id', $ids), [], ['id' => 'asc'])));
	}

	/**
	 * Updates the title, type and tags of a thread.
	 * @param BEForumThread $thread the thread
	 * @param array<string, mixed> $fields title, type, tags
	 * @throws BEForumValidationException when a value is invalid
	 * @return BEForumThread the thread
	 */
	public function updateThread(BEForumThread $thread, array $fields): BEForumThread
	{
		$thread->refresh();
		$this->authorize(BEForumPermissions::THREAD_EDIT, $this->extraFor((int) $thread->board_id, $this->ownerUsername($thread)));
		$this->ensureNotBanned(BEForumPermissions::THREAD_EDIT);
		$module = $this->getModule();
		if (!$this->isModerator((int) $thread->board_id)) {
			if ($thread->getIsDeleted()) {
				throw new BEForumNotFoundException('forum_thread_not_found', $thread->getId());
			}
			if ($thread->getIsLocked()) {
				throw new BEForumValidationException('thread', 'forum_thread_locked');
			}
		}
		$data = [];
		if (array_key_exists('title', $fields)) {
			$data['title'] = $this->validateText('title', (string) $fields['title'], 1, $module->getMaxTitleLength(), 'forum_thread_title_required', 'forum_thread_title_too_long');
		}
		if (array_key_exists('type', $fields)) {
			$data['type'] = $this->validateType($fields['type']);
			if ($data['type'] === BEForumThread::TYPE_ANNOUNCEMENT && $data['type'] !== $thread->type && !$this->isModerator((int) $thread->board_id)) {
				throw new BEForumValidationException('type', 'forum_thread_type_moderator', $data['type']);
			}
		}
		if (array_key_exists('tags', $fields)) {
			$data['tags'] = (array) $fields['tags'];
		}
		$data = $this->dyValidateThread($data, $thread);
		$previous = ['title' => $thread->title, 'type' => $thread->type];
		return $this->transaction(function () use ($thread, $data, $previous, $module): BEForumThread {
			if (isset($data['title'])) {
				$thread->title = $data['title'];
			}
			if (isset($data['type'])) {
				$thread->type = $data['type'];
				if ($data['type'] !== BEForumThread::TYPE_QUESTION) {
					$thread->accepted_post_id = null;
				}
			}
			$thread->save();
			if (isset($data['tags']) && $module->getEnableTags()) {
				$module->getTags()->setThreadTags($thread, $data['tags'], false);
			}
			$this->flushRequestCache('thread:' . $thread->getId());
			$this->raise('onThreadUpdated', $thread, ['previous' => $previous]);
			return $thread;
		});
	}

	/**
	 * Soft deletes a thread.
	 * @param BEForumThread $thread the thread
	 * @param null|string $reason the reason
	 * @return BEForumThread the thread
	 */
	public function deleteThread(BEForumThread $thread, ?string $reason = null): BEForumThread
	{
		$thread->refresh();
		$this->authorize(BEForumPermissions::THREAD_DELETE, $this->extraFor((int) $thread->board_id, $this->ownerUsername($thread)));
		if ($thread->getIsDeleted()) {
			return $thread;
		}
		return $this->transaction(function () use ($thread, $reason): BEForumThread {
			$module = $this->getModule();
			$thread->is_deleted = true;
			$thread->deleted_at = $this->now();
			$thread->deleted_by_member_id = $this->getMember()?->getId();
			$thread->save();
			if ($thread->getIsApproved()) {
				$module->getBoards()->adjustCounters((int) $thread->board_id, -1, -$thread->getPostCount());
				$module->getBoards()->refreshLastPost((int) $thread->board_id);
				$module->getMembers()->adjustCounters($thread->member_id ? (int) $thread->member_id : null, 0, -1);
			}
			$this->flushRequestCache('thread:' . $thread->getId());
			$module->getModeration()->flushRequestCache('pending');
			$module->getModeration()->log('delete_thread', BEForumNotification::TARGET_THREAD, $thread->getId(), ['reason' => $reason, 'title' => $thread->title]);
			$this->raise('onThreadDeleted', $thread, ['reason' => $reason]);
			return $thread;
		});
	}

	/**
	 * Restores a soft deleted thread.
	 * @param BEForumThread $thread the thread
	 * @return BEForumThread the thread
	 */
	public function restoreThread(BEForumThread $thread): BEForumThread
	{
		$thread->refresh();
		$this->authorize(BEForumPermissions::MODERATE, $this->extraFor((int) $thread->board_id));
		if (!$thread->getIsDeleted()) {
			return $thread;
		}
		return $this->transaction(function () use ($thread): BEForumThread {
			$module = $this->getModule();
			$thread->is_deleted = false;
			$thread->deleted_at = null;
			$thread->deleted_by_member_id = null;
			$thread->save();
			if ($thread->getIsApproved()) {
				$module->getBoards()->adjustCounters((int) $thread->board_id, 1, $thread->getPostCount());
				$module->getBoards()->refreshLastPost((int) $thread->board_id);
				$module->getMembers()->adjustCounters($thread->member_id ? (int) $thread->member_id : null, 0, 1);
			}
			$this->flushRequestCache('thread:' . $thread->getId());
			$module->getModeration()->flushRequestCache('pending');
			$module->getModeration()->log('restore_thread', BEForumNotification::TARGET_THREAD, $thread->getId(), ['title' => $thread->title]);
			$this->raise('onThreadRestored', $thread);
			return $thread;
		});
	}

	/**
	 * Permanently removes a thread with all its posts and dependent rows.
	 * @param BEForumThread $thread the thread
	 */
	public function purgeThread(BEForumThread $thread): void
	{
		$thread->refresh();
		$this->authorize(BEForumPermissions::MODERATE, $this->extraFor((int) $thread->board_id));
		$this->transaction(function () use ($thread): void {
			$module = $this->getModule();
			$id = $thread->getId();
			$wasCounted = !$thread->getIsDeleted() && $thread->getIsApproved();
			$authorId = $thread->member_id ? (int) $thread->member_id : null;
			foreach (BEForumPost::finder()->findAll('thread_id = ?', [$id]) as $post) {
				if (!$post->getIsDeleted() && $post->getIsApproved()) {
					$module->getMembers()->adjustCounters($post->member_id ? (int) $post->member_id : null, -1);
				}
				$module->getPosts()->purgePostRecord($post);
			}
			$module->getPolls()->deletePollOfThread($thread);
			$module->getTags()->setThreadTags($thread, [], false);
			BEForumThreadRead::finder()->deleteAll('thread_id = ?', [$id]);
			$module->getSubscriptions()->removeTarget(BEForumSubscription::TYPE_THREAD, $id);
			$boardId = (int) $thread->board_id;
			$postCount = $thread->getPostCount();
			$title = $thread->title;
			$thread->delete();
			if ($wasCounted) {
				$module->getBoards()->adjustCounters($boardId, -1, -$postCount);
				$module->getMembers()->adjustCounters($authorId, 0, -1);
			}
			$module->getBoards()->refreshLastPost($boardId);
			$this->flushRequestCache('thread:' . $id);
			$module->getModeration()->flushRequestCache('pending');
			$module->getModeration()->log('purge_thread', BEForumNotification::TARGET_THREAD, $id, ['title' => $title]);
			$this->raise('onThreadDeleted', null, ['id' => $id, 'title' => $title, 'purged' => true]);
		});
	}

	/**
	 * Locks or unlocks a thread.
	 * @param BEForumThread $thread the thread
	 * @param bool $locked whether the thread is locked
	 * @return BEForumThread the thread
	 */
	public function setLocked(BEForumThread $thread, bool $locked): BEForumThread
	{
		$thread->refresh();
		$this->authorize(BEForumPermissions::MODERATE, $this->extraFor((int) $thread->board_id));
		$thread->is_locked = $locked;
		$thread->save();
		$this->flushRequestCache('thread:' . $thread->getId());
		$this->getModule()->getModeration()->log($locked ? 'lock_thread' : 'unlock_thread', BEForumNotification::TARGET_THREAD, $thread->getId());
		$this->raise('onThreadUpdated', $thread, ['locked' => $locked]);
		return $thread;
	}

	/**
	 * Pins or unpins a thread, optionally until a time.
	 * @param BEForumThread $thread the thread
	 * @param bool $pinned whether the thread is pinned
	 * @param null|string $until a storage time or strtotime expression, null for indefinitely
	 * @throws BEForumValidationException when the time is invalid
	 * @return BEForumThread the thread
	 */
	public function setPinned(BEForumThread $thread, bool $pinned, ?string $until = null): BEForumThread
	{
		$thread->refresh();
		$this->authorize(BEForumPermissions::MODERATE, $this->extraFor((int) $thread->board_id));
		$thread->is_pinned = $pinned;
		$thread->pinned_until = null;
		if ($pinned && $until !== null && trim($until) !== '') {
			$stamp = BEForumTime::parse($until);
			if ($stamp === null) {
				throw new BEForumValidationException('until', 'forum_pin_until_invalid', $until);
			}
			$thread->pinned_until = BEForumTime::format($stamp);
		}
		$thread->save();
		$this->flushRequestCache('thread:' . $thread->getId());
		$this->getModule()->getModeration()->log($pinned ? 'pin_thread' : 'unpin_thread', BEForumNotification::TARGET_THREAD, $thread->getId(), ['until' => $thread->pinned_until]);
		$this->raise('onThreadUpdated', $thread, ['pinned' => $pinned]);
		return $thread;
	}

	/**
	 * Clears expired pins.
	 * @return int the number of threads unpinned
	 */
	public function expirePins(): int
	{
		$this->getDbConnection();
		return BEForumThread::execute('UPDATE {table} SET is_pinned = :off, pinned_until = NULL WHERE is_pinned = :on AND pinned_until IS NOT NULL AND pinned_until <= :now', ['off' => false, 'on' => true, 'now' => $this->now()]);
	}

	/**
	 * Moves a thread to another board.
	 * @param BEForumThread $thread the thread
	 * @param BEForumBoard|int $target the target board or its id
	 * @throws BEForumValidationException when the target is the current board
	 * @return BEForumThread the thread
	 */
	public function moveThread(BEForumThread $thread, $target): BEForumThread
	{
		$thread->refresh();
		$target = $target instanceof BEForumBoard ? $target : $this->getModule()->getBoards()->getBoard((int) $target);
		$this->authorize(BEForumPermissions::MODERATE, $this->extraFor((int) $thread->board_id));
		$this->authorize(BEForumPermissions::MODERATE, $this->extraFor($target));
		if ((int) $thread->board_id === $target->getId()) {
			throw new BEForumValidationException('board', 'forum_thread_move_same_board');
		}
		return $this->transaction(function () use ($thread, $target): BEForumThread {
			$module = $this->getModule();
			$source = (int) $thread->board_id;
			$thread->board_id = $target->getId();
			$thread->slug = BEForumSlug::unique($thread->slug, fn (string $slug) => BEForumThread::countWhere('board_id = ? AND slug = ? AND id <> ?', [$target->getId(), $slug, $thread->getId()]) > 0, 120);
			$thread->save();
			BEForumPost::execute('UPDATE {table} SET board_id = :board WHERE thread_id = :thread', ['board' => $target->getId(), 'thread' => $thread->getId()]);
			if (!$thread->getIsDeleted() && $thread->getIsApproved()) {
				$module->getBoards()->adjustCounters($source, -1, -$thread->getPostCount());
				$module->getBoards()->adjustCounters($target->getId(), 1, $thread->getPostCount());
			}
			$module->getBoards()->refreshLastPost($source);
			$module->getBoards()->refreshLastPost($target->getId());
			$this->flushRequestCache('thread:' . $thread->getId());
			$module->getModeration()->log('move_thread', BEForumNotification::TARGET_THREAD, $thread->getId(), ['from' => $source, 'to' => $target->getId()]);
			$this->raise('onThreadMoved', $thread, ['from' => $source, 'to' => $target->getId()]);
			return $thread;
		});
	}

	/**
	 * Approves a thread awaiting moderation (and its opening post).
	 * @param BEForumThread $thread the thread
	 * @return BEForumThread the thread
	 */
	public function approveThread(BEForumThread $thread): BEForumThread
	{
		$thread->refresh();
		$this->authorize(BEForumPermissions::MODERATE, $this->extraFor((int) $thread->board_id));
		if ($thread->getIsApproved()) {
			return $thread;
		}
		return $this->transaction(function () use ($thread): BEForumThread {
			$module = $this->getModule();
			$thread->is_approved = true;
			$thread->save();
			BEForumPost::execute('UPDATE {table} SET is_approved = :on WHERE id = :id', ['on' => true, 'id' => (int) $thread->first_post_id]);
			if (!$thread->getIsDeleted()) {
				$module->getBoards()->adjustCounters((int) $thread->board_id, 1, 1);
				$module->getBoards()->refreshLastPost((int) $thread->board_id);
				$module->getMembers()->adjustCounters($thread->member_id ? (int) $thread->member_id : null, 1, 1, $thread->last_post_at);
			}
			$this->flushRequestCache('thread:' . $thread->getId());
			$module->getModeration()->flushRequestCache('pending');
			$module->getModeration()->log('approve_thread', BEForumNotification::TARGET_THREAD, $thread->getId());
			$this->raise('onThreadApproved', $thread);
			$post = $thread->first_post_id ? $module->getPosts()->findPost((int) $thread->first_post_id) : null;
			if ($post !== null) {
				$this->notifyBoardSubscribers($thread, $post);
				$module->getPosts()->notifyMentions($post);
			}
			return $thread;
		});
	}

	/**
	 * Marks a post as the accepted answer of a question thread (or clears it with null).
	 * @param BEForumThread $thread the thread
	 * @param null|BEForumPost $post the accepted post, null to clear
	 * @throws BEForumValidationException when the thread is not a question or the post is foreign
	 * @return BEForumThread the thread
	 */
	public function setAcceptedPost(BEForumThread $thread, ?BEForumPost $post): BEForumThread
	{
		$thread->refresh();
		$this->authorize(BEForumPermissions::THREAD_EDIT, $this->extraFor((int) $thread->board_id, $this->ownerUsername($thread)));
		if (!$thread->getIsQuestion()) {
			throw new BEForumValidationException('thread', 'forum_thread_not_question');
		}
		if ($post !== null && ((int) $post->thread_id !== $thread->getId() || $post->getIsFirstPost() || $post->getIsDeleted())) {
			throw new BEForumValidationException('post', 'forum_accepted_post_invalid');
		}
		$previous = $thread->accepted_post_id ? (int) $thread->accepted_post_id : null;
		if ($previous === $post?->getId()) {
			return $thread;
		}
		if (!$this->isModerator((int) $thread->board_id) && ($thread->getIsLocked() || $thread->getIsDeleted())) {
			throw new BEForumValidationException('thread', 'forum_thread_locked');
		}
		BEForumThread::execute('UPDATE {table} SET accepted_post_id = :post WHERE id = :id', ['post' => $post?->getId(), 'id' => $thread->getId()]);
		$thread->accepted_post_id = $post?->getId();
		$this->flushRequestCache('thread:' . $thread->getId());
		$members = $this->getModule()->getMembers();
		$previousPost = $previous !== null ? $this->getModule()->getPosts()->findPost($previous) : null;
		if ($previousPost !== null && $previousPost->member_id && (int) $previousPost->member_id !== $this->getMember()?->getId()) {
			$previousAuthor = $members->findById((int) $previousPost->member_id);
			if ($previousAuthor !== null) {
				$members->adjustReputation($previousAuthor, -self::ACCEPTED_REPUTATION);
			}
		}
		if ($post !== null && $post->member_id && (int) $post->member_id !== $this->getMember()?->getId()) {
			$author = $members->findById((int) $post->member_id);
			if ($author !== null) {
				$members->adjustReputation($author, self::ACCEPTED_REPUTATION);
				$this->getModule()->getNotifications()->notify($author, BEForumNotification::TYPE_ACCEPTED, $this->getMember(), BEForumNotification::TARGET_POST, $post->getId(), ['thread_id' => $thread->getId(), 'title' => $thread->title]);
			}
		}
		$this->raise('onThreadSolved', $thread, ['post' => $post, 'previous' => $previous]);
		return $thread;
	}

	/**
	 * Counts a view of a thread, at most once per session per thread.
	 * @param BEForumThread $thread the thread
	 * @return bool whether the view was counted
	 */
	public function countView(BEForumThread $thread): bool
	{
		$app = $this->getModule()->getApplication();
		$session = null;
		try {
			$session = $app ? $app->getSession() : null;
		} catch (\Throwable $e) {
			$session = null;
		}
		if ($session !== null) {
			try {
				$session->open();
				$key = 'beforum:viewed';
				$viewed = $session->itemAt($key);
				$viewed = is_array($viewed) ? $viewed : [];
				if (isset($viewed[$thread->getId()])) {
					return false;
				}
				$viewed[$thread->getId()] = BEForumTime::timestamp();
				if (count($viewed) > 500) {
					$viewed = array_slice($viewed, -250, null, true);
				}
				$session->add($key, $viewed);
			} catch (\Throwable $e) {
				// a session that cannot start (headers sent, CLI) only disables the throttle
			}
		}
		BEForumThread::execute('UPDATE {table} SET view_count = view_count + 1 WHERE id = :id', ['id' => $thread->getId()]);
		$thread->view_count = (int) $thread->view_count + 1;
		return true;
	}

	/**
	 * Recomputes the counters and last post of a thread from its posts.
	 * @param BEForumThread $thread the thread
	 * @return BEForumThread the thread
	 */
	public function recount(BEForumThread $thread): BEForumThread
	{
		$this->getDbConnection();
		$first = BEForumPost::finder()->find(BEForumPost::criteria('thread_id = ?', [$thread->getId()], ['position' => 'asc'], 1));
		$last = BEForumPost::finder()->find(BEForumPost::criteria('thread_id = ? AND is_deleted = ? AND is_approved = ?', [$thread->getId(), false, true], ['position' => 'desc'], 1));
		$count = BEForumPost::countWhere('thread_id = ? AND is_deleted = ? AND is_approved = ?', [$thread->getId(), false, true]);
		$thread->first_post_id = $first instanceof BEForumPost ? $first->getId() : null;
		$thread->reply_count = max(0, $count - 1);
		$thread->last_post_id = $last instanceof BEForumPost ? $last->getId() : null;
		// without a visible post the thread keeps sorting by its creation time
		$thread->last_post_at = $last instanceof BEForumPost ? $last->created_at : $thread->created_at;
		$thread->last_poster_member_id = $last instanceof BEForumPost ? $last->member_id : null;
		// a targeted update so that counters maintained by other requests are not clobbered by a stale instance
		BEForumThread::execute('UPDATE {table} SET first_post_id = :first, reply_count = :replies, last_post_id = :post, last_post_at = :at, last_poster_member_id = :member WHERE id = :id', [
			'first' => $thread->first_post_id,
			'replies' => $thread->reply_count,
			'post' => $thread->last_post_id,
			'at' => $thread->last_post_at,
			'member' => $thread->last_poster_member_id,
			'id' => $thread->getId(),
		]);
		$this->flushRequestCache('thread:' . $thread->getId());
		return $thread;
	}

	/**
	 * Recomputes every thread.
	 * @return int the number of threads recounted
	 */
	public function recountAll(): int
	{
		$this->getDbConnection();
		$count = 0;
		foreach (BEForumThread::finder()->findAll() as $thread) {
			$this->recount($thread);
			$count++;
		}
		return $count;
	}

	/**
	 * @param BEForumThread[] $threads the threads
	 * @return array<int, BEForumTag[]> the tags keyed by thread id
	 */
	public function getTagsFor(array $threads): array
	{
		return $this->getModule()->getTags()->getTagsForThreads(array_map(fn (BEForumThread $thread) => (int) $thread->getId(), $threads));
	}

	/**
	 * @param BEForumThread $thread the thread
	 * @return BEForumThreadTag[] the tag assignments
	 */
	public function getThreadTags(BEForumThread $thread): array
	{
		$this->getDbConnection();
		return BEForumThreadTag::finder()->findAll('thread_id = ?', [$thread->getId()]);
	}

	/**
	 * Counts the visible threads of the forum.
	 * @return int the count
	 */
	public function countThreads(): int
	{
		$this->getDbConnection();
		return BEForumThread::countWhere('is_deleted = ? AND is_approved = ?', [false, true]);
	}
}
