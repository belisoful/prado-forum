<?php

use Belisoful\Forum\Util\BEForumTime;
use PHPUnit\Framework\TestCase;

class BEForumTimeTest extends TestCase
{
	protected function tearDown(): void
	{
		BEForumTime::freeze(null);
		parent::tearDown();
	}

	public function testFreezeAndNow(): void
	{
		BEForumTime::freeze(1_700_000_000);
		self::assertSame(1_700_000_000, BEForumTime::timestamp());
		self::assertSame('2023-11-14 22:13:20', BEForumTime::now());
		self::assertSame('2023-11-14 22:14:20', BEForumTime::fromNow(60));
		BEForumTime::freeze(null);
		self::assertEqualsWithDelta(time(), BEForumTime::timestamp(), 2);
	}

	public function testParse(): void
	{
		self::assertNull(BEForumTime::parse(null));
		self::assertNull(BEForumTime::parse(''));
		self::assertSame(1_700_000_000, BEForumTime::parse('2023-11-14 22:13:20'));
		self::assertSame(1_700_000_000, BEForumTime::parse(1_700_000_000));
		self::assertSame(1_700_000_000, BEForumTime::parse('1700000000'));
		self::assertSame(1_700_000_000, BEForumTime::parse(new DateTimeImmutable('@1700000000')));
		BEForumTime::freeze(1_700_000_000);
		self::assertSame(1_700_000_000 + 86400, BEForumTime::parse('+1 day'));
		self::assertNull(BEForumTime::parse('not a date'));
	}

	public function testAgeAndRelative(): void
	{
		BEForumTime::freeze(1_700_000_000);
		self::assertSame(0, BEForumTime::age(null));
		self::assertSame(90, BEForumTime::age(BEForumTime::format(1_700_000_000 - 90)));
		self::assertSame(0, BEForumTime::age(BEForumTime::format(1_700_000_000 + 90)));
		self::assertSame('', BEForumTime::relative(null));
		self::assertSame('just now', BEForumTime::relative(BEForumTime::format(1_700_000_000 - 5)));
		self::assertSame('1 minute ago', BEForumTime::relative(BEForumTime::format(1_700_000_000 - 65)));
		self::assertSame('3 hours ago', BEForumTime::relative(BEForumTime::format(1_700_000_000 - 3 * 3600)));
		self::assertSame('2 days ago', BEForumTime::relative(BEForumTime::format(1_700_000_000 - 2 * 86400)));
		self::assertSame('in 2 weeks', BEForumTime::relative(BEForumTime::format(1_700_000_000 + 15 * 86400)));
		self::assertSame('1 year ago', BEForumTime::relative(BEForumTime::format(1_700_000_000 - 400 * 86400)));
	}

	public function testDisplay(): void
	{
		self::assertSame('', BEForumTime::display(null));
		self::assertSame('2023-11-14 22:13', BEForumTime::display('2023-11-14 22:13:20'));
		self::assertSame('2023-11-14 23:13', BEForumTime::display('2023-11-14 22:13:20', 'Y-m-d H:i', 'Europe/Berlin'));
		self::assertSame('2023-11-14 22:13', BEForumTime::display('2023-11-14 22:13:20', 'Y-m-d H:i', 'Not/AZone'));
	}
}
