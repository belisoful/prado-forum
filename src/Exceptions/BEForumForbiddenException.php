<?php

/**
 * BEForumForbiddenException class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Exceptions;

use Prado\Exceptions\THttpException;

/**
 * BEForumForbiddenException class.
 *
 * BEForumForbiddenException is an HTTP 403 exception raised when the current
 * user is not authorized for a forum permission.  The failed permission name
 * is available through {@see getPermission}.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumForbiddenException extends THttpException
{
	/** @var string the permission that failed */
	private string $_permission;

	/**
	 * @param string $permission the permission that was denied
	 * @param string $errorMessage the error message key
	 * @param mixed ...$args the message placeholders
	 */
	public function __construct(string $permission, $errorMessage = 'forum_permission_denied', ...$args)
	{
		$this->_permission = $permission;
		if (!$args) {
			$args = [$permission];
		}
		parent::__construct(403, $errorMessage, ...$args);
	}

	/**
	 * @return string the permission that was denied
	 */
	public function getPermission(): string
	{
		return $this->_permission;
	}

	/**
	 * @return string the path of the forum error message file
	 */
	protected function getErrorMessageFile()
	{
		return BEForumException::getForumMessageFile();
	}
}
