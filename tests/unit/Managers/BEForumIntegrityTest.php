<?php

use Belisoful\Forum\Data\BEForumAttachment;
use Belisoful\Forum\Data\BEForumSubscription;
use Belisoful\Forum\Data\BEForumThread;
use Belisoful\Forum\Exceptions\BEForumForbiddenException;
use Belisoful\Forum\Exceptions\BEForumNotFoundException;
use Belisoful\Forum\Exceptions\BEForumValidationException;
use Belisoful\Forum\Managers\BEForumManager;
use Belisoful\Forum\Managers\BEForumThreadManager;

/**
 * Cross manager integrity: counters stay consistent with the recounts,
 * hidden content never leaks into public listings, searches escape
 * wildcards, caches are invalidated and ownership rules are enforced.
 */
class BEForumIntegrityTest extends BEForumTestCase
{
	public function testLikeEscapingMatchesLiteralWildcards(): void
	{
		self::assertSame(" ESCAPE '!'", BEForumManager::LIKE_ESCAPE_CLAUSE);
		$board = $this->createBoard();
		$this->createThreadAs($board, 'alice', 'Underscore', 'the my_var token and 100% sure');
		$this->createThreadAs($board, 'alice', 'Plain', 'the myXvar token and 100 sure');
		$this->logout();
		$search = $this->forum->getSearch();
		[$posts] = $search->searchPosts('my_var');
		self::assertCount(1, $posts, 'an underscore is a literal character, not a single-character wildcard');
		[$posts] = $search->searchPosts('100%');
		self::assertCount(1, $posts, 'a percent sign is a literal character');
		[$posts] = $search->searchPosts('token!');
		self::assertCount(0, $posts, 'the escape character itself is escaped');
		[$posts, $pagination] = $search->searchPosts('token', ['page_size' => 1]);
		self::assertSame(2, $pagination->getItemCount(), 'counting ignores the ordering of the criteria');
		self::assertCount(1, $posts);
		$this->member('under_score');
		$this->member('underXscore');
		[$members] = $this->forum->getMembers()->listMembers(1, 'under_');
		self::assertCount(1, $members);
		self::assertSame('under_score', $members[0]->username);
		$this->createThreadAs($board, 'alice', 'Tagged', 'tagged', ['tags' => ['ph_one', 'phXone']]);
		$suggested = $this->forum->getTags()->suggest('ph_');
		self::assertCount(1, $suggested);
		self::assertSame('ph_one', $suggested[0]->name);
	}

	public function testValidationMessagesNameFieldAndLimits(): void
	{
		$board = $this->createBoard();
		$this->loginAs('admin');
		try {
			$this->forum->getBoards()->createCategory(str_repeat('x', 121));
			self::fail('too long');
		} catch (BEForumValidationException $e) {
			self::assertSame('forum_field_too_long', $e->getErrorCode());
			self::assertSame("The field 'name' must not exceed 120 characters (121 given).", $e->getMessage());
		}
		try {
			$this->forum->getThreads()->createThread($board, str_repeat('t', 300), 'content');
			self::fail('title too long');
		} catch (BEForumValidationException $e) {
			self::assertSame('The title must not exceed ' . $this->forum->getMaxTitleLength() . ' characters (300 given).', $e->getMessage());
		}
		$alice = $this->member('alice');
		$this->loginAs('alice');
		foreach (['website' => 'https://example.com/' . str_repeat('p', 500), 'avatar_url' => 'https://example.com/' . str_repeat('a', 500)] as $field => $value) {
			try {
				$this->forum->getMembers()->updateProfile($alice, [$field => $value]);
				self::fail($field . ' too long');
			} catch (BEForumValidationException $e) {
				self::assertSame('forum_field_too_long', $e->getErrorCode());
			}
		}
		$this->loginAs('admin');
		try {
			$this->forum->getMembers()->defineBadge('Helper', str_repeat('d', 501));
			self::fail('description too long');
		} catch (BEForumValidationException $e) {
			self::assertSame('forum_field_too_long', $e->getErrorCode());
		}
	}

