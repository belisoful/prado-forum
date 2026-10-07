<?php

use Belisoful\Forum\Data\BEForumNotification;
use Belisoful\Forum\Data\BEForumPost;
use Belisoful\Forum\Data\BEForumSubscription;
use Belisoful\Forum\Data\BEForumThread;
use Belisoful\Forum\Exceptions\BEForumForbiddenException;
use Belisoful\Forum\Exceptions\BEForumNotFoundException;
use Belisoful\Forum\Exceptions\BEForumValidationException;
use Belisoful\Forum\Util\BEForumTime;

/**
 * Thread, post, tag, poll, reaction, attachment, notification and search manager edge cases.
 */
class BEForumContentManagersTest extends BEForumTestCase
{
	public function testThreadCreationRules(): void
	{
		$threads = $this->forum->getThreads();
		$board = $this->createBoard('Main', ['is_locked' => true]);
		$this->loginAs('alice');
		try {
			$threads->createThread($board, 'T', 'c');
			self::fail('locked boards refuse threads');
		} catch (BEForumValidationException $e) {
			self::assertSame('forum_board_locked', $e->getErrorCode());
		}
		try {
			$threads->createThread($board->getId(), 'T', 'c', ['type' => 'weird']);
			self::fail('unknown types are refused');
		} catch (BEForumValidationException $e) {
		}
		$this->loginAs('mod');
		$thread = $threads->createThread($board->getId(), 'Mods may post in locked boards', 'c', ['type' => BEForumThread::TYPE_ANNOUNCEMENT]);
		self::assertSame(BEForumThread::TYPE_ANNOUNCEMENT, $thread->type);
	}

	public function testThreadTitleLength(): void
	{
		$threads = $this->forum->getThreads();
		$board = $this->createBoard();
		$this->loginAs('alice');
		try {
			$threads->createThread($board, str_repeat('t', 300), 'c');
			self::fail('long titles are refused');
		} catch (BEForumValidationException $e) {
			self::assertSame('forum_thread_title_too_long', $e->getErrorCode());
		}
		try {
			$threads->createThread($board, 'Announce', 'c', ['type' => BEForumThread::TYPE_ANNOUNCEMENT]);
			self::fail('announcements are moderator only');
		} catch (BEForumValidationException $e) {
			self::assertSame('type', $e->getField());
		}
		$this->forum->setMaxPostLength(5);
		try {
			$threads->createThread($board, 'Short', 'too long content');
			self::fail('long content is refused');
		} catch (BEForumValidationException $e) {
			self::assertSame('forum_post_content_too_long', $e->getErrorCode());
		}
		$this->forum->setMaxPostLength(65535);
		$this->forum->setEnableTags(false);
		$thread = $threads->createThread($board, 'Hello World', 'x', ['tags' => ['php']]);
		self::assertSame([], $this->forum->getTags()->getThreadTags($thread), 'tags are ignored when disabled');
		$second = $threads->createThread($board, 'Hello World', 'x');
		self::assertSame('hello-world-2', $second->slug);
		$this->loginAs('mod');
		$announcement = $threads->createThread($board, 'News', 'x', ['type' => BEForumThread::TYPE_ANNOUNCEMENT, 'subscribe' => false]);
		self::assertSame(BEForumThread::TYPE_ANNOUNCEMENT, $announcement->type);
		self::assertFalse($this->forum->getSubscriptions()->isSubscribed(BEForumSubscription::TYPE_THREAD, $announcement->getId()));
	}

	public function testGuestThreadsAndPosts(): void
	{
		$board = $this->createBoard();
		$this->member('taken');
		$this->logout();
		$this->forum->attachBehavior('guests', new class () extends \Prado\Util\TBehavior {
			public function dyAuthorize($allowed, $permission, $extra, $user, $chain)
			{
				return $chain->dyAuthorize(true, $permission, $extra, $user);
			}
		});
		$threads = $this->forum->getThreads();
		try {
			$threads->createThread($board, 'G', 'guest content', ['guest_name' => 'taken']);
			self::fail('guest names of members are refused');
		} catch (BEForumValidationException $e) {
			self::assertSame('guest_name', $e->getField());
		}
		$thread = $threads->createThread($board, 'G', 'guest content', ['guest_name' => ' Visitor ']);
		self::assertNull($thread->member_id);
		self::assertSame('Visitor', $thread->guest_name);
		self::assertSame('Visitor', $thread->getAuthorName());
		$post = $this->forum->getPosts()->createPost($thread, 'reply', []);
		self::assertSame($this->forum->getGuestName(), $post->guest_name);
		self::assertSame(2, (int) $this->forum->getBoards()->getBoard($board->getId())->post_count);
		self::assertFalse($threads->isOwner($thread));
		self::assertFalse($this->forum->getPosts()->isOwner($post));
	}

