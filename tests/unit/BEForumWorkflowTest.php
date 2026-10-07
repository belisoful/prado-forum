<?php

use Belisoful\Forum\BEForumEventParameter;
use Belisoful\Forum\Data\BEForumNotification;
use Belisoful\Forum\Data\BEForumReport;
use Belisoful\Forum\Data\BEForumSubscription;
use Belisoful\Forum\Data\BEForumThread;
use Belisoful\Forum\Exceptions\BEForumFloodException;
use Belisoful\Forum\Exceptions\BEForumForbiddenException;
use Belisoful\Forum\Exceptions\BEForumNotFoundException;
use Belisoful\Forum\Exceptions\BEForumValidationException;
use Belisoful\Forum\Security\BEForumPermissions;
use Belisoful\Forum\Util\BEForumTime;

/**
 * End to end workflow of the forum through the module and its managers.
 */
class BEForumWorkflowTest extends BEForumTestCase
{
	public function testStructureThreadsPostsAndCounters(): void
	{
		$board = $this->createBoard('Chat');
		self::assertSame('chat', $board->slug);
		self::assertSame(1, (int) $this->forum->getBoards()->getCategory((int) $board->category_id)->refresh()->board_count);

		$events = [];
		$this->forum->onThreadCreated[] = function ($sender, BEForumEventParameter $param) use (&$events) {
			$events[] = 'thread:' . $param->getRecord()->title;
		};
		$this->forum->onPostCreated[] = function ($sender, BEForumEventParameter $param) use (&$events) {
			$events[] = 'post:' . $param->getRecord()->position;
		};

		$bob = $this->member('bob');
		$this->loginAs('alice');
		$thread = $this->forum->getThreads()->createThread($board, 'Hello World', "Hi **there** @bob", ['tags' => ['PHP', 'prado framework'], 'type' => BEForumThread::TYPE_QUESTION]);
		self::assertSame('hello-world', $thread->slug);
		self::assertSame(0, (int) $thread->reply_count);
		self::assertNotNull($thread->first_post_id);
		self::assertSame(['post:1', 'thread:Hello World'], $events);

		$first = $this->forum->getPosts()->getPost((int) $thread->first_post_id);
		self::assertStringContainsString('<strong>there</strong>', (string) $first->content_html);
		self::assertSame(1, (int) $first->position);
		self::assertSame('alice', $first->getAuthorName());

		$tags = $this->forum->getTags()->getThreadTags($thread);
		self::assertSame(['php', 'prado-framework'], array_map(fn ($tag) => $tag->slug, $tags));

		$this->loginAs('bob');
		$reply = $this->forum->getPosts()->createPost($thread, 'Welcome!', ['reply_to' => $first]);
		self::assertSame(2, (int) $reply->position);
		self::assertTrue($reply->getIsReply());

		$thread = $this->forum->getThreads()->getThread($thread->getId());
		self::assertSame(1, (int) $thread->reply_count);
		self::assertSame($reply->getId(), (int) $thread->last_post_id);
		self::assertSame($bob->getId(), (int) $thread->last_poster_member_id);

		$board = $this->forum->getBoards()->getBoard($board->getId());
		self::assertSame(1, (int) $board->thread_count);
		self::assertSame(2, (int) $board->post_count);
		self::assertSame($reply->getId(), (int) $board->last_post_id);

		$alice = $this->forum->getMembers()->getMemberByUsername('alice');
		self::assertSame(1, (int) $alice->post_count);
		self::assertSame(1, (int) $alice->thread_count);
		self::assertSame(1, (int) $this->forum->getMembers()->getMemberByUsername('bob')->post_count);

		// alice subscribed to her thread automatically and gets notified of bob's reply and the quote
		$this->loginAs('alice');
		self::assertTrue($this->forum->getSubscriptions()->isSubscribed(BEForumSubscription::TYPE_THREAD, $thread->getId()));
		[$notifications] = $this->forum->getNotifications()->listNotifications();
		$types = array_map(fn ($n) => $n->type, $notifications);
		sort($types);
		self::assertSame([BEForumNotification::TYPE_QUOTE, BEForumNotification::TYPE_REPLY], $types);
		self::assertSame(2, $this->forum->getNotifications()->countUnread());
		$this->forum->getNotifications()->markAllRead();
		self::assertSame(0, $this->forum->getNotifications()->countUnread());

		// bob was mentioned in the first post
		$this->loginAs('bob');
		[$bobNotifications] = $this->forum->getNotifications()->listNotifications();
		self::assertSame([BEForumNotification::TYPE_MENTION], array_map(fn ($n) => $n->type, $bobNotifications));

		// accepted answer
		$this->loginAs('alice');
		$this->forum->getThreads()->setAcceptedPost($thread, $reply);
		self::assertTrue($this->forum->getThreads()->getThread($thread->getId())->getIsSolved());
		self::assertSame(5, (int) $this->forum->getMembers()->getMemberByUsername('bob')->reputation);

		// listing
		[$threads, $pagination] = $this->forum->getThreads()->listThreads($board);
		self::assertCount(1, $threads);
		self::assertSame(1, $pagination->getItemCount());
		[$posts, $postPagination] = $this->forum->getPosts()->listPosts($thread);
		self::assertCount(2, $posts);
		self::assertSame(2, $postPagination->getItemCount());
		self::assertSame(1, $this->forum->getPosts()->getPageOfPost($reply));

		// edit with revision
		$this->loginAs('bob');
		$edited = $this->forum->getPosts()->updatePost($reply, 'Welcome, edited!', 'typo');
		self::assertSame(1, (int) $edited->edit_count);
		$revisions = $this->forum->getPosts()->getRevisions($reply);
		self::assertCount(1, $revisions);
		self::assertSame('Welcome!', $revisions[0]->content);

		// alice cannot edit bob's post
		$this->loginAs('alice');
		self::assertFalse($this->forum->getPosts()->canEdit($reply));
		try {
			$this->forum->getPosts()->updatePost($reply, 'hacked');
			self::fail('editing a foreign post must be denied');
		} catch (BEForumForbiddenException $e) {
			self::assertSame(BEForumPermissions::POST_EDIT, $e->getPermission());
		}

		// delete and restore
		$this->loginAs('bob');
		$this->forum->getPosts()->deletePost($reply, 'oops');
		self::assertTrue($this->forum->getPosts()->findPost($reply->getId())->getIsDeleted());
		self::assertSame(0, (int) $this->forum->getThreads()->getThread($thread->getId())->reply_count);
		self::assertSame(1, (int) $this->forum->getBoards()->getBoard($board->getId())->post_count);
		self::assertNull($this->forum->getThreads()->getThread($thread->getId())->accepted_post_id);
		$this->loginAs('alice');
		try {
			$this->forum->getPosts()->getPost($reply->getId());
			self::fail('deleted posts are hidden from members');
		} catch (BEForumNotFoundException $e) {
			self::assertSame(404, $e->getStatusCode());
		}
		$this->loginAs('mod');
		$this->forum->getPosts()->restorePost($this->forum->getPosts()->findPost($reply->getId()));
		self::assertSame(1, (int) $this->forum->getThreads()->getThread($thread->getId())->reply_count);
		self::assertSame(2, (int) $this->forum->getBoards()->getBoard($board->getId())->post_count);

		// statistics
		$summary = $this->forum->getStatistics()->getSummary(true);
		self::assertSame(1, $summary['threads']);
		self::assertSame(2, $summary['posts']);
		self::assertSame(4, $summary['members']);
		self::assertSame(['threads' => 1, 'boards' => 1, 'tags' => 2, 'reactions' => 0, 'members' => 4], $this->forum->getStatistics()->recountAll());
	}

