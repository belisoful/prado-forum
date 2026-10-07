<?php

/**
 * BEForumUserBehavior class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Behaviors;

use Belisoful\Forum\BEForumModule;
use Belisoful\Forum\Data\BEForumMember;
use Prado\Security\IUser;
use Prado\Util\TBehavior;

/**
 * BEForumUserBehavior class.
 *
 * BEForumUserBehavior is attached by {@see BEForumModule} as a class behavior
 * to every {@see \Prado\Security\IUser} so the application user exposes its
 * forum identity:
 * ```php
 * $member = $this->getUser()->getForumMember();      // null for guests
 * $name = $this->getUser()->getForumDisplayName();
 * if ($this->getUser()->forumCan(BEForumPermissions::MODERATE)) { ... }
 * ```
 * The member profile is created on first access for authenticated users when
 * the module property `AutoCreateMembers` is enabled.
 *
 * @method IUser getOwner()
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumUserBehavior extends TBehavior
{
	/** @var null|\WeakReference<BEForumModule> the module */
	private ?\WeakReference $_module = null;

	/**
	 * @return null|BEForumModule the module
	 */
	public function getForumModule(): ?BEForumModule
	{
		return $this->_module?->get();
	}

	/**
	 * @param BEForumModule|\WeakReference<BEForumModule> $module the module
	 */
	public function setModule($module): void
	{
		if ($module instanceof \WeakReference) {
			$this->_module = $module;
		} elseif ($module instanceof BEForumModule) {
			$this->_module = \WeakReference::create($module);
		} else {
			$this->_module = null;
		}
	}

	/**
	 * @param bool $create whether to create the member profile for an authenticated user without one
	 * @return null|BEForumMember the forum member of the user, null for guests
	 */
	public function getForumMember(bool $create = true): ?BEForumMember
	{
		$module = $this->getForumModule();
		$owner = $this->getOwner();
		if ($module === null || !($owner instanceof IUser) || $owner->getIsGuest()) {
			return null;
		}
		return $module->getMembers()->getMemberForUser($owner, $create);
	}

	/**
	 * @return string the display name of the member, the user name, or the guest name of the manager
	 */
	public function getForumDisplayName(): string
	{
		$module = $this->getForumModule();
		$member = $this->getForumMember(false);
		if ($member !== null) {
			return $member->getDisplayName();
		}
		$owner = $this->getOwner();
		if ($owner instanceof IUser && !$owner->getIsGuest()) {
			return (string) $owner->getName();
		}
		return $module !== null ? $module->getGuestName() : ($owner instanceof IUser ? (string) $owner->getName() : '');
	}

	/**
	 * @param string $permission a forum permission name
	 * @param null|array $extra extra authorization data
	 * @return bool whether the user holds the permission
	 */
	public function forumCan(string $permission, ?array $extra = null): bool
	{
		$module = $this->getForumModule();
		return $module !== null && $module->canUser($this->getOwner(), $permission, $extra);
	}

	/**
	 * Keeps the module reference out of serialized user state.
	 * @param array $exprops by reference, the properties to exclude
	 */
	protected function _getZappableSleepProps(&$exprops)
	{
		parent::_getZappableSleepProps($exprops);
		$exprops[] = "\0" . __CLASS__ . "\0_module";
	}
}
