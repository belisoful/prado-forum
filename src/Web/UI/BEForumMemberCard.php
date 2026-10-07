<?php

/**
 * BEForumMemberCard class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Web\UI;

use Belisoful\Forum\Data\BEForumMember;
use Prado\TPropertyValue;

/**
 * BEForumMemberCard class.
 *
 * BEForumMemberCard shows a member: avatar, display name, join date,
 * counters, reputation and badges.  It is used next to posts and on the
 * profile page and may be placed anywhere with {@see setUsername}.
 * ```xml
 * <com:Belisoful\Forum\Web\UI\BEForumMemberCard Username="alice" Size="64" />
 * ```
 *
 * @property \Prado\Web\UI\WebControls\TRepeater $Badges
 * @property \Prado\Web\UI\WebControls\TRepeater $Stats
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumMemberCard extends BEForumControl
{
	/** @var null|BEForumMember the member */
	private ?BEForumMember $_member = null;

	/** @var null|string the guest name shown without a member */
	private ?string $_guestName = null;

	/**
	 * @return string the username to show
	 */
	public function getUsername(): string
	{
		return (string) $this->getViewState('Username', '');
	}

	/**
	 * @param string $username the username to show
	 */
	public function setUsername($username): void
	{
		$this->setViewState('Username', TPropertyValue::ensureString($username), '');
		$this->_member = null;
	}

	/**
	 * @return int the avatar size in pixels
	 */
	public function getSize(): int
	{
		return (int) $this->getViewState('Size', 48);
	}

	/**
	 * @param int $size the avatar size in pixels
	 */
	public function setSize($size): void
	{
		$this->setViewState('Size', max(16, TPropertyValue::ensureInteger($size)), 48);
	}

	/**
	 * @return bool whether the counters are shown
	 */
	public function getShowStats(): bool
	{
		return (bool) $this->getViewState('ShowStats', true);
	}

	/**
	 * @param bool $show whether the counters are shown
	 */
	public function setShowStats($show): void
	{
		$this->setViewState('ShowStats', TPropertyValue::ensureBoolean($show), true);
	}

	/**
	 * @return null|BEForumMember the member, resolved from the username when not set
	 */
	public function getMemberRecord(): ?BEForumMember
	{
		if ($this->_member === null && $this->getUsername() !== '') {
			$this->_member = $this->getForum()->getMembers()->findByUsername($this->getUsername());
		}
		return $this->_member;
	}

	/**
	 * @param null|BEForumMember $member the member to show
	 */
	public function setMemberRecord(?BEForumMember $member): void
	{
		$this->_member = $member;
	}

	/**
	 * @param null|string $name the guest name shown without a member
	 */
	public function setGuestName(?string $name): void
	{
		$this->_guestName = $name;
	}

	/**
	 * @return string the guest name
	 */
	public function getGuestName(): string
	{
		return $this->_guestName ?? $this->getForum()->getGuestName();
	}

	/**
	 * @return array<int, array{name: string, icon: string, title: string}> the badge rows (HTML escaped)
	 */
	public function getBadgeRows(): array
	{
		$member = $this->getMemberRecord();
		if ($member === null || !$this->getForum()->getEnableBadges()) {
			return [];
		}
		$rows = [];
		foreach ($this->getForum()->getMembers()->getMemberBadges($member) as $badge) {
			$icon = (string) $badge->icon;
			$rows[] = [
				'name' => $this->e((string) $badge->name),
				'title' => $this->e((string) ($badge->description ?? $badge->name)),
				'icon' => preg_match('#^(https?:)?/#', $icon) ? '<img src="' . $this->e($icon) . '" alt="" />' : ($icon !== '' ? '<i class="' . $this->e($icon) . '"></i>' : ''),
			];
		}
		return $rows;
	}

	/**
	 * @return string the avatar image HTML
	 */
	public function getAvatarHtml(): string
	{
		$member = $this->getMemberRecord();
		$size = $this->getSize();
		if ($member === null) {
			return '<span class="' . $this->css('avatar', 'guest') . '" style="width:' . $size . 'px;height:' . $size . 'px"></span>';
		}
		return '<img class="' . $this->css('avatar') . '" src="' . $this->e($this->avatarUrl($member, $size)) . '" width="' . $size . '" height="' . $size . '" alt="" loading="lazy" />';
	}

	/**
	 * @return string the name HTML
	 */
	public function getNameHtml(): string
	{
		return $this->memberLink($this->getMemberRecord(), $this->getGuestName());
	}

	/**
	 * @return array<int, array{label: string, value: string}> the stat rows (HTML escaped)
	 */
	public function getStatRows(): array
	{
		$member = $this->getMemberRecord();
		if ($member === null || !$this->getShowStats()) {
			return [];
		}
		$rows = [
			['label' => $this->te('Joined'), 'value' => $this->timeTag($member->joined_at)],
			['label' => $this->te('Posts'), 'value' => $this->e((string) (int) $member->post_count)],
			['label' => $this->te('Reputation'), 'value' => $this->e((string) (int) $member->reputation)],
		];
		if ($member->getIsBanned()) {
			$rows[] = ['label' => $this->te('Status'), 'value' => '<span class="' . $this->css('banned') . '">' . $this->te('Banned') . '</span>'];
		}
		return $rows;
	}

	/**
	 * Binds the card.
	 * @param mixed $param the event parameter
	 */
	public function onPreRender($param)
	{
		parent::onPreRender($param);
		$this->bindRepeater('Stats', $this->getStatRows());
		$badges = $this->getBadgeRows();
		$this->bindRepeater('Badges', $badges);
		$this->Badges->setVisible(count($badges) > 0);
	}
}