	public function testThreadEditingAndVisibility(): void
	{
		$threads = $this->forum->getThreads();
		$board = $this->createBoard();
		$thread = $this->createThreadAs($board, 'alice', 'Original', 'body', ['tags' => ['a', 'b']]);
		$this->loginAs('alice');
		self::assertTrue($threads->canEdit($thread));
		self::assertTrue($threads->canDelete($thread));
		self::assertTrue($threads->isOwner($thread));
		$threads->updateThread($thread, ['title' => 'Changed', 'type' => BEForumThread::TYPE_QUESTION, 'tags' => ['c']]);
		$fresh = $threads->getThread($thread->getId());
		self::assertSame('Changed', $fresh->title);
		self::assertSame('original', $fresh->slug, 'slugs stay stable');
		self::assertSame(['c'], array_map(fn ($t) => $t->name, $this->forum->getTags()->getThreadTags($fresh)));
		self::assertSame(2, $this->forum->getTags()->removeUnused());
		try {
			$threads->updateThread($thread, ['type' => BEForumThread::TYPE_ANNOUNCEMENT]);
			self::fail('owners cannot make announcements');
		} catch (BEForumValidationException $e) {
		}
		$reply = $this->replyAs($thread, 'bob');
		$threads->setAcceptedPost($fresh, $reply);
		$threads->updateThread($fresh, ['type' => BEForumThread::TYPE_DISCUSSION]);
		self::assertNull($threads->getThread($thread->getId())->accepted_post_id, 'leaving the question type clears the answer');
		try {
			$threads->setAcceptedPost($threads->getThread($thread->getId()), $reply);
			self::fail('only questions have answers');
		} catch (BEForumValidationException $e) {
		}
		$this->loginAs('bob');
		self::assertFalse($threads->canEdit($fresh));
		try {
			$threads->updateThread($fresh, ['title' => 'Nope']);
			self::fail('foreign threads');
		} catch (BEForumForbiddenException $e) {
		}
		$this->loginAs('alice');
		$threads->deleteThread($fresh, 'my choice');
		self::assertTrue($threads->findThread($thread->getId())->getIsDeleted());
		$this->loginAs('bob');
		try {
			$threads->getThread($thread->getId());
			self::fail('deleted threads are hidden');
		} catch (BEForumNotFoundException $e) {
		}
		try {
			$this->forum->getPosts()->createPost($threads->findThread($thread->getId()), 'x');
			self::fail('no replies in deleted threads');
		} catch (BEForumNotFoundException $e) {
		}
		[$listed] = $threads->listThreads($board);
		self::assertCount(0, $listed);
		$this->loginAs('mod');
		self::assertTrue($threads->isVisible($threads->findThread($thread->getId())));
		[$listed] = $threads->listThreads($board);
		self::assertCount(1, $listed, 'moderators see deleted threads');
		$threads->restoreThread($threads->findThread($thread->getId()));
		self::assertFalse($threads->findThread($thread->getId())->getIsDeleted());
		self::assertSame(1, (int) $this->forum->getBoards()->getBoard($board->getId())->thread_count);
		self::assertSame(2, (int) $this->forum->getBoards()->getBoard($board->getId())->post_count);
		$threads->purgeThread($threads->findThread($thread->getId()));
		self::assertNull($threads->findThread($thread->getId()));
		self::assertNull($this->forum->getPosts()->findPost($reply->getId()));
		self::assertSame(0, (int) $this->forum->getBoards()->getBoard($board->getId())->post_count);
		self::assertSame(0, $threads->countThreads());
	}