	public function testHiddenThreadsNeverLeakIntoPublicListings(): void
	{
		$board = $this->createBoard();
		$visible = $this->createThreadAs($board, 'alice', 'Visible', 'visible needle');
		$this->replyAs($visible, 'bob', 'visible needle reply');
		$deleted = $this->createThreadAs($board, 'alice', 'Doomed', 'doomed needle');
		$this->replyAs($deleted, 'bob', 'doomed needle reply');
		$this->forum->setRequireApproval(true);
		$pending = $this->createThreadAs($board, 'carol', 'Pending', 'pending needle');
		$this->forum->setRequireApproval(false);
		$this->loginAs('mod');
		$this->forum->getThreads()->deleteThread($this->forum->getThreads()->getThread($deleted->getId()));
		$this->logout();
		$posts = $this->forum->getPosts();
		$recent = array_map(fn ($post) => (int) $post->thread_id, $posts->getRecentPosts(50));
		self::assertNotContains($deleted->getId(), $recent, 'posts of a deleted thread are not recent');
		self::assertNotContains($pending->getId(), $recent, 'posts of a pending thread are not recent');
		self::assertContains($visible->getId(), $recent);
		[$bobPosts] = $posts->getPostsByMember($this->member('bob'));
		self::assertCount(1, $bobPosts, 'profile listings skip deleted threads');
		[$found] = $this->forum->getSearch()->searchPosts('needle');
		self::assertCount(2, $found, 'search skips deleted and pending threads');
		self::assertSame(2, $posts->countPosts());
		$stats = $this->forum->getStatistics();
		$stats->flushRequestCache();
		self::assertSame(2, (int) $stats->getSummary()['posts']);
	}

	public function testRecountsMatchIncrementalCounters(): void
	{
		$board = $this->createBoard();
		$thread = $this->createThreadAs($board, 'alice');
		$this->replyAs($thread, 'bob');
		$this->forum->setRequireApproval(true);
		$pendingReply = $this->replyAs($thread, 'carol');
		$pendingThread = $this->createThreadAs($board, 'carol', 'Pending');
		$this->forum->setRequireApproval(false);
		$boards = $this->forum->getBoards();
		$before = $boards->getBoard($board->getId());
		self::assertSame(1, (int) $before->thread_count);
		self::assertSame(2, (int) $before->post_count);
		$boards->recountAll();
		$after = $boards->getBoard($board->getId());
		self::assertSame((int) $before->thread_count, (int) $after->thread_count, 'pending threads are not counted either way');
		self::assertSame((int) $before->post_count, (int) $after->post_count, 'pending posts are not counted either way');

		// approving an older pending reply must not move the board's last post backwards
		$this->loginAs('mod');
		$newer = $this->replyAs($thread, 'bob', 'newest');
		$this->forum->getPosts()->approvePost($this->forum->getPosts()->findPost($pendingReply->getId()));
		self::assertSame($newer->getId(), (int) $boards->getBoard($board->getId())->last_post_id);
		$boards->recountAll();
		self::assertSame($newer->getId(), (int) $boards->getBoard($board->getId())->last_post_id);
		self::assertSame(1, (int) $boards->getBoard($board->getId())->thread_count);
		self::assertSame(4, (int) $boards->getBoard($board->getId())->post_count);

		// soft deleting and restoring a thread moves the author's thread count, purging moves the post counts
		$members = $this->forum->getMembers();
		self::assertSame(1, (int) $members->getMemberByUsername('alice')->thread_count);
		self::assertSame(2, (int) $members->getMemberByUsername('bob')->post_count);
		$threads = $this->forum->getThreads();
		$threads->deleteThread($threads->getThread($thread->getId()));
		self::assertSame(0, (int) $members->getMemberByUsername('alice')->thread_count);
		$threads->restoreThread($threads->getThread($thread->getId()));
		self::assertSame(1, (int) $members->getMemberByUsername('alice')->thread_count);
		$threads->purgeThread($threads->getThread($thread->getId()));
		self::assertSame(0, (int) $members->getMemberByUsername('alice')->thread_count);
		self::assertSame(0, (int) $members->getMemberByUsername('alice')->post_count);
		self::assertSame(0, (int) $members->getMemberByUsername('bob')->post_count);
		$this->forum->getStatistics()->recountMembers();
		self::assertSame(0, (int) $members->getMemberByUsername('bob')->post_count, 'the recount agrees');
		self::assertSame(0, (int) $members->getMemberByUsername('carol')->post_count);
		self::assertSame($pendingThread->getId(), $threads->findThread($pendingThread->getId())->getId());
	}

	public function testRecountKeepsForeignCountersAndSortKey(): void
	{
		$board = $this->createBoard();
		$thread = $this->createThreadAs($board, 'alice');
		$reply = $this->replyAs($thread, 'bob');
		$threads = $this->forum->getThreads();
		$stale = $threads->getThread($thread->getId());
		BEForumThread::execute('UPDATE {table} SET view_count = 42 WHERE id = :id', ['id' => $thread->getId()]);
		$threads->recount($stale);
		self::assertSame(42, (int) BEForumThread::finder()->findByPk($thread->getId())->view_count, 'recount writes only the counters it computes');
		$this->loginAs('mod');
		$this->forum->getPosts()->purgePost($this->forum->getPosts()->getPost($reply->getId()));
		$fresh = BEForumThread::finder()->findByPk($thread->getId());
		self::assertSame((int) $thread->first_post_id, (int) $fresh->last_post_id);
		$this->forum->getPosts()->deletePost($this->forum->getPosts()->getPost((int) $thread->first_post_id));
		$fresh = BEForumThread::finder()->findByPk($thread->getId());
		self::assertTrue($fresh->getIsDeleted());
	}

