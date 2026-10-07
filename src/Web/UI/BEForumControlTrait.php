<?php

/**
 * BEForumControlTrait trait file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Web\UI;

use Belisoful\Forum\BEForumModule;
use Belisoful\Forum\Data\BEForumMember;
use Belisoful\Forum\Exceptions\BEForumConfigurationException;
use Belisoful\Forum\Util\BEForumTime;
use Belisoful\Forum\Web\BEForumUrlBuilder;
use Prado\Prado;
use Prado\Security\IUser;
use Prado\TPropertyValue;

/**
 * BEForumControlTrait trait.
 *
 * BEForumControlTrait holds the helpers shared by {@see BEForumControl}
 * (template controls) and {@see BEForumItemRenderer} (repeater item
 * renderers): module resolution, URL builder, current member, authorization,
 * CSS class naming, localization, escaping, dates and member rendering.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
trait BEForumControlTrait
{
	/** @var null|BEForumModule the resolved module */
	private ?BEForumModule $_forum = null;

	/**
	 * @return null|string the id of the forum module to use, null for the plugin module of this control
	 */
	public function getModuleID(): ?string
	{
		return $this->getViewState('ModuleID');
	}

	/**
	 * @param null|string $id the id of the forum module to use
	 */
	public function setModuleID($id): void
	{
		$id = TPropertyValue::ensureString($id);
		$this->setViewState('ModuleID', $id === '' ? null : $id);
		$this->_forum = null;
	}

	/**
	 * @throws BEForumConfigurationException when no forum module is available
	 * @return BEForumModule the forum module
	 */
	public function getForum(): BEForumModule
	{
		if ($this->_forum === null) {
			$module = null;
			$id = $this->getModuleID();
			$app = $this->getApplication();
			if ($id !== null) {
				$module = $app->getModule($id);
			} else {
				$parent = $this->getParent();
				while ($parent !== null && !($parent instanceof BEForumControl) && !($parent instanceof BEForumItemRenderer)) {
					$parent = $parent->getParent();
				}
				if ($parent !== null) {
					$module = $parent->getForum();
				} else {
					$module = $this->getPluginModule();
				}
				if (!($module instanceof BEForumModule)) {
					foreach ($app->getModulesByType(BEForumModule::class) as $candidate) {
						if ($candidate instanceof BEForumModule) {
							$module = $candidate;
							break;
						}
					}
				}
			}
			if (!($module instanceof BEForumModule)) {
				throw new BEForumConfigurationException('forum_module_not_found', (string) $id);
			}
			$this->_forum = $module;
		}
		return $this->_forum;
	}

	/**
	 * @param BEForumModule $module the forum module (for programmatic setups and tests)
	 */
	public function setForum(BEForumModule $module): void
	{
		$this->_forum = $module;
	}

	/**
	 * @return BEForumUrlBuilder the URL builder
	 */
	public function getUrls(): BEForumUrlBuilder
	{
		return $this->getForum()->getUrls();
	}

	/**
	 * @return null|BEForumMember the member of the current user, null for guests
	 */
	public function getMember(): ?BEForumMember
	{
		return $this->getForum()->getMember();
	}

	/**
	 * @return null|IUser the current application user
	 */
	public function getForumUser(): ?IUser
	{
		return $this->getForum()->getUser();
	}

	/**
	 * @return bool whether the current user is a guest
	 */
	public function getIsGuest(): bool
	{
		$user = $this->getForumUser();
		return $user === null || $user->getIsGuest();
	}

	/**
	 * @param string $permission a forum permission
	 * @param null|array $extra extra rule data
	 * @return bool whether the current user holds the permission
	 */
	public function can(string $permission, ?array $extra = null): bool
	{
		return $this->getForum()->can($permission, $extra);
	}

	/**
	 * @param null|int $boardId a board id, null for global
	 * @return bool whether the current user moderates the board
	 */
	public function getIsModerator(?int $boardId = null): bool
	{
		return $this->getForum()->getModeration()->isModerator($boardId);
	}

	/**
	 * Builds a prefixed CSS class name.
	 * @param string $name the element name, e.g. `thread-list`
	 * @param null|string $modifier an optional modifier appended as `--modifier`
	 * @return string the class name, e.g. `beforum-thread-list beforum-thread-list--pinned`
	 */
	public function css(string $name, ?string $modifier = null): string
	{
		$prefix = $this->getForum()->getCssClassPrefix();
		$base = $prefix === '' ? $name : $prefix . '-' . $name;
		if ($modifier === null || $modifier === '') {
			return $base;
		}
		return $base . ' ' . $base . '--' . $modifier;
	}

	/**
	 * Localizes a message.
	 * @param string $text the message
	 * @param array $parameters the placeholders
	 * @return string the localized message
	 */
	public function t(string $text, array $parameters = []): string
	{
		return Prado::localize($text, $parameters);
	}

	/**
	 * HTML escapes text for output in templates.
	 * @param null|string $text the text
	 * @return string the escaped text
	 */
	public function e(?string $text): string
	{
		return htmlspecialchars((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}

	/**
	 * Localizes and escapes a message in one call.
	 * @param string $text the message
	 * @param array $parameters the placeholders
	 * @return string the escaped, localized message
	 */
	public function te(string $text, array $parameters = []): string
	{
		return $this->e($this->t($text, $parameters));
	}

	/**
	 * Localizes a message whose placeholders receive HTML (links, time tags
	 * or already escaped text): the message is escaped first and the HTML
	 * parameters are substituted afterwards, so neither is double escaped.
	 * @param string $text the message with `{0}`, `{1}`... placeholders
	 * @param array $htmlParameters the HTML (or already escaped) placeholder values
	 * @return string the HTML
	 */
	public function th(string $text, array $htmlParameters = []): string
	{
		$map = [];
		foreach ($htmlParameters as $key => $value) {
			$map['{' . $key . '}'] = (string) $value;
		}
		return strtr($this->e($this->t($text)), $map);
	}

	/**
	 * @return null|string the display timezone: the member timezone, then the module timezone
	 */
	public function getTimezone(): ?string
	{
		$member = $this->getMember();
		if ($member !== null && $member->timezone) {
			return (string) $member->timezone;
		}
		return $this->getForum()->getTimezone();
	}

	/**
	 * Formats a stored time for display.
	 * @param null|string $time a storage format time
	 * @param bool $relative whether to render a relative phrase ("3 minutes ago")
	 * @return string the formatted time
	 */
	public function formatDate(?string $time, bool $relative = false): string
	{
		if ($relative) {
			return BEForumTime::relative($time);
		}
		return BEForumTime::display($time, $this->getForum()->getDateFormat(), $this->getTimezone());
	}

	/**
	 * Renders a `<time>` element with the absolute time as title and a relative phrase as text.
	 * @param null|string $time a storage format time
	 * @return string the HTML
	 */
	public function timeTag(?string $time): string
	{
		if (!$time) {
			return '';
		}
		return '<time class="' . $this->css('time') . '" datetime="' . $this->e(BEForumTime::display($time, 'c')) . '" title="' . $this->e($this->formatDate($time)) . '">' . $this->e($this->formatDate($time, true)) . '</time>';
	}

	/**
	 * @param BEForumMember $member the member
	 * @param int $size the size in pixels
	 * @return string the avatar URL: the member avatar, or a gravatar (identicon fallback)
	 */
	public function avatarUrl(BEForumMember $member, int $size = 48): string
	{
		if ($member->avatar_url) {
			return (string) $member->avatar_url;
		}
		$hash = $member->getEmailHash() ?: md5(strtolower((string) $member->username));
		return 'https://www.gravatar.com/avatar/' . $hash . '?s=' . max(16, $size) . '&d=identicon&r=g';
	}

	/**
	 * Renders a member link.
	 * @param null|BEForumMember $member the member
	 * @param null|string $fallback the text for guests
	 * @return string the HTML
	 */
	public function memberLink(?BEForumMember $member, ?string $fallback = null): string
	{
		if ($member === null) {
			return '<span class="' . $this->css('member-name', 'guest') . '">' . $this->e($fallback ?? $this->getForum()->getGuestName()) . '</span>';
		}
		return '<a class="' . $this->css('member-name') . '" href="' . $this->e($this->getUrls()->member($member)) . '">' . $this->e($member->getDisplayName()) . '</a>';
	}

	/**
	 * Builds an inline `onclick` confirmation script.
	 * @param string $text the plain text question
	 * @return string the script, e.g. `return confirm("Delete?");`
	 */
	public function confirmScript(string $text): string
	{
		return 'return confirm(' . json_encode($text, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . ');';
	}

	/**
	 * Renders a small flag icon with a title.
	 * @param string $modifier the flag modifier (pinned, locked, solved, ...)
	 * @param string $symbol the symbol HTML
	 * @param string $title the plain text title
	 * @return string the HTML
	 */
	public function flag(string $modifier, string $symbol, string $title): string
	{
		return '<span class="' . $this->css('flag', $modifier) . '" title="' . $this->e($title) . '" aria-label="' . $this->e($title) . '">' . $symbol . '</span>';
	}
}