	public function testListingsRecentAndFilters(): void
	{
		$threads = $this->forum->getThreads();
		$board = $this->createBoard('A');
		$sub = $this->createBoard('Sub', ['parent_id' => $board->getId()]);
		$other = $this->createBoard('B');
		$t1 = $this->createThreadAs($board, 'alice', 'One', 'x', ['tags' => ['php']]);
		$t2 = $this->createThreadAs($board, 'bob', 'Two', 'x', ['type' => BEForumThread::TYPE_QUESTION]);
		$t3 = $this->createThreadAs($sub, 'alice', 'Three', 'x');
		$t4 = $this->createThreadAs($other, 'alice', 'Four', 'x', ['tags' => ['php']]);
		$this->loginAs('mod');
		$threads->setPinned($threads->getThread($t1->getId()), true);
		$this->loginAs('alice');
		[$page, $pagination] = $threads->listThreads($board, 1, [], 1);
		self::assertSame([$t1->getId()], array_map(fn ($t) => $t->getId(), $page), 'pinned first');
		self::assertSame(2, $pagination->getPageCount());
		[$page] = $threads->listThreads($board, 2, [], 1);
		self::assertSame([$t2->getId()], array_map(fn ($t) => $t->getId(), $page));
		[$page] = $threads->listThreads($board, 1, ['include_subboards' => true]);
		self::assertCount(3, $page);
		[$page] = $threads->listThreads($board, 1, ['type' => BEForumThread::TYPE_QUESTION]);
		self::assertSame([$t2->getId()], array_map(fn ($t) => $t->getId(), $page));
		[$page] = $threads->listThreads($board, 1, ['member_id' => $this->member('bob')->getId()]);
		self::assertCount(1, $page);
		[$page] = $threads->listThreads($board, 1, ['tag' => 'php']);
		self::assertSame([$t1->getId()], array_map(fn ($t) => $t->getId(), $page));
		[$page, $pagination] = $threads->listThreads($board, 1, ['tag' => 'missing']);
		self::assertCount(0, $page);
		self::assertSame(0, $pagination->getItemCount());
		$tag = $this->forum->getTags()->findBySlug('php');
		[$page] = $threads->getThreadsByTag($tag);
		self::assertCount(2, $page);
		[$page] = $threads->getThreadsByMember($this->member('alice'));
		self::assertCount(3, $page);
		self::assertSame([$t4->getId(), $t3->getId(), $t2->getId()], array_map(fn ($t) => $t->getId(), $threads->getRecentThreads(3)));
		self::assertSame([$t4->getId()], array_map(fn ($t) => $t->getId(), $threads->getRecentThreads(3, $other)));
		self::assertSame([$t1->getId(), $t2->getId()], array_keys($threads->getThreadsByIds([$t2->getId(), $t1->getId(), 0])));
		self::assertSame([], $threads->getThreadsByIds([]));
		self::assertSame(4, $threads->countThreads());
		self::assertSame(4, $threads->recountAll());
		self::assertSame(4, $this->forum->getPosts()->countPosts());
		self::assertCount(2, $this->forum->getPosts()->getRecentPosts(2));
		self::assertSame([$t4->first_post_id], array_map(fn ($p) => (int) $p->getId(), $this->forum->getPosts()->getRecentPosts(5, $other->getId())));
		[$posts] = $this->forum->getPosts()->getPostsByMember($this->member('alice'));
		self::assertCount(3, $posts);
	}

	public function testViewsPinsMoveAndApproval(): void
	{
		$threads = $this->forum->getThreads();
		$board = $this->createBoard('A');
		$other = $this->createBoard('B');
		$thread = $this->createThreadAs($board, 'alice');
		self::assertTrue($threads->countView($thread));
		self::assertSame(1, (int) $threads->getThread($thread->getId())->view_count);
		$this->loginAs('mod');
		try {
			$threads->setPinned($thread, true, 'garbage');
			self::fail('invalid pin end');
		} catch (BEForumValidationException $e) {
			self::assertSame('until', $e->getField());
		}
		try {
			$threads->moveThread($thread, $board);
			self::fail('same board');
		} catch (BEForumValidationException $e) {
		}
		$threads->moveThread($thread, $other->getId());
		self::assertSame($other->getId(), (int) $threads->getThread($thread->getId())->board_id);
		self::assertSame(0, (int) $this->forum->getBoards()->getBoard($board->getId())->thread_count);
		self::assertSame(1, (int) $this->forum->getBoards()->getBoard($other->getId())->thread_count);
		self::assertSame($thread, $threads->approveThread($thread), 'already approved');
		self::assertSame($thread, $threads->setLocked($thread, true));
		self::assertSame($thread, $threads->restoreThread($thread), 'not deleted');
		$this->loginAs('alice');
		self::assertFalse($threads->canReply($threads->getThread($thread->getId())));
		$this->loginAs('mod');
		self::assertTrue($threads->canReply($threads->getThread($thread->getId())), 'moderators reply to locked threads');
		$this->forum->getPosts()->createPost($threads->getThread($thread->getId()), 'mod reply');
		$threads->setLocked($threads->getThread($thread->getId()), false);

		$this->forum->setRequireApproval(true);
		$this->loginAs('carol');
		$pending = $threads->createThread($board, 'Pending', 'p', ['tags' => ['x']]);
		self::assertSame(0, (int) $this->forum->getBoards()->getBoard($board->getId())->thread_count, 'unapproved threads are not counted');
		$reply = $this->forum->getPosts()->createPost($threads->getThread($thread->getId()), 'pending reply');
		self::assertFalse($reply->getIsApproved());
		self::assertSame(1, (int) $threads->getThread($thread->getId())->reply_count, 'unapproved replies are not counted');
		$this->loginAs('dave');
		[$posts] = $this->forum->getPosts()->listPosts($threads->getThread($thread->getId()));
		self::assertCount(2, $posts, 'others do not see pending replies');
		$this->loginAs('carol');
		[$posts] = $this->forum->getPosts()->listPosts($threads->getThread($thread->getId()));
		self::assertCount(3, $posts, 'authors see their own pending replies');
		$this->loginAs('mod');
		self::assertSame(2, $this->forum->getModeration()->countPending());
		[$pendingThreads] = $this->forum->getModeration()->listPendingThreads();
		[$pendingPosts] = $this->forum->getModeration()->listPendingPosts();
		self::assertCount(1, $pendingThreads);
		self::assertCount(1, $pendingPosts);
		$this->forum->getPosts()->approvePost($reply);
		self::assertSame(2, (int) $threads->getThread($thread->getId())->reply_count);
		$firstPending = $this->forum->getPosts()->findPost((int) $pending->first_post_id);
		$this->forum->getPosts()->approvePost($firstPending);
		self::assertTrue($threads->getThread($pending->getId())->getIsApproved(), 'approving the opening post approves the thread');
		self::assertSame(1, (int) $this->forum->getBoards()->getBoard($board->getId())->thread_count);
		self::assertSame(0, $this->forum->getModeration()->countPending());
	}

