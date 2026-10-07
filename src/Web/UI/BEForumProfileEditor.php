<?php

/**
 * BEForumProfileEditor class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Web\UI;

use Belisoful\Forum\Data\BEForumMember;
use Belisoful\Forum\Data\BEForumNotification;
use Belisoful\Forum\Security\BEForumPermissions;
use Prado\TPropertyValue;

/**
 * BEForumProfileEditor class.
 *
 * BEForumProfileEditor edits the forum profile of a member (display name,
 * e-mail for the avatar, avatar URL, signature, biography, location, web site,
 * timezone) and the notification preferences.  Without {@see setUsername} it
 * edits the current member's profile; moderators and administrators may edit
 * others through the profile page.
 * ```xml
 * <com:Belisoful\Forum\Web\UI\BEForumProfileEditor />
 * ```
 *
 * @property \Prado\Web\UI\WebControls\TLabel $Error
 * @property \Prado\Web\UI\WebControls\TLabel $Saved
 * @property \Prado\Web\UI\WebControls\TPanel $Form
 * @property \Prado\Web\UI\WebControls\TTextBox $DisplayName
 * @property \Prado\Web\UI\WebControls\TTextBox $Email
 * @property \Prado\Web\UI\WebControls\TTextBox $AvatarUrl
 * @property \Prado\Web\UI\WebControls\TTextBox $Location
 * @property \Prado\Web\UI\WebControls\TTextBox $Website
 * @property \Prado\Web\UI\WebControls\TDropDownList $Timezone
 * @property \Prado\Web\UI\WebControls\TTextBox $Bio
 * @property \Prado\Web\UI\WebControls\TTextBox $Signature
 * @property \Prado\Web\UI\WebControls\TCheckBoxList $Notifications
 * @property \Prado\Web\UI\WebControls\TButton $Save
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumProfileEditor extends BEForumControl
{
	/** @var null|BEForumMember the member being edited */
	private ?BEForumMember $_member = null;

	/**
	 * @return string the username being edited, empty for the current member
	 */
	public function getUsername(): string
	{
		return (string) $this->getViewState('Username', '');
	}

	/**
	 * @param string $username the username being edited
	 */
	public function setUsername($username): void
	{
		$this->setViewState('Username', TPropertyValue::ensureString($username), '');
		$this->_member = null;
	}

	/**
	 * @return null|BEForumMember the member being edited
	 */
	public function getMemberRecord(): ?BEForumMember
	{
		if ($this->_member === null) {
			$this->_member = $this->getUsername() !== '' ? $this->getForum()->getMembers()->findByUsername($this->getUsername()) : $this->getMember();
		}
		return $this->_member;
	}

	/**
	 * @return array<string, string> the notification types keyed by setting name (HTML escaped labels)
	 */
	public function getNotificationOptions(): array
	{
		return [
			'notify_' . BEForumNotification::TYPE_REPLY => $this->te('Replies in subscribed threads'),
			'notify_' . BEForumNotification::TYPE_THREAD => $this->te('New threads in subscribed boards'),
			'notify_' . BEForumNotification::TYPE_MENTION => $this->te('Mentions'),
			'notify_' . BEForumNotification::TYPE_QUOTE => $this->te('Replies to my posts'),
			'notify_' . BEForumNotification::TYPE_REACTION => $this->te('Reactions to my posts'),
			'notify_' . BEForumNotification::TYPE_ACCEPTED => $this->te('Accepted answers'),
			'subscribe_own_threads' => $this->te('Subscribe to threads I start'),
			'subscribe_on_reply' => $this->te('Subscribe to threads I reply to'),
		];
	}

	/**
	 * Fills the form.
	 * @param mixed $param the event parameter
	 */
	public function onLoad($param)
	{
		parent::onLoad($param);
		$member = $this->getMemberRecord();
		if ($member === null) {
			$this->Form->setVisible(false);
			return;
		}
		if (!$this->can(BEForumPermissions::PROFILE_EDIT, ['username' => $member->username])) {
			$this->Form->setVisible(false);
			$this->showError($this->t('You may not edit this profile.'));
			return;
		}
		if (!$this->getPage()->getIsPostBack()) {
			$this->DisplayName->setText((string) $member->display_name);
			$this->Email->setText((string) $member->email);
			$this->AvatarUrl->setText((string) $member->avatar_url);
			$this->Signature->setText((string) $member->signature);
			$this->Bio->setText((string) $member->bio);
			$this->Location->setText((string) $member->location);
			$this->Website->setText((string) $member->website);
			$zones = ['' => $this->te('Forum default')];
			foreach (\DateTimeZone::listIdentifiers() as $zone) {
				$zones[$zone] = $this->e($zone);
			}
			$this->Timezone->setDataSource($zones);
			$this->Timezone->dataBind();
			$this->Timezone->setSelectedValue((string) $member->timezone);
			$this->Notifications->setDataSource($this->getNotificationOptions());
			$this->Notifications->dataBind();
			$selected = [];
			foreach (array_keys($this->getNotificationOptions()) as $key) {
				if ($member->getSetting($key, true) !== false) {
					$selected[] = $key;
				}
			}
			$this->Notifications->setSelectedValues($selected);
		}
	}

	/**
	 * Saves the profile.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function saveClicked($sender, $param): void
	{
		$member = $this->getMemberRecord();
		if ($member === null) {
			return;
		}
		$saved = $this->attempt(function () use ($member): void {
			$members = $this->getForum()->getMembers();
			$members->updateProfile($member, [
				'display_name' => (string) $this->DisplayName->getText(),
				'email' => (string) $this->Email->getText(),
				'avatar_url' => (string) $this->AvatarUrl->getText(),
				'signature' => (string) $this->Signature->getText(),
				'bio' => (string) $this->Bio->getText(),
				'location' => (string) $this->Location->getText(),
				'website' => (string) $this->Website->getText(),
				'timezone' => (string) $this->Timezone->getSelectedValue(),
			]);
			$selected = $this->Notifications->getSelectedValues();
			foreach (array_keys($this->getNotificationOptions()) as $key) {
				$member->setSetting($key, in_array($key, $selected, true) ? null : false);
			}
			$members->saveSettings($member);
		});
		if ($saved) {
			$this->Saved->setVisible(true);
			$this->onSaved(null);
		}
	}

	/**
	 * Raised after the profile has been saved.
	 * @param mixed $param the event parameter
	 */
	public function onSaved($param)
	{
		$this->raiseEvent('OnSaved', $this, $param);
	}
}