	public function testGuestsMayReadButNotPost(): void
	{
		$board = $this->createBoard();
		$thread = $this->createThreadAs($board, 'alice');
		$this->logout();
		self::assertTrue($this->forum->can(BEForumPermissions::VIEW));
		self::assertFalse($this->forum->can(BEForumPermissions::POST_CREATE));
		self::assertSame($thread->getId(), $this->forum->getThreads()->getThread($thread->getId())->getId());
		$this->expectException(BEForumForbiddenException::class);
		$this->forum->getPosts()->createPost($thread, 'guest reply');
	}

	public function testModerationLockPinMoveAndReports(): void
	{
		$board = $this->createBoard('One');
		$other = $this->createBoard('Two');
		$thread = $this->createThreadAs($board, 'alice');
		$reply = $this->replyAs($thread, 'bob', 'spam spam');

		$this->loginAs('alice');
		try {
			$this->forum->getThreads()->setLocked($thread, true);
			self::fail('only moderators may lock');
		} catch (BEForumForbiddenException $e) {
			self::assertSame(BEForumPermissions::MODERATE, $e->getPermission());
		}
		$report = $this->forum->getModeration()->report($reply, 'This is spam');
		self::assertTrue($report->getIsOpen());

		$this->loginAs('mod');
		self::assertTrue($this->forum->getModeration()->isModerator($board));
		$this->forum->getThreads()->setLocked($thread, true);
		$this->forum->getThreads()->setPinned($thread, true, '+1 day');
		$thread = $this->forum->getThreads()->getThread($thread->getId());
		self::assertTrue($thread->getIsLocked());
		self::assertTrue($thread->getIsPinned());

		$this->loginAs('alice');
		try {
			$this->forum->getPosts()->createPost($thread, 'locked?');
			self::fail('locked threads refuse replies');
		} catch (BEForumValidationException $e) {
			self::assertSame('thread', $e->getField());
		}

		$this->loginAs('mod');
		$this->forum->getThreads()->moveThread($thread, $other);
		self::assertSame(0, (int) $this->forum->getBoards()->getBoard($board->getId())->thread_count);
		self::assertSame(1, (int) $this->forum->getBoards()->getBoard($other->getId())->thread_count);
		self::assertSame(2, (int) $this->forum->getBoards()->getBoard($other->getId())->post_count);
		self::assertSame($other->getId(), (int) $this->forum->getPosts()->findPost($reply->getId())->board_id);

		[$reports] = $this->forum->getModeration()->listReports();
		self::assertCount(1, $reports);
		$this->forum->getModeration()->resolve($reports[0], 'removed');
		self::assertSame(0, $this->forum->getModeration()->countOpenReports());
		[$log] = $this->forum->getModeration()->listLog(1, ['action' => 'move_thread']);
		self::assertCount(1, $log);

		BEForumTime::freeze(BEForumTime::timestamp() + 2 * 86400);
		self::assertSame(1, $this->forum->getThreads()->expirePins());
		self::assertFalse($this->forum->getThreads()->getThread($thread->getId())->getIsPinned());
	}