	public function testPostEditingDeletionAndPages(): void
	{
		$posts = $this->forum->getPosts();
		$board = $this->createBoard();
		$thread = $this->createThreadAs($board, 'alice');
		$this->forum->setPostsPerPage(2);
		$replies = [];
		foreach (['bob', 'carol', 'bob', 'alice'] as $name) {
			$replies[] = $this->replyAs($thread, $name, 'reply by ' . $name);
		}
		self::assertSame(1, $posts->getPageOfPost($replies[0]));
		self::assertSame(2, $posts->getPageOfPost($replies[1]));
		self::assertSame(3, $posts->getPageOfPost($replies[3]));
		[$page, $pagination] = $posts->listPosts($thread, 2);
		self::assertSame([3, 4], array_map(fn ($p) => (int) $p->position, $page));
		self::assertSame(3, $pagination->getPageCount());
		$this->loginAs('bob');
		$edited = $posts->updatePost($replies[0], 'reply by bob', null);
		self::assertSame(0, (int) $edited->edit_count, 'unchanged content is not a revision');
		$posts->updatePost($replies[0], 'changed', 'why', 'text');
		$fresh = $posts->getPost($replies[0]->getId());
		self::assertSame('text', $fresh->content_format);
		self::assertSame(1, (int) $fresh->edit_count);
		self::assertSame('why', $posts->getRevisions($fresh)[0]->reason);
		try {
			$posts->updatePost($fresh, 'x', null, 'bbcode');
			self::fail('unknown formats are refused');
		} catch (BEForumValidationException $e) {
			self::assertSame('format', $e->getField());
		}
		$this->forum->setEditWindow(60);
		BEForumTime::freeze(BEForumTime::timestamp() + 120);
		self::assertFalse($posts->isWithinEditWindow($fresh));
		self::assertFalse($posts->canEdit($fresh));
		try {
			$posts->updatePost($fresh, 'late');
			self::fail('edit window closed');
		} catch (BEForumForbiddenException $e) {
			self::assertStringContainsString('time allowed', $e->getMessage());
		}
		$this->loginAs('mod');
		self::assertTrue($posts->canEdit($fresh), 'moderators ignore the window');
		$posts->updatePost($fresh, 'moderated', 'cleanup');
		[$log] = $this->forum->getModeration()->listLog(1, ['action' => 'edit_post']);
		self::assertCount(1, $log);
		try {
			$posts->purgePost($posts->getPost((int) $thread->first_post_id));
			self::fail('opening posts cannot be purged alone');
		} catch (BEForumValidationException $e) {
		}
		$posts->purgePost($fresh);
		self::assertNull($posts->findPost($fresh->getId()));
		self::assertSame(3, (int) $this->forum->getThreads()->getThread($thread->getId())->reply_count);
		$first = $posts->getPost((int) $thread->first_post_id);
		$posts->deletePost($first);
		self::assertTrue($this->forum->getThreads()->findThread($thread->getId())->getIsDeleted(), 'deleting the opening post deletes the thread');
		$posts->restorePost($posts->findPost($first->getId()));
		self::assertFalse($this->forum->getThreads()->findThread($thread->getId())->getIsDeleted());
		self::assertSame(4, $posts->rerenderAll());
		try {
			$posts->createPost($thread, 'x', ['reply_to' => 999]);
			self::fail('reply target must exist in the thread');
		} catch (BEForumValidationException $e) {
			self::assertSame('reply_to', $e->getField());
		}
		try {
			$posts->getPost(999);
			self::fail('missing post');
		} catch (BEForumNotFoundException $e) {
		}
		self::assertSame([], $posts->getPostsByIds([]));
	}

