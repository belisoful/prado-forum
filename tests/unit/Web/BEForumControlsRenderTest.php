<?php

use Belisoful\Forum\Data\BEForumThread;
use Belisoful\Forum\Exceptions\BEForumForbiddenException;
use Belisoful\Forum\Exceptions\BEForumNotFoundException;
use Belisoful\Forum\Util\BEForumPagination;
use Belisoful\Forum\Web\UI\BEForumAdminMembers;
use Belisoful\Forum\Web\UI\BEForumAdminModeration;
use Belisoful\Forum\Web\UI\BEForumAdminStructure;
use Belisoful\Forum\Web\UI\BEForumBoardHeader;
use Belisoful\Forum\Web\UI\BEForumBookmarkList;
use Belisoful\Forum\Web\UI\BEForumBreadcrumbs;
use Belisoful\Forum\Web\UI\BEForumCategoryList;
use Belisoful\Forum\Web\UI\BEForumMemberCard;
use Belisoful\Forum\Web\UI\BEForumMemberList;
use Belisoful\Forum\Web\UI\BEForumMemberProfile;
use Belisoful\Forum\Web\UI\BEForumNotificationList;
use Belisoful\Forum\Web\UI\BEForumPager;
use Belisoful\Forum\Web\UI\BEForumPoll;
use Belisoful\Forum\Web\UI\BEForumPostEditor;
use Belisoful\Forum\Web\UI\BEForumRecentPosts;
use Belisoful\Forum\Web\UI\BEForumSearchBox;
use Belisoful\Forum\Web\UI\BEForumSearchResults;
use Belisoful\Forum\Web\UI\BEForumStatistics;
use Belisoful\Forum\Web\UI\BEForumSubscribeButton;
use Belisoful\Forum\Web\UI\BEForumSubscriptionList;
use Belisoful\Forum\Web\UI\BEForumTagCloud;
use Belisoful\Forum\Web\UI\BEForumThreadEditor;
use Belisoful\Forum\Web\UI\BEForumThreadList;
use Belisoful\Forum\Web\UI\BEForumThreadView;
use Belisoful\Forum\Web\UI\BEForumToolbar;

/**
 * Renders every forum control through the page lifecycle with real data.
 */
