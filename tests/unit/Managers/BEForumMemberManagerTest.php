<?php

use Belisoful\Forum\Data\BEForumMember;
use Belisoful\Forum\Exceptions\BEForumForbiddenException;
use Belisoful\Forum\Exceptions\BEForumNotFoundException;
use Belisoful\Forum\Exceptions\BEForumValidationException;
use Belisoful\Forum\Managers\BEForumMemberManager;
use Belisoful\Forum\Util\BEForumTime;

class BEForumMemberManagerTest extends BEForumTestCase
{
	public function testLookupAndCreation(): void
	{
		$members = $this->forum->getMembers();
		self::assertNull($members->getCurrentMember());
		self::assertNull($members->findByUsername(''));
		self::assertNull($members->findByUsername('nobody'));
		self::assertNull($members->findById(0));
		$user = $this->loginAs('Alice');
		$member = $members->getCurrentMember();
		self::assertSame('Alice', $member->username);
		self::assertSame($member->getId(), $members->findByUsername('alice')->getId(), 'lookups are case insensitive');
		self::assertSame($member->getId(), $members->getMemberForUser($user)->getId());
		self::assertSame($member->getId(), $members->getMemberById($member->getId())->getId());
		self::assertSame($member->getId(), $members->getMemberByUsername('ALICE')->getId());
		self::assertSame(1, $members->countMembers());
		$ensured = $members->ensureMember('Alice');
		self::assertSame($member->getId(), $ensured->getId());
		$bob = $members->ensureMember(' bob ');
		self::assertSame('bob', $bob->username);
		self::assertSame([$member->getId(), $bob->getId()], array_keys($members->getMembersByIds([$member->getId(), $bob->getId(), 0, 999])));
		self::assertSame([], $members->getMembersByIds([]));
		try {
			$members->ensureMember('  ');
			self::fail('empty usernames are refused');
		} catch (BEForumValidationException $e) {
		}
		try {
			$members->getMemberById(999);
			self::fail('missing member');
		} catch (BEForumNotFoundException $e) {
		}
		try {
			$members->getMemberByUsername('zed');
			self::fail('missing member');
		} catch (BEForumNotFoundException $e) {
		}
		self::assertSame(['bob', 'Alice'], array_map(fn ($m) => $m->username, $members->getNewestMembers(5)));
		self::assertCount(1, $members->getOnlineMembers(15), 'alice was seen when logging in');
	}

	public function testPresenceThrottle(): void
	{
		$members = $this->forum->getMembers();
		BEForumTime::freeze(1_700_000_000);
		$member = $members->ensureMember('p');
		self::assertTrue($members->touchPresence($member));
		self::assertFalse($members->touchPresence($member));
		BEForumTime::freeze(1_700_000_000 + BEForumMemberManager::PRESENCE_THROTTLE + 1);
		self::assertTrue($members->touchPresence($member));
		self::assertFalse($members->touchPresence(new BEForumMember()));
	}

	public function testProfileUpdates(): void
	{
		$members = $this->forum->getMembers();
		$this->loginAs('alice');
		$alice = $members->getCurrentMember();
		$updated = $members->updateProfile($alice, [
			'display_name' => ' Alice A. ',
			'email' => 'alice@example.com',
			'website' => 'https://alice.example',
			'location' => 'Earth',
			'timezone' => 'Europe/Berlin',
			'bio' => 'Hi',
			'signature' => '',
			'ignored' => 'x',
		]);
		self::assertSame('Alice A.', $updated->display_name);
		self::assertSame('alice@example.com', $updated->email);
		self::assertSame('Europe/Berlin', $updated->timezone);
		self::assertNull($updated->signature);
		foreach ([
			['email', 'not-an-email', 'forum_email_invalid'],
			['website', 'ftp://x', 'forum_url_invalid'],
			['avatar_url', 'javascript:alert(1)', 'forum_url_invalid'],
			['timezone', 'Nowhere/City', 'forum_timezone_invalid'],
			['display_name', str_repeat('x', 121), 'forum_field_too_long'],
			['location', str_repeat('x', 121), 'forum_field_too_long'],
			['bio', str_repeat('x', 2001), 'forum_field_too_long'],
		] as [$field, $value, $code]) {
			try {
				$members->updateProfile($alice, [$field => $value]);
				self::fail($field . ' must be validated');
			} catch (BEForumValidationException $e) {
				self::assertSame($field, $e->getField());
				self::assertSame($code, $e->getErrorCode());
			}
		}
		$members->setSetting($alice, 'notify_reply', false);
		self::assertFalse($members->getMemberById($alice->getId())->getSetting('notify_reply'));
		$this->loginAs('bob');
		try {
			$members->updateProfile($alice, ['display_name' => 'Hacked']);
			self::fail('members cannot edit other profiles');
		} catch (BEForumForbiddenException $e) {
		}
		$this->loginAs('admin');
		self::assertSame('By admin', $members->updateProfile($alice, ['display_name' => 'By admin'])->display_name);
	}