	public function testTagsPollsAndReactions(): void
	{
		$tags = $this->forum->getTags();
		self::assertSame(['PHP', 'prado'], $tags->parseList("#PHP, prado,\n, php"));
		self::assertSame('', $tags->normalizeName('  #  '));
		self::assertSame(64, mb_strlen($tags->normalizeName(str_repeat('a', 70))));
		try {
			$tags->ensureTag(' ');
			self::fail('empty tags');
		} catch (BEForumValidationException $e) {
		}
		$board = $this->createBoard();
		$thread = $this->createThreadAs($board, 'alice');
		$this->loginAs('alice');
		$this->forum->setMaxTagsPerThread(2);
		try {
			$tags->setThreadTags($thread, ['a', 'b', 'c']);
			self::fail('too many tags');
		} catch (BEForumValidationException $e) {
			self::assertSame('tags', $e->getField());
		}
		$tags->setThreadTags($thread, ['a, b']);
		self::assertSame(['a', 'b'], array_map(fn ($t) => $t->name, $tags->getThreadTags($thread)));
		self::assertSame([$thread->getId()], $tags->getThreadIdsForTag($tags->findBySlug('a')));
		self::assertSame(['a', 'b'], array_map(fn ($t) => $t->name, $tags->getPopularTags()));
		self::assertSame(['a'], array_map(fn ($t) => $t->name, $tags->suggest('A')));
		self::assertSame([], $tags->suggest(''));
		self::assertSame(2, $tags->recountAll());
		self::assertSame([], $tags->getTagsForThreads([]));
		$this->loginAs('bob');
		try {
			$tags->setThreadTags($thread, ['x']);
			self::fail('only owners and moderators tag');
		} catch (BEForumForbiddenException $e) {
		}

		$polls = $this->forum->getPolls();
		$this->loginAs('alice');
		foreach ([
			[['question' => '', 'options' => ['a', 'b']], 'question'],
			[['question' => 'Q', 'options' => ['a', 'a']], 'options'],
			[['question' => 'Q', 'options' => array_map('strval', range(1, 21))], 'options'],
			[['question' => 'Q', 'options' => ['a', 'b'], 'closes_at' => '-1 day'], 'closes_at'],
		] as [$definition, $field]) {
			try {
				$polls->createPoll($thread, $definition);
				self::fail('invalid poll: ' . $field);
			} catch (BEForumValidationException $e) {
				self::assertSame($field, $e->getField());
			}
		}
		$poll = $polls->createPoll($thread, ['question' => 'Q', 'options' => ['a', 'b', 'c'], 'max_choices' => 9, 'closes_at' => '+1 day']);
		self::assertSame(3, (int) $poll->max_choices, 'choices are capped by the option count');
		self::assertNotNull($poll->closes_at);
		try {
			$polls->createPoll($thread, ['question' => 'Again', 'options' => ['a', 'b']]);
			self::fail('one poll per thread');
		} catch (BEForumValidationException $e) {
		}
		$options = $polls->getOptions($poll);
		try {
			$polls->vote($poll, []);
			self::fail('choice required');
		} catch (BEForumValidationException $e) {
		}
		try {
			$polls->vote($poll, [999]);
			self::fail('foreign option');
		} catch (BEForumValidationException $e) {
		}
		$polls->vote($poll, [$options[0]->getId(), $options[2]->getId()]);
		self::assertTrue($polls->hasVoted($poll));
		self::assertFalse($polls->canVote($poll));
		try {
			$polls->vote($poll, [$options[1]->getId()]);
			self::fail('no revote');
		} catch (BEForumValidationException $e) {
			self::assertSame('forum_poll_already_voted', $e->getErrorCode());
		}
		$results = $polls->getResults($poll);
		self::assertSame(2, $results['total_votes']);
		self::assertSame(1, $results['voters']);
		self::assertSame(50.0, $results['options'][$options[0]->getId()]['percent']);
		$polls->setClosed($poll, true);
		self::assertTrue($polls->findPollOfThread($thread)->getIsClosed());
		$this->loginAs('bob');
		try {
			$polls->vote($poll, [$options[1]->getId()]);
			self::fail('closed');
		} catch (BEForumValidationException $e) {
		}
		$this->loginAs('alice');
		$polls->setClosed($poll, false);
		self::assertFalse($polls->findPollOfThread($thread)->getIsClosed());
		self::assertTrue($polls->deletePollOfThread($thread));
		self::assertFalse($polls->deletePollOfThread($thread));
		$this->forum->setEnablePolls(false);
		try {
			$polls->createPoll($thread, ['question' => 'Q', 'options' => ['a', 'b']]);
			self::fail('polls disabled');
		} catch (BEForumValidationException $e) {
		}
		$this->forum->setEnablePolls(true);

		$reactions = $this->forum->getReactions();
		$first = $this->forum->getPosts()->getPost((int) $thread->first_post_id);
		try {
			$reactions->react($first, 'like');
			self::fail('own post');
		} catch (BEForumValidationException $e) {
		}
		$this->logout();
		try {
			$reactions->react($first, 'like');
			self::fail('guests');
		} catch (BEForumForbiddenException $e) {
		}
		$this->loginAs('bob');
		$reactions->react($first, 'like');
		$this->loginAs('carol');
		$reactions->react($first, 'like');
		$reactions->react($first, 'love');
		self::assertSame(['like' => 1, 'love' => 1], $reactions->getSummary($first));
		self::assertSame([$first->getId() => 'love'], $reactions->getMemberReactions([$first->getId()]));
		self::assertCount(2, $reactions->getReactions($first));
		self::assertTrue($reactions->unreact($first));
		self::assertFalse($reactions->unreact($first));
		self::assertSame(1, $reactions->recountAll());
		self::assertSame(1, (int) $this->forum->getPosts()->getPost($first->getId())->reaction_count);
		$reactions->setReputationPerReaction(3);
		self::assertSame(3, $reactions->getReputationPerReaction());
		$reactions->react($first, 'wow');
		self::assertSame(4, (int) $this->forum->getMembers()->findByUsername('alice')->reputation);
		$this->forum->setEnableReactions(false);
		try {
			$reactions->react($first, 'like');
			self::fail('disabled');
		} catch (BEForumValidationException $e) {
		}
		self::assertSame([], $reactions->getSummaries([]));
	}

