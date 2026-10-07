<?php

/**
 * BEForumModeratorRule class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Security;

use Prado\Security\IUser;
use Prado\Security\TAuthorizationRule;

/**
 * BEForumModeratorRule class.
 *
 * BEForumModeratorRule allows (or denies, depending on the rule action) a user
 * when the extra authorization data lists the user as a board moderator.  The
 * forum passes `['moderators' => ['alice', 'bob']]` (the usernames assigned to
 * the board of the thread or post being acted upon) as extra data, so a member
 * moderating only some boards receives the `forum_moderate` capabilities there
 * without holding the global permission.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumModeratorRule extends TAuthorizationRule
{
	/**
	 * @param IUser $user the user object
	 * @param string $verb the request verb (GET, POST)
	 * @param string $ip the request IP address
	 * @param null|array $extra extra data with a 'moderators' list of usernames
	 * @return int 1 if the user is allowed, -1 if the user is denied, 0 if the rule does not apply to the user
	 */
	public function isUserAllowed(IUser $user, $verb, $ip, $extra = null)
	{
		if (parent::isUserAllowed($user, $verb, $ip, $extra) === 0 || $user->getIsGuest()) {
			return 0;
		}
		$moderators = $extra['moderators'] ?? [];
		if (!is_array($moderators)) {
			$moderators = [$moderators];
		}
		foreach ($moderators as $moderator) {
			if (is_string($moderator) && strcasecmp($moderator, (string) $user->getName()) === 0) {
				return ($this->getAction() === 'allow') ? 1 : -1;
			}
		}
		return 0;
	}
}
