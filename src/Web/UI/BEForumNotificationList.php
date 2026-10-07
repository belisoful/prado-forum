<?php

/**
 * BEForumNotificationList class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Web\UI;

use Belisoful\Forum\Data\BEForumNotification;
use Belisoful\Forum\Security\BEForumPermissions;
use Belisoful\Forum\Web\BEForumUrlBuilder;
use Prado\TPropertyValue;
use Prado\Web\UI\WebControls\TRepeaterCommandEventParameter;

/**
 * BEForumNotificationList class.
 *
 * BEForumNotificationList shows the notifications of the current member with
 * links to their targets and lets the member mark them read (one or all) or
 * delete them.  Guests are sent to the login page.
 * ```xml
 * <com:Belisoful\Forum\Web\UI\BEForumNotificationList />
 * ```
 *
 * @property \Prado\Web\UI\WebControls\THyperLink $UnreadLink
 * @property \Prado\Web\UI\WebControls\TLinkButton $MarkAll
 * @property \Prado\Web\UI\WebControls\TLabel $Error
 * @property \Prado\Web\UI\WebControls\TRepeater $Rows
 * @property \Belisoful\Forum\Web\UI\BEForumPager $Pager
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumNotificationList extends BEForumControl
{
	/**
	 * @return bool whether only unread notifications are listed (request parameter `unread`)
	 */
	public function getUnreadOnly(): bool
	{
		return $this->getRequestInt('unread', 0) === 1;
	}

	/**
	 * @return int the page size, 0 for the module default
	 */
	public function getPageSize(): int
	{
		return (int) $this->getViewState('PageSize', 0);
	}

	/**
	 * @param int $size the page size, 0 for the module default
	 */
	public function setPageSize($size): void
	{
		$this->setViewState('PageSize', max(0, TPropertyValue::ensureInteger($size)), 0);
	}

	/**
	 * Requires a logged in member.
	 * @param mixed $param the event parameter
	 */
	public function onInit($param)
	{
		parent::onInit($param);
		$this->requireLogin(BEForumPermissions::SUBSCRIBE);
		$this->setPageTitle($this->t('Notifications'));
	}

	/**
	 * Builds the message and link of a notification.
	 * @param BEForumNotification $notification the notification
	 * @param array<int, \Belisoful\Forum\Data\BEForumMember> $actors the actors keyed by id
	 * @return array{message: string, url: string} the HTML message and the target URL
	 */
	public function describe(BEForumNotification $notification, array $actors): array
	{
		$urls = $this->getUrls();
		$actor = $notification->actor_member_id ? ($actors[(int) $notification->actor_member_id] ?? null) : null;
		$who = $actor ? $this->memberLink($actor) : $this->te('Someone');
		$title = $this->e((string) $notification->getDetail('title', ''));
		$targetId = (int) $notification->target_id;
		$url = '';
		switch ($notification->target_type) {
			case BEForumNotification::TARGET_POST:
				$url = $urls->postById($targetId);
				break;
			case BEForumNotification::TARGET_THREAD:
				$url = $urls->thread($targetId);
				break;
			case BEForumNotification::TARGET_BOARD:
				$url = $urls->board($targetId);
				break;
			case BEForumNotification::TARGET_MEMBER:
				$member = $this->getForum()->getMembers()->findById($targetId);
				$url = $member ? $urls->member($member) : '';
				break;
		}
		$message = match ($notification->type) {
			BEForumNotification::TYPE_REPLY => $this->th('{0} replied in {1}', [$who, $title]),
			BEForumNotification::TYPE_THREAD => $this->th('{0} started {1}', [$who, $title]),
			BEForumNotification::TYPE_MENTION => $this->th('{0} mentioned you in {1}', [$who, $title]),
			BEForumNotification::TYPE_QUOTE => $this->th('{0} replied to your post in {1}', [$who, $title]),
			BEForumNotification::TYPE_REACTION => $this->th('{0} reacted to your post in {1}', [$who, $title]),
			BEForumNotification::TYPE_ACCEPTED => $this->th('{0} accepted your answer in {1}', [$who, $title]),
			BEForumNotification::TYPE_BADGE => $this->th('You received the badge {0}', [$this->e((string) $notification->getDetail('badge', ''))]),
			BEForumNotification::TYPE_REPORT => $this->th('{0} reported a post in {1}', [$who, $title]),
			BEForumNotification::TYPE_MODERATION => $this->e($this->t(match ((string) $notification->getDetail('action', '')) {
				'ban' => 'Your account has been banned.',
				'warn' => 'You received a warning.',
				default => 'A moderator acted on your account.',
			})) . ($notification->getDetail('reason') ? ' ' . $this->e((string) $notification->getDetail('reason')) : ''),
			default => $this->e((string) $notification->type),
		};
		return ['message' => $message, 'url' => $url];
	}

	/**
	 * Builds the rows (HTML escaped).
	 * @param BEForumNotification[] $notifications the notifications
	 * @return array the rows
	 */
	public function buildRows(array $notifications): array
	{
		$actors = $this->getForum()->getMembers()->getMembersByIds(array_map(fn (BEForumNotification $n) => (int) $n->actor_member_id, $notifications));
		$rows = [];
		foreach ($notifications as $notification) {
			$described = $this->describe($notification, $actors);
			$rows[] = [
				'id' => (int) $notification->getId(),
				'message' => $described['message'],
				'url' => $this->e($described['url']),
				'time' => $this->timeTag($notification->created_at),
				'read' => $notification->getIsRead(),
				'type' => $this->e((string) $notification->type),
			];
		}
		return $rows;
	}

	/**
	 * Handles the row commands.
	 * @param mixed $sender the repeater
	 * @param TRepeaterCommandEventParameter $param the event parameter
	 */
	public function itemCommand($sender, $param): void
	{
		$id = (int) $param->getCommandParameter();
		$this->attempt(function () use ($param, $id): void {
			$notifications = $this->getForum()->getNotifications();
			if ($param->getCommandName() === 'read') {
				$notifications->markRead($id);
			} elseif ($param->getCommandName() === 'delete') {
				$notifications->delete($id);
			}
		});
	}

	/**
	 * Marks every notification read.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function markAllClicked($sender, $param): void
	{
		$this->attempt(fn () => $this->getForum()->getNotifications()->markAllRead());
	}

	/**
	 * Loads and binds the notifications.
	 * @param mixed $param the event parameter
	 */
	public function onPreRender($param)
	{
		parent::onPreRender($param);
		[$notifications, $pagination] = $this->getForum()->getNotifications()->listNotifications($this->getRequestedPage(), $this->getUnreadOnly(), null, $this->getPageSize() > 0 ? $this->getPageSize() : null);
		$this->bindRepeater('Rows', $this->buildRows($notifications));
		$this->Pager->setPagination($pagination);
		$urls = $this->getUrls();
		$this->Pager->setUrlCallback(fn (int $p) => $urls->build($urls->getPagePath('notifications'), ['unread' => $this->getUnreadOnly() ? 1 : null, BEForumUrlBuilder::PARAM_PAGE => $p > 1 ? $p : null]));
		$this->UnreadLink->setNavigateUrl($urls->build($urls->getPagePath('notifications'), ['unread' => $this->getUnreadOnly() ? null : 1]));
		$this->UnreadLink->setText($this->te($this->getUnreadOnly() ? 'Show all' : 'Show unread only'));
		$this->MarkAll->setVisible($this->getForum()->getNotifications()->countUnread() > 0);
	}
}