	public function testNotificationsSubscriptionsAndBookmarks(): void
	{
		$notifications = $this->forum->getNotifications();
		$subscriptions = $this->forum->getSubscriptions();
		$board = $this->createBoard();
		$alice = $this->member('alice');
		$bob = $this->member('bob');
		self::assertNull($notifications->notify(999, 'x'));
		self::assertNull($notifications->notify($alice, 'x', $alice), 'no self notifications');
		$alice->setSetting('notify_muted', false);
		$alice->save();
		self::assertNull($notifications->notify($alice, 'muted', $bob), 'muted types are skipped');
		$one = $notifications->notify($alice, 'reply', $bob, 'post', 5, ['a' => 1]);
		$two = $notifications->notify($alice, 'reply', $bob, 'post', 5, ['a' => 2]);
		self::assertSame($one->getId(), $two->getId(), 'unread duplicates fold');
		self::assertSame(2, $two->getDetail('a'));
		self::assertSame(1, $notifications->countUnread($alice));
		self::assertSame(0, $notifications->countUnread(null), 'guests have none');
		$this->loginAs('alice');
		self::assertSame(1, $notifications->countUnread());
		$notifications->markRead($one->getId());
		self::assertSame(0, $notifications->countUnread());
		$three = $notifications->notify($alice, 'reply', $bob, 'post', 5);
		self::assertNotSame($one->getId(), $three->getId(), 'read notifications are not reused');
		[$unread] = $notifications->listNotifications(1, true);
		self::assertCount(1, $unread);
		self::assertSame(2, $notifications->notifyMany([$alice, $bob, 'junk'], 'thread', null, 'thread', 1, [], null));
		self::assertSame(1, $notifications->notifyMany([$alice, $bob], 'mention', null, 'thread', 2, [], $bob->getId()));
		$this->loginAs('bob');
		try {
			$notifications->getNotification($three->getId());
			self::fail('foreign notifications are refused');
		} catch (BEForumForbiddenException $e) {
		}
		$this->loginAs('alice');
		$notifications->delete($three);
		BEForumTime::freeze(BEForumTime::timestamp() + 100 * 86400);
		self::assertSame(1, $notifications->prune(90), 'old read notifications are pruned');
		BEForumTime::freeze(null);
		$this->forum->attachBehavior('mute', new class () extends \Prado\Util\TBehavior {
		});
		$notifications->attachBehavior('veto', new class () extends \Prado\Util\TBehavior {
			public function dyNotify($notification, $recipient, $chain)
			{
				return $chain->dyNotify($notification->type === 'vetoed' ? null : $notification, $recipient);
			}
		});
		self::assertNull($notifications->notify($alice, 'vetoed', $bob));
		self::assertNotNull($notifications->notify($alice, 'fine', $bob));

		try {
			$subscriptions->subscribe('planet', 1);
			self::fail('invalid target type');
		} catch (BEForumValidationException $e) {
		}
		$this->logout();
		try {
			$subscriptions->subscribe(BEForumSubscription::TYPE_BOARD, $board->getId());
			self::fail('guests cannot subscribe');
		} catch (BEForumForbiddenException $e) {
		}
		self::assertFalse($subscriptions->isSubscribed(BEForumSubscription::TYPE_BOARD, $board->getId()));
		$this->loginAs('alice');
		self::assertTrue($subscriptions->toggle(BEForumSubscription::TYPE_BOARD, $board->getId()));
		self::assertSame(1, count($subscriptions->getSubscribers(BEForumSubscription::TYPE_BOARD, $board->getId())));
		self::assertFalse($subscriptions->unsubscribe(BEForumSubscription::TYPE_THREAD, 999));
		self::assertSame(1, $subscriptions->removeTarget(BEForumSubscription::TYPE_BOARD, $board->getId()));
		[$list] = $subscriptions->listSubscriptions(null, 1, BEForumSubscription::TYPE_BOARD);
		self::assertCount(0, $list);

		$thread = $this->createThreadAs($board, 'bob');
		$post = $this->forum->getPosts()->getPost((int) $thread->first_post_id);
		$bookmarks = $this->forum->getBookmarks();
		$this->loginAs('alice');
		self::assertFalse($bookmarks->isBookmarked($post));
		$bookmark = $bookmarks->bookmark($post);
		self::assertSame($bookmark->getId(), $bookmarks->bookmark($post)->getId());
		self::assertSame([$post->getId()], $bookmarks->getBookmarkedPostIds([$post->getId(), 999]));
		self::assertTrue($bookmarks->unbookmark($post));
		self::assertFalse($bookmarks->unbookmark($post));
		$this->forum->setEnableBookmarks(false);
		try {
			$bookmarks->bookmark($post);
			self::fail('disabled');
		} catch (BEForumValidationException $e) {
		}
		self::assertSame([], $bookmarks->getBookmarkedPostIds([$post->getId()]));
	}

