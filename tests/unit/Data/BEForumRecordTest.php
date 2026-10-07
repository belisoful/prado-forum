<?php

use Belisoful\Forum\Data\BEForumBoard;
use Belisoful\Forum\Data\BEForumCategory;
use Belisoful\Forum\Data\BEForumMember;
use Belisoful\Forum\Data\BEForumNotification;
use Belisoful\Forum\Data\BEForumPoll;
use Belisoful\Forum\Data\BEForumPost;
use Belisoful\Forum\Data\BEForumRecord;
use Belisoful\Forum\Data\BEForumThread;
use Belisoful\Forum\Util\BEForumTime;
use Prado\Exceptions\TInvalidDataValueException;

class BEForumRecordTest extends BEForumTestCase
{
	public function testTableNamesAndPrefix(): void
	{
		self::assertSame('forum_threads', (new BEForumThread())->table());
		self::assertSame('forum_', BEForumRecord::getTablePrefix());
		try {
			BEForumRecord::setTablePrefix('no spaces');
			self::fail('invalid prefix must throw');
		} catch (TInvalidDataValueException $e) {
			self::assertSame('forum_', BEForumRecord::getTablePrefix());
		}
	}

	public function testConnectionProvider(): void
	{
		$calls = 0;
		BEForumRecord::setForumDbConnection(function () use (&$calls) {
			$calls++;
			return $this->db;
		});
		self::assertSame($this->db, BEForumRecord::getForumDbConnection());
		self::assertSame($this->db, BEForumRecord::getForumDbConnection());
		self::assertSame(1, $calls, 'the provider is resolved once');
		self::assertSame($this->db, (new BEForumCategory())->getDbConnection());
		BEForumRecord::setForumDbConnection(fn () => 'not a connection');
		self::assertNull(BEForumRecord::getForumDbConnection());
		BEForumRecord::setForumDbConnection($this->db);
	}

	public function testTimestampsBooleansAndJson(): void
	{
		BEForumTime::freeze(1_700_000_000);
		$category = new BEForumCategory();
		$category->slug = 'general';
		$category->name = 'General';
		$category->is_hidden = 'yes';
		self::assertTrue($category->getIsNew());
		self::assertNull($category->getId());
		self::assertTrue($category->save());
		self::assertFalse($category->getIsNew());
		self::assertSame('2023-11-14 22:13:20', $category->created_at);
		self::assertNull($category->updated_at);
		self::assertTrue($category->getIsHidden());
		BEForumTime::freeze(1_700_000_100);
		$category->is_hidden = '0';
		$category->save();
		self::assertSame('2023-11-14 22:15:00', $category->updated_at);
		$fresh = BEForumCategory::findOne($category->getId());
		self::assertFalse($fresh->getIsHidden());
		self::assertSame(0, (int) $fresh->is_hidden, 'booleans are stored as integers on sqlite');

		$member = new BEForumMember();
		$member->username = 'json';
		$member->setSetting('a', ['x' => 1]);
		$member->setSetting('b', 'two');
		$member->save();
		$fresh = BEForumMember::findOne($member->getId());
		self::assertSame(['x' => 1], $fresh->getSetting('a'));
		self::assertSame('two', $fresh->getSetting('b'));
		self::assertSame('default', $fresh->getSetting('missing', 'default'));
		$fresh->setSetting('b', null);
		self::assertSame(['a' => ['x' => 1]], $fresh->getJsonColumn('settings'));
		$fresh->settings = 'not json';
		self::assertSame([], $fresh->getJsonColumn('settings'));
		$fresh->settings = ['direct' => true];
		self::assertSame(['direct' => true], $fresh->getJsonColumn('settings'));
		$fresh->save();
		self::assertSame(['direct' => true], BEForumMember::findOne($member->getId())->getJsonColumn('settings'));
		self::assertNotNull($fresh->joined_at);
	}