	public function testBansWarningsAndReputation(): void
	{
		$members = $this->forum->getMembers();
		$bob = $members->ensureMember('bob');
		$this->loginAs('alice');
		try {
			$members->ban($bob);
			self::fail('members cannot ban');
		} catch (BEForumForbiddenException $e) {
		}
		$this->loginAs('mod');
		$mod = $members->getCurrentMember();
		try {
			$members->ban($mod);
			self::fail('cannot ban yourself');
		} catch (BEForumValidationException $e) {
			self::assertSame('forum_ban_self', $e->getErrorCode());
		}
		try {
			$members->ban($bob, 'never');
			self::fail('invalid until');
		} catch (BEForumValidationException $e) {
			self::assertSame('until', $e->getField());
		}
		$members->ban($bob, null, str_repeat('r', 600));
		self::assertTrue($bob->getIsBanned());
		self::assertFalse($bob->getIsTemporaryBan());
		self::assertSame(500, mb_strlen((string) $bob->ban_reason));
		self::assertSame(0, $members->expireBans(), 'permanent bans do not expire');
		$members->unban($bob);
		self::assertFalse($bob->getIsBanned());
		$members->ban($bob, '+1 hour', 'temp');
		self::assertTrue($members->getMemberById($bob->getId())->getIsTemporaryBan());
		BEForumTime::freeze(BEForumTime::timestamp() + 7200);
		self::assertSame(1, $members->expireBans());
		self::assertFalse($members->getMemberById($bob->getId())->getIsBanned());
		self::assertNotNull(BEForumMember::finder()->find('username = ?', ['bob']));
		try {
			$members->warn($bob, '');
			self::fail('warnings need a reason');
		} catch (BEForumValidationException $e) {
		}
		$members->warn($bob, 'be nice');
		$members->warn($bob, 'be nicer');
		self::assertSame(2, (int) $members->getMemberById($bob->getId())->warning_count);
		[$log] = $this->forum->getModeration()->listLog(1, ['action' => 'warn', 'target_id' => $bob->getId()]);
		self::assertCount(2, $log);
		$this->loginAs('bob');
		self::assertSame(4, $this->forum->getNotifications()->countUnread(), 'both bans and both warnings notify the member');
		$members->adjustReputation($bob, 7);
		$members->adjustReputation($bob, 0);
		self::assertSame(7, (int) $members->getMemberById($bob->getId())->reputation);
		$members->adjustCounters(null, 1);
		$members->adjustCounters($bob->getId(), 2, 1, BEForumTime::now());
		$fresh = $members->getMemberById($bob->getId());
		self::assertSame(2, (int) $fresh->post_count);
		self::assertSame(1, (int) $fresh->thread_count);
		self::assertNotNull($fresh->last_post_at);
	}

