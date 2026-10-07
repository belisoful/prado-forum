<?php

/**
 * BEForumToolbar class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Web\UI;

use Belisoful\Forum\Security\BEForumPermissions;
use Prado\Security\TAuthManager;
use Prado\TPropertyValue;

/**
 * BEForumToolbar class.
 *
 * BEForumToolbar is the forum navigation bar: the forum title, a search box,
 * member links (notifications with unread count, subscriptions, bookmarks,
 * profile), moderation and administration links for those allowed, and
 * optional login/logout links.
 * ```xml
 * <com:Belisoful\Forum\Web\UI\BEForumToolbar LoginUrl="/login" LogoutUrl="/logout" />
 * ```
 * When `LoginUrl` is empty the login page of the application's
 * {@see \Prado\Security\TAuthManager} is used.
 *
 * @property \Prado\Web\UI\WebControls\TRepeater $Links
 * @property \Belisoful\Forum\Web\UI\BEForumSearchBox $Search
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumToolbar extends BEForumControl
{
	/**
	 * @return bool whether the search box is shown
	 */
	public function getShowSearch(): bool
	{
		return (bool) $this->getViewState('ShowSearch', true);
	}

	/**
	 * @param bool $show whether the search box is shown
	 */
	public function setShowSearch($show): void
	{
		$this->setViewState('ShowSearch', TPropertyValue::ensureBoolean($show), true);
	}

	/**
	 * @return string the login URL
	 */
	public function getLoginUrl(): string
	{
		$url = (string) $this->getViewState('LoginUrl', '');
		if ($url === '') {
			foreach ($this->getApplication()->getModulesByType(TAuthManager::class) as $auth) {
				if ($auth instanceof TAuthManager && $auth->getLoginPage()) {
					return $this->getService()->constructUrl($auth->getLoginPage());
				}
			}
		}
		return $url;
	}

	/**
	 * @param string $url the login URL
	 */
	public function setLoginUrl($url): void
	{
		$this->setViewState('LoginUrl', TPropertyValue::ensureString($url), '');
	}

	/**
	 * @return string the logout URL
	 */
	public function getLogoutUrl(): string
	{
		return (string) $this->getViewState('LogoutUrl', '');
	}

	/**
	 * @param string $url the logout URL
	 */
	public function setLogoutUrl($url): void
	{
		$this->setViewState('LogoutUrl', TPropertyValue::ensureString($url), '');
	}

	/**
	 * Builds the link view model (HTML escaped).
	 * @return array<int, array{label: string, url: string, badge: string, css: string}> the links
	 */
	public function getLinks(): array
	{
		$forum = $this->getForum();
		$urls = $this->getUrls();
		$links = [];
		$links[] = ['label' => $this->e($this->t('Members')), 'url' => $this->e($urls->members()), 'badge' => '', 'css' => 'members'];
		$member = $this->getMember();
		if ($member !== null) {
			if ($forum->getEnableSubscriptions()) {
				$unread = $forum->getNotifications()->countUnread($member);
				$links[] = ['label' => $this->e($this->t('Notifications')), 'url' => $this->e($urls->notifications()), 'badge' => $unread > 0 ? (string) $unread : '', 'css' => 'notifications'];
				$links[] = ['label' => $this->e($this->t('Subscriptions')), 'url' => $this->e($urls->subscriptions()), 'badge' => '', 'css' => 'subscriptions'];
			}
			if ($forum->getEnableBookmarks()) {
				$links[] = ['label' => $this->e($this->t('Bookmarks')), 'url' => $this->e($urls->bookmarks()), 'badge' => '', 'css' => 'bookmarks'];
			}
			$links[] = ['label' => $this->e($member->getDisplayName()), 'url' => $this->e($urls->member($member)), 'badge' => '', 'css' => 'profile'];
			if ($this->getIsModerator()) {
				$pending = $forum->getModeration()->countOpenReports() + $forum->getModeration()->countPending();
				$links[] = ['label' => $this->e($this->t('Moderation')), 'url' => $this->e($urls->adminModeration()), 'badge' => $pending > 0 ? (string) $pending : '', 'css' => 'moderation'];
			}
			if ($this->can(BEForumPermissions::ADMIN)) {
				$links[] = ['label' => $this->e($this->t('Structure')), 'url' => $this->e($urls->adminStructure()), 'badge' => '', 'css' => 'admin'];
				$links[] = ['label' => $this->e($this->t('Member admin')), 'url' => $this->e($urls->adminMembers()), 'badge' => '', 'css' => 'admin'];
			}
			$logout = $this->getLogoutUrl();
			if ($logout !== '') {
				$links[] = ['label' => $this->e($this->t('Log out')), 'url' => $this->e($logout), 'badge' => '', 'css' => 'logout'];
			}
		} else {
			$login = $this->getLoginUrl();
			if ($login !== '') {
				$links[] = ['label' => $this->e($this->t('Log in')), 'url' => $this->e($login), 'badge' => '', 'css' => 'login'];
			}
		}
		return $links;
	}

	/**
	 * Binds the links.
	 * @param mixed $param the event parameter
	 */
	public function onPreRender($param)
	{
		parent::onPreRender($param);
		$this->Search->setVisible($this->getShowSearch());
		$this->bindRepeater('Links', $this->getLinks());
	}
}
