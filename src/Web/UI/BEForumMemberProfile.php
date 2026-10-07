<?php

/**
 * BEForumMemberProfile class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Web\UI;

use Belisoful\Forum\Data\BEForumMember;
use Belisoful\Forum\Security\BEForumPermissions;
use Belisoful\Forum\Web\BEForumUrlBuilder;
use Prado\TPropertyValue;

/**
 * BEForumMemberProfile class.
 *
 * BEForumMemberProfile is the profile page of a member: the member card,
 * biography, signature, the recent threads and posts, an "edit profile"
 * form for the member (and administrators) and moderation actions (ban,
 * unban, warn, award badge) for moderators.  The member comes from the
 * `member` request parameter unless {@see setUsername} is used.
 * ```xml
 * <com:Belisoful\Forum\Web\UI\BEForumMemberProfile />
 * ```
 *
 * @property \Belisoful\Forum\Web\UI\BEForumMemberCard $Card
 * @property \Prado\Web\UI\WebControls\TRepeater $Details
 * @property \Prado\Web\UI\WebControls\TLinkButton $EditButton
 * @property \Prado\Web\UI\WebControls\TLabel $Error
 * @property \Prado\Web\UI\WebControls\TLiteral $Bio
 * @property \Prado\Web\UI\WebControls\TLiteral $Signature
 * @property \Belisoful\Forum\Web\UI\BEForumProfileEditor $Editor
 * @property \Prado\Web\UI\WebControls\TPanel $ModerationPanel
 * @property \Prado\Web\UI\WebControls\TTextBox $BanUntil
 * @property \Prado\Web\UI\WebControls\TTextBox $BanReason
 * @property \Prado\Web\UI\WebControls\TLinkButton $Ban
 * @property \Prado\Web\UI\WebControls\TLinkButton $Unban
 * @property \Prado\Web\UI\WebControls\TTextBox $WarnReason
 * @property \Prado\Web\UI\WebControls\TLinkButton $Warn
 * @property \Prado\Web\UI\WebControls\TPanel $BadgePanel
 * @property \Prado\Web\UI\WebControls\TDropDownList $BadgeList
 * @property \Prado\Web\UI\WebControls\TLinkButton $Award
 * @property \Belisoful\Forum\Web\UI\BEForumThreadList $Threads
 * @property \Prado\Web\UI\WebControls\TPanel $RecentPosts
 * @property \Prado\Web\UI\WebControls\TRepeater $Posts
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumMemberProfile extends BEForumControl
{
	use BEForumPostRowsTrait;

	/** @var null|BEForumMember the member */
	private ?BEForumMember $_member = null;

	/**
	 * @return string the username, read from the `member` request parameter when unset
	 */
	public function getUsername(): string
	{
		$name = (string) $this->getViewState('Username', '');
		return $name !== '' ? $name : $this->getRequestString(BEForumUrlBuilder::PARAM_MEMBER);
	}

	/**
	 * @param string $username the username
	 */
	public function setUsername($username): void
	{
		$this->setViewState('Username', TPropertyValue::ensureString($username), '');
		$this->_member = null;
	}

	/**
	 * @return int how many recent posts to show
	 */
	public function getRecentPostCount(): int
	{
		return (int) $this->getViewState('RecentPostCount', 5);
	}

	/**
	 * @param int $count how many recent posts to show
	 */
	public function setRecentPostCount($count): void
	{
		$this->setViewState('RecentPostCount', max(0, TPropertyValue::ensureInteger($count)), 5);
	}

	/**
	 * @return BEForumMember the member
	 */
	public function getMemberRecord(): BEForumMember
	{
		if ($this->_member === null) {
			$username = $this->getUsername();
			$this->_member = $username !== '' ? $this->getForum()->getMembers()->getMemberByUsername($username) : $this->getForum()->getMembers()->requireMember();
		}
		return $this->_member;
	}

	/**
	 * @return bool whether the profile belongs to the current user
	 */
	public function getIsOwnProfile(): bool
	{
		$member = $this->getMember();
		return $member !== null && $member->getId() === $this->getMemberRecord()->getId();
	}

	/**
	 * Distributes the member to the children.
	 * @param mixed $param the event parameter
	 */
	public function onInit($param)
	{
		parent::onInit($param);
		$member = $this->getMemberRecord();
		$this->Card->setMemberRecord($member);
		$this->Card->setSize(96);
		$this->Editor->setUsername((string) $member->username);
		$this->Threads->setMemberID((int) $member->getId());
		$this->Threads->setShowBoard(true);
		$this->Threads->setShowNewThreadButton(false);
	}

	/**
	 * Toggles the profile editor.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function editClicked($sender, $param): void
	{
		$this->Editor->setVisible(!$this->Editor->getVisible());
	}

	/**
	 * Bans the member.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function banClicked($sender, $param): void
	{
		$until = trim((string) $this->BanUntil->getText());
		$this->attempt(function () use ($until): void {
			$this->getForum()->getMembers()->ban($this->getMemberRecord(), $until !== '' ? str_replace('T', ' ', $until) : null, (string) $this->BanReason->getText());
			$this->_member = null;
		});
	}

	/**
	 * Lifts the ban.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function unbanClicked($sender, $param): void
	{
		$this->attempt(function (): void {
			$this->getForum()->getMembers()->unban($this->getMemberRecord());
			$this->_member = null;
		});
	}

	/**
	 * Warns the member.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function warnClicked($sender, $param): void
	{
		$this->attempt(function (): void {
			$this->getForum()->getMembers()->warn($this->getMemberRecord(), (string) $this->WarnReason->getText());
			$this->WarnReason->setText('');
			$this->_member = null;
		});
	}

	/**
	 * Awards the selected badge.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function awardClicked($sender, $param): void
	{
		$slug = (string) $this->BadgeList->getSelectedValue();
		$this->attempt(function () use ($slug): void {
			$badge = $this->getForum()->getMembers()->findBadge($slug);
			if ($badge !== null) {
				$this->getForum()->getMembers()->awardBadge($this->getMemberRecord(), $badge);
			}
		});
	}

	/**
	 * @return array<int, array{label: string, value: string}> the detail rows (HTML escaped)
	 */
	public function getDetailRows(): array
	{
		$member = $this->getMemberRecord();
		$rows = [];
		if ($member->location) {
			$rows[] = ['label' => $this->te('Location'), 'value' => $this->e((string) $member->location)];
		}
		if ($member->website) {
			$rows[] = ['label' => $this->te('Web site'), 'value' => '<a rel="nofollow noopener" href="' . $this->e((string) $member->website) . '">' . $this->e((string) $member->website) . '</a>'];
		}
		$rows[] = ['label' => $this->te('Threads'), 'value' => $this->e((string) (int) $member->thread_count)];
		$rows[] = ['label' => $this->te('Last seen'), 'value' => $this->timeTag($member->last_seen_at)];
		if ($this->getIsModerator()) {
			$rows[] = ['label' => $this->te('Warnings'), 'value' => $this->e((string) (int) $member->warning_count)];
			if ($member->getIsBanned()) {
				$rows[] = ['label' => $this->te('Banned'), 'value' => $this->e($member->banned_until ? $this->t('until {0}', [$this->formatDate($member->banned_until)]) : $this->t('permanently')) . ($member->ban_reason ? ' &mdash; ' . $this->e((string) $member->ban_reason) : '')];
			}
		}
		return $rows;
	}

	/**
	 * Binds the profile.
	 * @param mixed $param the event parameter
	 */
	public function onPreRender($param)
	{
		parent::onPreRender($param);
		$forum = $this->getForum();
		$member = $this->getMemberRecord();
		$this->setPageTitle($member->getDisplayName());
		$this->Bio->setText($member->bio ? $forum->renderContent((string) $member->bio) : '');
		$this->Signature->setText($forum->getEnableSignatures() && $member->signature ? $forum->renderContent((string) $member->signature) : '');
		$this->bindRepeater('Details', $this->getDetailRows());
		$canEdit = $this->can(BEForumPermissions::PROFILE_EDIT, ['username' => $member->username]);
		$this->EditButton->setVisible($canEdit);
		if (!$canEdit) {
			$this->Editor->setVisible(false);
		}
		$moderator = $this->getIsModerator() && !$this->getIsOwnProfile();
		$this->ModerationPanel->setVisible($moderator);
		if ($moderator) {
			$this->Ban->setVisible(!$member->getIsBanned());
			$this->BanUntil->setVisible(!$member->getIsBanned());
			$this->BanReason->setVisible(!$member->getIsBanned());
			$this->Unban->setVisible($member->getIsBanned());
			$badges = [];
			foreach ($forum->getMembers()->getBadges() as $badge) {
				$badges[(string) $badge->slug] = $this->e((string) $badge->name);
			}
			$this->BadgePanel->setVisible($forum->getEnableBadges() && count($badges) > 0);
			$this->BadgeList->setDataSource($badges);
			$this->BadgeList->dataBind();
		}
		if ($this->getRecentPostCount() > 0 && !$this->getIsCallback()) {
			[$posts] = $forum->getPosts()->getPostsByMember($member, 1, $this->getRecentPostCount());
			$this->bindRepeater('Posts', $this->buildPostRows($posts, true, false));
		}
		$this->RecentPosts->setVisible($this->getRecentPostCount() > 0);
	}
}
