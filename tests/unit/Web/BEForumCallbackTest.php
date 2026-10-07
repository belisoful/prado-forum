<?php

use Belisoful\Forum\Data\BEForumSubscription;
use Belisoful\Forum\Web\UI\BEForumPostView;
use Belisoful\Forum\Web\UI\BEForumThreadView;
use Prado\Web\UI\ActiveControls\TActiveLinkButton;

/**
 * Runs the active controls (reactions, subscribe button, bookmark) through
 * real callback requests and checks that only the affected part of the page
 * is refreshed, without a redirect.
 */
class BEForumCallbackTest extends BEForumControlTestCase
{
	private $board;
	private $thread;
	private $reply;

	protected function setUp(): void
	{
		parent::setUp();
		$this->board = $this->createBoard();
		$this->thread = $this->createThreadAs($this->board, 'alice');
		$this->reply = $this->replyAs($this->thread, 'bob', 'An answer');
		$this->loginAs('alice');
	}

	private function viewFactory(): callable
	{
		return function (): BEForumThreadView {
			$view = new BEForumThreadView();
			$view->setThreadID($this->thread->getId());
			return $view;
		};
	}

	/**
	 * Finds the renderer of the reply by its PostID view state, which is also
	 * available on the rows restored during a callback (they carry no data).
	 * @param BEForumThreadView $view
	 */
	private function replyView(BEForumThreadView $view): BEForumPostView
	{
		foreach ($view->Posts->Posts->getItems() as $item) {
			if ($item instanceof BEForumPostView && $item->getPostID() === $this->reply->getId()) {
				return $item;
			}
		}
		self::fail('the reply has no renderer');
	}

	/**
	 * @param array $result
	 * @return string[] the names of the client functions queued by the callback
	 */
	private function functions(array $result): array
	{
		return array_map(fn (array $action) => (string) array_key_first($action), $result['actions']);
	}

	public function testReactionCallbackRefreshesOnlyTheBar(): void
	{
		$result = $this->runCallback($this->viewFactory(), function (BEForumThreadView $view): TActiveLinkButton {
			$buttons = $this->replyView($view)->Reactions->Buttons->getItems()[0]->findControlsByType(TActiveLinkButton::class);
			return reset($buttons);
		});
		self::assertNull($result['redirect'], 'no page reload');
		self::assertContains('Prado.Element.replace', $this->functions($result));
		$panelId = $this->replyView($result['control'])->Reactions->Panel->getClientID();
		$replaced = array_filter($result['actions'], fn (array $action) => isset($action['Prado.Element.replace']) && $action['Prado.Element.replace'][0] === $panelId);
		self::assertCount(1, $replaced, 'the reaction bar of the reply is replaced');
		self::assertStringContainsString('beforum-reaction--active', $result['content'], 'the fresh bar shows the active reaction');
		self::assertStringContainsString('beforum-reaction-count">1<', $result['content']);
		self::assertStringNotContainsString('PRADO_PAGESTATE', $result['content'], 'only the bar is rendered');
		$summary = $this->forum->getReactions()->getSummary($this->forum->getPosts()->getPost($this->reply->getId()));
		self::assertSame(1, array_sum($summary));

		// the same button again removes the reaction
		$result = $this->runCallback($this->viewFactory(), function (BEForumThreadView $view): TActiveLinkButton {
			$buttons = $this->replyView($view)->Reactions->Buttons->getItems()[0]->findControlsByType(TActiveLinkButton::class);
			return reset($buttons);
		});
		self::assertStringNotContainsString('beforum-reaction--active', $result['content']);
		self::assertSame(0, array_sum($this->forum->getReactions()->getSummary($this->forum->getPosts()->getPost($this->reply->getId()))));
	}