	public function testFloodControlAndValidation(): void
	{
		$this->forum->setFloodInterval(60);
		$board = $this->createBoard();
		$thread = $this->createThreadAs($board, 'alice');
		$this->loginAs('alice');
		try {
			$this->forum->getPosts()->createPost($thread, 'too fast');
			self::fail('flood control must trigger');
		} catch (BEForumFloodException $e) {
			self::assertGreaterThan(0, $e->getRetryAfter());
		}
		BEForumTime::freeze(BEForumTime::timestamp() + 120);
		$this->forum->getPosts()->createPost($thread, 'slow enough');
		$this->forum->setFloodInterval(0);

		try {
			$this->forum->getThreads()->createThread($board, '', 'no title');
			self::fail('titles are required');
		} catch (BEForumValidationException $e) {
			self::assertSame('title', $e->getField());
		}
		try {
			$this->forum->getPosts()->createPost($thread, '   ');
			self::fail('content is required');
		} catch (BEForumValidationException $e) {
			self::assertSame('content', $e->getField());
		}
	}

	public function testReactionsPollsBookmarksAndSearch(): void
	{
		$board = $this->createBoard();
		$thread = $this->createThreadAs($board, 'alice', 'Tabs or spaces', 'The eternal question', [
			'poll' => ['question' => 'Tabs or spaces?', 'options' => ['Tabs', 'Spaces'], 'allow_revote' => true],
		]);
		$first = $this->forum->getPosts()->getPost((int) $thread->first_post_id);

		$this->loginAs('bob');
		$reaction = $this->forum->getReactions()->react($first, 'like');
		self::assertNotNull($reaction);
		self::assertSame(['like' => 1], $this->forum->getReactions()->getSummary($first));
		self::assertSame(1, (int) $this->forum->getMembers()->getMemberByUsername('alice')->reputation);
		self::assertNotNull($this->forum->getReactions()->react($first, 'love'));
		self::assertSame(['love' => 1], $this->forum->getReactions()->getSummary($first));
		self::assertNull($this->forum->getReactions()->react($first, 'love'));
		self::assertSame([], $this->forum->getReactions()->getSummary($first));
		self::assertSame(0, (int) $this->forum->getMembers()->getMemberByUsername('alice')->reputation);
		try {
			$this->forum->getReactions()->react($first, 'nope');
			self::fail('unknown reaction types are refused');
		} catch (BEForumValidationException $e) {
			self::assertSame('type', $e->getField());
		}

		$poll = $this->forum->getPolls()->findPollOfThread($thread);
		self::assertNotNull($poll);
		$options = $this->forum->getPolls()->getOptions($poll);
		self::assertCount(2, $options);
		self::assertTrue($this->forum->getPolls()->canVote($poll));
		$this->forum->getPolls()->vote($poll, [$options[0]->getId()]);
		$results = $this->forum->getPolls()->getResults($poll);
		self::assertSame(1, $results['total_votes']);
		self::assertSame(100.0, $results['options'][$options[0]->getId()]['percent']);
		$this->forum->getPolls()->vote($poll, [$options[1]->getId()]);
		$results = $this->forum->getPolls()->getResults($poll);
		self::assertSame(0, $results['options'][$options[0]->getId()]['votes']);
		self::assertSame(1, $results['options'][$options[1]->getId()]['votes']);
		self::assertSame(1, $results['voters']);

		self::assertTrue($this->forum->getBookmarks()->toggle($first));
		self::assertTrue($this->forum->getBookmarks()->isBookmarked($first));
		[$bookmarks] = $this->forum->getBookmarks()->listBookmarks();
		self::assertCount(1, $bookmarks);
		self::assertFalse($this->forum->getBookmarks()->toggle($first));

		[$posts, $pagination] = $this->forum->getSearch()->searchPosts('eternal question');
		self::assertCount(1, $posts);
		self::assertSame(1, $pagination->getItemCount());
		[$threads] = $this->forum->getSearch()->searchThreads('tabs');
		self::assertCount(1, $threads);
		[$none] = $this->forum->getSearch()->searchPosts('eternal zebra');
		self::assertCount(0, $none);
		try {
			$this->forum->getSearch()->searchPosts('ab');
			self::fail('short queries are refused');
		} catch (BEForumValidationException $e) {
			self::assertSame('q', $e->getField());
		}
	}

