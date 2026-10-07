<?php

use Belisoful\Forum\Data\BEForumCategory;
use Belisoful\Forum\Data\BEForumNotification;
use Belisoful\Forum\Data\BEForumPoll;
use Belisoful\Forum\Data\BEForumPollOption;
use Belisoful\Forum\Data\BEForumReport;
use Belisoful\Forum\Data\BEForumSubscription;
use Belisoful\Forum\Data\BEForumThread;
use Belisoful\Forum\Web\UI\BEForumAdminMembers;
use Belisoful\Forum\Web\UI\BEForumAdminModeration;
use Belisoful\Forum\Web\UI\BEForumAdminStructure;
use Belisoful\Forum\Web\UI\BEForumBoardHeader;
use Belisoful\Forum\Web\UI\BEForumCommandEventParameter;
use Belisoful\Forum\Web\UI\BEForumMemberList;
use Belisoful\Forum\Web\UI\BEForumMemberProfile;
use Belisoful\Forum\Web\UI\BEForumNotificationList;
use Belisoful\Forum\Web\UI\BEForumPostEditor;
use Belisoful\Forum\Web\UI\BEForumPostView;
use Belisoful\Forum\Web\UI\BEForumProfileEditor;
use Belisoful\Forum\Web\UI\BEForumSearchBox;
use Belisoful\Forum\Web\UI\BEForumSearchResults;
use Belisoful\Forum\Web\UI\BEForumSubscriptionList;
use Belisoful\Forum\Web\UI\BEForumThreadEditor;
use Belisoful\Forum\Web\UI\BEForumThreadView;

/**
 * Exercises every postback handler of the template controls: the control is
 * rendered through the page lifecycle, the handler is invoked as PRADO would
 * after restoring the tree, and the effect is asserted on the managers and
 * on the control state (errors, visibility, redirects).
 */