class BEForumControlsRenderTest extends BEForumControlTestCase
{
	private $board;
	private $thread;
	private $reply;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loginAs('admin');
		$category = $this->forum->getBoards()->createCategory('General', 'The *general* category');
		$this->board = $this->forum->getBoards()->createBoard($category, 'Chat', 'Everything **else**');
		$this->forum->getBoards()->createBoard($category, 'Sub', null, ['parent_id' => $this->board->getId()]);
		$this->thread = $this->createThreadAs($this->board, 'alice', 'Hello World', "Hi **there** @bob", ['tags' => ['php', 'prado'], 'type' => BEForumThread::TYPE_QUESTION, 'poll' => ['question' => 'Tabs or spaces?', 'options' => ['Tabs', 'Spaces']]]);
		$this->reply = $this->replyAs($this->thread, 'bob', 'Welcome to the forum');
		$this->loginAs('carol');
		$this->forum->getReactions()->react($this->reply, 'like');
		$this->forum->getModeration()->report($this->reply, 'Testing reports');
		$this->forum->getBookmarks()->bookmark($this->reply);
		$this->forum->getSubscriptions()->subscribe('board', $this->board->getId());
		$this->loginAs('alice');
	}

	public function testIndexControls(): void
	{
		$html = $this->renderClass(BEForumCategoryList::class);
		self::assertStringContainsString('beforum-index', $html);
		self::assertStringContainsString('General', $html);
		self::assertStringContainsString('<em>general</em>', $html);
		self::assertStringContainsString('Chat', $html);
		self::assertStringContainsString('beforum-sub-board', $html);
		self::assertStringContainsString('Hello World', $html, 'last thread is linked');
		self::assertStringContainsString('beforum-board--unread', $html);
		self::assertStringContainsString('beforum.css', $html, 'the stylesheet is registered in the head');

		$html = $this->renderClass(BEForumToolbar::class);
		self::assertStringContainsString('Notifications', $html);
		self::assertStringContainsString('beforum-badge', $html, 'alice has an unread notification (mention/reply)');
		self::assertStringNotContainsString('Structure', $html);
		self::assertStringContainsString('beforum-search-box', $html);
		$this->loginAs('admin');
		$html = $this->renderClass(BEForumToolbar::class, [], fn ($c) => $c->setShowSearch(false));
		self::assertStringContainsString('Structure', $html);
		self::assertStringContainsString('Moderation', $html);
		self::assertStringNotContainsString('beforum-search-box', $html);
		$this->logout();
		$html = $this->renderClass(BEForumToolbar::class, [], fn ($c) => $c->setLoginUrl('/login'));
		self::assertStringContainsString('href="/login"', $html);
		self::assertStringNotContainsString('Notifications', $html);

		$html = $this->renderClass(BEForumStatistics::class);
		self::assertStringContainsString('beforum-statistics', $html);
		self::assertStringContainsString('<dd>1</dd>', $html);
		$html = $this->renderClass(BEForumRecentPosts::class, [], fn ($c) => $c->setLimit(1));
		self::assertStringContainsString('Welcome to the forum', $html);
		self::assertStringNotContainsString('Hi <strong>there', $html);
		$html = $this->renderClass(BEForumTagCloud::class);
		self::assertStringContainsString('beforum-tag--w3', $html);
		self::assertStringContainsString('php', $html);
		$html = $this->renderClass(BEForumSearchBox::class, ['q' => 'hello']);
		self::assertStringContainsString('value="hello"', $html);
		$html = $this->renderClass(BEForumBreadcrumbs::class, ['thread' => $this->thread->getId()]);
		self::assertStringContainsString('Chat', $html);
		self::assertStringContainsString('aria-current="page">Hello World', $html);
		$html = $this->renderClass(BEForumBreadcrumbs::class, [], function ($c) {
			$c->setShowIndex(false);
			$c->addCrumb('Custom', '/x');
			$c->addCrumb('Last');
		});
		self::assertStringContainsString('href="/x">Custom</a>', $html);
		self::assertStringContainsString('aria-current="page">Last', $html);
	}

	public function testBoardControls(): void
	{
		$html = $this->renderClass(BEForumBoardHeader::class, ['board' => $this->board->getId()]);
		self::assertStringContainsString('<h1 class="beforum-board-header-title">Chat</h1>', $html);
		self::assertStringContainsString('<strong>else</strong>', $html);
		self::assertStringContainsString('Sub', $html);
		self::assertStringContainsString('Subscribe', $html);
		self::assertStringContainsString('Mark all read', $html);
		$html = $this->renderClass(BEForumThreadList::class, ['board' => $this->board->getId()]);
		self::assertStringContainsString('Hello World', $html);
		self::assertStringContainsString('beforum-thread--type-question', $html);
		self::assertStringContainsString('New thread', $html);
		self::assertStringContainsString('beforum-tag', $html);
		self::assertStringNotContainsString('beforum-pager-list', $html, 'single page has no pager');
		$this->forum->setThreadsPerPage(1);
		$this->createThreadAs($this->board, 'bob', 'Second');
		$html = $this->renderClass(BEForumThreadList::class, ['board' => $this->board->getId(), 'pg' => 2]);
		self::assertStringContainsString('Page 2 of 2', $html);
		self::assertStringContainsString('rel="prev"', $html);
		$html = $this->renderClass(BEForumThreadList::class, [], fn ($c) => $c->setTagSlug('php') || $c->setShowBoard(true));
		self::assertStringContainsString('Hello World', $html);
		self::assertStringContainsString('beforum-thread-board', $html);
		$html = $this->renderClass(BEForumThreadList::class, [], fn ($c) => $c->setTagSlug('missing'));
		self::assertStringContainsString('No threads yet', $html);
		$html = $this->renderClass(BEForumThreadList::class, [], fn ($c) => $c->setMemberID($this->member('alice')->getId()));
		self::assertStringContainsString('Hello World', $html);
		self::assertStringNotContainsString('Second', $html);
		$this->expectException(BEForumNotFoundException::class);
		$this->renderClass(BEForumBoardHeader::class, ['board' => 999]);
	}

	public function testThreadView(): void
	{
		$html = $this->renderClass(BEForumThreadView::class, ['thread' => $this->thread->getId()]);
		self::assertStringContainsString('Hello World', $html);
		self::assertStringContainsString('Hi <strong>there</strong>', $html);
		self::assertStringContainsString('Welcome to the forum', $html);
		self::assertStringContainsString('beforum-post--first', $html);
		self::assertStringContainsString('beforum-reaction', $html);
		self::assertStringContainsString('Tabs or spaces?', $html);
		self::assertStringContainsString('beforum-poll-form', $html, 'alice may vote');
		self::assertStringContainsString('Accept answer', $html, 'the owner may accept the reply');
		self::assertStringContainsString('beforum-editor', $html, 'reply editor is shown');
		self::assertStringContainsString('Edit thread', $html);
		self::assertStringNotContainsString('Lock', $html, 'owners cannot lock');
		self::assertStringContainsString('#post-' . $this->reply->getId(), $html);
		self::assertSame(1, (int) $this->forum->getThreads()->getThread($this->thread->getId())->view_count);
		$this->loginAs('mod');
		$html = $this->renderClass(BEForumThreadView::class, ['thread' => $this->thread->getId()]);
		self::assertStringContainsString('>Lock<', $html);
		self::assertStringContainsString('Delete thread', $html);
		self::assertStringContainsString('>Pin<', $html);
		$this->logout();
		$html = $this->renderClass(BEForumThreadView::class, ['thread' => $this->thread->getId()]);
		self::assertStringNotContainsString('beforum-editor-form', $html, 'guests get no editor');
		self::assertStringContainsString('beforum-poll-results', $html, 'guests see results');
		$this->loginAs('alice');
		$this->forum->getThreads()->setAcceptedPost($this->forum->getThreads()->getThread($this->thread->getId()), $this->reply);
		$html = $this->renderClass(BEForumThreadView::class, ['thread' => $this->thread->getId()]);
		self::assertStringContainsString('Jump to accepted answer', $html);
		self::assertStringContainsString('beforum-post--accepted', $html);
		$this->expectException(BEForumNotFoundException::class);
		$this->renderClass(BEForumThreadView::class, ['thread' => 999]);
	}

	public function testEditors(): void
	{
		$html = $this->renderClass(BEForumThreadEditor::class, ['board' => $this->board->getId()]);
		self::assertStringContainsString('beforum-editor-poll', $html);
		self::assertStringContainsString('Create thread', $html);
		self::assertStringContainsString('name="', $html);
		$this->logout();
		$html = $this->renderClass(BEForumThreadEditor::class, ['board' => $this->board->getId()]);
		self::assertStringContainsString('You may not create threads', $html);
		$this->loginAs('bob');
		$html = $this->renderClass(BEForumPostEditor::class, ['post' => $this->reply->getId()]);
		self::assertStringContainsString('Edit post', $html);
		self::assertStringContainsString('Welcome to the forum', $html);
		self::assertStringContainsString('Edit reason', $html);
		$this->loginAs('alice');
		$html = $this->renderClass(BEForumPostEditor::class, ['post' => $this->reply->getId()]);
		self::assertStringContainsString('beforum-error', $html, 'alice may not edit bob\'s post');
		$html = $this->renderClass(BEForumPostEditor::class, ['thread' => $this->thread->getId()], fn ($c) => $c->quote($this->reply));
		self::assertStringContainsString('Replying to bob', $html);
		self::assertStringContainsString('&gt; Welcome to the forum', $html);
		$this->loginAs('mod');
		$this->forum->getThreads()->setLocked($this->forum->getThreads()->getThread($this->thread->getId()), true);
		$this->loginAs('alice');
		$html = $this->renderClass(BEForumPostEditor::class, ['thread' => $this->thread->getId()]);
		self::assertStringContainsString('locked', $html);
	}

	public function testMemberAndListControls(): void
	{
		$html = $this->renderClass(BEForumMemberProfile::class, ['member' => 'alice']);
		self::assertStringContainsString('beforum-profile', $html);
		self::assertStringContainsString('Edit profile', $html);
		self::assertStringContainsString('Hello World', $html);
		self::assertStringNotContainsString('beforum-profile-moderation', $html);
		$this->loginAs('mod');
		$html = $this->renderClass(BEForumMemberProfile::class, ['member' => 'alice']);
		self::assertStringContainsString('beforum-profile-moderation', $html);
		self::assertStringContainsString('>Ban<', $html);
		$html = $this->renderClass(BEForumMemberCard::class, [], fn ($c) => $c->setUsername('bob'));
		self::assertStringContainsString('gravatar.com', $html);
		self::assertStringContainsString('member=bob', $html);
		$html = $this->renderClass(BEForumMemberCard::class, [], fn ($c) => $c->setGuestName('Visitor'));
		self::assertStringContainsString('Visitor', $html);
		$html = $this->renderClass(BEForumMemberList::class, ['q' => 'ali', 'sort' => 'reputation']);
		self::assertStringContainsString('@alice', $html);
		self::assertStringNotContainsString('@bob', $html);
		$this->loginAs('alice');
		$html = $this->renderClass(BEForumNotificationList::class);
		self::assertStringContainsString('beforum-notification--unread', $html);
		self::assertStringContainsString('replied in', $html);
		self::assertStringContainsString('Mark all read', $html);
		$this->loginAs('carol');
		$html = $this->renderClass(BEForumSubscriptionList::class);
		self::assertStringContainsString('Chat', $html);
		self::assertStringContainsString('Unsubscribe', $html);
		$html = $this->renderClass(BEForumBookmarkList::class);
		self::assertStringContainsString('Welcome to the forum', $html);
		self::assertStringContainsString('Remove bookmark', $html);
		$html = $this->renderClass(BEForumSubscribeButton::class, [], function ($c) {
			$c->setTargetType('board');
			$c->setTargetID($this->board->getId());
		});
		self::assertStringContainsString('Unsubscribe', $html);
		$html = $this->renderClass(BEForumPoll::class, ['thread' => $this->thread->getId()]);
		self::assertStringContainsString('Vote', $html);
		$html = $this->renderClass(BEForumPoll::class, ['thread' => 999]);
		self::assertStringNotContainsString('beforum-poll', $html);
		$this->logout();
		$this->expectException(BEForumForbiddenException::class);
		$this->renderClass(BEForumNotificationList::class);
	}

	public function testSearchAndAdmin(): void
	{
		$html = $this->renderClass(BEForumSearchResults::class, ['q' => 'welcome']);
		self::assertStringContainsString('1 results for', $html);
		self::assertStringContainsString('Welcome to the forum', $html);
		self::assertStringContainsString('beforum-post-context', $html);
		$html = $this->renderClass(BEForumSearchResults::class, ['q' => 'hello', 'type' => 'threads']);
		self::assertStringContainsString('Hello World', $html);
		$html = $this->renderClass(BEForumSearchResults::class, ['q' => 'x']);
		self::assertStringContainsString('beforum-error', $html);
		$html = $this->renderClass(BEForumSearchResults::class);
		self::assertStringNotContainsString('results for', $html);
		$this->loginAs('admin');
		$html = $this->renderClass(BEForumAdminStructure::class);
		self::assertStringContainsString('General', $html);
		self::assertStringContainsString('beforum-admin-board--child', $html);
		self::assertStringContainsString('Add moderator', $html);
		self::assertStringContainsString('New category', $html);
		$html = $this->renderClass(BEForumAdminModeration::class);
		self::assertStringContainsString('Testing reports', $html);
		self::assertStringContainsString('Nothing awaits approval', $html);
		self::assertStringContainsString('create board', $html);
		$html = $this->renderClass(BEForumAdminMembers::class, ['q' => 'bob']);
		self::assertStringContainsString('@bob', $html);
		self::assertStringContainsString('Badges', $html);
		$this->loginAs('alice');
		$this->expectException(BEForumForbiddenException::class);
		$this->renderClass(BEForumAdminStructure::class);
	}

	public function testPagerAndErrors(): void
	{
		$pager = new BEForumPager();
		$pager->setWindow(2);
		self::assertSame(3, $pager->getWindow());
		self::assertFalse($pager->getHasPages());
		self::assertSame([], $pager->getItems());
		self::assertSame('', $pager->getSummary());
		$pager->setPagination(new BEForumPagination(3, 10, 200));
		$pager->setUrlCallback(fn (int $p) => '/p/' . $p);
		self::assertTrue($pager->getHasPages());
		$items = $pager->getItems();
		self::assertSame('/p/2', $items[0]['url']);
		self::assertSame('prev', $items[0]['rel']);
		self::assertSame('next', end($items)['rel']);
		self::assertContains(true, array_column($items, 'gap'));
		self::assertSame(1, count(array_filter($items, fn ($i) => $i['current'])));
		$html = $this->render($pager, [], fn ($c) => $c->setPagination(new BEForumPagination(1, 10, 25)) ?? $c->setUrlCallback(fn (int $p) => '/p/' . $p));
		self::assertStringContainsString('aria-current="page">1</span>', $html);
		self::assertStringContainsString('href="/p/2"', $html);
		self::assertStringContainsString('Page 1 of 3', $html);
	}

	public function testHtmlPlaceholdersAreNotDoubleEscaped(): void
	{
		$view = new BEForumThreadView();
		$view->setThreadID($this->thread->getId());
		$html = $this->render($view);
		self::assertStringContainsString('Started by <a ', $html, 'the member link is real markup');
		self::assertStringNotContainsString('&lt;a ', $html);
		self::assertStringContainsString('CommandName', (string) file_get_contents(__DIR__ . '/../../../src/Web/UI/BEForumPostView.tpl'), 'quote bubbles as a repeater command');
		$this->logout();
		$this->loginAs('alice');
		$html = $this->renderClass(BEForumNotificationList::class);
		self::assertStringContainsString('<a ', $html);
		self::assertStringNotContainsString('&lt;a href', $html, 'notification messages keep the member links as markup');
		$trait = new BEForumBreadcrumbs();
		self::assertSame('x &lt;b&gt; <em>y</em>', $trait->th('x <b> {0}', ['<em>y</em>']));
		self::assertSame('a &amp; b', $trait->th('a & b'));
	}

	public function testPagerFallsBackToTheCurrentPageUrl(): void
	{
		$_GET = ['page' => 'Forum.Board', 'board' => '7', 'junk' => ['nested'], 'q' => 'a&b'];
		try {
			$pager = new BEForumPager();
			$pager->setPagination(new BEForumPagination(1, 10, 25));
			$this->render($pager);
			$url = $pager->getUrlFor(2);
			self::assertStringContainsString('board=7', $url);
			self::assertStringContainsString('pg=2', $url);
			self::assertStringNotContainsString('&amp;', $url, 'the URL is escaped once, when rendered');
			self::assertStringNotContainsString('junk', $url);
			self::assertSame(1, substr_count($url, 'Forum.Board') + substr_count($url, 'Forum/Board'), 'the service parameter is added once');
		} finally {
			$_GET = [];
		}
	}

	public function testGuestLastPosterNameComesFromTheLastPost(): void
	{
		$this->logout();
		$this->forum->attachBehavior('guests', new class () extends \Prado\Util\TBehavior {
			public function dyAuthorize($allowed, $permission, $extra, $user, $chain)
			{
				return $chain->dyAuthorize(true, $permission, $extra, $user);
			}
		});
		$this->forum->getPosts()->createPost($this->thread, 'guest reply', ['guest_name' => 'Wanda']);
		$this->forum->detachBehavior('guests');
		$html = $this->renderClass(BEForumThreadList::class, ['board' => (string) $this->board->getId()]);
		self::assertStringContainsString('Wanda', $html);
		self::assertSame(1, preg_match_all('/beforum-thread-last-poster"><span class="beforum-member-name beforum-member-name--guest">Wanda</', $html), 'the guest who replied is the last poster');
	}
}
