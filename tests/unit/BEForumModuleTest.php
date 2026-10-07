<?php

use Belisoful\Forum\BEForumModule;
use Belisoful\Forum\Content\BEForumContentRenderer;
use Belisoful\Forum\Data\BEForumRecord;
use Belisoful\Forum\Exceptions\BEForumConfigurationException;
use Belisoful\Forum\Exceptions\BEForumForbiddenException;
use Belisoful\Forum\Managers\BEForumManager;
use Belisoful\Forum\Managers\BEForumThreadManager;
use Belisoful\Forum\Security\BEForumPermissions;
use Belisoful\Forum\Shell\BEForumShellAction;
use Belisoful\Forum\Web\BEForumUrlBuilder;
use Prado\Exceptions\TInvalidDataValueException;
use Prado\Exceptions\TInvalidOperationException;
use Prado\Security\Permissions\TPermissionEvent;
use Prado\Security\Permissions\TPermissionsManager;
use Prado\Security\TUser;
use Prado\Util\Cron\TCronTaskInfo;

class BEForumModuleTest extends BEForumTestCase
{
	public function testInitOnceAndDefaults(): void
	{
		self::assertTrue($this->forum->getIsInitialized());
		self::assertSame('forum_', $this->forum->getTablePrefix());
		self::assertSame('Forum', $this->forum->getTitle());
		self::assertSame('Forum', $this->forum->getPagePathPrefix());
		self::assertSame(25, $this->forum->getThreadsPerPage());
		self::assertSame(20, $this->forum->getPostsPerPage());
		self::assertSame('markdown', $this->forum->getContentFormat());
		self::assertSame(['like', 'love', 'laugh', 'wow', 'sad'], $this->forum->getReactionTypes());
		self::assertSame(['admin'], $this->forum->getAdminUsers());
		self::assertSame(['mod'], $this->forum->getModeratorUsers());
		self::assertStringEndsWith('beforum-attachments', $this->forum->getAttachmentPath());
		self::assertSame($this->db, $this->forum->getDbConnection());
		self::assertSame($this->db, BEForumRecord::getForumDbConnection());
		try {
			$this->forum->init(null);
			self::fail('init twice must throw');
		} catch (TInvalidOperationException $e) {
		}
		try {
			$this->forum->setTablePrefix('x_');
			self::fail('prefix cannot change after init');
		} catch (TInvalidOperationException $e) {
		}
	}

	public function testPropertyValidation(): void
	{
		$forum = new BEForumModule();
		$forum->setTablePrefix('abc_');
		self::assertSame('abc_', $forum->getTablePrefix());
		$forum->setReactionTypes('Like, LOVE ,, like');
		self::assertSame(['like', 'love'], $forum->getReactionTypes());
		$forum->setAttachmentTypes(['PNG', 'jpg']);
		self::assertSame(['png', 'jpg'], $forum->getAttachmentTypes());
		$forum->setAdminRoles('Admins, Owners');
		self::assertSame(['Admins', 'Owners'], $forum->getAdminRoles());
		$forum->setModeratorRoles(['Mods']);
		self::assertSame(['Mods'], $forum->getModeratorRoles());
		$forum->setPagePathPrefix('.X.');
		self::assertSame('X', $forum->getPagePathPrefix());
		$forum->setTimezone('Europe/Paris');
		self::assertSame('Europe/Paris', $forum->getTimezone());
		$forum->setTimezone('');
		self::assertNull($forum->getTimezone());
		$forum->setMaxTitleLength(1000);
		self::assertSame(255, $forum->getMaxTitleLength());
		$forum->setEditWindow(0);
		self::assertSame(0, $forum->getEditWindow());
		$forum->setAttachmentPath('');
		self::assertStringEndsWith('beforum-attachments', $forum->getAttachmentPath());
		$forum->setAttachmentPath('/tmp/att');
		self::assertSame('/tmp/att', $forum->getAttachmentPath());
		foreach (['Polls', 'Attachments', 'Reactions', 'Tags', 'Subscriptions', 'Signatures', 'Bookmarks', 'Badges', 'DefaultStyles'] as $flag) {
			$forum->{'setEnable' . $flag}('false');
			self::assertFalse($forum->{'getEnable' . $flag}());
			$forum->{'setEnable' . $flag}(true);
			self::assertTrue($forum->{'getEnable' . $flag}());
		}
		$forum->setRequireApproval('true');
		self::assertTrue($forum->getRequireApproval());
		$forum->setAutoCreateMembers(false);
		self::assertFalse($forum->getAutoCreateMembers());
		$forum->setAutoInstall(false);
		self::assertFalse($forum->getAutoInstall());
		$forum->setRendererClass(BEForumContentRenderer::class);
		$forum->setUrlBuilderClass(BEForumUrlBuilder::class);
		$forum->setShellActionClass(BEForumShellAction::class);
		$forum->setCssClassPrefix(' x ');
		self::assertSame('x', $forum->getCssClassPrefix());
		$forum->setDateFormat('d.m.Y');
		self::assertSame('d.m.Y', $forum->getDateFormat());
		$forum->setGuestName('Anon');
		self::assertSame('Anon', $forum->getGuestName());
		$forum->setSearchMinLength(2);
		self::assertSame(2, $forum->getSearchMinLength());
		$forum->setNotificationRetentionDays(10);
		self::assertSame(10, $forum->getNotificationRetentionDays());
		$forum->setMaxTagsPerThread(0);
		self::assertSame(0, $forum->getMaxTagsPerThread());

		$invalid = [
			['setTablePrefix', 'bad prefix'],
			['setThreadsPerPage', 0],
			['setPostsPerPage', -1],
			['setItemsPerPage', 'abc'],
			['setFloodInterval', -1],
			['setMinPostLength', 0],
			['setMaxPostLength', 0],
			['setContentFormat', 'bbcode'],
			['setReactionTypes', 'bad type!'],
			['setAttachmentTypes', 'ex e'],
			['setAttachmentMaxSize', 0],
			['setTimezone', 'Mars/Olympus'],
			['setRendererClass', BEForumModule::class],
			['setUrlBuilderClass', 'NoSuchClass'],
			['setShellActionClass', BEForumUrlBuilder::class],
			['setSearchMinLength', 0],
			['setNotificationRetentionDays', 0],
			['setMaxTagsPerThread', -1],
		];
		foreach ($invalid as [$setter, $value]) {
			try {
				$forum->$setter($value);
				self::fail($setter . ' must reject ' . var_export($value, true));
			} catch (TInvalidDataValueException $e) {
				self::assertNotEmpty($e->getMessage());
			}
		}
		// the application event registry keeps listening components alive; release the throwaway module
		$forum->__destruct();
	}