	public function testAcceptedAnswerReputationIsAwardedOnce(): void
	{
		$board = $this->createBoard();
		$thread = $this->createThreadAs($board, 'alice', 'Q', 'question?', ['type' => BEForumThread::TYPE_QUESTION]);
		$first = $this->replyAs($thread, 'bob', 'answer one');
		$second = $this->replyAs($thread, 'carol', 'answer two');
		$members = $this->forum->getMembers();
		$threads = $this->forum->getThreads();
		$this->loginAs('alice');
		$threads->setAcceptedPost($threads->getThread($thread->getId()), $first);
		$threads->setAcceptedPost($threads->getThread($thread->getId()), $first);
		self::assertSame(BEForumThreadManager::ACCEPTED_REPUTATION, (int) $members->getMemberByUsername('bob')->reputation, 'accepting twice awards once');
		$threads->setAcceptedPost($threads->getThread($thread->getId()), $second);
		self::assertSame(0, (int) $members->getMemberByUsername('bob')->reputation, 'the previous answer loses the award');
		self::assertSame(BEForumThreadManager::ACCEPTED_REPUTATION, (int) $members->getMemberByUsername('carol')->reputation);
		$threads->setAcceptedPost($threads->getThread($thread->getId()), null);
		self::assertSame(0, (int) $members->getMemberByUsername('carol')->reputation);
		self::assertNull($threads->getThread($thread->getId())->accepted_post_id);
		$this->loginAs('mod');
		$threads->setLocked($threads->getThread($thread->getId()), true);
		$this->loginAs('alice');
		try {
			$threads->setAcceptedPost($threads->getThread($thread->getId()), $first);
			self::fail('locked threads cannot be changed by the owner');
		} catch (BEForumValidationException $e) {
			self::assertSame('forum_thread_locked', $e->getErrorCode());
		}
		try {
			$threads->updateThread($threads->getThread($thread->getId()), ['title' => 'New']);
			self::fail('locked threads cannot be edited by the owner');
		} catch (BEForumValidationException $e) {
			self::assertSame('forum_thread_locked', $e->getErrorCode());
		}
	}

	public function testOwnersCannotDeleteInLockedThreadsOrAfterTheWindow(): void
	{
		$board = $this->createBoard();
		$thread = $this->createThreadAs($board, 'alice');
		$reply = $this->replyAs($thread, 'bob');
		$posts = $this->forum->getPosts();
		$this->loginAs('mod');
		$this->forum->getThreads()->setLocked($this->forum->getThreads()->getThread($thread->getId()), true);
		$this->loginAs('bob');
		self::assertFalse($posts->canDelete($posts->getPost($reply->getId())));
		try {
			$posts->deletePost($posts->getPost($reply->getId()));
			self::fail('locked');
		} catch (BEForumValidationException $e) {
			self::assertSame('forum_thread_locked', $e->getErrorCode());
		}
		$this->loginAs('mod');
		$this->forum->getThreads()->setLocked($this->forum->getThreads()->getThread($thread->getId()), false);
		$posts->deletePost($posts->getPost($reply->getId()));
		$this->loginAs('bob');
		try {
			$posts->updatePost($posts->getPost($reply->getId()), 'changed');
			self::fail('deleted posts are not found for editing');
		} catch (BEForumNotFoundException $e) {
			self::assertStringContainsString('was not found', $e->getMessage());
		}
	}