	public function testReadTrackingAndSubscriptions(): void
	{
		$board = $this->createBoard();
		$thread = $this->createThreadAs($board, 'alice');
		$this->loginAs('bob');
		self::assertTrue($this->forum->getReadTracker()->isThreadUnread($thread));
		$first = $this->forum->getReadTracker()->getFirstUnreadPost($thread);
		self::assertSame((int) $thread->first_post_id, $first->getId());
		$this->forum->getReadTracker()->markThreadRead($thread);
		self::assertFalse($this->forum->getReadTracker()->isThreadUnread($thread));

		$this->forum->getSubscriptions()->subscribe(BEForumSubscription::TYPE_BOARD, $board->getId());
		$this->createThreadAs($board, 'alice', 'Another');
		$this->loginAs('bob');
		self::assertSame(1, $this->forum->getNotifications()->countUnread());
		[$subscriptions] = $this->forum->getSubscriptions()->listSubscriptions();
		self::assertCount(1, $subscriptions);
		self::assertFalse($this->forum->getSubscriptions()->toggle(BEForumSubscription::TYPE_BOARD, $board->getId()));
		self::assertCount(0, $this->forum->getSubscriptions()->getSubscribers(BEForumSubscription::TYPE_BOARD, $board->getId()));
	}

	public function testBansAndApprovalQueue(): void
	{
		$board = $this->createBoard();
		$thread = $this->createThreadAs($board, 'alice');
		$this->loginAs('mod');
		$bob = $this->member('bob');
		$this->forum->getMembers()->ban($bob, '+1 hour', 'trolling');
		$this->loginAs('bob');
		try {
			$this->forum->getPosts()->createPost($thread, 'still here?');
			self::fail('banned members cannot post');
		} catch (BEForumForbiddenException $e) {
			self::assertSame(BEForumPermissions::POST_CREATE, $e->getPermission());
		}
		BEForumTime::freeze(BEForumTime::timestamp() + 7200);
		self::assertSame(1, $this->forum->getMembers()->expireBans());
		$this->forum->flushRequestState($this->getApp(), null);
		$this->forum->getPosts()->createPost($thread, 'back again');

		$this->forum->setRequireApproval(true);
		$this->loginAs('carol');
		$pending = $this->forum->getThreads()->createThread($board, 'Needs approval', 'please');
		self::assertFalse($pending->getIsApproved());
		[$visible] = $this->forum->getThreads()->listThreads($board);
		self::assertCount(2, $visible, 'authors see their own pending threads');
		$this->loginAs('dave');
		[$visible] = $this->forum->getThreads()->listThreads($board);
		self::assertCount(1, $visible);
		$this->loginAs('mod');
		self::assertSame(1, $this->forum->getModeration()->countPending());
		$this->forum->getThreads()->approveThread($pending);
		$this->loginAs('dave');
		[$visible] = $this->forum->getThreads()->listThreads($board);
		self::assertCount(2, $visible);
		self::assertSame(2, (int) $this->forum->getBoards()->getBoard($board->getId())->thread_count);
	}
}