	public function testReadTrackerAndSearch(): void
	{
		$tracker = $this->forum->getReadTracker();
		$board = $this->createBoard();
		$thread = $this->createThreadAs($board, 'alice');
		$this->logout();
		self::assertFalse($tracker->isThreadUnread($thread));
		self::assertNull($tracker->getFirstUnreadPost($thread));
		self::assertNull($tracker->markThreadRead($thread));
		self::assertSame([$board->getId() => false], $tracker->getBoardUnreadMap([$board]));
		$this->loginAs('bob');
		$board = $this->forum->getBoards()->getBoard($board->getId());
		self::assertSame([$board->getId() => true], $tracker->getBoardUnreadMap([$board]));
		$tracker->markBoardRead($board);
		self::assertFalse($tracker->isThreadUnread($thread));
		BEForumTime::freeze(BEForumTime::timestamp() + 5);
		$reply = $this->replyAs($thread, 'alice');
		$this->loginAs('bob');
		self::assertTrue($tracker->isThreadUnread($this->forum->getThreads()->getThread($thread->getId())));
		self::assertSame($reply->getId(), $tracker->getFirstUnreadPost($this->forum->getThreads()->getThread($thread->getId()))->getId());
		$tracker->markAllRead();
		self::assertFalse($tracker->isThreadUnread($this->forum->getThreads()->getThread($thread->getId())));
		self::assertSame([$board->getId() => false], $tracker->getBoardUnreadMap([$board]));
		$tracker->setUnreadHorizonDays(0);
		self::assertSame(1, $tracker->getUnreadHorizonDays());
		$read = $tracker->markThreadRead($thread, 1);
		self::assertSame($read->getId(), $tracker->markThreadRead($thread, 1)->getId(), 'older positions do not move the marker back');

		$search = $this->forum->getSearch();
		$this->createThreadAs($board, 'alice', 'Searching for prado', 'Prado forum rocks');
		[$posts, $pagination] = $search->searchPosts('"prado" forum', ['board' => $board, 'page_size' => 1]);
		self::assertCount(1, $posts);
		self::assertSame(1, $pagination->getItemCount());
		[$posts] = $search->searchPosts('prado', ['board' => 999]);
		self::assertCount(1, $posts, 'unknown boards widen to everything visible');
		[$posts] = $search->searchPosts('prado', ['member_id' => 999]);
		self::assertCount(0, $posts);
		[$threads] = $search->searchThreads('searching prado');
		self::assertCount(1, $threads);
		self::assertSame(['searching', 'prado'], $search->parseQuery('searching prado searching'));
		self::assertCount(8, $search->parseQuery('a b c d e f g h i j'));
		self::assertSame('<mark>Pra</mark>do &amp; &lt;b&gt;', \Belisoful\Forum\Managers\BEForumSearchManager::highlight('Prado & <b>', ['pra', '']));
	}