	public function testAttachmentsRequireEditRightsAndDetectTheType(): void
	{
		$attachments = $this->forum->getAttachments();
		$dir = sys_get_temp_dir() . '/beforum-att-' . uniqid();
		$this->forum->setAttachmentPath($dir);
		$this->forum->setAttachmentTypes(['txt', 'png', 'svg']);
		$board = $this->createBoard();
		$thread = $this->createThreadAs($board, 'alice');
		$post = $this->forum->getPosts()->getPost((int) $thread->first_post_id);
		$source = tempnam(sys_get_temp_dir(), 'up');
		file_put_contents($source, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
		$this->loginAs('bob');
		try {
			$attachments->attachFile($post, 'a.txt', $source, 'text/plain', false);
			self::fail('bob may not attach to alice\'s post');
		} catch (BEForumForbiddenException $e) {
			self::assertSame('You may not attach files to this post.', $e->getMessage());
		}
		$this->loginAs('alice');
		$attachment = $attachments->attachFile($post, 'picture.png', $source, 'image/png', false);
		self::assertNotSame('image/png', $attachment->mime_type, 'the claimed type is replaced by the detected one');
		self::assertFalse($attachment->getIsInlineImage());
		$png = new BEForumAttachment();
		$png->mime_type = 'image/png';
		self::assertTrue($png->getIsImage());
		self::assertTrue($png->getIsInlineImage());
		$svg = new BEForumAttachment();
		$svg->mime_type = 'image/svg+xml';
		self::assertTrue($svg->getIsImage());
		self::assertFalse($svg->getIsInlineImage(), 'SVG is never displayed inline');
		$this->loginAs('mod');
		self::assertTrue($attachments->removeAttachment($attachment), 'moderators may remove attachments');
		unlink($source);
		array_map('unlink', glob($dir . '/*') ?: []);
		@rmdir($dir);
	}

	public function testSubscriptionsRequireVisibleTargets(): void
	{
		$board = $this->createBoard();
		$hidden = $this->createBoard('Hidden', ['is_hidden' => true]);
		$thread = $this->createThreadAs($board, 'alice');
		$this->loginAs('bob');
		$subscriptions = $this->forum->getSubscriptions();
		try {
			$subscriptions->subscribe(BEForumSubscription::TYPE_BOARD, $hidden->getId());
			self::fail('hidden boards cannot be followed');
		} catch (BEForumNotFoundException | BEForumForbiddenException $e) {
		}
		try {
			$subscriptions->subscribe(BEForumSubscription::TYPE_THREAD, 999999);
			self::fail('missing thread');
		} catch (BEForumNotFoundException $e) {
		}
		self::assertNotNull($subscriptions->subscribe(BEForumSubscription::TYPE_THREAD, $thread->getId()));
		self::assertNotNull($subscriptions->subscribe(BEForumSubscription::TYPE_BOARD, $board->getId()));
	}

	public function testModerationCountersAreInvalidated(): void
	{
		$board = $this->createBoard();
		$thread = $this->createThreadAs($board, 'alice');
		$reply = $this->replyAs($thread, 'bob');
		$moderation = $this->forum->getModeration();
		$this->loginAs('mod');
		self::assertSame(0, $moderation->countOpenReports());
		self::assertSame(0, $moderation->countPending());
		$this->loginAs('carol');
		$moderation->report($reply, 'spam');
		self::assertSame(1, $moderation->countOpenReports(), 'reporting flushes the open report count');
		$this->forum->setRequireApproval(true);
		$this->loginAs('dave');
		$pending = $this->forum->getPosts()->createPost($thread, 'pending content');
		self::assertSame(1, $moderation->countPending(), 'creating pending content flushes the pending count');
		$this->loginAs('mod');
		$this->forum->getPosts()->purgePost($this->forum->getPosts()->getPost($pending->getId()));
		self::assertSame(0, $moderation->countPending(), 'purging flushes the pending count');
	}

	public function testMovingABoardBetweenCategoriesMovesItsChildren(): void
	{
		$this->loginAs('admin');
		$boards = $this->forum->getBoards();
		$first = $this->getCategory();
		$second = $boards->createCategory('Second');
		$parent = $boards->createBoard($first, 'Parent');
		$child = $boards->createBoard($first, 'Child', null, ['parent_id' => $parent->getId()]);
		try {
			$boards->updateBoard($child, ['category_id' => $second->getId()]);
			self::fail('a sub board cannot leave the category of its parent');
		} catch (BEForumValidationException $e) {
			self::assertSame('forum_board_parent_category', $e->getErrorCode());
		}
		$boards->updateBoard($boards->getBoard($parent->getId()), ['category_id' => $second->getId()]);
		self::assertSame($second->getId(), (int) $boards->getBoard($child->getId())->category_id, 'children follow the parent');
		self::assertSame(2, (int) $boards->getCategory($second->getId())->board_count);
		self::assertSame(0, (int) $boards->getCategory($first->getId())->board_count);
	}

	public function testSettingsWritesKeepCounters(): void
	{
		$board = $this->createBoard();
		$this->createThreadAs($board, 'alice');
		$members = $this->forum->getMembers();
		$this->loginAs('alice');
		$stale = $members->getMemberByUsername('alice');
		$this->replyAs($this->forum->getThreads()->listThreads($board)[0][0], 'alice', 'more');
		$members->setSetting($stale, 'theme', 'dark');
		$fresh = $members->getMemberByUsername('alice');
		self::assertSame('dark', $fresh->getSetting('theme'));
		self::assertSame(2, (int) $fresh->post_count, 'saving a setting does not clobber the post counter');
		$this->forum->getReadTracker()->markBoardRead($board);
		$this->forum->getReadTracker()->markAllRead();
		self::assertSame(2, (int) $members->getMemberByUsername('alice')->post_count);
	}
}
