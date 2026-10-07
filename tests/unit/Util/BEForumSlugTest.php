<?php

use Belisoful\Forum\Util\BEForumSlug;
use PHPUnit\Framework\TestCase;

class BEForumSlugTest extends TestCase
{
	public function testCreateNormalizes(): void
	{
		self::assertSame('hello-world', BEForumSlug::create('Hello, World!'));
		self::assertSame('hello-world', BEForumSlug::create('  hello   world  '));
		self::assertSame('n-a', BEForumSlug::create(''));
		self::assertSame('n-a', BEForumSlug::create('!!!'));
		self::assertSame('hello-worl', BEForumSlug::create('hello world', 10));
		self::assertSame('a', BEForumSlug::create('a', 0));
	}

	public function testCreateTransliterates(): void
	{
		self::assertSame('hello-world', BEForumSlug::create('Héllo Wörld'));
		self::assertMatchesRegularExpression('/^[a-z0-9-]+$/', BEForumSlug::create('日本語 テスト'));
	}

	public function testUniqueAppendsCounter(): void
	{
		$taken = ['hello', 'hello-2'];
		self::assertSame('hello-3', BEForumSlug::unique('Hello', fn (string $slug) => in_array($slug, $taken, true)));
		self::assertSame('hello', BEForumSlug::unique('Hello', fn () => false));
		self::assertSame('hell-2', BEForumSlug::unique('hello', fn (string $slug) => $slug === 'hello', 6));
	}

	public function testUniqueNeverLeavesADoubleHyphen(): void
	{
		self::assertSame('ab-2', BEForumSlug::unique('ab-cd', fn (string $slug) => $slug === 'ab-cd', 5));
		self::assertSame('abc-10', BEForumSlug::unique('abcdef', fn (string $slug) => $slug !== 'abc-10', 6));
	}
}