	public function testAttachments(): void
	{
		$attachments = $this->forum->getAttachments();
		$dir = sys_get_temp_dir() . '/beforum-att-' . uniqid();
		$this->forum->setAttachmentPath($dir);
		$board = $this->createBoard();
		$thread = $this->createThreadAs($board, 'alice');
		$post = $this->forum->getPosts()->getPost((int) $thread->first_post_id);
		$this->loginAs('alice');
		$source = tempnam(sys_get_temp_dir(), 'up');
		file_put_contents($source, 'hello');
		try {
			$attachments->attachFile($post, 'evil.exe', $source, 'application/octet-stream', false);
			self::fail('type not allowed');
		} catch (BEForumValidationException $e) {
			self::assertSame('forum_attachment_type_invalid', $e->getErrorCode());
		}
		$this->forum->setAttachmentMaxSize(2);
		try {
			$attachments->attachFile($post, 'note.txt', $source, 'text/plain', false);
			self::fail('too large');
		} catch (BEForumValidationException $e) {
			self::assertSame('forum_attachment_too_large', $e->getErrorCode());
		}
		$this->forum->setAttachmentMaxSize(1024);
		try {
			$attachments->attachFile($post, 'note.txt', '/no/such/file', null, false);
			self::fail('missing source');
		} catch (BEForumValidationException $e) {
		}
		$attachment = $attachments->attachFile($post, '../../note.txt', $source, null, true);
		self::assertSame('note.txt', $attachment->file_name);
		self::assertSame(5, (int) $attachment->size);
		self::assertStringContainsString('text/plain', (string) $attachment->mime_type);
		self::assertFileExists($attachments->getFilePath($attachment));
		self::assertFileDoesNotExist($source, 'the source was moved');
		self::assertSame($attachment->getId(), $attachments->getAttachment($attachment->getId())->getId());
		self::assertCount(1, $attachments->getAttachments($post));
		self::assertSame('5 B', \Belisoful\Forum\Managers\BEForumAttachmentManager::formatSize(5));
		self::assertSame('1.5 KB', \Belisoful\Forum\Managers\BEForumAttachmentManager::formatSize(1536));
		self::assertSame('2.0 GB', \Belisoful\Forum\Managers\BEForumAttachmentManager::formatSize(2 * 1024 ** 3));
		$this->loginAs('bob');
		try {
			$attachments->removeAttachment($attachment);
			self::fail('others cannot remove');
		} catch (BEForumForbiddenException $e) {
		}
		$this->loginAs('alice');
		self::assertTrue($attachments->removeAttachment($attachment));
		try {
			$attachments->getAttachment($attachment->getId());
			self::fail('gone');
		} catch (BEForumNotFoundException $e) {
		}
		$this->forum->setEnableAttachments(false);
		self::assertSame([], $attachments->getAttachmentsForPosts([$post->getId()]));
		try {
			$attachments->attachFile($post, 'a.txt', __FILE__, 'text/plain', false);
			self::fail('disabled');
		} catch (BEForumValidationException $e) {
		}
		array_map('unlink', glob($dir . '/*') ?: []);
		@rmdir($dir);
	}
}