	public function testManagersAndComponents(): void
	{
		self::assertInstanceOf(BEForumThreadManager::class, $this->forum->getThreads());
		self::assertSame($this->forum->getThreads(), $this->forum->getThreads());
		self::assertSame($this->forum, $this->forum->getThreads()->getModule());
		foreach (['getMembers', 'getBoards', 'getPosts', 'getReactions', 'getPolls', 'getTags', 'getAttachments', 'getSubscriptions', 'getNotifications', 'getSearch', 'getModeration', 'getReadTracker', 'getBookmarks', 'getStatistics'] as $getter) {
			self::assertInstanceOf(BEForumManager::class, $this->forum->$getter());
		}
		$custom = new class ($this->forum) extends BEForumThreadManager {
		};
		$this->forum->setManager(BEForumThreadManager::class, $custom);
		self::assertSame($custom, $this->forum->getThreads());
		try {
			$this->forum->getManager(BEForumModule::class);
			self::fail('non-manager classes are refused');
		} catch (BEForumConfigurationException $e) {
		}
		self::assertInstanceOf(BEForumContentRenderer::class, $this->forum->getRenderer());
		self::assertStringContainsString('<em>x</em>', $this->forum->renderContent('*x*'));
		self::assertStringContainsString('&lt;em&gt;', $this->forum->renderContent('<em>x</em>', 'text'));
		self::assertInstanceOf(BEForumUrlBuilder::class, $this->forum->getUrls());
		self::assertSame($this->db, $this->forum->getSchema()->getDbConnection());
		self::assertTrue($this->forum->ensureSchema());
		self::assertTrue($this->forum->ensureSchema(true));
	}

	public function testDynamicManagerCreation(): void
	{
		$forum = $this->forum;
		$forum->attachBehavior('factory', new class () extends \Prado\Util\TBehavior {
			public function dyCreateManager($manager, $class, $chain)
			{
				if ($class === BEForumThreadManager::class) {
					$manager = new class ($this->getOwner()) extends BEForumThreadManager {
						public bool $custom = true;
					};
				}
				return $chain->dyCreateManager($manager, $class);
			}
		});
		$forum->flushRequestState($this->getApp(), null);
		$forum->setManager(BEForumThreadManager::class, $forum->dyCreateManager(null, BEForumThreadManager::class));
		self::assertTrue($forum->getThreads()->custom);
	}

