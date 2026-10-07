<?php

/**
 * BEForumRoleRule class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Security;

use Belisoful\Forum\BEForumModule;
use Prado\Prado;
use Prado\Security\IUser;
use Prado\Security\TAuthorizationRule;

/**
 * BEForumRoleRule class.
 *
 * BEForumRoleRule allows the users and roles configured on the module
 * properties `AdminUsers`/`AdminRoles` (kind `admin`) or `ModeratorUsers`/
 * `ModeratorRoles` (kind `moderator`).  The lists are read at check time, so
 * the rule works even though permissions are registered with
 * {@see \Prado\Security\Permissions\TPermissionsManager} before the module
 * properties have been applied, and it gives a zero-configuration forum a way
 * to name its administrators without a permissions manager.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumRoleRule extends TAuthorizationRule
{
	public const KIND_ADMIN = 'admin';
	public const KIND_MODERATOR = 'moderator';

	/** @var null|\WeakReference<BEForumModule> the module */
	private ?\WeakReference $_module = null;

	/** @var string the kind of list to consult */
	private string $_kind;

	/**
	 * @param BEForumModule $module the module
	 * @param string $kind `admin` or `moderator`
	 * @param null|numeric $priority the rule priority
	 */
	public function __construct(BEForumModule $module, string $kind = self::KIND_ADMIN, $priority = null)
	{
		$this->_module = \WeakReference::create($module);
		$this->_kind = $kind === self::KIND_MODERATOR ? self::KIND_MODERATOR : self::KIND_ADMIN;
		parent::__construct('allow', '*', '*', '*', '*', $priority);
	}

	/**
	 * @return string `admin` or `moderator`
	 */
	public function getKind(): string
	{
		return $this->_kind;
	}

	/**
	 * Returns the modules whose configuration applies: the module that created
	 * the rule when it has been initialized, otherwise every initialized forum
	 * module of the application (permissions are registered once per
	 * application, possibly by an instance other than the configured one).
	 * @return BEForumModule[] the modules
	 */
	public function getModules(): array
	{
		$module = $this->_module?->get();
		if ($module instanceof BEForumModule && $module->getIsInitialized()) {
			return [$module];
		}
		$modules = [];
		$app = Prado::getApplication();
		if ($app !== null) {
			foreach ($app->getModulesByType(BEForumModule::class) as $candidate) {
				if ($candidate instanceof BEForumModule && $candidate->getIsInitialized()) {
					$modules[] = $candidate;
				}
			}
		}
		if (!$modules && $module instanceof BEForumModule) {
			$modules[] = $module;
		}
		return $modules;
	}

	/**
	 * @return string[] the configured usernames (administrators are also moderators)
	 */
	public function getConfiguredUsers(): array
	{
		$users = [];
		foreach ($this->getModules() as $module) {
			$users = array_merge($users, $module->getAdminUsers(), $this->_kind === self::KIND_MODERATOR ? $module->getModeratorUsers() : []);
		}
		return array_values(array_unique($users));
	}

	/**
	 * @return string[] the configured roles (administrator roles are also moderator roles)
	 */
	public function getConfiguredRoles(): array
	{
		$roles = [];
		foreach ($this->getModules() as $module) {
			$roles = array_merge($roles, $module->getAdminRoles(), $this->_kind === self::KIND_MODERATOR ? $module->getModeratorRoles() : []);
		}
		return array_values(array_unique($roles));
	}

	/**
	 * @param IUser $user the user object
	 * @param string $verb the request verb
	 * @param string $ip the request IP address
	 * @param null|mixed $extra extra data (unused)
	 * @return int 1 if the user is allowed, 0 if the rule does not apply
	 */
	public function isUserAllowed(IUser $user, $verb, $ip, $extra = null)
	{
		if ($user->getIsGuest()) {
			return 0;
		}
		$name = (string) $user->getName();
		foreach ($this->getConfiguredUsers() as $configured) {
			if (strcasecmp($configured, $name) === 0) {
				return 1;
			}
		}
		foreach ($this->getConfiguredRoles() as $role) {
			if ($user->isInRole($role)) {
				return 1;
			}
		}
		return 0;
	}

	/**
	 * Keeps the module reference out of serialized state.
	 * @param array $exprops by reference, the properties to exclude
	 */
	protected function _getZappableSleepProps(&$exprops)
	{
		parent::_getZappableSleepProps($exprops);
		$exprops[] = "\0" . __CLASS__ . "\0_module";
	}
}
