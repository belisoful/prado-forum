<?php

use Belisoful\Forum\Exceptions\BEForumConfigurationException;
use Belisoful\Forum\Exceptions\BEForumException;
use Belisoful\Forum\Exceptions\BEForumFloodException;
use Belisoful\Forum\Exceptions\BEForumForbiddenException;
use Belisoful\Forum\Exceptions\BEForumNotFoundException;
use Belisoful\Forum\Exceptions\BEForumValidationException;
use PHPUnit\Framework\TestCase;

class BEForumExceptionsTest extends TestCase
{
	public function testMessagesResolveFromTheForumFile(): void
	{
		self::assertFileExists(BEForumException::getForumMessageFile());
		$e = new BEForumException('forum_board_not_found', 12);
		self::assertSame('forum_board_not_found', $e->getErrorCode());
		self::assertSame('Board #12 was not found.', $e->getMessage());
		$e = new BEForumNotFoundException('forum_thread_not_found', 7);
		self::assertSame(404, $e->getStatusCode());
		self::assertSame('Thread #7 was not found.', $e->getMessage());
		$e = new BEForumForbiddenException('forum_admin');
		self::assertSame(403, $e->getStatusCode());
		self::assertSame('forum_admin', $e->getPermission());
		self::assertSame('You do not have permission to do this (forum_admin).', $e->getMessage());
		$e = new BEForumForbiddenException('forum_view', 'forum_login_required');
		self::assertSame('Please log in to continue.', $e->getMessage());
		$e = new BEForumValidationException('title', 'forum_thread_title_too_long', 'title', 255, 300);
		self::assertSame('title', $e->getField());
		self::assertSame('The title must not exceed 255 characters (300 given).', $e->getMessage());
		$e = new BEForumFloodException(0);
		self::assertSame(1, $e->getRetryAfter());
		self::assertSame('', $e->getField());
		self::assertStringContainsString('1 second', $e->getMessage());
		$e = new BEForumConfigurationException('forum_schema_driver_unsupported', 'oracle');
		self::assertStringContainsString('oracle', $e->getMessage());
		$e = new BEForumException('forum_unknown_key');
		self::assertSame('forum_unknown_key', $e->getMessage(), 'unknown keys are returned verbatim');
	}

	public function testEveryUsedKeyHasAMessage(): void
	{
		$messages = [];
		foreach (file(BEForumException::getForumMessageFile()) as $line) {
			if (preg_match('/^(forum_[a-z0-9_]+)\s*=/', $line, $m)) {
				$messages[$m[1]] = true;
			}
		}
		$missing = [];
		$directory = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 3) . '/src'));
		foreach ($directory as $file) {
			if ($file->getExtension() !== 'php') {
				continue;
			}
			$source = file_get_contents($file->getPathname());
			if (preg_match_all("/new BEForum\\w*Exception\\(\\s*(?:'[^']*',\\s*)?'(forum_[a-z0-9_]+)'/", $source, $matches)) {
				foreach ($matches[1] as $key) {
					if (!isset($messages[$key])) {
						$missing[$key] = basename($file->getPathname());
					}
				}
			}
			if (preg_match_all("/TInvalidDataValueException\\('(forum_[a-z0-9_]+)'/", $source, $matches)) {
				foreach ($matches[1] as $key) {
					if (!isset($messages[$key])) {
						$missing[$key] = basename($file->getPathname());
					}
				}
			}
		}
		self::assertSame([], $missing, 'every error key used in src/ must be defined in errorMessages.txt');
	}
}