	public function testSubscribeCallbackTogglesInPlace(): void
	{
		$subscriptions = $this->forum->getSubscriptions();
		self::assertTrue($subscriptions->isSubscribed(BEForumSubscription::TYPE_THREAD, $this->thread->getId()), 'the author starts subscribed');
		$result = $this->runCallback($this->viewFactory(), fn (BEForumThreadView $view) => $view->Subscribe->Toggle);
		self::assertNull($result['redirect']);
		self::assertFalse($subscriptions->isSubscribed(BEForumSubscription::TYPE_THREAD, $this->thread->getId()));
		self::assertStringContainsString('>Subscribe<', $result['content'], 'the refreshed button offers to subscribe again');
		self::assertStringContainsString('beforum-button--secondary', $result['content']);
		$result = $this->runCallback($this->viewFactory(), fn (BEForumThreadView $view) => $view->Subscribe->Toggle);
		self::assertTrue($subscriptions->isSubscribed(BEForumSubscription::TYPE_THREAD, $this->thread->getId()));
		self::assertStringContainsString('>Unsubscribe<', $result['content']);
		self::assertStringContainsString('beforum-button--active', $result['content']);
	}

	public function testBookmarkCallbackUpdatesTheButton(): void
	{
		$bookmarks = $this->forum->getBookmarks();
		$result = $this->runCallback($this->viewFactory(), fn (BEForumThreadView $view) => $this->replyView($view)->Bookmark);
		self::assertNull($result['redirect']);
		self::assertTrue($bookmarks->isBookmarked($this->forum->getPosts()->getPost($this->reply->getId())));
		$buttonId = $this->replyView($result['control'])->Bookmark->getClientID();
		$updates = array_filter($result['actions'], fn (array $action) => isset($action['Prado.Element.j']) && $action['Prado.Element.j'][0] === $buttonId && $action['Prado.Element.j'][1] === 'html');
		self::assertCount(1, $updates, 'the button text is updated in place');
		self::assertSame(['Remove bookmark'], reset($updates)['Prado.Element.j'][2]);
		self::assertStringNotContainsString('class="beforum-', $result['content'], 'no control is re-rendered, only the headers are sent');
		self::assertStringContainsString('<!--X-PRADO-PAGESTATE-->', $result['content'], 'the page state travels back');
		self::assertNotContains('Prado.Element.replace', $this->functions($result));
		$restyled = array_filter($result['actions'], fn (array $action) => isset($action['Prado.Element.setAttribute']) && str_contains($action['Prado.Element.setAttribute'][0], 'Reactions'));
		self::assertCount(0, $restyled, 'untouched reaction bars are not re-bound during a callback');

		$result = $this->runCallback($this->viewFactory(), fn (BEForumThreadView $view) => $this->replyView($view)->Bookmark);
		self::assertFalse($bookmarks->isBookmarked($this->forum->getPosts()->getPost($this->reply->getId())));
		$updates = array_filter($result['actions'], fn (array $action) => isset($action['Prado.Element.j']) && $action['Prado.Element.j'][1] === 'html');
		self::assertSame(['Bookmark'], reset($updates)['Prado.Element.j'][2]);

		// an error (the post vanished after the page was shown) is displayed in the row's notice panel
		$result = $this->runCallback($this->viewFactory(), fn (BEForumThreadView $view) => $this->replyView($view)->Bookmark, [], '', function (): void {
			$this->loginAs('mod');
			$this->forum->getPosts()->deletePost($this->forum->getPosts()->getPost($this->reply->getId()));
			$this->loginAs('alice');
		});
		self::assertNull($result['redirect']);
		self::assertContains('Prado.Element.replace', $this->functions($result));
		self::assertStringContainsString('was not found', $result['content']);
		self::assertStringContainsString('beforum-error', $result['content']);
	}

	public function testPostbackStillRendersTheWholePage(): void
	{
		$view = $this->viewFactory()();
		$this->render($view);
		$bar = $this->replyView($view)->Reactions;
		self::assertNull($this->invoke($bar, fn ($b) => $b->reactionCommand(null, $this->command('like'))), 'a plain postback does not redirect');
		self::assertFalse($view->getIsCallback());
		self::assertSame(1, array_sum($this->forum->getReactions()->getSummary($this->forum->getPosts()->getPost($this->reply->getId()))));
		$html = $this->render($this->viewFactory()());
		self::assertStringContainsString('beforum-reaction--active', $html);
		self::assertStringContainsString('beforum-post-notice', $html);
		self::assertStringContainsString('beforum-subscribe', $html);
	}
}
