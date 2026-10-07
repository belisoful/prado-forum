<?php

use Belisoful\Forum\Exceptions\BEForumForbiddenException;
use Belisoful\Forum\Exceptions\BEForumNotFoundException;
use Belisoful\Forum\Exceptions\BEForumValidationException;

class BEForumBoardManagerTest extends BEForumTestCase
{
	public function testCategories(): void
	{
		$boards = $this->forum->getBoards();
		$this->loginAs('alice');
		try {
			$boards->createCategory('Nope');
			self::fail('only administrators create categories');
		} catch (BEForumForbiddenException $e) {
		}
		$this->loginAs('admin');
		$first = $boards->createCategory('  First  ', ' desc ', null, false);
		$second = $boards->createCategory('First', '', null, true);
		self::assertSame('First', $first->name);
		self::assertSame('desc', $first->description);
		self::assertSame('first', $first->slug);
		self::assertSame('first-2', $second->slug);
		self::assertSame(1, (int) $first->position);
		self::assertSame(2, (int) $second->position);
		self::assertTrue($second->getIsHidden());
		self::assertCount(1, $boards->getCategories());
		self::assertCount(2, $boards->getCategories(true));
		self::assertCount(2, $boards->getVisibleCategories(), 'admins see hidden categories');
		$this->loginAs('alice');
		self::assertCount(1, $boards->getVisibleCategories());
		$this->loginAs('admin');
		$boards->updateCategory($first, ['name' => 'Renamed', 'slug' => 'Custom Slug', 'description' => '', 'position' => 5, 'is_hidden' => true]);
		self::assertSame('Renamed', $first->name);
		self::assertSame('custom-slug', $first->slug);
		self::assertNull($first->description);
		self::assertSame(5, (int) $first->position);
		self::assertTrue($first->getIsHidden());
		self::assertSame($first->getId(), $boards->findCategoryBySlug('custom-slug')->getId());
		self::assertNull($boards->findCategoryBySlug('missing'));
		try {
			$boards->updateCategory($second, ['slug' => 'custom-slug']);
			self::fail('duplicate slugs are refused');
		} catch (BEForumValidationException $e) {
			self::assertSame('slug', $e->getField());
		}
		$boards->reorderCategories([$second->getId(), $first->getId()]);
		self::assertSame([$second->getId(), $first->getId()], array_map(fn ($c) => $c->getId(), $boards->getCategories(true)));
		$board = $boards->createBoard($first, 'B');
		try {
			$boards->deleteCategory($first);
			self::fail('categories with boards cannot be deleted');
		} catch (BEForumValidationException $e) {
		}
		$boards->deleteBoard($board);
		$boards->deleteCategory($first);
		try {
			$boards->getCategory($first->getId());
			self::fail('deleted');
		} catch (BEForumNotFoundException $e) {
		}
		try {
			$boards->createCategory('');
			self::fail('name required');
		} catch (BEForumValidationException $e) {
			self::assertSame('name', $e->getField());
		}
	}

