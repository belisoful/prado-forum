<?php

/**
 * BEForumGuestUser class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Security;

use Prado\Security\IUser;
use Prado\Security\IUserManager;

/**
 * BEForumGuestUser class.
 *
 * BEForumGuestUser is the anonymous user that {@see \Belisoful\Forum\BEForumModule::canUser}
 * evaluates the preset authorization rules against when a web application has
 * no user module at all.  It is always a guest without roles and is never
 * persisted.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumGuestUser implements IUser
{
	/** @var string the guest name */
	private string $_name;

	/**
	 * @param string $name the guest name
	 */
	public function __construct(string $name = 'Guest')
	{
		$this->_name = $name;
	}

	/**
	 * @return null the guest has no user manager
	 */
	public function getManager(): ?IUserManager
	{
		return null;
	}

	/**
	 * @return string the guest name
	 */
	public function getName()
	{
		return $this->_name;
	}

	/**
	 * @param string $value the guest name
	 */
	public function setName($value)
	{
		$this->_name = (string) $value;
	}

	/**
	 * @return bool always true
	 */
	public function getIsGuest()
	{
		return true;
	}

	/**
	 * The guest state cannot be changed.
	 * @param bool $value ignored
	 */
	public function setIsGuest($value)
	{
	}

	/**
	 * @return array no roles
	 */
	public function getRoles()
	{
		return [];
	}

	/**
	 * Roles cannot be assigned to the guest.
	 * @param mixed $value ignored
	 */
	public function setRoles($value)
	{
	}

	/**
	 * @param string $role a role name
	 * @return bool always false
	 */
	public function isInRole($role)
	{
		return false;
	}

	/**
	 * @return string the serialized name
	 */
	public function saveToString()
	{
		return serialize($this->_name);
	}

	/**
	 * @param string $string a serialized name
	 * @return static the user
	 */
	public function loadFromString($string)
	{
		$name = @unserialize($string);
		$this->_name = is_string($name) ? $name : $this->_name;
		return $this;
	}
}