class BEForumPostbackTest extends BEForumControlTestCase
{
	private $board;
	private $thread;
	private $reply;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loginAs('admin');
		$category = $this->forum->getBoards()->createCategory('General');
		$this->board = $this->forum->getBoards()->createBoard($category, 'Chat', 'Talk');
		$this->forum->getBoards()->createBoard($category, 'Other');
		$this->thread = $this->createThreadAs($this->board, 'alice', 'Question?', 'Body **text** @bob', ['type' => BEForumThread::TYPE_QUESTION, 'tags' => ['php'], 'poll' => ['question' => 'Pick', 'options' => ['One', 'Two']]]);
		$this->reply = $this->replyAs($this->thread, 'bob', 'An answer');
		$this->loginAs('alice');
	}

	private function threadView(): BEForumThreadView
	{
		$view = new BEForumThreadView();
		$view->setThreadID($this->thread->getId());
		$this->render($view);
		return $view;
	}

	private function postViewOf(BEForumThreadView $view, int $postId): BEForumPostView
	{
		$item = $this->itemWhere($view->Posts->Posts, 'id', $postId);
		self::assertInstanceOf(BEForumPostView::class, $item);
		return $item;
	}

	public function testModerationToolsActions(): void
	{
		$threads = $this->forum->getThreads();
		$this->loginAs('mod');
		$view = $this->threadView();
		$tools = $view->Tools;
		self::assertNotNull($this->invoke($tools, fn ($t) => $t->toggleLockClicked(null, null)), 'actions redirect to the thread');
		self::assertTrue($threads->getThread($this->thread->getId())->getIsLocked());
		$this->invoke($tools, fn ($t) => $t->toggleLockClicked(null, null));
		self::assertFalse($threads->getThread($this->thread->getId())->getIsLocked());
		$tools->PinUntil->setText('2030-01-01T10:00');
		$this->invoke($tools, fn ($t) => $t->togglePinClicked(null, null));
		$pinned = $threads->getThread($this->thread->getId());
		self::assertTrue($pinned->getIsPinned());
		self::assertStringStartsWith('2030-01-01 10:00', (string) $pinned->pinned_until);
		$other = $this->forum->getBoards()->findBoardBySlug('other');
		$tools->MoveTarget->setSelectedValue((string) $other->getId());
		$this->invoke($tools, fn ($t) => $t->moveClicked(null, null));
		self::assertSame($other->getId(), (int) $threads->getThread($this->thread->getId())->board_id);
		$this->invoke($tools, fn ($t) => $t->editClicked(null, null));
		self::assertTrue($tools->EditPanel->getVisible());
		self::assertSame('Question?', $tools->Title->getText());
		self::assertSame('php', $tools->Tags->getText());
		$tools->Title->setText('Edited title');
		$tools->Tags->setText('php, prado');
		self::assertNotNull($this->invoke($tools, fn ($t) => $t->saveClicked(null, null)));
		self::assertSame('Edited title', $threads->getThread($this->thread->getId())->title);
		self::assertCount(2, $this->forum->getTags()->getThreadTags($threads->getThread($this->thread->getId())));
		$this->invoke($tools, fn ($t) => $t->cancelEditClicked(null, null));
		self::assertFalse($tools->EditPanel->getVisible());
		$tools->Title->setText('');
		self::assertNull($this->invoke($tools, fn ($t) => $t->saveClicked(null, null)), 'validation errors stay on the page');
		self::assertNotNull($tools->getErrorMessage());
		$this->invoke($tools, fn ($t) => $t->deleteClicked(null, null));
		self::assertTrue($threads->findThread($this->thread->getId())->getIsDeleted());
		$this->invoke($tools, fn ($t) => $t->restoreClicked(null, null));
		self::assertFalse($threads->findThread($this->thread->getId())->getIsDeleted());
		$this->forum->setRequireApproval(true);
		$pending = $this->createThreadAs($this->board, 'carol', 'Pending');
		$this->forum->setRequireApproval(false);
		$pendingView = new BEForumThreadView();
		$pendingView->setThreadID($pending->getId());
		$this->render($pendingView);
		$this->invoke($pendingView->Tools, fn ($t) => $t->approveClicked(null, null));
		self::assertTrue($threads->getThread($pending->getId())->getIsApproved());
		self::assertNotNull($this->invoke($pendingView->Tools, fn ($t) => $t->purgeClicked(null, null)), 'purging redirects to the board');
		self::assertNull($threads->findThread($pending->getId()));

		$this->loginAs('dave');
		$view = $this->threadView();
		self::assertNull($this->invoke($view->Tools, fn ($t) => $t->toggleLockClicked(null, null)));
		self::assertNotNull($view->Tools->getErrorMessage(), 'members without the permission see an error');
		self::assertFalse($threads->getThread($this->thread->getId())->getIsLocked());
	}

	public function testPostViewActions(): void
	{
		$posts = $this->forum->getPosts();
		$threads = $this->forum->getThreads();
		$view = $this->threadView();
		$replyView = $this->postViewOf($view, $this->reply->getId());
		self::assertNotNull($this->invoke($replyView, fn ($v) => $v->acceptClicked(null, null)), 'success reloads the page');
		self::assertSame($this->reply->getId(), (int) $threads->getThread($this->thread->getId())->accepted_post_id);
		$this->invoke($replyView, fn ($v) => $v->unacceptClicked(null, null));
		self::assertNull($threads->getThread($this->thread->getId())->accepted_post_id);
		$this->invoke($replyView, fn ($v) => $v->bookmarkClicked(null, null));
		self::assertTrue($this->forum->getBookmarks()->isBookmarked($posts->getPost($this->reply->getId())));
		$this->invoke($replyView, fn ($v) => $v->bookmarkClicked(null, null));
		self::assertFalse($this->forum->getBookmarks()->isBookmarked($posts->getPost($this->reply->getId())));

		$this->invoke($replyView, fn ($v) => $v->reportClicked(null, null));
		self::assertTrue($replyView->ReportPanel->getVisible());
		$this->invoke($replyView, fn ($v) => $v->cancelReportClicked(null, null));
		self::assertFalse($replyView->ReportPanel->getVisible());
		$this->invoke($replyView, fn ($v) => $v->reportClicked(null, null));
		$replyView->ReportReason->setText('');
		self::assertNull($this->invoke($replyView, fn ($v) => $v->sendReportClicked(null, null)), 'an empty reason is an error');
		self::assertNotNull($replyView->getErrorMessage());
		self::assertTrue($replyView->ReportPanel->getVisible());
		$replyView->ReportReason->setText('Spam');
		self::assertNotNull($this->invoke($replyView, fn ($v) => $v->sendReportClicked(null, null)));
		self::assertSame(1, $this->forum->getModeration()->countOpenReports());

		// the rebuilt row carries the open report form and the error of the postback
		$view = new BEForumThreadView();
		$view->setThreadID($this->thread->getId());
		$html = $this->render($view, [], function (BEForumThreadView $v): void {
			$v->ensureChildControls();
			$v->Posts->openReportFor($this->reply->getId());
			$v->Posts->setPostError($this->reply->getId(), 'Kept <error>');
		});
		self::assertStringContainsString('Kept &lt;error&gt;', $html);
		$rebuilt = $this->postViewOf($view, $this->reply->getId());
		self::assertTrue($rebuilt->ReportPanel->getVisible());
		self::assertSame('Kept <error>', $rebuilt->getErrorMessage());

		$this->loginAs('bob');
		$view = $this->threadView();
		$replyView = $this->postViewOf($view, $this->reply->getId());
		self::assertNull($this->invoke($replyView, fn ($v) => $v->acceptClicked(null, null)), 'only the thread owner accepts');
		self::assertNotNull($replyView->getErrorMessage());
		self::assertNotNull($this->invoke($replyView, fn ($v) => $v->deleteClicked(null, null)), 'owners delete their post');
		self::assertTrue($posts->findPost($this->reply->getId())->getIsDeleted());

		$this->loginAs('mod');
		$view = $this->threadView();
		$replyView = $this->postViewOf($view, $this->reply->getId());
		$this->invoke($replyView, fn ($v) => $v->restoreClicked(null, null));
		self::assertFalse($posts->getPost($this->reply->getId())->getIsDeleted());
		$this->forum->setRequireApproval(true);
		$pending = $this->replyAs($this->thread, 'dave', 'pending reply');
		$this->forum->setRequireApproval(false);
		$view = $this->threadView();
		$pendingView = $this->postViewOf($view, $pending->getId());
		$this->invoke($pendingView, fn ($v) => $v->approveClicked(null, null));
		self::assertTrue($posts->getPost($pending->getId())->getIsApproved());
	}

	public function testQuoteReactionsPollAndSubscribe(): void
	{
		$view = $this->threadView();
		$view->postCommand($view->Posts, new BEForumCommandEventParameter('quote', $this->reply->getId()));
		self::assertStringContainsString('bob wrote:', $view->Editor->Content->getText());
		self::assertStringContainsString('> An answer', $view->Editor->Content->getText());
		self::assertSame($this->reply->getId(), $view->Editor->getReplyToID());
		$view->Editor->clearReplyToClicked(null, null);
		self::assertSame(0, $view->Editor->getReplyToID());
		$view->Posts->itemCommand($view->Posts->Posts, $this->command('quote', (string) $this->reply->getId()));
		self::assertSame($this->reply->getId(), $view->Editor->getReplyToID(), 'repeater commands reach the editor too');

		$replyView = $this->postViewOf($view, $this->reply->getId());
		$bar = $replyView->Reactions;
		$this->invoke($bar, fn ($b) => $b->reactionCommand(null, $this->command('like')));
		self::assertNull($bar->getErrorMessage());
		self::assertSame(1, (int) ($this->forum->getReactions()->getSummary($this->forum->getPosts()->getPost($this->reply->getId()))['like'] ?? 0));
		$this->invoke($bar, fn ($b) => $b->reactionCommand(null, $this->command('nope')));
		self::assertNotNull($bar->getErrorMessage(), 'unknown reaction types are rejected');
		self::assertStringContainsString('reaction', strtolower((string) $view->Posts->buildPostRows([$this->forum->getPosts()->getPost($this->reply->getId())])[0]['error']), 'the error is routed to the post row');

		$poll = BEForumPoll::finder()->find('thread_id = ?', [$this->thread->getId()]);
		$option = BEForumPollOption::finder()->find('poll_id = ?', [$poll->getId()]);
		$pollView = $view->PollView;
		$pollView->SingleChoice->setSelectedValue((string) $option->getId());
		$this->invoke($pollView, fn ($p) => $p->voteClicked(null, null));
		self::assertNull($pollView->getErrorMessage());
		self::assertSame(1, (int) BEForumPoll::finder()->findByPk($poll->getId())->vote_count);
		$this->invoke($pollView, fn ($p) => $p->toggleClosedClicked(null, null));
		self::assertNull($pollView->getErrorMessage(), 'the thread owner may close the poll');
		self::assertTrue(BEForumPoll::finder()->findByPk($poll->getId())->getIsClosed());
		$this->invoke($pollView, fn ($p) => $p->voteClicked(null, null));
		self::assertNotNull($pollView->getErrorMessage(), 'closed polls refuse votes');

		$subscribe = $view->Subscribe;
		$subscriptions = $this->forum->getSubscriptions();
		$before = $subscriptions->isSubscribed(BEForumSubscription::TYPE_THREAD, $this->thread->getId());
		$this->invoke($subscribe, fn ($s) => $s->toggleClicked(null, null));
		self::assertSame(!$before, $subscriptions->isSubscribed(BEForumSubscription::TYPE_THREAD, $this->thread->getId()));
		$this->invoke($subscribe, fn ($s) => $s->toggleClicked(null, null));
		self::assertSame($before, $subscriptions->isSubscribed(BEForumSubscription::TYPE_THREAD, $this->thread->getId()));
	}

	public function testEditorsSubmitAndPreview(): void
	{
		$posts = $this->forum->getPosts();
		$editor = new BEForumPostEditor();
		$editor->setThreadID($this->thread->getId());
		$this->render($editor);
		$editor->Content->setText('Some *markdown*');
		$this->invoke($editor, fn ($e) => $e->previewClicked(null, null));
		self::assertTrue($editor->PreviewPanel->getVisible());
		self::assertStringContainsString('<em>markdown</em>', $editor->Preview->getText());
		$editor->Content->setText('');
		self::assertNull($this->invoke($editor, fn ($e) => $e->submitClicked(null, null)));
		self::assertNotNull($editor->getErrorMessage(), 'empty content is refused');
		$editor->Content->setText('A fresh reply');
		self::assertNotNull($this->invoke($editor, fn ($e) => $e->submitClicked(null, null)), 'a saved reply redirects to the post');
		[$list] = $posts->listPosts($this->thread, 1, 50);
		self::assertSame('A fresh reply', end($list)->content);

		$this->loginAs('bob');
		$edit = new BEForumPostEditor();
		$edit->setPostID($this->reply->getId());
		$this->render($edit);
		self::assertTrue($edit->getIsEditMode());
		self::assertSame('An answer', $edit->Content->getText());
		$edit->Content->setText('An edited answer');
		$edit->Reason->setText('typo');
		self::assertNotNull($this->invoke($edit, fn ($e) => $e->submitClicked(null, null)));
		self::assertSame('An edited answer', $posts->getPost($this->reply->getId())->content);
		self::assertSame(1, (int) $posts->getPost($this->reply->getId())->edit_count);

		$this->loginAs('alice');
		$threadEditor = new BEForumThreadEditor();
		$threadEditor->setBoardID($this->board->getId());
		$this->render($threadEditor);
		$threadEditor->Content->setText('Preview **me**');
		$this->invoke($threadEditor, fn ($e) => $e->previewClicked(null, null));
		self::assertStringContainsString('<strong>me</strong>', $threadEditor->Preview->getText());
		$threadEditor->Title->setText('');
		self::assertNull($this->invoke($threadEditor, fn ($e) => $e->submitClicked(null, null)));
		self::assertNotNull($threadEditor->getErrorMessage());
		$threadEditor->Title->setText('Brand new thread');
		$threadEditor->Tags->setText('news');
		$threadEditor->PollQuestion->setText('Yes?');
		$threadEditor->PollOptions->setText("Yes\nNo");
		self::assertNotNull($this->invoke($threadEditor, fn ($e) => $e->submitClicked(null, null)), 'a created thread redirects to it');
		$created = BEForumThread::finder()->find('title = ?', ['Brand new thread']);
		self::assertNotNull($created);
		self::assertNotNull($this->forum->getPolls()->findPollOfThread($created));
		self::assertSame(['news'], array_map(fn ($t) => $t->name, $this->forum->getTags()->getThreadTags($created)));
	}

	public function testProfileEditorAndMemberProfile(): void
	{
		$members = $this->forum->getMembers();
		$editor = new BEForumProfileEditor();
		$this->render($editor);
		$editor->DisplayName->setText('Alice A.');
		$editor->Location->setText('Berlin');
		$editor->Website->setText('not a url');
		self::assertNull($this->invoke($editor, fn ($e) => $e->saveClicked(null, null)));
		self::assertNotNull($editor->getErrorMessage(), 'invalid URLs are refused');
		$editor->Website->setText('https://example.org');
		$editor->Notifications->clearSelection();
		$this->invoke($editor, fn ($e) => $e->saveClicked(null, null));
		self::assertTrue($editor->Saved->getVisible());
		$alice = $members->getMemberByUsername('alice');
		self::assertSame('Alice A.', $alice->display_name);
		self::assertSame('Berlin', $alice->location);
		self::assertFalse($alice->getSetting(array_key_first($editor->getNotificationOptions())));
		self::assertSame(1, (int) $alice->post_count, 'saving the profile keeps the counters');

		$this->loginAs('admin');
		$this->forum->getMembers()->defineBadge('Helper', 'Helps out');
		$profile = new BEForumMemberProfile();
		$this->render($profile, ['member' => 'bob']);
		$this->invoke($profile, fn ($p) => $p->editClicked(null, null));
		self::assertTrue($profile->Editor->getVisible());
		$profile->BanReason->setText('Rules');
		$profile->BanUntil->setText('not a date');
		$this->invoke($profile, fn ($p) => $p->banClicked(null, null));
		self::assertNotNull($profile->getErrorMessage(), 'an invalid end time is refused');
		self::assertFalse($members->getMemberByUsername('bob')->getIsBanned());
		$profile->BanReason->setText('Rules');
		$profile->BanUntil->setText('2031-05-05T12:00');
		$this->invoke($profile, fn ($p) => $p->banClicked(null, null));
		self::assertTrue($members->getMemberByUsername('bob')->getIsBanned());
		$this->invoke($profile, fn ($p) => $p->unbanClicked(null, null));
		self::assertFalse($members->getMemberByUsername('bob')->getIsBanned());
		$profile->WarnReason->setText('Careful');
		$this->invoke($profile, fn ($p) => $p->warnClicked(null, null));
		self::assertSame(1, (int) $members->getMemberByUsername('bob')->warning_count);
		self::assertSame('', $profile->WarnReason->getText());
		$profile->BadgeList->setSelectedValue('helper');
		$this->invoke($profile, fn ($p) => $p->awardClicked(null, null));
		self::assertCount(1, $members->getMemberBadges($members->getMemberByUsername('bob')));
	}

	public function testNotificationsSubscriptionsAndBoardHeader(): void
	{
		$notifications = $this->forum->getNotifications();
		$this->loginAs('alice');
		self::assertGreaterThan(0, $notifications->countUnread(), 'alice was notified about the reply');
		$list = new BEForumNotificationList();
		$this->render($list);
		[$rows] = $notifications->listNotifications();
		$first = $rows[0];
		$this->invoke($list, fn ($l) => $l->itemCommand(null, $this->command('read', (string) $first->getId())));
		self::assertNotNull(BEForumNotification::finder()->findByPk($first->getId())->read_at);
		$this->invoke($list, fn ($l) => $l->itemCommand(null, $this->command('delete', (string) $first->getId())));
		self::assertNull(BEForumNotification::finder()->findByPk($first->getId()));
		$this->invoke($list, fn ($l) => $l->itemCommand(null, $this->command('read', '999999')));
		self::assertNotNull($list->getErrorMessage(), 'unknown notifications are reported');
		$this->invoke($list, fn ($l) => $l->markAllClicked(null, null));
		self::assertSame(0, $notifications->countUnread());

		$subscriptions = $this->forum->getSubscriptions();
		self::assertTrue($subscriptions->isSubscribed(BEForumSubscription::TYPE_THREAD, $this->thread->getId()), 'thread authors are subscribed');
		$subList = new BEForumSubscriptionList();
		$this->render($subList);
		$this->invoke($subList, fn ($l) => $l->itemCommand(null, $this->command('unsubscribe', 'thread:' . $this->thread->getId())));
		self::assertFalse($subscriptions->isSubscribed(BEForumSubscription::TYPE_THREAD, $this->thread->getId()));

		$this->loginAs('carol');
		$reads = $this->forum->getReadTracker();
		self::assertTrue($reads->isThreadUnread($this->forum->getThreads()->getThread($this->thread->getId())));
		$header = new BEForumBoardHeader();
		$header->setBoardID($this->board->getId());
		$this->render($header);
		$this->invoke($header, fn ($h) => $h->markReadClicked(null, null));
		$reads->flushRequestCache();
		self::assertFalse($reads->isThreadUnread($this->forum->getThreads()->getThread($this->thread->getId())));
	}

	public function testSearchAndFilterRedirects(): void
	{
		$box = new BEForumSearchBox();
		$this->render($box);
		$box->Query->setText('   ');
		self::assertNull($this->invoke($box, fn ($b) => $b->searchClicked(null, null)), 'empty queries do nothing');
		$box->Query->setText('prado');
		self::assertNotNull($this->invoke($box, fn ($b) => $b->searchClicked(null, null)));

		$results = new BEForumSearchResults();
		$this->render($results, ['q' => 'answer']);
		$results->Query->setText('answer');
		$results->Board->setSelectedValue((string) $this->board->getId());
		self::assertNotNull($this->invoke($results, fn ($r) => $r->searchClicked(null, null)));

		$members = new BEForumMemberList();
		$this->render($members);
		$members->Query->setText('ali');
		self::assertNotNull($this->invoke($members, fn ($m) => $m->filterClicked(null, null)));

		$this->loginAs('admin');
		$admin = new BEForumAdminMembers();
		$this->render($admin);
		$admin->Query->setText('bob');
		self::assertNotNull($this->invoke($admin, fn ($a) => $a->filterClicked(null, null)));
	}

	public function testAdminStructure(): void
	{
		$this->loginAs('admin');
		$boards = $this->forum->getBoards();
		$admin = new BEForumAdminStructure();
		$this->render($admin);
		$admin->CategoryName->setText('');
		$this->invoke($admin, fn ($a) => $a->saveCategoryClicked(null, null));
		self::assertNotNull($admin->getErrorMessage(), 'a category needs a name');
		$admin->CategoryName->setText('Second');
		$admin->CategoryDescription->setText('More');
		$this->invoke($admin, fn ($a) => $a->saveCategoryClicked(null, null));
		$second = $boards->findCategoryBySlug('second');
		self::assertNotNull($second);
		self::assertSame('', $admin->CategoryName->getText(), 'the form is reset after saving');
		$this->invoke($admin, fn ($a) => $a->categoryCommand(null, $this->command('edit', (string) $second->getId())));
		self::assertSame('Second', $admin->CategoryName->getText());
		self::assertSame($second->getId(), $admin->getEditCategoryID());
		$admin->CategoryName->setText('Second renamed');
		$this->invoke($admin, fn ($a) => $a->saveCategoryClicked(null, null));
		self::assertSame('Second renamed', $boards->getCategory($second->getId())->name);
		self::assertSame(0, $admin->getEditCategoryID());
		$this->invoke($admin, fn ($a) => $a->categoryCommand(null, $this->command('edit', (string) $second->getId())));
		$this->invoke($admin, fn ($a) => $a->cancelCategoryClicked(null, null));
		self::assertSame(0, $admin->getEditCategoryID());
		$general = $boards->getCategories(true)[0];
		$this->invoke($admin, fn ($a) => $a->categoryCommand(null, $this->command('down', (string) $general->getId())));
		self::assertSame($second->getId(), $boards->getCategories(true)[0]->getId(), 'moved down');
		$this->invoke($admin, fn ($a) => $a->categoryCommand(null, $this->command('up', (string) $general->getId())));
		self::assertSame($general->getId(), $boards->getCategories(true)[0]->getId(), 'moved up again');

		// the selectors were bound before the category existed: a fresh request sees it
		$admin = new BEForumAdminStructure();
		$this->render($admin);
		$admin->BoardName->setText('Lounge');
		$admin->BoardDescription->setText('Relax');
		$admin->BoardCategory->setSelectedValue((string) $second->getId());
		$admin->BoardParent->setSelectedValue('0');
		$admin->BoardLocked->setChecked(true);
		$this->invoke($admin, fn ($a) => $a->saveBoardClicked(null, null));
		self::assertNull($admin->getErrorMessage());
		$lounge = $boards->findBoardBySlug('lounge');
		self::assertNotNull($lounge);
		self::assertTrue($lounge->getIsLocked());
		self::assertSame($second->getId(), (int) $lounge->category_id);
		$this->invoke($admin, fn ($a) => $a->boardCommand(null, $this->command('edit', (string) $lounge->getId())));
		self::assertSame('Lounge', $admin->BoardName->getText());
		self::assertSame($lounge->getId(), $admin->getEditBoardID());
		$admin->BoardName->setText('Lounge 2');
		$admin->BoardLocked->setChecked(false);
		$this->invoke($admin, fn ($a) => $a->saveBoardClicked(null, null));
		self::assertSame('Lounge 2', $boards->getBoard($lounge->getId())->name);
		self::assertFalse($boards->getBoard($lounge->getId())->getIsLocked());
		$this->invoke($admin, fn ($a) => $a->boardCommand(null, $this->command('edit', (string) $lounge->getId())));
		$this->invoke($admin, fn ($a) => $a->cancelBoardClicked(null, null));
		self::assertSame(0, $admin->getEditBoardID());
		$chat = $this->board;
		$other = $boards->findBoardBySlug('other');
		$this->invoke($admin, fn ($a) => $a->boardCommand(null, $this->command('down', (string) $chat->getId())));
		$ordered = array_values(array_filter($boards->getBoards(true), fn ($b) => (int) $b->category_id === (int) $chat->category_id));
		self::assertSame($other->getId(), $ordered[0]->getId());
		$this->invoke($admin, fn ($a) => $a->boardCommand(null, $this->command('up', (string) $chat->getId())));
		$ordered = array_values(array_filter($boards->getBoards(true), fn ($b) => (int) $b->category_id === (int) $chat->category_id));
		self::assertSame($chat->getId(), $ordered[0]->getId());
		$this->invoke($admin, fn ($a) => $a->boardCommand(null, $this->command('delete', (string) $chat->getId())));
		self::assertNotNull($admin->getErrorMessage(), 'boards with threads cannot be deleted');
		$this->invoke($admin, fn ($a) => $a->boardCommand(null, $this->command('delete', (string) $lounge->getId())));
		self::assertNull($boards->findBoard($lounge->getId()));
		$this->invoke($admin, fn ($a) => $a->categoryCommand(null, $this->command('delete', (string) $second->getId())));
		self::assertNull(BEForumCategory::finder()->findByPk($second->getId()));

		// moderators through the nested repeaters, as rendered
		$admin = new BEForumAdminStructure();
		$this->render($admin);
		$categoryItem = $this->itemWhere($admin->Categories, 'id', $general->getId());
		$boardItem = $this->itemWhere($categoryItem->findControl('Boards'), 'id', $chat->getId());
		$boardItem->findControl('NewModerator')->setText('nobody-here');
		$this->invoke($admin, fn ($a) => $a->moderatorCommand(null, $this->command('addmod', (string) $chat->getId(), $boardItem)));
		self::assertNotNull($admin->getErrorMessage(), 'unknown members are reported');
		$boardItem->findControl('NewModerator')->setText('bob');
		$this->invoke($admin, fn ($a) => $a->moderatorCommand(null, $this->command('addmod', (string) $chat->getId(), $boardItem)));
		self::assertSame(['bob'], $this->forum->getMembers()->getBoardModeratorUsernames($chat->getId()));
		$admin = new BEForumAdminStructure();
		$this->render($admin);
		$categoryItem = $this->itemWhere($admin->Categories, 'id', $general->getId());
		$boardItem = $this->itemWhere($categoryItem->findControl('Boards'), 'id', $chat->getId());
		$moderatorItems = $boardItem->findControl('Moderators')->getItems();
		self::assertCount(1, $moderatorItems);
		$bob = $this->forum->getMembers()->getMemberByUsername('bob');
		$this->invoke($admin, fn ($a) => $a->moderatorCommand(null, $this->command('removemod', $chat->getId() . ':' . $bob->getId(), $moderatorItems[0])));
		self::assertSame([], $this->forum->getMembers()->getBoardModeratorUsernames($chat->getId()));
		$this->invoke($admin, fn ($a) => $a->moderatorCommand(null, $this->command('up', (string) $chat->getId(), $boardItem)));
		self::assertNull($admin->getErrorMessage() ?? null);
	}

	public function testAdminMembersAndModeration(): void
	{
		$members = $this->forum->getMembers();
		$this->loginAs('admin');
		$admin = new BEForumAdminMembers();
		$this->render($admin);
		$bob = $members->getMemberByUsername('bob');
		$item = $this->itemWhere($admin->Rows, 'id', $bob->getId());
		$item->findControl('Reason')->setText('Be nice');
		$this->invoke($admin, fn ($a) => $a->memberCommand(null, $this->command('warn', (string) $bob->getId(), $item)));
		self::assertSame(1, (int) $members->getMemberByUsername('bob')->warning_count);
		$item->findControl('Until')->setText('2032-01-01T00:00');
		$this->invoke($admin, fn ($a) => $a->memberCommand(null, $this->command('ban', (string) $bob->getId(), $item)));
		$banned = $members->getMemberByUsername('bob');
		self::assertTrue($banned->getIsBanned());
		self::assertStringStartsWith('2032-01-01', (string) $banned->banned_until);
		$this->invoke($admin, fn ($a) => $a->memberCommand(null, $this->command('unban', (string) $bob->getId(), $item)));
		self::assertFalse($members->getMemberByUsername('bob')->getIsBanned());
		$this->invoke($admin, fn ($a) => $a->memberCommand(null, $this->command('ban', '999999', $item)));
		self::assertNotNull($admin->getErrorMessage());
		$admin->BadgeName->setText('Veteran');
		$admin->BadgeDescription->setText('Around for years');
		$admin->BadgeIcon->setText('star');
		$this->invoke($admin, fn ($a) => $a->defineBadgeClicked(null, null));
		self::assertNotNull($members->findBadge('veteran'));
		self::assertSame('', $admin->BadgeName->getText());
		$admin->BadgeName->setText('');
		$this->invoke($admin, fn ($a) => $a->defineBadgeClicked(null, null));
		self::assertNotNull($admin->getErrorMessage());

		$this->loginAs('carol');
		$this->forum->getModeration()->report($this->reply, 'Spam');
		$this->loginAs('dave');
		$this->forum->getModeration()->report($this->reply, 'Also spam');
		$this->forum->setRequireApproval(true);
		$pendingThread = $this->createThreadAs($this->board, 'erin', 'Pending thread');
		$pendingPost = $this->replyAs($this->thread, 'erin', 'Pending post');
		$pendingThread2 = $this->createThreadAs($this->board, 'erin', 'Rejected thread');
		$pendingPost2 = $this->replyAs($this->thread, 'erin', 'Rejected post');
		$this->forum->setRequireApproval(false);
		$this->loginAs('mod');
		$moderation = $this->forum->getModeration();
		$panel = new BEForumAdminModeration();
		$this->render($panel);
		[$reports] = $moderation->listReports(BEForumReport::STATUS_OPEN);
		self::assertCount(2, $reports);
		$reportItem = $this->itemWhere($panel->Reports, 'id', $reports[0]->getId());
		$reportItem->findControl('Note')->setText('Handled');
		$this->invoke($panel, fn ($p) => $p->reportCommand(null, $this->command('resolve', (string) $reports[0]->getId(), $reportItem)));
		self::assertSame(BEForumReport::STATUS_RESOLVED, $moderation->getReport($reports[0]->getId())->status);
		self::assertSame('Handled', $moderation->getReport($reports[0]->getId())->resolution);
		$reportItem2 = $this->itemWhere($panel->Reports, 'id', $reports[1]->getId());
		$this->invoke($panel, fn ($p) => $p->reportCommand(null, $this->command('dismiss', (string) $reports[1]->getId(), $reportItem2)));
		self::assertSame(BEForumReport::STATUS_DISMISSED, $moderation->getReport($reports[1]->getId())->status);
		self::assertSame(0, $moderation->countOpenReports());
		$this->invoke($panel, fn ($p) => $p->reportCommand(null, $this->command('resolve', '999999', $reportItem)));
		self::assertNotNull($panel->getErrorMessage());

		$this->invoke($panel, fn ($p) => $p->pendingCommand(null, $this->command('approve', 'thread:' . $pendingThread->getId())));
		self::assertTrue($this->forum->getThreads()->getThread($pendingThread->getId())->getIsApproved());
		$this->invoke($panel, fn ($p) => $p->pendingCommand(null, $this->command('approve', 'post:' . $pendingPost->getId())));
		self::assertTrue($this->forum->getPosts()->getPost($pendingPost->getId())->getIsApproved());
		$this->invoke($panel, fn ($p) => $p->pendingCommand(null, $this->command('reject', 'thread:' . $pendingThread2->getId())));
		self::assertTrue($this->forum->getThreads()->findThread($pendingThread2->getId())->getIsDeleted());
		$this->invoke($panel, fn ($p) => $p->pendingCommand(null, $this->command('reject', 'post:' . $pendingPost2->getId())));
		self::assertTrue($this->forum->getPosts()->findPost($pendingPost2->getId())->getIsDeleted());
		$this->invoke($panel, fn ($p) => $p->pendingCommand(null, $this->command('approve', 'post:999999')));
		self::assertSame(0, $moderation->countPending());
	}
}