	public function testBoardsHierarchyAndVisibility(): void
	{
		$boards = $this->forum->getBoards();
		$this->loginAs('admin');
		$category = $boards->createCategory('Cat');
		$parent = $boards->createBoard($category, 'Parent', 'p');
		$child = $boards->createBoard($category, 'Child', null, ['parent_id' => $parent->getId(), 'is_private' => true]);
		$hidden = $boards->createBoard($category, 'Hidden', null, ['is_hidden' => true, 'slug' => 'Custom!']);
		$locked = $boards->createBoard($category, 'Locked', null, ['is_locked' => true]);
		self::assertSame('custom', $hidden->slug);
		self::assertTrue($child->getIsSubBoard());
		self::assertTrue($child->getIsPrivate());
		self::assertSame(4, (int) $boards->getCategory($category->getId())->board_count);
		self::assertSame([$parent->getId()], array_map(fn ($b) => $b->getId(), $boards->getAncestors($child)));
		self::assertSame([$parent->getId(), $child->getId()], $boards->getDescendantIds($parent));
		self::assertCount(4, $boards->getVisibleBoards(), 'admin sees everything');
		self::assertSame([$parent->getId(), $hidden->getId(), $locked->getId()], array_map(fn ($b) => $b->getId(), $boards->getCategoryBoards($category)));
		self::assertSame([$child->getId()], array_map(fn ($b) => $b->getId(), $boards->getSubBoards($parent)));

		$this->loginAs('alice');
		self::assertCount(3, $boards->getVisibleBoards(), 'members do not see hidden boards');
		self::assertTrue($boards->canView($child));
		$this->logout();
		self::assertCount(2, $boards->getVisibleBoards(), 'guests see neither hidden nor private boards');
		self::assertFalse($boards->canView($child));
		try {
			$boards->getViewableBoard($child->getId());
			self::fail('private boards need a login');
		} catch (BEForumForbiddenException $e) {
			self::assertStringContainsString('log in', $e->getMessage());
		}
		try {
			$boards->ensureViewable($hidden);
			self::fail('hidden boards are not viewable');
		} catch (BEForumForbiddenException $e) {
		}
		try {
			$boards->getBoard(999);
			self::fail('missing');
		} catch (BEForumNotFoundException $e) {
		}
		self::assertNull($boards->findBoard(0));
		self::assertSame($locked->getId(), $boards->findBoardBySlug('locked')->getId());

		$this->loginAs('admin');
		try {
			$boards->updateBoard($parent, ['parent_id' => $child->getId()]);
			self::fail('cycles are refused');
		} catch (BEForumValidationException $e) {
			self::assertSame('parent_id', $e->getField());
		}
		$other = $boards->createCategory('Other');
		try {
			$boards->createBoard($other, 'X', null, ['parent_id' => $parent->getId()]);
			self::fail('parents must share the category');
		} catch (BEForumValidationException $e) {
		}
		$boards->updateBoard($child, ['parent_id' => 0, 'category_id' => $other->getId(), 'name' => 'Moved', 'is_private' => false, 'is_locked' => true, 'position' => 9, 'description' => 'd', 'slug' => 'moved-slug']);
		self::assertFalse($child->getIsSubBoard());
		self::assertSame($other->getId(), (int) $child->category_id);
		self::assertSame(3, (int) $boards->getCategory($category->getId())->board_count);
		self::assertSame(1, (int) $boards->getCategory($other->getId())->board_count);
		self::assertSame('moved-slug', $child->slug);
		try {
			$boards->updateBoard($child, ['slug' => 'locked']);
			self::fail('duplicate board slugs are refused');
		} catch (BEForumValidationException $e) {
		}
		$boards->reorderBoards([$locked->getId(), $hidden->getId(), $parent->getId()]);
		self::assertSame(1, (int) $boards->getBoard($locked->getId())->position);
		$sub = $boards->createBoard($category, 'Sub', null, ['parent_id' => $parent->getId()]);
		try {
			$boards->deleteBoard($parent);
			self::fail('boards with sub boards cannot be deleted');
		} catch (BEForumValidationException $e) {
		}
		$this->createThreadAs($sub, 'alice');
		try {
			$boards->deleteBoard($sub);
			self::fail('boards with threads cannot be deleted');
		} catch (BEForumValidationException $e) {
		}
	}

	public function testCountersAndLastPost(): void
	{
		$boards = $this->forum->getBoards();
		$board = $this->createBoard();
		$thread = $this->createThreadAs($board, 'alice');
		$reply = $this->replyAs($thread, 'bob');
		$fresh = $boards->getBoard($board->getId());
		self::assertSame(1, (int) $fresh->thread_count);
		self::assertSame(2, (int) $fresh->post_count);
		self::assertSame($reply->getId(), (int) $fresh->last_post_id);
		self::assertSame($thread->getId(), (int) $fresh->last_thread_id);
		$boards->adjustCounters($board->getId(), -5, -9);
		$fresh = $boards->getBoard($board->getId());
		self::assertSame(0, (int) $fresh->thread_count, 'counters never go negative');
		self::assertSame(0, (int) $fresh->post_count);
		$boards->adjustCounters($board->getId(), 0, 0);
		self::assertSame(1, $boards->recountAll());
		$fresh = $boards->getBoard($board->getId());
		self::assertSame(1, (int) $fresh->thread_count);
		self::assertSame(2, (int) $fresh->post_count);
		$this->loginAs('mod');
		$this->forum->getThreads()->deleteThread($this->forum->getThreads()->getThread($thread->getId()));
		$fresh = $boards->getBoard($board->getId());
		self::assertNull($fresh->last_post_id);
		self::assertSame(0, (int) $fresh->thread_count);
	}
}
