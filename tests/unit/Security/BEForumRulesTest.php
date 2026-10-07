<?php

use Belisoful\Forum\Security\BEForumModeratorRule;
use Belisoful\Forum\Security\BEForumPermissions;
use Belisoful\Forum\Security\BEForumRoleRule;
use Prado\Security\TAuthorizationRule;
use Prado\Security\Permissions\TUserOwnerRule;
use Belisoful\Forum\Security\BEForumGuestUser;

class BEForumRulesTest extends BEForumTestCase
{
	public function testDefinitions(): void
	{
		$names = BEForumPermissions::getNames();
		self::assertContains(BEForumPermissions::VIEW, $names);
		self::assertContains(BEForumPermissions::SHELL, $names);
		self::assertSame('dyCreatePost', BEForumPermissions::getEvent(BEForumPermissions::POST_CREATE));
		self::assertSame('dyCreatePost', BEForumPermissions::getEvent('FORUM_POST_CREATE'));
		self::assertNull(BEForumPermissions::getEvent('nope'));
		self::assertSame([], BEForumPermissions::getPresetRules('nope'));
		$rules = BEForumPermissions::getPresetRules(BEForumPermissions::POST_EDIT);
		self::assertInstanceOf(BEForumModeratorRule::class, $rules[0]);
		self::assertInstanceOf(TUserOwnerRule::class, $rules[1]);
		self::assertNotSame($rules[0], BEForumPermissions::getPresetRules(BEForumPermissions::POST_EDIT)[0], 'rules are created fresh');
		foreach (BEForumPermissions::getDefinitions() as $name => $definition) {
			self::assertStringStartsWith('forum_', $name);
			self::assertStringStartsWith('dy', $definition['event']);
			self::assertNotEmpty($definition['description']);
			self::assertContainsOnlyInstancesOf(TAuthorizationRule::class, $definition['rules']());
		}
	}

	public function testModeratorRule(): void
	{
		$rule = new BEForumModeratorRule();
		$guest = $this->logout();
		self::assertSame(0, $rule->isUserAllowed($guest, 'get', '', ['moderators' => ['guest']]));
		$user = $this->loginAs('Alice');
		self::assertSame(0, $rule->isUserAllowed($user, 'get', '', null));
		self::assertSame(0, $rule->isUserAllowed($user, 'get', '', ['moderators' => ['bob']]));
		self::assertSame(1, $rule->isUserAllowed($user, 'get', '', ['moderators' => ['bob', 'alice']]));
		self::assertSame(1, $rule->isUserAllowed($user, 'get', '', ['moderators' => 'ALICE']));
		$deny = new BEForumModeratorRule('deny');
		self::assertSame(-1, $deny->isUserAllowed($user, 'get', '', ['moderators' => ['alice']]));
		$postOnly = new BEForumModeratorRule('allow', '*', '*', 'post');
		self::assertSame(0, $postOnly->isUserAllowed($user, 'get', '', ['moderators' => ['alice']]));
	}

	public function testRoleRule(): void
	{
		$this->forum->setAdminUsers('root');
		$this->forum->setAdminRoles('Admins');
		$this->forum->setModeratorUsers('mod');
		$this->forum->setModeratorRoles('Staff');
		$admin = new BEForumRoleRule($this->forum, BEForumRoleRule::KIND_ADMIN);
		$moderator = new BEForumRoleRule($this->forum, BEForumRoleRule::KIND_MODERATOR, 3);
		self::assertSame('admin', $admin->getKind());
		self::assertSame('moderator', $moderator->getKind());
		self::assertSame(3, $moderator->getPriority());
		self::assertSame(['root'], $admin->getConfiguredUsers());
		self::assertSame(['root', 'mod'], $moderator->getConfiguredUsers());
		self::assertSame(['Admins'], $admin->getConfiguredRoles());
		self::assertSame(['Admins', 'Staff'], $moderator->getConfiguredRoles());
		self::assertSame(0, $admin->isUserAllowed($this->logout(), 'get', ''));
		$user = $this->loginAs('nobody');
		self::assertSame(0, $admin->isUserAllowed($user, 'get', ''));
		self::assertSame(0, $moderator->isUserAllowed($user, 'get', ''));
		self::assertSame(1, $admin->isUserAllowed($this->loginAs('ROOT'), 'get', ''));
		self::assertSame(1, $moderator->isUserAllowed($this->loginAs('mod'), 'get', ''));
		self::assertSame(0, $admin->isUserAllowed($this->loginAs('mod'), 'get', ''));
		self::assertSame(1, $admin->isUserAllowed($this->loginAs('x', ['admins']), 'get', ''));
		self::assertSame(1, $moderator->isUserAllowed($this->loginAs('y', ['Staff']), 'get', ''));
		$unknown = new BEForumRoleRule($this->forum, 'weird');
		self::assertSame('admin', $unknown->getKind());
		$serialized = unserialize(serialize($admin));
		self::assertContains('root', $serialized->getConfiguredUsers(), 'without its own module the rule consults the initialized forum modules');
		self::assertContains($this->forum, $serialized->getModules());
		self::assertSame([$this->forum], $admin->getModules());
	}

	public function testGuestUser(): void
	{
		$guest = new BEForumGuestUser('Visitor');
		self::assertSame('Visitor', $guest->getName());
		self::assertTrue($guest->getIsGuest());
		self::assertNull($guest->getManager());
		self::assertSame([], $guest->getRoles());
		self::assertFalse($guest->isInRole('Admins'));
		$guest->setIsGuest(false);
		$guest->setRoles(['Admins']);
		self::assertTrue($guest->getIsGuest(), 'the guest stays a guest');
		self::assertFalse($guest->isInRole('Admins'));
		$guest->setName('Other');
		$copy = (new BEForumGuestUser())->loadFromString($guest->saveToString());
		self::assertSame('Other', $copy->getName());
		self::assertSame('Guest', (new BEForumGuestUser())->loadFromString('garbage')->getName());
		$rule = new TAuthorizationRule('allow', '?', '*');
		self::assertSame(1, $rule->isUserAllowed($guest, 'get', ''));
		$rule = new TAuthorizationRule('allow', '@', '*');
		self::assertSame(0, $rule->isUserAllowed($guest, 'get', ''));
	}
}
