<?php

use Belisoful\Forum\Util\BEForumPagination;
use PHPUnit\Framework\TestCase;

class BEForumPaginationTest extends TestCase
{
	public function testDerivedValues(): void
	{
		$p = new BEForumPagination(2, 20, 95);
		self::assertSame(2, $p->getPage());
		self::assertSame(20, $p->getPageSize());
		self::assertSame(95, $p->getItemCount());
		self::assertSame(5, $p->getPageCount());
		self::assertSame(20, $p->getOffset());
		self::assertTrue($p->getHasNext());
		self::assertTrue($p->getHasPrevious());
		self::assertSame(21, $p->getFirstItem());
		self::assertSame(40, $p->getLastItem());
		self::assertSame(3, $p->getPageOfItem(45));
		self::assertSame(1, $p->getPageOfItem(-3));
	}

	public function testClamping(): void
	{
		$p = new BEForumPagination(99, 10, 25);
		self::assertSame(3, $p->getPage());
		self::assertFalse($p->getHasNext());
		$p = new BEForumPagination(-5, 0, -1);
		self::assertSame(1, $p->getPage());
		self::assertSame(1, $p->getPageSize());
		self::assertSame(0, $p->getItemCount());
		self::assertSame(1, $p->getPageCount());
		self::assertSame(0, $p->getFirstItem());
		self::assertSame(0, $p->getLastItem());
		self::assertFalse($p->getHasPrevious());
	}

	public function testPageNumbers(): void
	{
		self::assertSame([1, 2, 3], (new BEForumPagination(1, 10, 25))->getPageNumbers());
		$numbers = (new BEForumPagination(10, 10, 200))->getPageNumbers(7);
		self::assertSame(1, $numbers[0]);
		self::assertSame(20, end($numbers));
		self::assertContains(10, $numbers);
		self::assertCount(7, $numbers);
		self::assertSame([1, 2, 3, 4, 5, 6, 20], (new BEForumPagination(1, 10, 200))->getPageNumbers(7));
		self::assertSame([1, 15, 16, 17, 18, 19, 20], (new BEForumPagination(20, 10, 200))->getPageNumbers(7));
		self::assertSame([1, 2, 20], (new BEForumPagination(1, 10, 200))->getPageNumbers(1));
	}
}
