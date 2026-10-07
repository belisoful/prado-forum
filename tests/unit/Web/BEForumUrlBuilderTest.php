<?php

use Belisoful\Forum\Data\BEForumPost;
use Belisoful\Forum\Web\BEForumUrlBuilder;

class BEForumUrlBuilderTest extends BEForumTestCase
{
	public function testUrls(): void
	{
		$urls = $this->forum->getUrls();
		self::assertSame($urls, $this->forum->getUrls());
		self::assertSame('Forum.Index', $urls->getPagePath('index'));
		self::assertSame('Forum.Admin.Structure', $urls->getPagePath('adminStructure'));
		self::assertStringContainsString('page=Forum.Index', $urls->index());
		self::assertStringContainsString('page=Forum.Board', $urls->board(3));
		self::assertStringContainsString('board=3', $urls->board(3));
		self::assertStringNotContainsString('pg=2', $urls->board(3, 1));
		self::assertStringContainsString('pg=2', $urls->board(3, 2));
		self::assertSame(1, substr_count($urls->board(3, 2), 'page='), 'the pagination parameter does not collide with the page service parameter');
		self::assertStringContainsString('thread=7', $urls->thread(7));
		self::assertStringEndsWith('#post-9', $urls->thread(7, 1, 9));
		self::assertStringContainsString('post=9', $urls->postById(9));
		self::assertStringContainsString('q=hello+world', $urls->search('hello world'));
		self::assertStringContainsString('type=threads', $urls->search('x', null, 1, 'threads'));
		self::assertStringContainsString('tag=php', $urls->tag('php'));
		self::assertStringContainsString('member=alice', $urls->member('alice'));
		self::assertStringContainsString('attachment=4', $urls->attachment(4));
		foreach (['members', 'notifications', 'subscriptions', 'bookmarks', 'adminStructure', 'adminModeration', 'adminMembers'] as $role) {
			self::assertStringContainsString('page=' . $urls->getPagePath($role), $urls->$role());
		}
		self::assertStringContainsString('board=5', $urls->newThread(5));
		self::assertStringContainsString('post=6', $urls->editPost(6));
	}

	public function testRecordsPrefixAndAbsolute(): void
	{
		$board = $this->createBoard();
		$thread = $this->createThreadAs($board, 'alice');
		$this->replyAs($thread, 'bob');
		$urls = $this->forum->getUrls();
		self::assertStringContainsString('board=' . $board->getId(), $urls->board($board));
		$reply = BEForumPost::finder()->find('thread_id = ? AND position = 2', [$thread->getId()]);
		self::assertStringEndsWith('#post-' . $reply->getId(), $urls->post($reply));
		$this->forum->setPagePathPrefix('.Community.');
		self::assertSame('Community.Thread', $urls->getPagePath('thread'));
		$this->forum->setPagePathPrefix('');
		self::assertSame('Thread', $urls->getPagePath('thread'));
		$urls->setPageName('thread', 'Topic');
		self::assertSame('Topic', $urls->getPagePath('thread'));
		self::assertSame('Topic', $urls->getPageNames()['thread']);
		self::assertSame('https://x.y/z', $urls->absolute('https://x.y/z'));
		self::assertStringStartsWith('http', $urls->absolute('/relative'));
		self::assertStringEndsWith('/relative', $urls->absolute('relative'));
	}

	public function testDynamicUrlFilter(): void
	{
		$urls = $this->forum->getUrls();
		$urls->attachBehavior('rewrite', new class () extends \Prado\Util\TBehavior {
			public function dyBuildUrl($url, $pagePath, $params, $anchor, $chain)
			{
				return $chain->dyBuildUrl('/pretty/' . strtolower(str_replace('.', '/', $pagePath)) . '/' . ($params[BEForumUrlBuilder::PARAM_BOARD] ?? ''), $pagePath, $params, $anchor);
			}
		});
		self::assertSame('/pretty/forum/board/3', $urls->board(3));
		self::assertSame('/pretty/forum/index/', $urls->index());
	}
}
