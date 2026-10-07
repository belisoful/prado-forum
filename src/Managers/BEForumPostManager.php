<?php

/**
 * BEForumPostManager class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Managers;

use Belisoful\Forum\Data\BEForumAttachment;
use Belisoful\Forum\Data\BEForumBookmark;
use Belisoful\Forum\Data\BEForumMember;
use Belisoful\Forum\Data\BEForumNotification;
use Belisoful\Forum\Data\BEForumPost;
use Belisoful\Forum\Data\BEForumPostRevision;
use Belisoful\Forum\Data\BEForumReaction;
use Belisoful\Forum\Data\BEForumReport;
use Belisoful\Forum\Data\BEForumSubscription;
use Belisoful\Forum\Data\BEForumThread;
use Belisoful\Forum\Exceptions\BEForumFloodException;
use Belisoful\Forum\Exceptions\BEForumForbiddenException;
use Belisoful\Forum\Exceptions\BEForumNotFoundException;
use Belisoful\Forum\Exceptions\BEForumValidationException;
use Belisoful\Forum\Security\BEForumPermissions;
use Belisoful\Forum\Util\BEForumPagination;
use Belisoful\Forum\Util\BEForumTime;

/**
 * BEForumPostManager class.
 *
 * BEForumPostManager creates, edits, lists and moderates {@see BEForumPost
 * posts}: replies (optionally to a specific post), flood control, content
 * validation and rendering, edit history with revisions, soft deletion and
 * restoration, approval, mention notifications and page calculation.
 *
 * ```php
 * $post = $forum->getPosts()->createPost($thread, 'Thanks @alice!', ['reply_to' => $other]);
 * [$posts, $pagination] = $forum->getPosts()->listPosts($thread, 1);
 * $forum->getPosts()->updatePost($post, 'Edited text', 'typo');
 * ```
 *
 * @method array dyValidatePost(array $data, null|BEForumPost $post, BEForumThread $thread)
 * @method int dyEditWindow(int $seconds, BEForumPost $post)
 * @method bool dyIsPostVisible(bool $visible, BEForumPost $post)
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumPostManager extends BEForumManager
{
	/**
	 * Throws when the member posted less than the flood interval ago.
	 * @param null|BEForumMember $member the member, guests are not throttled by member
	 * @throws BEForumFloodException when posting too fast
	 */
	public function checkFlood(?BEForumMember $member): void
	{
		$interval = $this->getModule()->getFloodInterval();
		if ($interval <= 0 || $member === null || !$member->last_post_at) {
			return;
		}
		if ($this->isModerator()) {
			return;
		}
		$age = BEForumTime::age($member->last_post_at);
		if ($age < $interval) {
			throw new BEForumFloodException($interval - $age);
		}
	}

	/**
	 * Validates post content length.
	 * @param null|string $content the raw content
	 * @throws BEForumValidationException when too short or too long
	 * @return string the trimmed content
	 */
	public function validateContent(?string $content): string
	{
		$module = $this->getModule();
		return $this->validateText('content', $content, $module->getMinPostLength(), $module->getMaxPostLength(), 'forum_post_content_required', 'forum_post_content_too_long');
	}

	/**
	 * Validates a content format.
	 * @param null|string $format the format, null for the module default
	 * @throws BEForumValidationException when the format is unsupported
	 * @return string the format
	 */
	public function validateFormat(?string $format): string
	{
		$module = $this->getModule();
		$format = strtolower(trim((string) $format));
		if ($format === '') {
			return $module->getContentFormat();
		}
		if (!$module->getRenderer()->isFormatSupported($format)) {
			throw new BEForumValidationException('format', 'forum_content_format_invalid', $format);
		}
		return $format;
	}

	/**
	 * Validates the guest name of an anonymous author.
	 * @param null|BEForumMember $member the current member
	 * @param null|string $guestName the guest name
	 * @throws BEForumValidationException when a guest gives no or an invalid name
	 * @return null|string the guest name, null for members
	 */
	public function validateGuestName(?BEForumMember $member, ?string $guestName): ?string
	{
		if ($member !== null) {
			return null;
		}
		$guestName = trim((string) $guestName);
		if ($guestName === '') {
			$guestName = $this->getModule()->getGuestName();
		}
		if (mb_strlen($guestName) > 120) {
			throw new BEForumValidationException('guest_name', 'forum_field_too_long', 'name', 120, mb_strlen($guestName));
		}
		if ($this->getModule()->getMembers()->findByUsername($guestName) !== null) {
			throw new BEForumValidationException('guest_name', 'forum_guest_name_taken', $guestName);
		}
		return $guestName;
	}

	/**
	 * Creates a reply in a thread.
	 * @param BEForumThread|int $thread the thread or its id
	 * @param string $content the raw content
	 * @param array<string, mixed> $options reply_to (BEForumPost|int), guest_name, format, ip_address, subscribe (bool)
	 * @throws BEForumValidationException when input is invalid
	 * @throws BEForumForbiddenException when not allowed
	 * @throws BEForumFloodException when posting too fast
	 * @return BEForumPost the post
	 */
	public function createPost($thread, string $content, array $options = []): BEForumPost
	{
		$module = $this->getModule();
		$thread = $thread instanceof BEForumThread ? $thread : $module->getThreads()->getThread((int) $thread);
		$board = $module->getThreads()->getBoardOf($thread);
		$module->getBoards()->ensureViewable($board);
		$extra = $this->extraFor($board);
		$this->authorize(BEForumPermissions::POST_CREATE, $extra);
		$this->ensureNotBanned(BEForumPermissions::POST_CREATE);
		$moderator = $this->isModerator($board);
		if ($thread->getIsDeleted()) {
			throw new BEForumNotFoundException('forum_thread_not_found', $thread->getId());
		}
		if ($thread->getIsLocked() && !$moderator) {
			throw new BEForumValidationException('thread', 'forum_thread_locked');
		}
		$member = $this->getMember();
		$this->checkFlood($member);
		$replyTo = null;
		if (!empty($options['reply_to'])) {
			$replyTo = $options['reply_to'] instanceof BEForumPost ? $options['reply_to'] : $this->findPost((int) $options['reply_to']);
			if ($replyTo === null || (int) $replyTo->thread_id !== $thread->getId()) {
				throw new BEForumValidationException('reply_to', 'forum_reply_target_invalid');
			}
		}
		$data = $this->dyValidatePost([
			'content' => $this->validateContent($content),
			'format' => $this->validateFormat($options['format'] ?? null),
			'guest_name' => $this->validateGuestName($member, $options['guest_name'] ?? null),
		], null, $thread);

		return $this->transaction(function () use ($thread, $member, $data, $options, $replyTo, $module, $moderator): BEForumPost {
			$approved = $moderator || !$module->getRequireApproval();
			$post = $this->createPostRecord($thread, $data['content'], [
				'format' => $data['format'],
				'guest_name' => $data['guest_name'],
				'ip_address' => $options['ip_address'] ?? null,
				'reply_to' => $replyTo,
				'is_approved' => $approved,
			]);
			if ($approved) {
				$this->registerPostInThread($thread, $post);
			}
			if ($member !== null && $module->getEnableSubscriptions() && ($options['subscribe'] ?? $member->getSetting('subscribe_on_reply', true))) {
				$module->getSubscriptions()->subscribe(BEForumSubscription::TYPE_THREAD, $thread->getId(), $member, false);
			}
			if ($member !== null) {
				$module->getReadTracker()->markThreadRead($thread, $post->getId());
			}
			if (!$post->getIsApproved()) {
				$module->getModeration()->flushRequestCache('pending');
			}
			$this->raise('onPostCreated', $post, ['thread' => $thread]);
			if ($approved) {
				$this->notifyThreadSubscribers($thread, $post);
				$this->notifyReplyTarget($post, $replyTo);
				$this->notifyMentions($post);
			}
			return $post;
		});
	}

	/**
	 * Inserts a post record with the next position of its thread and rendered
	 * content.  Used by {@see createPost} and by the thread manager for the
	 * opening post; it does not authorize or update counters.
	 * @param BEForumThread $thread the thread
	 * @param string $content the validated raw content
	 * @param array<string, mixed> $options format, guest_name, ip_address, reply_to (BEForumPost), is_approved
	 * @return BEForumPost the saved post
	 */
	public function createPostRecord(BEForumThread $thread, string $content, array $options = []): BEForumPost
	{
		$module = $this->getModule();
		$member = $this->getMember();
		$post = new BEForumPost();
		$post->thread_id = $thread->getId();
		$post->board_id = (int) $thread->board_id;
		$post->member_id = $member?->getId();
		$post->guest_name = $member === null ? ($options['guest_name'] ?? $module->getGuestName()) : null;
		$post->reply_to_post_id = isset($options['reply_to']) && $options['reply_to'] instanceof BEForumPost ? $options['reply_to']->getId() : null;
		$post->position = $this->nextPosition($thread);
		$post->content = $content;
		$post->content_format = $options['format'] ?? $module->getContentFormat();
		$post->content_html = $module->renderContent($content, $post->content_format);
		$post->ip_address = isset($options['ip_address']) ? mb_substr((string) $options['ip_address'], 0, 64) : $this->getRequestIp();
		$post->is_approved = $options['is_approved'] ?? true;
		$post->save();
		return $post;
	}

	/**
	 * @return null|string the client IP address of the current request
	 */
	protected function getRequestIp(): ?string
	{
		$app = $this->getModule()->getApplication();
		$request = $app ? $app->getRequest() : null;
		$ip = $request ? $request->getUserHostAddress() : null;
		return $ip ? mb_substr((string) $ip, 0, 64) : null;
	}

	/**
	 * @param BEForumThread $thread the thread
	 * @return int the next post position of the thread
	 */
	protected function nextPosition(BEForumThread $thread): int
	{
		$connection = $this->getDbConnection();
		if ($connection->getDriverName() !== 'sqlite' && $connection->getCurrentTransaction() !== null) {
			// serialize concurrent replies to the same thread so that positions stay unique
			$lock = $connection->createCommand('SELECT id FROM ' . $connection->quoteTableName(BEForumThread::finder()->table()) . ' WHERE id = :thread FOR UPDATE');
			$lock->bindValue(':thread', $thread->getId());
			$lock->queryScalar();
		}
		$command = $connection->createCommand('SELECT MAX(position) FROM ' . $connection->quoteTableName(BEForumPost::finder()->table()) . ' WHERE thread_id = :thread');
		$command->bindValue(':thread', $thread->getId());
		$max = $command->queryScalar();
		return ((int) $max) + 1;
	}

	/**
	 * Registers an approved post in its thread, board and author counters.
	 * @param BEForumThread $thread the thread
	 * @param BEForumPost $post the post
	 */
	protected function registerPostInThread(BEForumThread $thread, BEForumPost $post): void
	{
		$module = $this->getModule();
		$thread->refresh();
		BEForumThread::execute('UPDATE {table} SET reply_count = reply_count + 1, last_post_id = :post, last_post_at = :at, last_poster_member_id = :member WHERE id = :id', [
			'post' => $post->getId(),
			'at' => $post->created_at,
			'member' => $post->member_id,
			'id' => $thread->getId(),
		]);
		$thread->reply_count = (int) $thread->reply_count + 1;
		$thread->last_post_id = $post->getId();
		$thread->last_post_at = $post->created_at;
		$thread->last_poster_member_id = $post->member_id;
		if (!$thread->getIsDeleted() && $thread->getIsApproved()) {
			$module->getBoards()->adjustCounters((int) $thread->board_id, 0, 1);
			$module->getBoards()->setLastPost((int) $thread->board_id, $post);
		}
		$module->getMembers()->adjustCounters($post->member_id ? (int) $post->member_id : null, 1, 0, $post->created_at);
		$module->getThreads()->flushRequestCache('thread:' . $thread->getId());
	}

	/**
	 * Notifies the thread subscribers about a reply.
	 * @param BEForumThread $thread the thread
	 * @param BEForumPost $post the reply
	 */
	protected function notifyThreadSubscribers(BEForumThread $thread, BEForumPost $post): void
	{
		$module = $this->getModule();
		if (!$module->getEnableSubscriptions()) {
			return;
		}
		$recipients = $module->getSubscriptions()->getSubscribers(BEForumSubscription::TYPE_THREAD, $thread->getId());
		$module->getNotifications()->notifyMany($recipients, BEForumNotification::TYPE_REPLY, $this->getMember(), BEForumNotification::TARGET_POST, $post->getId(), [
			'thread_id' => $thread->getId(),
			'title' => $thread->title,
		], $post->member_id);
	}

	/**
	 * Notifies the author of the post being replied to.
	 * @param BEForumPost $post the reply
	 * @param null|BEForumPost $replyTo the post replied to
	 */
	protected function notifyReplyTarget(BEForumPost $post, ?BEForumPost $replyTo): void
	{
		if ($replyTo === null || !$replyTo->member_id || (int) $replyTo->member_id === (int) $post->member_id) {
			return;
		}
		$module = $this->getModule();
		if (!$module->getEnableSubscriptions()) {
			return;
		}
		$target = $module->getMembers()->findById((int) $replyTo->member_id);
		if ($target !== null) {
			$thread = $module->getThreads()->findThread((int) $post->thread_id);
			$module->getNotifications()->notify($target, BEForumNotification::TYPE_QUOTE, $this->getMember(), BEForumNotification::TARGET_POST, $post->getId(), ['thread_id' => (int) $post->thread_id, 'title' => $thread?->title]);
		}
	}

	/**
	 * Notifies the members mentioned in a post.
	 * @param BEForumPost $post the post
	 * @return int the number of members notified
	 */
	public function notifyMentions(BEForumPost $post): int
	{
		$module = $this->getModule();
		if (!$module->getEnableSubscriptions()) {
			return 0;
		}
		$thread = $module->getThreads()->findThread((int) $post->thread_id);
		$count = 0;
		foreach ($module->getRenderer()->extractMentions($post->content) as $username) {
			$member = $module->getMembers()->findByUsername($username);
			if ($member === null || $member->getId() === (int) $post->member_id) {
				continue;
			}
			if ($module->getNotifications()->notify($member, BEForumNotification::TYPE_MENTION, $this->getMember(), BEForumNotification::TARGET_POST, $post->getId(), ['thread_id' => (int) $post->thread_id, 'title' => $thread?->title]) !== null) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * @param int $id the post id
	 * @return null|BEForumPost the post, deleted posts included
	 */
	public function findPost(int $id): ?BEForumPost
	{
		if ($id <= 0) {
			return null;
		}
		$this->getDbConnection();
		return BEForumPost::findOne($id);
	}

	/**
	 * Returns a post the current user may view.
	 * @param int $id the post id
	 * @throws BEForumNotFoundException when the post does not exist or is not visible
	 * @return BEForumPost the post
	 */
	public function getPost(int $id): BEForumPost
	{
		$post = $this->findPost($id);
		if ($post === null || !$this->isVisible($post)) {
			throw new BEForumNotFoundException('forum_post_not_found', $id);
		}
		$this->getModule()->getThreads()->getThread((int) $post->thread_id);
		return $post;
	}

	/**
	 * @param BEForumPost $post the post
	 * @return bool whether the current user may see the post
	 */
	public function isVisible(BEForumPost $post): bool
	{
		$visible = true;
		if ($post->getIsDeleted() || !$post->getIsApproved()) {
			$visible = $this->isModerator((int) $post->board_id) || (!$post->getIsDeleted() && $this->isOwner($post));
		}
		return (bool) $this->dyIsPostVisible($visible, $post);
	}

	/**
	 * @param BEForumPost $post the post
	 * @return bool whether the current member wrote the post
	 */
	public function isOwner(BEForumPost $post): bool
	{
		$member = $this->getMember();
		return $member !== null && $post->member_id !== null && (int) $post->member_id === $member->getId();
	}

	/**
	 * @param BEForumPost $post the post
	 * @return null|string the username of the post author
	 */
	protected function ownerUsername(BEForumPost $post): ?string
	{
		if (!$post->member_id) {
			return null;
		}
		return $this->getModule()->getMembers()->findById((int) $post->member_id)?->username;
	}

	/**
	 * @param BEForumPost $post the post
	 * @return bool whether the edit window of the owner is still open
	 */
	public function isWithinEditWindow(BEForumPost $post): bool
	{
		$window = (int) $this->dyEditWindow($this->getModule()->getEditWindow(), $post);
		return $window <= 0 || BEForumTime::age($post->created_at) <= $window;
	}

	/**
	 * @param BEForumPost $post the post
	 * @return bool whether the current user may edit the post
	 */
	public function canEdit(BEForumPost $post): bool
	{
		if ($post->getIsDeleted()) {
			return false;
		}
		if ($this->isModerator((int) $post->board_id)) {
			return true;
		}
		$thread = $this->getModule()->getThreads()->findThread((int) $post->thread_id);
		if ($thread !== null && $thread->getIsLocked()) {
			return false;
		}
		if ($this->isOwner($post) && !$this->isWithinEditWindow($post)) {
			return false;
		}
		return $this->can(BEForumPermissions::POST_EDIT, $this->extraFor((int) $post->board_id, $this->ownerUsername($post)));
	}

	/**
	 * @param BEForumPost $post the post
	 * @return bool whether the current user may delete the post
	 */
	public function canDelete(BEForumPost $post): bool
	{
		if ($post->getIsDeleted()) {
			return false;
		}
		if ($this->isModerator((int) $post->board_id)) {
			return true;
		}
		$thread = $this->getModule()->getThreads()->findThread((int) $post->thread_id);
		if ($thread !== null && $thread->getIsLocked()) {
			return false;
		}
		if ($this->isOwner($post) && !$this->isWithinEditWindow($post)) {
			return false;
		}
		return $this->can(BEForumPermissions::POST_DELETE, $this->extraFor((int) $post->board_id, $this->ownerUsername($post)));
	}

	/**
	 * Lists a page of the posts of a thread in position order.
	 * @param BEForumThread $thread the thread
	 * @param int $page the 1-based page
	 * @param null|int $pageSize the page size, null for the module default
	 * @return array{0: BEForumPost[], 1: BEForumPagination} the posts and the pagination
	 */
	public function listPosts(BEForumThread $thread, int $page = 1, ?int $pageSize = null): array
	{
		$this->getDbConnection();
		$pageSize ??= $this->getModule()->getPostsPerPage();
		[$condition, $params] = $this->visibilityCondition($thread);
		$pagination = $this->paginate($page, $pageSize, BEForumPost::countWhere($condition, $params));
		$posts = BEForumPost::findAllPaged($condition, $params, ['position' => 'asc'], $pageSize, $pagination->getPage());
		return [$posts, $pagination];
	}

	/**
	 * @param BEForumThread $thread the thread
	 * @return array{0: string, 1: array} the condition and parameters selecting the posts of the thread visible to the current user
	 */
	protected function visibilityCondition(BEForumThread $thread): array
	{
		$condition = 'thread_id = :thread';
		$params = [':thread' => $thread->getId()];
		if (!$this->isModerator((int) $thread->board_id)) {
			$condition .= ' AND is_deleted = :notdeleted';
			$params[':notdeleted'] = false;
			$member = $this->getMember();
			if ($member !== null) {
				$condition .= ' AND (is_approved = :approved OR member_id = :self)';
				$params[':self'] = $member->getId();
			} else {
				$condition .= ' AND is_approved = :approved';
			}
			$params[':approved'] = true;
		}
		return [$condition, $params];
	}

	/**
	 * @param BEForumPost $post the post
	 * @return int the 1-based page of the thread containing the post
	 */
	public function getPageOfPost(BEForumPost $post): int
	{
		$thread = $this->getModule()->getThreads()->findThread((int) $post->thread_id);
		$pageSize = $this->getModule()->getPostsPerPage();
		if ($thread === null) {
			return (int) floor(max(0, (int) $post->position - 1) / $pageSize) + 1;
		}
		[$condition, $params] = $this->visibilityCondition($thread);
		$before = BEForumPost::countWhere($condition . ' AND position < :position', $params + [':position' => (int) $post->position]);
		return (int) floor($before / $pageSize) + 1;
	}

	/**
	 * Edits the content of a post, keeping the previous content as a revision.
	 * @param BEForumPost $post the post
	 * @param string $content the new raw content
	 * @param null|string $reason the edit reason
	 * @param null|string $format the new format, null keeps the current one
	 * @throws BEForumValidationException when input is invalid
	 * @throws BEForumForbiddenException when not allowed
	 * @return BEForumPost the post
	 */
	public function updatePost(BEForumPost $post, string $content, ?string $reason = null, ?string $format = null): BEForumPost
	{
		$post->refresh();
		$module = $this->getModule();
		$thread = $module->getThreads()->getThread((int) $post->thread_id);
		$this->authorize(BEForumPermissions::POST_EDIT, $this->extraFor((int) $post->board_id, $this->ownerUsername($post)));
		$this->ensureNotBanned(BEForumPermissions::POST_EDIT);
		if (!$this->canEdit($post)) {
			if ($post->getIsDeleted()) {
				throw new BEForumNotFoundException('forum_post_not_found', $post->getId());
			}
			if ($thread->getIsLocked()) {
				throw new BEForumValidationException('thread', 'forum_thread_locked');
			}
			throw new BEForumForbiddenException(BEForumPermissions::POST_EDIT, 'forum_edit_window_closed');
		}
		$data = $this->dyValidatePost([
			'content' => $this->validateContent($content),
			'format' => $format === null ? (string) $post->content_format : $this->validateFormat($format),
			'reason' => $reason === null ? null : mb_substr(trim($reason), 0, 255),
		], $post, $thread);
		if ($data['content'] === $post->content && $data['format'] === $post->content_format) {
			return $post;
		}
		return $this->transaction(function () use ($post, $data, $module): BEForumPost {
			$revision = new BEForumPostRevision();
			$revision->post_id = $post->getId();
			$revision->member_id = $this->getMember()?->getId();
			$revision->content = (string) $post->content;
			$revision->content_format = (string) $post->content_format;
			$revision->reason = $data['reason'];
			$revision->save();

			$post->content = $data['content'];
			$post->content_format = $data['format'];
			$post->content_html = $module->renderContent($data['content'], $data['format']);
			$post->edit_count = (int) $post->edit_count + 1;
			$post->edited_at = $this->now();
			$post->edited_by_member_id = $this->getMember()?->getId();
			$post->save();
			if (!$this->isOwner($post)) {
				$module->getModeration()->log('edit_post', BEForumNotification::TARGET_POST, $post->getId(), ['reason' => $data['reason']]);
			}
			$this->raise('onPostUpdated', $post, ['revision' => $revision, 'reason' => $data['reason']]);
			$this->notifyMentions($post);
			return $post;
		});
	}

	/**
	 * @param BEForumPost $post the post
	 * @return BEForumPostRevision[] the revisions, newest first
	 */
	public function getRevisions(BEForumPost $post): array
	{
		$this->getDbConnection();
		return BEForumPostRevision::finder()->findAll(BEForumPostRevision::criteria('post_id = ?', [$post->getId()], ['created_at' => 'desc', 'id' => 'desc']));
	}

	/**
	 * Soft deletes a post; deleting the opening post deletes the thread.
	 * @param BEForumPost $post the post
	 * @param null|string $reason the reason
	 * @return BEForumPost the post
	 */
	public function deletePost(BEForumPost $post, ?string $reason = null): BEForumPost
	{
		$post->refresh();
		$module = $this->getModule();
		$this->authorize(BEForumPermissions::POST_DELETE, $this->extraFor((int) $post->board_id, $this->ownerUsername($post)));
		if ($post->getIsDeleted()) {
			return $post;
		}
		$thread = $module->getThreads()->findThread((int) $post->thread_id);
		if (!$this->canDelete($post)) {
			if ($thread !== null && $thread->getIsLocked()) {
				throw new BEForumValidationException('thread', 'forum_thread_locked');
			}
			throw new BEForumForbiddenException(BEForumPermissions::POST_DELETE, 'forum_edit_window_closed');
		}
		if ($thread !== null && $post->getIsFirstPost()) {
			$module->getThreads()->deleteThread($thread, $reason);
			$post->refresh();
			return $post;
		}
		return $this->transaction(function () use ($post, $thread, $reason, $module): BEForumPost {
			$post->is_deleted = true;
			$post->deleted_at = $this->now();
			$post->deleted_by_member_id = $this->getMember()?->getId();
			$post->save();
			if ($post->getIsApproved()) {
				$module->getMembers()->adjustCounters($post->member_id ? (int) $post->member_id : null, -1);
				if ($thread !== null) {
					$module->getThreads()->recount($thread);
					if (!$thread->getIsDeleted() && $thread->getIsApproved()) {
						$module->getBoards()->adjustCounters((int) $post->board_id, 0, -1);
						$module->getBoards()->refreshLastPost((int) $post->board_id);
					}
					if ((int) $thread->accepted_post_id === $post->getId()) {
						BEForumThread::execute('UPDATE {table} SET accepted_post_id = NULL WHERE id = :id', ['id' => $thread->getId()]);
						$thread->accepted_post_id = null;
						$module->getThreads()->flushRequestCache('thread:' . $thread->getId());
					}
				}
			}
			$module->getModeration()->flushRequestCache('pending');
			if (!$this->isOwner($post)) {
				$module->getModeration()->log('delete_post', BEForumNotification::TARGET_POST, $post->getId(), ['reason' => $reason]);
			}
			$this->raise('onPostDeleted', $post, ['reason' => $reason]);
			return $post;
		});
	}

	/**
	 * Restores a soft deleted post.
	 * @param BEForumPost $post the post
	 * @return BEForumPost the post
	 */
	public function restorePost(BEForumPost $post): BEForumPost
	{
		$post->refresh();
		$module = $this->getModule();
		$this->authorize(BEForumPermissions::MODERATE, $this->extraFor((int) $post->board_id));
		$thread = $module->getThreads()->findThread((int) $post->thread_id);
		if ($thread !== null && $post->getIsFirstPost() && $thread->getIsDeleted()) {
			$module->getThreads()->restoreThread($thread);
		}
		if (!$post->getIsDeleted()) {
			return $post;
		}
		return $this->transaction(function () use ($post, $thread, $module): BEForumPost {
			$post->is_deleted = false;
			$post->deleted_at = null;
			$post->deleted_by_member_id = null;
			$post->save();
			if ($post->getIsApproved()) {
				$module->getMembers()->adjustCounters($post->member_id ? (int) $post->member_id : null, 1);
				if ($thread !== null) {
					$module->getThreads()->recount($thread);
					if (!$thread->getIsDeleted() && $thread->getIsApproved() && !$post->getIsFirstPost()) {
						$module->getBoards()->adjustCounters((int) $post->board_id, 0, 1);
					}
					$module->getBoards()->refreshLastPost((int) $post->board_id);
				}
			}
			$module->getModeration()->log('restore_post', BEForumNotification::TARGET_POST, $post->getId());
			$module->getModeration()->flushRequestCache('pending');
			$this->raise('onPostRestored', $post);
			return $post;
		});
	}

	/**
	 * Approves a post awaiting moderation.
	 * @param BEForumPost $post the post
	 * @return BEForumPost the post
	 */
	public function approvePost(BEForumPost $post): BEForumPost
	{
		$post->refresh();
		$module = $this->getModule();
		$this->authorize(BEForumPermissions::MODERATE, $this->extraFor((int) $post->board_id));
		if ($post->getIsApproved()) {
			return $post;
		}
		$thread = $module->getThreads()->findThread((int) $post->thread_id);
		if ($thread !== null && $post->getIsFirstPost()) {
			$module->getThreads()->approveThread($thread);
			$post->refresh();
			return $post;
		}
		return $this->transaction(function () use ($post, $thread, $module): BEForumPost {
			$post->is_approved = true;
			$post->save();
			if ($thread !== null && !$post->getIsDeleted()) {
				$this->registerPostInThread($thread, $post);
				$module->getThreads()->recount($thread);
				if (!$thread->getIsDeleted() && $thread->getIsApproved()) {
					$module->getBoards()->refreshLastPost((int) $post->board_id);
				}
			}
			$module->getModeration()->flushRequestCache('pending');
			$module->getModeration()->log('approve_post', BEForumNotification::TARGET_POST, $post->getId());
			$this->raise('onPostApproved', $post);
			if ($thread !== null) {
				$this->notifyThreadSubscribers($thread, $post);
				$this->notifyMentions($post);
			}
			return $post;
		});
	}

	/**
	 * Permanently removes a post and its dependent rows (used by thread purge).
	 * @param BEForumPost $post the post
	 */
	public function purgePostRecord(BEForumPost $post): void
	{
		$module = $this->getModule();
		$id = $post->getId();
		foreach (BEForumAttachment::finder()->findAll('post_id = ?', [$id]) as $attachment) {
			$module->getAttachments()->removeAttachment($attachment, false);
		}
		BEForumReaction::finder()->deleteAll('post_id = ?', [$id]);
		BEForumPostRevision::finder()->deleteAll('post_id = ?', [$id]);
		BEForumBookmark::finder()->deleteAll('post_id = ?', [$id]);
		BEForumReport::finder()->deleteAll('post_id = ?', [$id]);
		$post->delete();
	}

	/**
	 * Permanently removes a single reply.
	 * @param BEForumPost $post the post
	 * @throws BEForumValidationException when the post opens its thread (purge the thread instead)
	 */
	public function purgePost(BEForumPost $post): void
	{
		$post->refresh();
		$module = $this->getModule();
		$this->authorize(BEForumPermissions::MODERATE, $this->extraFor((int) $post->board_id));
		if ($post->getIsFirstPost()) {
			throw new BEForumValidationException('post', 'forum_purge_first_post');
		}
		$this->transaction(function () use ($post, $module): void {
			$counted = !$post->getIsDeleted() && $post->getIsApproved();
			$thread = $module->getThreads()->findThread((int) $post->thread_id);
			$id = $post->getId();
			$authorId = $post->member_id ? (int) $post->member_id : null;
			$boardId = (int) $post->board_id;
			$this->purgePostRecord($post);
			if ($counted) {
				$module->getMembers()->adjustCounters($authorId, -1);
				if ($thread !== null && !$thread->getIsDeleted() && $thread->getIsApproved()) {
					$module->getBoards()->adjustCounters($boardId, 0, -1);
				}
			}
			if ($thread !== null) {
				if ((int) $thread->accepted_post_id === $id) {
					$thread->accepted_post_id = null;
					$thread->save();
				}
				$module->getThreads()->recount($thread);
			}
			$module->getBoards()->refreshLastPost($boardId);
			$module->getModeration()->log('purge_post', BEForumNotification::TARGET_POST, $id);
			$module->getModeration()->flushRequestCache('pending');
			$this->raise('onPostDeleted', null, ['id' => $id, 'purged' => true]);
		});
	}

	/**
	 * Lists the most recent visible posts.
	 * @param int $limit the maximum number of posts
	 * @param null|int $boardId an optional board
	 * @return BEForumPost[] the posts, newest first
	 */
	public function getRecentPosts(int $limit = 10, ?int $boardId = null): array
	{
		$this->getDbConnection();
		$boardIds = $boardId === null ? $this->getModule()->getBoards()->getVisibleBoardIds() : [$boardId];
		$condition = $this->inCondition('board_id', $boardIds) . ' AND is_deleted = :notdeleted AND is_approved = :approved AND ' . $this->publicThreadCondition();
		$params = [':notdeleted' => false, ':approved' => true];
		return BEForumPost::finder()->findAll(BEForumPost::criteria($condition, $params, ['created_at' => 'desc', 'id' => 'desc'], max(1, $limit)));
	}

	/**
	 * Lists the posts of a member.
	 * @param BEForumMember $member the member
	 * @param int $page the 1-based page
	 * @param null|int $pageSize the page size
	 * @return array{0: BEForumPost[], 1: BEForumPagination} the posts and the pagination
	 */
	public function getPostsByMember(BEForumMember $member, int $page = 1, ?int $pageSize = null): array
	{
		$this->getDbConnection();
		$pageSize ??= $this->getModule()->getItemsPerPage();
		$condition = 'member_id = :member AND ' . $this->inCondition('board_id', $this->getModule()->getBoards()->getVisibleBoardIds()) . ' AND is_deleted = :notdeleted AND is_approved = :approved AND ' . $this->publicThreadCondition();
		$params = [':member' => $member->getId(), ':notdeleted' => false, ':approved' => true];
		$pagination = $this->paginate($page, $pageSize, BEForumPost::countWhere($condition, $params));
		return [BEForumPost::findAllPaged($condition, $params, ['created_at' => 'desc'], $pageSize, $pagination->getPage()), $pagination];
	}

	/**
	 * Loads several posts by id.
	 * @param int[] $ids the post ids
	 * @return array<int, BEForumPost> the posts keyed by id
	 */
	public function getPostsByIds(array $ids): array
	{
		$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
		if (!$ids) {
			return [];
		}
		$this->getDbConnection();
		return $this->indexById(BEForumPost::finder()->findAll($this->inCondition('id', $ids)));
	}

	/**
	 * Re-renders the HTML of every post (after changing renderer settings).
	 * @return int the number of posts rendered
	 */
	public function rerenderAll(): int
	{
		$module = $this->getModule();
		$this->getDbConnection();
		$count = 0;
		$offset = 0;
		while (true) {
			$posts = BEForumPost::finder()->findAll(BEForumPost::criteria(null, [], ['id' => 'asc'], 200, $offset));
			if (!$posts) {
				break;
			}
			foreach ($posts as $post) {
				$post->content_html = $module->renderContent((string) $post->content, (string) $post->content_format);
				$post->save();
				$count++;
			}
			$offset += 200;
		}
		return $count;
	}

	/**
	 * Counts the visible posts of the forum.
	 * @return int the count
	 */
	public function countPosts(): int
	{
		$this->getDbConnection();
		return BEForumPost::countWhere('is_deleted = :notdeleted AND is_approved = :approved AND ' . $this->publicThreadCondition(), [':notdeleted' => false, ':approved' => true]);
	}

	/**
	 * @return string a SQL condition (using the `:notdeleted` and `:approved` parameters of the caller) restricting posts to threads that are neither deleted nor pending
	 */
	public function publicThreadCondition(): string
	{
		$connection = $this->getDbConnection();
		return 'thread_id IN (SELECT id FROM ' . $connection->quoteTableName(BEForumThread::finder()->table()) . ' WHERE is_deleted = :notdeleted AND is_approved = :approved)';
	}
}