	public function testPermissionsWithoutManager(): void
	{
		self::assertFalse($this->forum->getHasPermissionsManager());
		$manager = new TPermissionsManager();
		$registered = new TPermissionsManager();
		try {
			$events = $this->forum->getPermissions($manager);
			self::assertCount(count(BEForumPermissions::getNames()), $events);
			$registered->registerPermission(BEForumPermissions::VIEW, 'taken');
			self::assertCount(count(BEForumPermissions::getNames()) - 1, $this->forum->getPermissions($registered), 'already registered permissions are skipped');
			self::assertContainsOnlyInstancesOf(TPermissionEvent::class, $events);
			self::assertSame(BEForumPermissions::VIEW, $events[0]->getName());
			self::assertSame(['dyviewforum'], $events[0]->getEvents());
			self::assertNotEmpty($events[0]->getRules());
		} finally {
			// un-initialized modules keep listening to global events; release them so later tests are isolated
			$manager->__destruct();
			$registered->__destruct();
		}

		$this->logout();
		self::assertTrue($this->forum->can(BEForumPermissions::VIEW));
		self::assertFalse($this->forum->can(BEForumPermissions::POST_CREATE));
		self::assertFalse($this->forum->can(BEForumPermissions::ADMIN));
		$this->loginAs('alice');
		self::assertTrue($this->forum->can(BEForumPermissions::POST_CREATE));
		self::assertFalse($this->forum->can(BEForumPermissions::MODERATE));
		self::assertFalse($this->forum->can(BEForumPermissions::ADMIN));
		self::assertTrue($this->forum->can(BEForumPermissions::POST_EDIT, ['username' => 'alice']));
		self::assertFalse($this->forum->can(BEForumPermissions::POST_EDIT, ['username' => 'bob']));
		self::assertTrue($this->forum->can(BEForumPermissions::MODERATE, ['moderators' => ['ALICE']]));
		$this->loginAs('mod');
		self::assertTrue($this->forum->can(BEForumPermissions::MODERATE));
		self::assertTrue($this->forum->can(BEForumPermissions::POST_EDIT, ['username' => 'bob']));
		self::assertFalse($this->forum->can(BEForumPermissions::ADMIN));
		$this->loginAs('admin');
		self::assertTrue($this->forum->can(BEForumPermissions::ADMIN));
		self::assertTrue($this->forum->can(BEForumPermissions::MODERATE));
		$this->loginAs('rolebased', ['Owners']);
		self::assertFalse($this->forum->can(BEForumPermissions::ADMIN));
		$this->forum->setAdminRoles('owners');
		self::assertTrue($this->forum->can(BEForumPermissions::ADMIN));
		self::assertFalse($this->forum->canUser(null, BEForumPermissions::ADMIN), 'a web request without a user is a guest');
		self::assertTrue($this->forum->canUser(null, BEForumPermissions::VIEW), 'guests may view');
		try {
			$this->logout();
			$this->forum->authorize(BEForumPermissions::ADMIN);
			self::fail('authorize must throw');
		} catch (BEForumForbiddenException $e) {
			self::assertSame(BEForumPermissions::ADMIN, $e->getPermission());
			self::assertSame(403, $e->getStatusCode());
			self::assertStringContainsString('forum_admin', $e->getMessage());
		}
	}

	public function testDynamicAuthorizeFilter(): void
	{
		$this->loginAs('alice');
		$this->forum->attachBehavior('deny', new class () extends \Prado\Util\TBehavior {
			public function dyAuthorize($allowed, $permission, $extra, $user, $chain)
			{
				return $chain->dyAuthorize($permission === BEForumPermissions::VIEW ? false : $allowed, $permission, $extra, $user);
			}
		});
		self::assertFalse($this->forum->can(BEForumPermissions::VIEW));
		self::assertTrue($this->forum->can(BEForumPermissions::POST_CREATE));
		$this->forum->detachBehavior('deny');
		self::assertTrue($this->forum->can(BEForumPermissions::VIEW));
	}

