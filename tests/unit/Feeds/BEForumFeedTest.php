<?php

use Belisoful\Forum\Exceptions\BEForumConfigurationException;
use Belisoful\Forum\Feeds\BEForumFeed;
use Belisoful\Forum\Feeds\BEForumJsonResponse;

class BEForumFeedTest extends BEForumTestCase
{
	private $board;
	private $thread;

	protected function setUp(): void
	{
		parent::setUp();
		$this->board = $this->createBoard('News');
		$this->thread = $this->createThreadAs($this->board, 'alice', 'First & <b>bold</b>', 'Body **strong**');
		$this->replyAs($this->thread, 'bob', 'A reply');
		$this->logout();
	}

	private function feed(): BEForumFeed
	{
		$feed = new BEForumFeed();
		$feed->setForumModule($this->forum);
		$feed->init(null);
		return $feed;
	}

	public function testRssThreads(): void
	{
		$feed = $this->feed();
		self::assertSame('application/rss+xml', $feed->getContentType());
		$xml = $feed->getFeedContent();
		self::assertStringStartsWith('<?xml version="1.0"', $xml);
		self::assertStringContainsString('<rss version="2.0"', $xml);
		self::assertStringContainsString('First &amp; &lt;b&gt;bold&lt;/b&gt;', $xml);
		self::assertStringContainsString('&lt;strong&gt;strong&lt;/strong&gt;', $xml);
		self::assertStringContainsString('thread-' . $this->thread->getId(), $xml);
		self::assertStringContainsString('<dc:creator', $xml);
		$document = new DOMDocument();
		self::assertTrue($document->loadXML($xml));
		self::assertSame(1, $document->getElementsByTagName('item')->length);
	}

	public function testAtomPostsAndFilters(): void
	{
		$feed = $this->feed();
		$feed->setFormat('ATOM');
		$feed->setScope('posts');
		$feed->setLimit(500);
		self::assertSame(100, $feed->getLimit());
		self::assertSame('atom', $feed->getFormat());
		self::assertSame('application/atom+xml', $feed->getContentType());
		$xml = $feed->getFeedContent();
		self::assertStringContainsString('<feed xmlns="http://www.w3.org/2005/Atom">', $xml);
		self::assertSame(2, count($feed->getItems()));
		self::assertStringContainsString('(reply)', $xml);
		$document = new DOMDocument();
		self::assertTrue($document->loadXML($xml));
		self::assertSame(2, $document->getElementsByTagName('entry')->length);
		$this->getApp()->getRequest()->add('thread', (string) $this->thread->getId());
		self::assertSame(2, count($feed->getItems()), 'thread scope lists its posts');
		$this->getApp()->getRequest()->clear();
		$this->getApp()->getRequest()->add('board', '999');
		$feed->setScope('threads');
		self::assertSame(0, count($feed->getItems()), 'unknown boards have no threads');
		$this->getApp()->getRequest()->clear();
		$feed->setModuleID('nope');
		self::assertSame('nope', $feed->getModuleID());
		$fresh = new BEForumFeed();
		$fresh->setModuleID($this->moduleId);
		self::assertSame($this->forum, $fresh->getForumModule());
		$fresh = new BEForumFeed();
		self::assertInstanceOf(BEForumFeed::class, $fresh);
		self::assertInstanceOf(\Belisoful\Forum\BEForumModule::class, $fresh->getForumModule(), 'a forum module is found without an id');
		try {
			$feed->setFormat('json');
			self::fail('invalid format');
		} catch (BEForumConfigurationException $e) {
		}
		try {
			$feed->setScope('members');
			self::fail('invalid scope');
		} catch (BEForumConfigurationException $e) {
		}
	}

	public function testJsonResponse(): void
	{
		$json = new BEForumJsonResponse();
		$json->setForumModule($this->forum);
		$json->setModuleID($this->moduleId);
		self::assertSame($this->moduleId, $json->getModuleID());
		$request = $this->getApp()->getRequest();
		$request->clear();
		$content = $json->getJsonContent();
		self::assertSame(1, $content['stats']['threads']);
		$request->add('action', 'threads');
		$content = $json->getJsonContent();
		self::assertSame('First & <b>bold</b>', $content['threads'][0]['title']);
		self::assertStringContainsString('thread=', $content['threads'][0]['url']);
		$request->add('board', (string) $this->board->getId());
		$content = $json->getJsonContent();
		self::assertSame('News', $content['board']['name']);
		self::assertSame(1, $content['total']);
		$request->clear();
		$request->add('action', 'posts');
		$request->add('thread', (string) $this->thread->getId());
		$content = $json->getJsonContent();
		self::assertCount(2, $content['posts']);
		self::assertSame(2, $content['posts'][1]['position']);
		$request->clear();
		$request->add('action', 'search');
		$request->add('q', 'reply');
		$content = $json->getJsonContent();
		self::assertCount(1, $content['posts']);
		$request->add('q', 'x');
		self::assertArrayHasKey('error', $json->getJsonContent());
		$request->clear();
		$request->add('action', 'tags');
		self::assertSame([], $json->getJsonContent()['tags']);
		$request->clear();
		$request->add('action', 'posts');
		$request->add('thread', '999');
		$content = $json->getJsonContent();
		self::assertSame(404, $content['status']);
		$request->clear();
		$fresh = new BEForumJsonResponse();
		self::assertInstanceOf(\Belisoful\Forum\BEForumModule::class, $fresh->getForumModule());
	}

	public function testThreadFeedListsTheNewestPosts(): void
	{
		for ($i = 1; $i <= 4; $i++) {
			$this->replyAs($this->thread, 'bob', 'reply number ' . $i);
		}
		$this->logout();
		$feed = $this->feed();
		$feed->setScope('posts');
		$feed->setLimit(2);
		$this->getApp()->getRequest()->add('thread', (string) $this->thread->getId());
		$items = $feed->getItems();
		$this->getApp()->getRequest()->clear();
		self::assertCount(2, $items);
		$posts = $this->forum->getPosts();
		$newest = $posts->listPosts($this->thread, 3, 2)[0];
		self::assertSame('post-' . $newest[1]->getId(), $items[0]['id'], 'the newest post comes first');
		self::assertSame('post-' . $newest[0]->getId(), $items[1]['id']);
	}
}