	public function testFindersAndHelpers(): void
	{
		$category = new BEForumCategory(['slug' => 'c', 'name' => 'C']);
		$category->save();
		for ($i = 1; $i <= 5; $i++) {
			$board = new BEForumBoard(['category_id' => $category->getId(), 'slug' => 'b' . $i, 'name' => 'Board ' . $i, 'position' => $i]);
			$board->save();
		}
		self::assertSame(5, BEForumBoard::countWhere());
		self::assertSame(2, BEForumBoard::countWhere('position > ?', [3]));
		$page = BEForumBoard::findAllPaged(null, [], ['position' => 'desc'], 2, 2);
		self::assertSame(['Board 3', 'Board 2'], array_map(fn ($b) => $b->name, $page));
		self::assertNull(BEForumBoard::findOne(null));
		self::assertNull(BEForumBoard::findOne(0));
		self::assertNull(BEForumBoard::findOne(999999));
		$first = BEForumBoard::finder()->find('slug = ?', ['b1']);
		self::assertSame('Board 1', BEForumBoard::findOne((string) $first->getId())->name, 'ids are accepted as numeric strings');
		$criteria = BEForumBoard::criteria('position = :p', [':p' => 2], 'position asc', 1, 0);
		self::assertSame(1, $criteria->getLimit());
		self::assertSame(0, $criteria->getOffset());
		self::assertSame('Board 2', BEForumBoard::finder()->find($criteria)->name);
		self::assertSame(5, BEForumBoard::execute('UPDATE {table} SET is_locked = :on WHERE position >= :p', ['on' => true, 'p' => 1]));
		$firstId = (int) $first->getId();
		$secondId = (int) BEForumBoard::finder()->find('slug = ?', ['b2'])->getId();
		self::assertTrue(BEForumBoard::findOne($firstId)->getIsLocked());
		self::assertSame(1, BEForumBoard::execute('UPDATE {table} SET is_locked = ? WHERE id = ?', [false, $firstId]));
		self::assertFalse(BEForumBoard::findOne($firstId)->getIsLocked());
		$board = BEForumBoard::findOne($secondId);
		BEForumBoard::execute('UPDATE {table} SET name = :n WHERE id = :id', ['n' => 'Renamed', 'id' => $secondId]);
		self::assertSame('Board 2', $board->name);
		self::assertSame('Renamed', $board->refresh()->name);
		$board->delete();
		self::assertTrue($board->getIsDeletedRecord());
	}

	public function testRelationsAndAccessors(): void
	{
		$board = $this->createBoard();
		$thread = $this->createThreadAs($board, 'alice', 'Title', 'Body', ['poll' => ['question' => 'Q', 'options' => ['a', 'b']]]);
		$reply = $this->replyAs($thread, 'bob', 'Reply');
		$thread = BEForumThread::findOne($thread->getId());
		self::assertSame($board->getId(), $thread->board->getId());
		self::assertSame('alice', $thread->author->username);
		self::assertSame('alice', $thread->getAuthorName());
		self::assertCount(2, $thread->posts);
		self::assertInstanceOf(BEForumPoll::class, $thread->poll);
		self::assertCount(2, $thread->poll->options);
		self::assertSame(2, $thread->getPostCount());
		self::assertFalse($thread->getIsSolved());
		self::assertFalse($thread->getIsQuestion());
		self::assertSame([BEForumThread::TYPE_DISCUSSION, BEForumThread::TYPE_QUESTION, BEForumThread::TYPE_ANNOUNCEMENT], BEForumThread::getTypes());
		$reply = BEForumPost::findOne($reply->getId());
		self::assertSame($thread->getId(), $reply->thread->getId());
		self::assertFalse($reply->getIsFirstPost());
		self::assertFalse($reply->getIsReply());
		self::assertFalse($reply->getIsEdited());
		self::assertSame('bob', $reply->getAuthorName());
		self::assertSame('Reply', $reply->getExcerpt());
		$reply->content_html = '<p>' . str_repeat('word ', 100) . '</p>';
		self::assertSame(20, mb_strlen($reply->getExcerpt(20)));
		self::assertSame('…', mb_substr($reply->getExcerpt(20), -1));
		$guest = new BEForumPost(['guest_name' => 'Visitor']);
		self::assertSame('Visitor', $guest->getAuthorName());
		$pinned = new BEForumThread(['is_pinned' => true, 'pinned_until' => BEForumTime::fromNow(-10)]);
		self::assertFalse($pinned->getIsPinned(), 'expired pins are not pinned');
		$pinned->pinned_until = BEForumTime::fromNow(10);
		self::assertTrue($pinned->getIsPinned());
		$member = new BEForumMember(['username' => 'x', 'is_banned' => true, 'banned_until' => BEForumTime::fromNow(-1)]);
		self::assertFalse($member->getIsBanned());
		self::assertTrue($member->getIsTemporaryBan());
		$member->banned_until = null;
		self::assertTrue($member->getIsBanned());
		self::assertSame('x', $member->getDisplayName());
		$member->display_name = ' Xavier ';
		self::assertSame('Xavier', $member->getDisplayName());
		self::assertSame('', $member->getEmailHash());
		$member->email = ' Foo@Example.com ';
		self::assertSame(md5('foo@example.com'), $member->getEmailHash());
		$poll = new BEForumPoll(['closes_at' => BEForumTime::fromNow(-5), 'max_choices' => 2]);
		self::assertTrue($poll->getIsClosed());
		self::assertTrue($poll->getIsMultipleChoice());
		self::assertFalse($poll->getAllowRevote());
		$notification = new BEForumNotification();
		$notification->setJsonColumn('data', ['k' => 'v']);
		self::assertSame('v', $notification->getDetail('k'));
		self::assertNull($notification->getDetail('missing'));
		self::assertFalse($notification->getIsRead());
	}
}