	public function testPermissionsWithManager(): void
	{
		$manager = new TPermissionsManager();
		$manager->setID('permissions_' . $this->moduleId);
		$this->getApp()->setModule($manager->getID(), $manager);
		$manager->init([
			'roles' => ['Default' => 'forum_view, forum_post_create', 'Staff' => 'forum_moderate'],
			'permissionrules' => [['name' => 'forum_thread_create', 'action' => 'deny', 'users' => 'alice', 'priority' => 0]],
		]);
		try {
			self::assertTrue($this->forum->getHasPermissionsManager());
			self::assertSame('Reply to forum threads.', $manager->getPermissionDescription(BEForumPermissions::POST_CREATE));
			$this->loginAs('alice', ['Default']);
			self::assertNotNull($this->getApp()->getUser()->asa(TPermissionsManager::USER_PERMISSIONS_BEHAVIOR));
			self::assertTrue($this->forum->can(BEForumPermissions::POST_CREATE));
			self::assertFalse($this->forum->can(BEForumPermissions::THREAD_CREATE), 'explicit deny rule wins');
			self::assertFalse($this->forum->can(BEForumPermissions::MODERATE));
			$this->loginAs('bob', ['Default', 'Staff']);
			self::assertTrue($this->forum->can(BEForumPermissions::THREAD_CREATE), 'preset rule allows authenticated users');
			self::assertTrue($this->forum->can(BEForumPermissions::MODERATE));
			$this->loginAs('admin', ['Default']);
			self::assertTrue($this->forum->can(BEForumPermissions::ADMIN), 'AdminUsers preset rule works with the manager');
			$this->logout();
			self::assertFalse($this->forum->can(BEForumPermissions::POST_CREATE));
			$board = $this->createBoard();
			$this->loginAs('alice', ['Default']);
			try {
				$this->forum->getThreads()->createThread($board, 'Denied', 'by rule');
				self::fail('the dynamic permission event must deny');
			} catch (BEForumForbiddenException $e) {
				self::assertSame(BEForumPermissions::THREAD_CREATE, $e->getPermission());
			}
		} finally {
			$manager->__destruct();
		}
	}

	public function testUserBehaviorAndMembers(): void
	{
		$user = $this->loginAs('carol');
		self::assertNotNull($user->asa(BEForumModule::USER_BEHAVIOR));
		$member = $user->getForumMember();
		self::assertSame('carol', $member->username);
		self::assertSame($member->getId(), $this->forum->getMember()->getId());
		self::assertSame('carol', $user->getForumDisplayName());
		self::assertTrue($user->forumCan(BEForumPermissions::POST_CREATE));
		self::assertFalse($user->forumCan(BEForumPermissions::ADMIN));
		$guest = $this->logout();
		self::assertNull($guest->getForumMember());
		self::assertSame($this->userManager->getGuestName(), $guest->getForumDisplayName());
		self::assertNull($this->forum->getMember());
		$this->forum->setAutoCreateMembers(false);
		$user = $this->loginAs('dave');
		self::assertNull($user->getForumMember(false));
		self::assertNull($this->forum->getMember());
		self::assertNotNull($user->getForumMember(true));
	}

	public function testCronAndMaintenance(): void
	{
		$info = $this->forum->fxGetCronTaskInfos(null, null);
		self::assertInstanceOf(TCronTaskInfo::class, $info);
		self::assertSame('forum_maintenance', $info->getName());
		self::assertSame($this->moduleId . '->runMaintenance', $info->getTask());
		$result = $this->forum->runMaintenance();
		self::assertSame(['bans_expired' => 0, 'pins_expired' => 0, 'notifications_pruned' => 0, 'tags_removed' => 0], $result);
		$recount = $this->forum->recountStatistics();
		self::assertSame(['threads', 'boards', 'tags', 'reactions', 'members'], array_keys($recount));
	}

	public function testShellActionRegistration(): void
	{
		$this->forum->registerShellAction($this->getApp(), null);
		self::assertTrue(true, 'no shell application: nothing registered, no error');
		$this->forum->attachBehavior('noshell', new class () extends \Prado\Util\TBehavior {
			public bool $called = false;

			public function dyRegisterShellAction($handled, $chain)
			{
				$this->called = true;
				return $chain->dyRegisterShellAction(true);
			}
		});
		$this->forum->registerShellAction($this->getApp(), null);
		self::assertTrue($this->forum->asa('noshell')->called);
	}

	public function testEventsAreRaised(): void
	{
		$seen = [];
		foreach (['onMemberCreated', 'onCategoryChanged', 'onBoardChanged', 'onThreadCreated', 'onPostCreated', 'onNotification', 'onSubscriptionChanged', 'onModerationAction'] as $event) {
			$this->forum->attachEventHandler($event, function ($sender, $param) use ($event, &$seen) {
				$seen[$event] = ($seen[$event] ?? 0) + 1;
				self::assertInstanceOf(\Belisoful\Forum\BEForumEventParameter::class, $param);
			});
		}
		$board = $this->createBoard();
		$thread = $this->createThreadAs($board, 'alice');
		$this->replyAs($thread, 'bob', 'hi @alice');
		self::assertSame(1, $seen['onCategoryChanged']);
		self::assertSame(1, $seen['onBoardChanged']);
		self::assertSame(1, $seen['onThreadCreated']);
		self::assertSame(2, $seen['onPostCreated']);
		self::assertGreaterThanOrEqual(3, $seen['onMemberCreated']);
		self::assertGreaterThanOrEqual(1, $seen['onNotification']);
		self::assertGreaterThanOrEqual(2, $seen['onSubscriptionChanged']);
		self::assertGreaterThanOrEqual(2, $seen['onModerationAction']);
	}
}