	public function testListing(): void
	{
		$members = $this->forum->getMembers();
		foreach (['carol', 'alice', 'bob'] as $name) {
			$members->ensureMember($name);
		}
		$alice = $members->findByUsername('alice');
		$alice->reputation = 10;
		$alice->post_count = 3;
		$alice->display_name = 'Zed';
		$alice->save();
		[$page, $pagination] = $members->listMembers(1, null, 'username', 2);
		self::assertSame(['alice', 'bob'], array_map(fn ($m) => $m->username, $page));
		self::assertSame(2, $pagination->getPageCount());
		[$page] = $members->listMembers(2, null, 'username', 2);
		self::assertSame(['carol'], array_map(fn ($m) => $m->username, $page));
		[$page] = $members->listMembers(1, null, 'reputation');
		self::assertSame('alice', $page[0]->username);
		[$page] = $members->listMembers(1, null, 'posts');
		self::assertSame('alice', $page[0]->username);
		[$page] = $members->listMembers(1, null, 'newest');
		self::assertSame('bob', $page[0]->username);
		[$page] = $members->listMembers(1, 'zed');
		self::assertSame(['alice'], array_map(fn ($m) => $m->username, $page), 'display names are searched');
		[$page] = $members->listMembers(1, '%');
		self::assertCount(0, $page, 'wildcards are escaped');
	}

	public function testBoardModeratorsAndBadges(): void
	{
		$members = $this->forum->getMembers();
		$board = $this->createBoard();
		$carol = $members->ensureMember('carol');
		self::assertSame([], $members->getBoardModeratorUsernames(0));
		self::assertSame([], $members->getBoardModeratorUsernames($board->getId()));
		$this->loginAs('mod');
		try {
			$members->addBoardModerator($board->getId(), $carol);
			self::fail('only administrators assign moderators');
		} catch (BEForumForbiddenException $e) {
		}
		$this->loginAs('admin');
		$assignment = $members->addBoardModerator($board->getId(), $carol);
		self::assertSame($assignment->getId(), $members->addBoardModerator($board->getId(), $carol)->getId(), 'idempotent');
		self::assertSame(['carol'], $members->getBoardModeratorUsernames($board->getId()));
		self::assertSame([$board->getId()], $members->getModeratedBoardIds($carol));
		self::assertSame(['carol'], array_map(fn ($m) => $m->username, $members->getBoardModerators($board->getId())));
		try {
			$members->addBoardModerator(999, $carol);
			self::fail('board must exist');
		} catch (BEForumNotFoundException $e) {
		}
		$this->loginAs('carol');
		self::assertTrue($this->forum->getModeration()->isModerator($board), 'board moderators moderate their board');
		self::assertFalse($this->forum->getModeration()->isModerator(), 'but not globally');
		$this->loginAs('admin');
		self::assertTrue($members->removeBoardModerator($board->getId(), $carol));
		self::assertFalse($members->removeBoardModerator($board->getId(), $carol));

		$badge = $members->defineBadge('Helper', 'Helps a lot', 'star');
		self::assertSame('helper', $badge->slug);
		self::assertSame($badge->getId(), $members->defineBadge('Helper', 'Updated')->getId());
		self::assertSame('Updated', $members->findBadge('helper')->description);
		self::assertNull($members->findBadge('nope'));
		self::assertCount(1, $members->getBadges());
		$this->loginAs('mod');
		$award = $members->awardBadge($carol, $badge);
		self::assertSame($award->getId(), $members->awardBadge($carol, $badge)->getId());
		self::assertSame(['helper'], array_map(fn ($b) => $b->slug, $members->getMemberBadges($carol)));
		self::assertTrue($members->revokeBadge($carol, $badge));
		self::assertFalse($members->revokeBadge($carol, $badge));
		self::assertSame([], $members->getMemberBadges($carol));
		$this->loginAs('alice');
		try {
			$members->defineBadge('X');
			self::fail('badges are defined by administrators');
		} catch (BEForumForbiddenException $e) {
		}
		$members->awardBadge($carol, $badge, false);
		self::assertCount(1, $members->getMemberBadges($carol), 'automatic awards skip authorization');
	}
}
